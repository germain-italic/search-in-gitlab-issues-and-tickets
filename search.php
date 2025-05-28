<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // .env file not found. This is not necessarily a fatal error,
    // as environment variables might be set directly in the server environment.
    // The script will later check for the presence of $gitlabUrl and $gitlabApiKey.
    // error_log("Notice: .env file not found. Relying on server environment variables. Error: " . $e->getMessage());
}

// PHP backend logic will go here in later steps
// This file will handle API requests for fetching projects and performing searches.

header('Content-Type: application/json'); // Default content type for responses

// Variables will be populated by phpdotenv from .env or directly from server environment
$gitlabUrl = getenv('GITLAB_URL') ?: $_ENV['GITLAB_URL'] ?: null;
$gitlabApiKey = getenv('GITLAB_API_KEY') ?: $_ENV['GITLAB_API_KEY'] ?: null;

// Determine Action Based on Request Method
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
}
// Note: If the request method is neither POST nor GET, $action remains '',
// and the script will later output 'No action specified.' or 'Invalid action or request method.'

if (!$gitlabUrl || !$gitlabApiKey) {
    echo json_encode([
        'error' => 'GitLab URL or API Key not configured. Please set GITLAB_URL and GITLAB_API_KEY in your .env file or server environment.',
        'projects' => [] // Ensure projects key exists even in error for JS
    ]);
    exit;
}

// Further logic for different actions (get_projects, search) will be added later.

/**
 * Fetches projects from GitLab API, handling pagination.
 *
 * @param string $gitlabUrl The base URL of the GitLab instance.
 * @param string $gitlabApiKey The API key for authentication.
 * @return array An array containing 'projects' or 'error'.
 */
function fetch_gitlab_projects(string $gitlabUrl, string $gitlabApiKey): array
{
    $allProjects = [];
    $nextPageUrl = "{$gitlabUrl}/api/v4/projects?membership=true&archived=false&simple=true&per_page=100"; // Initial URL

    $maxPages = 10; // Safety break to prevent infinite loops in case of API issues
    $pageCount = 0;

    do {
        $pageCount++;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $nextPageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds connection timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds total timeout
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "PRIVATE-TOKEN: " . $gitlabApiKey,
            "User-Agent: GitLab-MultiProject-Search"
        ]);
        // For capturing headers to find the 'Link' header for pagination
        curl_setopt($ch, CURLOPT_HEADER, 1);


        $responseWithHeaders = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($responseWithHeaders, 0, $headerSize);
        $body = substr($responseWithHeaders, $headerSize);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            return ['error' => "Could not connect to GitLab (cURL Error): " . $curlError, 'projects' => []];
        }
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $pageProjects = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'Failed to parse project data from GitLab: ' . json_last_error_msg(), 'projects' => []];
            }
            if (is_array($pageProjects)) {
                $allProjects = array_merge($allProjects, $pageProjects);
            }

            // Check for pagination link
            $nextPageUrl = null; // Reset for the current page
            if (preg_match('/<([^>]+)>; rel="next"/', $headers, $matches)) {
                $nextPageUrl = $matches[1];
            }
        } else {
            $errorMsg = "GitLab API error when fetching projects. HTTP Code: {$httpCode}.";
            if (!empty($body)) {
                $errorMsg .= " Response: " . substr(strip_tags($body), 0, 200); // Basic sanitization and limit
            }
            return ['error' => $errorMsg, 'projects' => []];
        }
    } while ($nextPageUrl && $pageCount < $maxPages);

    if ($pageCount >= $maxPages && $nextPageUrl) {
        // Potentially hit max pages limit, could log this or add a notice
        // For now, just return what we have.
    }
    
    // Filter and map projects to keep only necessary fields
    $filteredProjects = array_map(function($project) {
        return [
            'id' => $project['id'],
            'name' => $project['name'],
            'name_with_namespace' => $project['name_with_namespace'],
            'web_url' => $project['web_url']
        ];
    }, $allProjects);

    return ['projects' => $filteredProjects];
}


