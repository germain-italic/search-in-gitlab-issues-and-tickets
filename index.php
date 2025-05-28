<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Check if .env file exists and contains required variables
$configValid = isset($_ENV['GITLAB_URL']) && isset($_ENV['GITLAB_API_KEY']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitLab Search Portal</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <header>
            <div class="logo">
                <i class="fa-brands fa-gitlab"></i>
                <h1>GitLab Search Portal</h1>
            </div>
            <?php if (!$configValid): ?>
                <div class="config-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Configuration not complete. Please set up your .env file based on .env.dist template.</span>
                </div>
            <?php endif; ?>
        </header>

        <main class="content">
            <div class="search-container">
                <form id="searchForm" action="src/search.php" method="post">
                    <div class="search-row">
                        <div class="form-group">
                            <label for="searchString">Search String</label>
                            <input type="text" id="searchString" name="searchString" placeholder="Enter text to search for..." required>
                        </div>
                        
                        <div class="form-group">
                            <label for="projectFilter">Filter Projects</label>
                            <div class="project-filter-container">
                                <input type="text" id="projectFilter" placeholder="Type to filter projects...">
                                <div id="projectDropdown" class="dropdown-content">
                                    <div class="loading-projects">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <span>Loading projects...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="search-options">
                        <div class="form-group checkbox-group">
                            <label>Search In:</label>
                            <div class="checkbox-container">
                                <input type="checkbox" id="searchIssues" name="searchIn[]" value="issues" checked>
                                <label for="searchIssues">Issues</label>
                            </div>
                            <div class="checkbox-container">
                                <input type="checkbox" id="searchWiki" name="searchIn[]" value="wiki" checked>
                                <label for="searchWiki">Wiki</label>
                            </div>
                        </div>
                    </div>

                    <div class="selected-projects-container">
                        <h3>Selected Projects <span id="selectedCount">(0)</span></h3>
                        <div id="selectedProjects" class="selected-projects"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" id="searchButton" class="btn primary" <?= !$configValid ? 'disabled' : '' ?>>
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>

            <div id="results" class="results-container">
                <div class="results-placeholder">
                    <i class="fas fa-search"></i>
                    <p>Enter a search term and select projects to begin</p>
                </div>
            </div>
        </main>

        <footer>
            <p>GitLab Search Portal &copy; <?= date('Y') ?></p>
        </footer>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>