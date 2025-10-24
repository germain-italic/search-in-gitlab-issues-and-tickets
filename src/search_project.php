<?php
/**
 * Search in a single project
 * This allows the frontend to call multiple times and show progress
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

header('Content-Type: application/json');

try {
    // Load environment variables
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }

    if (!isset($_ENV['GITLAB_URL']) || !isset($_ENV['GITLAB_API_KEY'])) {
        throw new Exception('GitLab configuration not found.');
    }

    // Get parameters
    $projectId = $_POST['projectId'] ?? $_GET['projectId'] ?? '';
    $searchString = $_POST['searchString'] ?? $_GET['searchString'] ?? '';
    $searchIn = json_decode($_POST['searchIn'] ?? $_GET['searchIn'] ?? '[]', true);

    if (empty($projectId)) {
        throw new Exception('Project ID is required');
    }

    if (empty($searchString)) {
        throw new Exception('Search string is required');
    }

    // Initialize GitLab API client
    $client = new Client([
        'base_uri' => rtrim($_ENV['GITLAB_URL'], '/') . '/api/v4/',
        'headers' => [
            'PRIVATE-TOKEN' => $_ENV['GITLAB_API_KEY']
        ],
        'timeout' => 60
    ]);

    // Get project details
    $response = $client->request('GET', "projects/{$projectId}");
    $project = json_decode($response->getBody()->getContents(), true);

    $result = [
        'projectId' => $projectId,
        'projectName' => $project['name'],
        'searchString' => $searchString
    ];

    // Search in issues
    if (in_array('issues', $searchIn)) {
        try {
            $issuesResponse = $client->request('GET', "projects/{$projectId}/issues", [
                'query' => [
                    'search' => $searchString,
                    'scope' => 'all',
                    'per_page' => 100,
                    'with_labels_details' => true
                ]
            ]);

            $issues = json_decode($issuesResponse->getBody()->getContents(), true);
            $filteredIssues = [];

            foreach ($issues as $issue) {
                if (stripos($issue['title'], $searchString) !== false ||
                    stripos($issue['description'] ?? '', $searchString) !== false) {

                    $excerpt = extractExcerpt($issue['description'] ?? '', $searchString);

                    $filteredIssues[] = [
                        'id' => $issue['id'],
                        'iid' => $issue['iid'],
                        'title' => $issue['title'],
                        'excerpt' => $excerpt,
                        'web_url' => $issue['web_url'],
                        'labels' => array_map(function($label) {
                            if (is_array($label)) {
                                return [
                                    'name' => $label['name'],
                                    'color' => $label['color'] ?? '#888',
                                    'text_color' => $label['text_color'] ?? '#fff'
                                ];
                            }
                            return [
                                'name' => $label,
                                'color' => '#888',
                                'text_color' => '#fff'
                            ];
                        }, $issue['labels'] ?? []),
                        'state' => $issue['state'] ?? null
                    ];
                }
            }

            if (!empty($filteredIssues)) {
                $result['issues'] = $filteredIssues;
            }
        } catch (GuzzleException $e) {
            $result['errors']['issues'] = $e->getMessage();
        }
    }

    // Search in wiki
    if (in_array('wiki', $searchIn)) {
        try {
            $wikiResponse = $client->request('GET', "projects/{$projectId}/wikis", [
                'query' => ['with_content' => 1]
            ]);

            $wikiPages = json_decode($wikiResponse->getBody()->getContents(), true);
            $filteredWiki = [];

            foreach ($wikiPages as $page) {
                if (stripos($page['title'], $searchString) !== false ||
                    stripos($page['content'] ?? '', $searchString) !== false) {

                    $excerpt = extractExcerpt($page['content'] ?? '', $searchString);

                    $filteredWiki[] = [
                        'slug' => $page['slug'],
                        'title' => $page['title'],
                        'excerpt' => $excerpt,
                        'web_url' => getWikiPageUrl($project, $page['slug'])
                    ];
                }
            }

            if (!empty($filteredWiki)) {
                $result['wiki'] = $filteredWiki;
            }
        } catch (GuzzleException $e) {
            $result['errors']['wiki'] = $e->getMessage();
        }
    }

    // Search in comments
    if (in_array('comments', $searchIn)) {
        try {
            $issuesResponse = $client->request('GET', "projects/{$projectId}/issues", [
                'query' => [
                    'scope' => 'all',
                    'per_page' => 100
                ]
            ]);
            $issues = json_decode($issuesResponse->getBody()->getContents(), true);

            if (!empty($issues)) {
                $filteredComments = [];

                foreach ($issues as $issue) {
                    try {
                        $notesResponse = $client->request('GET', "projects/{$projectId}/issues/{$issue['iid']}/notes");
                        $notes = json_decode($notesResponse->getBody()->getContents(), true);

                        foreach ($notes as $note) {
                            if (isset($note['system']) && $note['system']) {
                                continue;
                            }

                            if (stripos($note['body'] ?? '', $searchString) !== false) {
                                $excerpt = extractExcerpt($note['body'] ?? '', $searchString);

                                $filteredComments[] = [
                                    'id' => $note['id'],
                                    'issue_iid' => $issue['iid'],
                                    'issue_title' => $issue['title'],
                                    'issue_url' => $issue['web_url'],
                                    'author' => $note['author']['name'] ?? 'Unknown',
                                    'created_at' => $note['created_at'] ?? null,
                                    'excerpt' => $excerpt,
                                    'web_url' => $issue['web_url'] . '#note_' . $note['id']
                                ];
                            }
                        }
                    } catch (GuzzleException $e) {
                        continue;
                    }
                }

                if (!empty($filteredComments)) {
                    $result['comments'] = $filteredComments;
                }
            }
        } catch (GuzzleException $e) {
            $result['errors']['comments'] = $e->getMessage();
        }
    }

    echo json_encode($result);

} catch (Exception | GuzzleException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function extractExcerpt($content, $searchTerm, $contextLength = 150) {
    if (empty($content)) {
        return '';
    }

    $pos = stripos($content, $searchTerm);
    if ($pos === false) {
        return mb_substr($content, 0, $contextLength * 2) . '...';
    }

    $start = max(0, $pos - $contextLength);
    $end = min(mb_strlen($content), $pos + mb_strlen($searchTerm) + $contextLength);
    $excerpt = mb_substr($content, $start, $end - $start);

    if ($start > 0) {
        $excerpt = '...' . $excerpt;
    }
    if ($end < mb_strlen($content)) {
        $excerpt .= '...';
    }

    return $excerpt;
}

function getWikiPageUrl($project, $slug) {
    $baseUrl = rtrim($_ENV['GITLAB_URL'], '/');
    $namespace = $project['path_with_namespace'] ?? $project['path'];
    return "{$baseUrl}/{$namespace}/-/wikis/{$slug}";
}
