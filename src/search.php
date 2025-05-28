<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise;

header('Content-Type: application/json');

try {
    // Load environment variables
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }
    
    // Check if required environment variables are set
    if (!isset($_ENV['GITLAB_URL']) || !isset($_ENV['GITLAB_API_KEY'])) {
        throw new Exception('GitLab configuration not found. Please check your .env file.');
    }
    
    // Get search parameters
    $searchString = $_POST['searchString'] ?? '';
    $projectIds = json_decode($_POST['projectIds'] ?? '[]', true);
    $searchIn = $_POST['searchIn'] ?? ['issues', 'wiki'];
    
    if (empty($searchString)) {
        throw new Exception('Search string is required');
    }
    
    if (empty($projectIds)) {
        throw new Exception('At least one project must be selected');
    }
    
    // Initialize GitLab API client
    $client = new Client([
        'base_uri' => rtrim($_ENV['GITLAB_URL'], '/') . '/api/v4/',
        'headers' => [
            'PRIVATE-TOKEN' => $_ENV['GITLAB_API_KEY']
        ]
    ]);
    
    // Get project details (for names)
    $projectDetails = [];
    foreach ($projectIds as $projectId) {
        try {
            $response = $client->request('GET', "projects/{$projectId}");
            $projectDetails[$projectId] = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            // Skip projects that can't be accessed
            continue;
        }
    }
    
    // Initialize results array
    $results = [];
    
    // Create requests array for concurrent execution
    $requests = [];
    
    // Search in each project
    foreach ($projectIds as $projectId) {
        if (!isset($projectDetails[$projectId])) {
            continue;
        }
        
        // Initialize project results
        $results[$projectId] = [
            'id' => $projectId,
            'name' => $projectDetails[$projectId]['name'],
            'searchString' => $searchString
        ];
        
        // Search in issues
        if (in_array('issues', $searchIn)) {
            $requests[] = function() use ($client, $projectId, $searchString) {
                return $client->getAsync("projects/{$projectId}/issues", [
                    'query' => [
                        'search' => $searchString,
                        'scope' => 'all',
                        'per_page' => 100
                    ]
                ]);
            };
        }
        
        // Search in wiki
        if (in_array('wiki', $searchIn)) {
            $requests[] = function() use ($client, $projectId, $searchString) {
                return $client->getAsync("projects/{$projectId}/wikis", [
                    'query' => [
                        'with_content' => 1
                    ]
                ]);
            };
        }
    }
    
    // Execute requests concurrently
    $responses = [];
    $pool = new Pool($client, $requests, [
        'concurrency' => 5,
        'fulfilled' => function($response, $index) use (&$responses) {
            $responses[$index] = $response;
        },
        'rejected' => function($reason, $index) use (&$responses) {
            $responses[$index] = $reason;
        }
    ]);
    
    // Wait for the requests to complete
    $promise = $pool->promise();
    $promise->wait();
    
    // Process responses
    $requestIndex = 0;
    foreach ($projectIds as $projectId) {
        if (!isset($projectDetails[$projectId])) {
            continue;
        }
        
        // Process issues
        if (in_array('issues', $searchIn)) {
            if (isset($responses[$requestIndex]) && !($responses[$requestIndex] instanceof \Exception)) {
                $issuesResponse = $responses[$requestIndex];
                $issues = json_decode($issuesResponse->getBody()->getContents(), true);
                
                // Filter issues containing the search string
                $filteredIssues = [];
                foreach ($issues as $issue) {
                    if (stripos($issue['title'], $searchString) !== false || 
                        stripos($issue['description'] ?? '', $searchString) !== false) {
                        
                        // Extract excerpt from description
                        $excerpt = extractExcerpt($issue['description'] ?? '', $searchString);
                        
                        $filteredIssues[] = [
                            'id' => $issue['id'],
                            'iid' => $issue['iid'],
                            'title' => $issue['title'],
                            'excerpt' => $excerpt,
                            'web_url' => $issue['web_url']
                        ];
                    }
                }
                
                if (!empty($filteredIssues)) {
                    $results[$projectId]['issues'] = $filteredIssues;
                }
            }
            $requestIndex++;
        }
        
        // Process wiki
        if (in_array('wiki', $searchIn)) {
            if (isset($responses[$requestIndex]) && !($responses[$requestIndex] instanceof \Exception)) {
                $wikiResponse = $responses[$requestIndex];
                $wikiPages = json_decode($wikiResponse->getBody()->getContents(), true);
                
                // Filter wiki pages containing the search string
                $filteredWikiPages = [];
                foreach ($wikiPages as $page) {
                    if (stripos($page['title'], $searchString) !== false || 
                        stripos($page['content'] ?? '', $searchString) !== false) {
                        
                        // Extract excerpt from content
                        $excerpt = extractExcerpt($page['content'] ?? '', $searchString);
                        
                        $filteredWikiPages[] = [
                            'slug' => $page['slug'],
                            'title' => $page['title'],
                            'excerpt' => $excerpt,
                            'web_url' => getWikiPageUrl($projectDetails[$projectId], $page['slug'])
                        ];
                    }
                }
                
                if (!empty($filteredWikiPages)) {
                    $results[$projectId]['wiki'] = $filteredWikiPages;
                }
            }
            $requestIndex++;
        }
        
        // Remove projects with no results
        if (!isset($results[$projectId]['issues']) && !isset($results[$projectId]['wiki'])) {
            unset($results[$projectId]);
        }
    }
    
    echo json_encode($results);
    
} catch (Exception | GuzzleException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Extract excerpt from content around search term
 * 
 * @param string $content
 * @param string $searchTerm
 * @param int $contextLength
 * @return string
 */
function extractExcerpt($content, $searchTerm, $contextLength = 150) {
    if (empty($content)) {
        return '';
    }
    
    // Find position of search term (case-insensitive)
    $pos = stripos($content, $searchTerm);
    if ($pos === false) {
        // If not found, return first part of content
        return mb_substr($content, 0, $contextLength * 2) . '...';
    }
    
    // Calculate start and end positions for excerpt
    $start = max(0, $pos - $contextLength);
    $end = min(mb_strlen($content), $pos + mb_strlen($searchTerm) + $contextLength);
    $excerpt = mb_substr($content, $start, $end - $start);
    
    // Add ellipsis if needed
    if ($start > 0) {
        $excerpt = '...' . $excerpt;
    }
    if ($end < mb_strlen($content)) {
        $excerpt .= '...';
    }
    
    return $excerpt;
}

/**
 * Generate wiki page URL
 * 
 * @param array $project
 * @param string $slug
 * @return string
 */
function getWikiPageUrl($project, $slug) {
    $baseUrl = rtrim($_ENV['GITLAB_URL'], '/');
    $namespace = $project['path_with_namespace'] ?? $project['path'];
    return "{$baseUrl}/{$namespace}/-/wikis/{$slug}";
}