if ($action === 'get_projects') {
    $result = fetch_gitlab_projects($gitlabUrl, $gitlabApiKey);
    if (isset($result['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $result['error'], 'projects' => []]);
    } else {
        echo json_encode(['projects' => $result['projects']]);
    }
} elseif ($action === 'search' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchTerm = $_POST['search_term'] ?? '';
    $projectIds = $_POST['project_ids'] ?? [];
    // Ensure project_ids is an array, even if a single value is sent without '[]'
    if (!is_array($projectIds) && !empty($projectIds)) {
        $projectIds = [$projectIds]; 
    }
    // Validate project_ids contains integers
    $projectIds = array_filter(array_map('intval', $projectIds), function($id) { return $id > 0; });


    if (empty(trim($searchTerm))) {
        http_response_code(400);
        echo json_encode(['error' => 'Search term is required.', 'results' => []]);
        exit;
    }

    if (empty($projectIds)) {
        http_response_code(400);
        echo json_encode(['error' => 'At least one project ID must be selected.', 'results' => []]);
        exit;
    }

    $allResults = [];
    $errorsByProject = [];

    foreach ($projectIds as $projectId) {
        $projectData = [ // Used to collect data for this project before adding to allResults
            'project_id' => $projectId,
            'issues' => [],
            'wiki_pages' => []
        ];
        $hasDataForProject = false;

        // Search issues
        $issuesResult = search_in_project_issues($gitlabUrl, $gitlabApiKey, $projectId, $searchTerm);
        if (isset($issuesResult['error'])) {
            $errorsByProject[] = [
                'project_id' => $projectId,
                'type' => 'issues',
                'message' => "Failed to search issues: " . $issuesResult['error']
            ];
        } elseif (!empty($issuesResult['issues'])) {
            $projectData['issues'] = $issuesResult['issues'];
            $hasDataForProject = true;
        }

        // Search wiki pages
        $wikiResult = search_in_project_wikis($gitlabUrl, $gitlabApiKey, $projectId, $searchTerm);
        if (isset($wikiResult['error'])) {
            $errorsByProject[] = [
                'project_id' => $projectId,
                'type' => 'wiki_pages',
                'message' => "Failed to search wiki pages: " . $wikiResult['error']
            ];
        } elseif (!empty($wikiResult['wiki_pages'])) {
            $projectData['wiki_pages'] = $wikiResult['wiki_pages'];
            $hasDataForProject = true;
        }
        
        if ($hasDataForProject) {
            $allResults[] = $projectData;
        }
    }

    $responsePayload = ['results' => $allResults, 'errors_by_project' => $errorsByProject, 'error' => null];
    if (empty($allResults) && !empty($errorsByProject) && empty($_POST['search_term']) /* This last check is redundant due to earlier validation */ ) {
        // If there are no successful results AND there are project-specific errors,
        // it might be better to use the top-level 'error' for a general message,
        // but for now, specific errors in errors_by_project is more granular.
    }
    echo json_encode($responsePayload);

} else {
    http_response_code(400); // Bad Request
    // Check if action is missing or if it's a search action with wrong method
    if (empty($action)) {
        echo json_encode(['error' => 'No action specified.']);
    } elseif ($action === 'search' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Search action requires POST method.']);
    } else {
        echo json_encode(['error' => 'Invalid action or request method.']);
    }
}


/**
 * Generic helper function to make GET requests to GitLab API.
 *
 * @param string $url The full URL for the API endpoint.
 * @param string $gitlabApiKey The API key.
 * @return array ['success' => true, 'data' => array] or ['success' => false, 'error' => string]
 */
function search_gitlab_api(string $url, string $gitlabApiKey): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds total timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "PRIVATE-TOKEN: " . $gitlabApiKey,
        "User-Agent: GitLab-MultiProject-Search"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $resourceName = basename(parse_url($url, PHP_URL_PATH)); // Get a general idea of the resource

    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => "Could not connect to GitLab for '{$resourceName}' (cURL Error): {$curlError}"];
    }
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => "Failed to parse '{$resourceName}' data from GitLab: " . json_last_error_msg()];
        }
        return ['success' => true, 'data' => $data];
    } elseif ($httpCode === 401) {
        return ['success' => false, 'error' => "GitLab API Authentication Error (401) for '{$resourceName}'. Check your API key."];
    } elseif ($httpCode === 403) {
        return ['success' => false, 'error' => "GitLab API Forbidden (403) for '{$resourceName}'. Ensure the API key has sufficient permissions."];
    } elseif ($httpCode === 404) {
        return ['success' => false, 'error' => "GitLab API Resource Not Found (404) for '{$resourceName}'."];
    } else {
        $errorMsg = "GitLab API error for '{$resourceName}'. HTTP Code: {$httpCode}.";
        if (!empty($response)) {
            $errorMsg .= " Response: " . substr(strip_tags($response), 0, 150); // Basic sanitization and limit
        }
        return ['success' => false, 'error' => $errorMsg];
    }
}

/**
 * Creates a basic excerpt from text.
 *
 * @param string $text The text to create an excerpt from.
 * @param string $searchTerm The term to highlight (optional).
 * @param int $length The approximate length of the excerpt.
 * @return string The generated excerpt.
 */
