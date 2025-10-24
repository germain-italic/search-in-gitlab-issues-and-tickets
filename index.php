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
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitLab Search Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="min-vh-100 d-flex flex-column">
        <header class="bg-white shadow-sm py-3">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fa-brands fa-gitlab gitlab-logo"></i>
                        <h1 class="h4 mb-0">GitLab Search Portal</h1>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                            <label class="form-check-label" for="darkModeSwitch" id="darkModeLabel">
                                <i class="fas fa-moon"></i>
                            </label>
                        </div>
                        <?php if (!$configValid): ?>
                            <div class="alert alert-warning py-2 px-3 mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <span>Configuration not complete. Please set up your .env file based on .env.dist template.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="container my-4 flex-grow-1">
            <div class="bg-white rounded shadow-sm p-4 mb-4">
                <form id="searchForm" action="src/search.php" method="post">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="searchString" class="form-label">Search String</label>
                                <input type="text" class="form-control" id="searchString" name="searchString" 
                                       placeholder="Enter text to search for..." required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="projectFilter" class="form-label">Filter Projects</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="projectFilter" 
                                           placeholder="Type to filter projects...">
                                    <div id="projectDropdown" class="dropdown-menu w-100">
                                        <div class="p-3 text-center">
                                            <i class="fas fa-spinner fa-spin me-2"></i>
                                            <span>Loading projects...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Search In:</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="searchIssues"
                                       name="searchIn[]" value="issues" checked>
                                <label class="form-check-label" for="searchIssues">Issues</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="searchWiki"
                                       name="searchIn[]" value="wiki" checked>
                                <label class="form-check-label" for="searchWiki">Wiki</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="searchComments"
                                       name="searchIn[]" value="comments" checked>
                                <label class="form-check-label" for="searchComments">Comments</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">Selected Projects <span id="selectedCount" class="text-muted">(0)</span></h6>
                        <div id="selectedProjects" class="d-flex flex-wrap gap-2"></div>
                    </div>

                    <div class="text-end">
                        <button type="submit" id="searchButton" class="btn btn-gitlab" <?= !$configValid ? 'disabled' : '' ?>>
                            <i class="fas fa-search me-2"></i> Search
                        </button>
                    </div>
                </form>
            </div>

            <div id="results" class="bg-white rounded shadow-sm p-4">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-search fs-1 mb-3 d-block"></i>
                    <p>Enter a search term and select projects to begin</p>
                </div>
            </div>
        </main>

        <footer class="bg-white border-top py-3 text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> <a href="https://github.com/germain-italic/search-in-gitlab-issues-and-tickets" target="_blank">GitLab Search Portal</a> by <a href="https://github.com/germain-italic" target="_blank">Germain Italic</a></p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    // Dark mode logic
    const darkModeSwitch = document.getElementById('darkModeSwitch');
    function setDarkMode(enabled) {
        if (enabled) {
            document.documentElement.classList.add('dark-mode');
        } else {
            document.documentElement.classList.remove('dark-mode');
        }
    }
    // Load preference from localStorage or system
    function loadDarkModePref() {
        const stored = localStorage.getItem('darkMode');
        if (stored === 'dark') return true;
        if (stored === 'light') return false;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    // Save preference
    function saveDarkModePref(enabled) {
        localStorage.setItem('darkMode', enabled ? 'dark' : 'light');
    }
    // Initial
    let darkMode = loadDarkModePref();
    setDarkMode(darkMode);
    darkModeSwitch.checked = darkMode;
    // Listen to switch
    darkModeSwitch.addEventListener('change', function() {
        setDarkMode(this.checked);
        saveDarkModePref(this.checked);
    });
    // Listen to system changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        // Only update if user has not set a manual preference
        const stored = localStorage.getItem('darkMode');
        if (stored !== 'dark' && stored !== 'light') {
            setDarkMode(e.matches);
            darkModeSwitch.checked = e.matches;
        }
    });
    </script>
</body>
</html>