<?php
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
    
    // Check if required environment variables are set
    if (!isset($_ENV['GITLAB_URL']) || !isset($_ENV['GITLAB_API_KEY'])) {
        throw new Exception('GitLab configuration not found. Please check your .env file.');
    }
    
    // Initialize GitLab API client
    $client = new Client([
        'base_uri' => rtrim($_ENV['GITLAB_URL'], '/') . '/api/v4/',
        'headers' => [
            'PRIVATE-TOKEN' => $_ENV['GITLAB_API_KEY']
        ]
    ]);
    
    // Fetch projects (paginated)
    $page = 1;
    $perPage = 100;
    $allProjects = [];
    
    do {
        $response = $client->request('GET', 'projects', [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
                'order_by' => 'name',
                'sort' => 'asc',
                'membership' => true, // Only get projects the user is a member of
                'simple' => true // Simplified project data
            ]
        ]);
        
        $projects = json_decode($response->getBody()->getContents(), true);
        $allProjects = array_merge($allProjects, $projects);
        
        // Check if there are more pages
        $totalPages = $response->getHeader('X-Total-Pages');
        $totalPages = $totalPages ? (int)$totalPages[0] : 1;
        
        $page++;
    } while ($page <= $totalPages);
    
    echo json_encode($allProjects);
    
} catch (Exception | GuzzleException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}