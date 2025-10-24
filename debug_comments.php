<?php
/**
 * Debug script to test comment search functionality
 * Usage: php debug_comments.php <project_id> <search_term>
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Check command line arguments
if ($argc < 3) {
    echo "Usage: php debug_comments.php <project_id> <search_term>\n";
    exit(1);
}

$projectId = $argv[1];
$searchString = $argv[2];

echo "Debugging comment search...\n";
echo "Project ID: $projectId\n";
echo "Search term: $searchString\n";
echo str_repeat("-", 50) . "\n\n";

// Initialize GitLab API client
$client = new Client([
    'base_uri' => rtrim($_ENV['GITLAB_URL'], '/') . '/api/v4/',
    'headers' => [
        'PRIVATE-TOKEN' => $_ENV['GITLAB_API_KEY']
    ]
]);

try {
    // Get project details
    echo "1. Fetching project details...\n";
    $response = $client->request('GET', "projects/{$projectId}");
    $project = json_decode($response->getBody()->getContents(), true);
    echo "   Project: {$project['name']}\n\n";

    // Get all issues
    echo "2. Fetching issues...\n";
    $issuesResponse = $client->request('GET', "projects/{$projectId}/issues", [
        'query' => [
            'scope' => 'all',
            'per_page' => 100
        ]
    ]);
    $issues = json_decode($issuesResponse->getBody()->getContents(), true);
    echo "   Found " . count($issues) . " issues\n\n";

    if (empty($issues)) {
        echo "No issues found in this project.\n";
        exit(0);
    }

    // Search through issues
    echo "3. Searching for comments containing '$searchString'...\n\n";
    $foundComments = 0;

    foreach ($issues as $issue) {
        echo "   Issue #{$issue['iid']}: {$issue['title']}\n";

        // Get notes for this issue
        try {
            $notesResponse = $client->request('GET', "projects/{$projectId}/issues/{$issue['iid']}/notes");
            $notes = json_decode($notesResponse->getBody()->getContents(), true);

            echo "     - Found " . count($notes) . " notes\n";

            foreach ($notes as $note) {
                // Skip system notes
                if (isset($note['system']) && $note['system']) {
                    continue;
                }

                // Check if comment contains search term
                if (stripos($note['body'] ?? '', $searchString) !== false) {
                    $foundComments++;
                    echo "     ✓ MATCH in comment by {$note['author']['name']}\n";
                    echo "       Date: {$note['created_at']}\n";
                    echo "       Excerpt: " . substr($note['body'], 0, 100) . "...\n";
                    echo "       URL: {$issue['web_url']}#note_{$note['id']}\n";
                }
            }
        } catch (Exception $e) {
            echo "     ERROR: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    echo str_repeat("-", 50) . "\n";
    echo "Total comments found: $foundComments\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