function create_excerpt(string $text, string $searchTerm = '', int $length = 200): string {
    if (empty($text)) return '';

    $text = strip_tags($text); // Remove HTML tags

    if (empty($searchTerm)) {
        return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
    }

    // Attempt to find the search term and create context around it
    $searchTermPos = stripos($text, $searchTerm);
    if ($searchTermPos !== false) {
        $start = max(0, $searchTermPos - ($length / 2));
        $excerpt = mb_substr($text, $start, $length);
        if ($start > 0) $excerpt = "..." . $excerpt;
        if (mb_strlen($text) > ($start + $length)) $excerpt .= "...";
    } else {
        $excerpt = mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
    }
    
    // Highlight the search term
    if (!empty($searchTerm)) {
        $excerpt = preg_replace('/(' . preg_quote($searchTerm, '/') . ')/i', '<mark>$1</mark>', $excerpt);
    }
    return $excerpt;
}


/**
 * Searches for issues in a specific project.
 *
 * @param string $gitlabUrl
 * @param string $gitlabApiKey
 * @param int $projectId
 * @param string $searchTerm
 * @return array ['issues' => array] or ['error' => string]
 */
function search_in_project_issues(string $gitlabUrl, string $gitlabApiKey, int $projectId, string $searchTerm): array {
    $encodedSearchTerm = urlencode($searchTerm);
    $url = "{$gitlabUrl}/api/v4/projects/{$projectId}/issues?search={$encodedSearchTerm}&scope=all&in=title,description";
    
    $apiResult = search_gitlab_api($url, $gitlabApiKey);

    if (!$apiResult['success']) {
        return ['error' => $apiResult['error'], 'issues' => []];
    }

    $foundIssues = [];
    if (is_array($apiResult['data'])) {
        foreach ($apiResult['data'] as $issue) {
            $foundIssues[] = [
                'id' => $issue['id'],
                'iid' => $issue['iid'],
                'title' => htmlspecialchars($issue['title']),
                'web_url' => htmlspecialchars($issue['web_url']),
                'excerpt' => create_excerpt($issue['description'] ?? '', $searchTerm)
            ];
        }
    }
    return ['issues' => $foundIssues];
}

/**
 * Searches for wiki pages in a specific project.
 *
 * @param string $gitlabUrl
 * @param string $gitlabApiKey
 * @param int $projectId
 * @param string $searchTerm
 * @return array ['wiki_pages' => array] or ['error' => string]
 */
function search_in_project_wikis(string $gitlabUrl, string $gitlabApiKey, int $projectId, string $searchTerm): array {
    $encodedSearchTerm = urlencode($searchTerm);
    // Note: GitLab's project search for wiki_blobs often requires project's web_url to construct links.
    // We might need to fetch project details first or pass web_url if this function needs it.
    // For now, assuming the API returns enough info or we construct a generic link.
    // The API endpoint for wiki search is /projects/:id/search?scope=wiki_blobs&search=:term
    
    $url = "{$gitlabUrl}/api/v4/projects/{$projectId}/search?scope=wiki_blobs&search={$encodedSearchTerm}";
    
    $apiResult = search_gitlab_api($url, $gitlabApiKey);

    if (!$apiResult['success']) {
        return ['error' => $apiResult['error'], 'wiki_pages' => []];
    }

    $foundWikiPages = [];
    if (is_array($apiResult['data'])) {
        foreach ($apiResult['data'] as $wikiPage) {
            // Adjust based on actual API response structure for wiki_blobs search
            // Common fields: title, slug (or filename), content (or data for content match)
            // web_url needs to be constructed: project_web_url + /-/wikis/ + slug
            // This will require having the project's web_url.
            // For now, let's assume a simplified structure or that the API gives a direct web_url.
            // A more robust solution would fetch project details (including web_url) if not available.
            
            $title = $wikiPage['title'] ?? ($wikiPage['filename'] ?? 'Unknown Title');
            $slug = $wikiPage['slug'] ?? $wikiPage['filename']; // slug is preferred
            $content = $wikiPage['content'] ?? ($wikiPage['data'] ?? ''); // 'data' might be the field name in some GitLab versions for content match

            // Placeholder for web_url construction. This would ideally use the project's web_url.
            // $projectWebUrl = ...; // This needs to be fetched or passed.
            // $webUrl = $projectWebUrl . '/-/wikis/' . $slug;
            // For now, if the API doesn't provide a direct web_url, we'll omit it or use a placeholder.
            $webUrl = $wikiPage['web_url'] ?? ($gitlabUrl . "/" . $projectId . "/-/wikis/" . $slug); // Fallback, may not be accurate if project path is complex

            if (isset($wikiPage['project_id']) && $wikiPage['project_id'] != $projectId) {
                // This check is because the /search endpoint can sometimes return results from other projects if not careful
                // However, for /projects/:id/search, it should be scoped. Keeping as a safeguard.
                continue;
            }

            $foundWikiPages[] = [
                'title' => htmlspecialchars($title),
                'web_url' => htmlspecialchars($webUrl), // This needs accurate construction
                'excerpt' => create_excerpt($content, $searchTerm)
            ];
        }
    }
    return ['wiki_pages' => $foundWikiPages];
}

?>
