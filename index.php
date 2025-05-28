<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitLab Search</title>
    <style>
        /* Basic styling - will improve later */
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: auto; }
        .project-list { max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;}
        .project-item { display: block; }
        .results { margin-top: 20px; }
        .result-project { margin-bottom: 15px; }
        .result-project h3 { margin-bottom: 5px; }
        .result-type { margin-left: 10px; }
        .result-item { margin-left: 20px; padding: 5px; border-bottom: 1px solid #eee; }
        .result-item a { text-decoration: none; color: #007bff; }
        .result-item mark { background-color: yellow; }
        .error { color: red; }
        .loading { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>GitLab Multi-Project Search</h1>

        <div id="error-message" class="error"></div>
        <div id="loading-indicator" class="loading">Loading...</div>

        <div>
            <label for="search-term">Search Term:</label>
            <input type="text" id="search-term" name="search-term">
        </div>
        <br>
        <div>
            <label for="project-filter">Filter Projects:</label>
            <input type="text" id="project-filter" name="project-filter" placeholder="Start typing to filter projects...">
            <div id="project-list" class="project-list">
                <!-- Projects will be loaded here by JavaScript -->
            </div>
        </div>
        <br>
        <button id="search-button">Search</button>

        <div id="results" class="results">
            <!-- Search results will be displayed here -->
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const projectListDiv = document.getElementById('project-list');
            const projectFilterInput = document.getElementById('project-filter');
            const errorMessageDiv = document.getElementById('error-message');
            const loadingIndicator = document.getElementById('loading-indicator');
            const searchButton = document.getElementById('search-button');
            const resultsDiv = document.getElementById('results');
            const searchTermInput = document.getElementById('search-term');

            let projectMap = new Map(); // To store project details for easy lookup

            async function fetchProjects() {
                loadingIndicator.style.display = 'block';
                errorMessageDiv.textContent = '';
                errorMessageDiv.style.display = 'none';
                projectListDiv.innerHTML = ''; // Clear previous projects
                // Disable controls initially, enable on success
                projectFilterInput.disabled = true;
                searchButton.disabled = true;

                try {
                    const response = await fetch('search.php?action=get_projects');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
                    }
                    const data = await response.json();

                    if (data.error) {
                        throw new Error(data.error);
                    }

                    if (data.projects && data.projects.length > 0) {
                        // Clear previous projectMap and populate with new projects
                        projectMap.clear();
                        data.projects.forEach(project => {
                            projectMap.set(project.id.toString(), project); // Store by ID (string)
                        });
                        displayProjects(data.projects);
                    } else {
                        projectMap.clear(); // Clear map if no projects
                        projectListDiv.innerHTML = 'No projects found. Please check your GitLab setup or if you have access to any projects.';
                        projectFilterInput.disabled = true;
                        searchButton.disabled = true;
                    }
                } catch (error) {
                    console.error('Fetch projects error:', error);
                    const userFriendlyError = `Error fetching projects: ${error.message}. Please ensure GitLab URL and API Key are correctly configured and the GitLab instance is reachable.`;
                    errorMessageDiv.textContent = userFriendlyError;
                    errorMessageDiv.style.display = 'block';
                    projectListDiv.innerHTML = '<p style="color: red;">Could not load project list. Check error messages above.</p>';
                    projectMap.clear(); // Clear map on error
                    projectFilterInput.disabled = true;
                    searchButton.disabled = true;
                } finally {
                    loadingIndicator.style.display = 'none';
                    // Enable controls if projects were loaded
                    if (projectMap.size > 0) {
                        projectFilterInput.disabled = false;
                        searchButton.disabled = false;
                    }
                }
            }

            function displayProjects(projects) {
                projectListDiv.innerHTML = ''; // Clear current list
                if (!projects || !Array.isArray(projects) || projects.length === 0) {
                    // This case should ideally be handled before calling displayProjects
                    // by checking projectMap.size or data.projects.length in fetchProjects
                    projectListDiv.innerHTML = 'No projects found or error in loading projects.';
                    projectFilterInput.disabled = true;
                    searchButton.disabled = true;
                    return;
                }
                
                projectFilterInput.disabled = false;
                searchButton.disabled = false;

                projects.forEach(project => {
                    const projectItemLabel = document.createElement('label');
                    projectItemLabel.className = 'project-item';
                    
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'project_ids[]';
                    checkbox.value = project.id;
                    checkbox.dataset.name = project.name_with_namespace.toLowerCase();
                    
                    projectItemLabel.appendChild(checkbox);
                    projectItemLabel.appendChild(document.createTextNode(` ${project.name_with_namespace}`));
                    
                    projectListDiv.appendChild(projectItemLabel);
                });
            }

            projectFilterInput.addEventListener('input', () => {
                const filterText = projectFilterInput.value.toLowerCase();
                const projectItems = projectListDiv.getElementsByClassName('project-item');

                Array.from(projectItems).forEach(item => {
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        const projectName = checkbox.dataset.name;
                        if (projectName.includes(filterText)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    }
                });
            });

            searchButton.addEventListener('click', async () => {
                const searchTerm = searchTermInput.value.trim();
                const selectedProjectCheckboxes = document.querySelectorAll('#project-list input[type="checkbox"]:checked');
                const selectedProjectIds = Array.from(selectedProjectCheckboxes).map(cb => cb.value);

                // Clear previous general and results-specific errors
                errorMessageDiv.textContent = '';
                errorMessageDiv.style.display = 'none';
                resultsDiv.innerHTML = ''; // Clear previous results

                if (!searchTerm) {
                    errorMessageDiv.textContent = 'Search term is required.';
                    errorMessageDiv.style.display = 'block';
                    return;
                }

                if (selectedProjectIds.length === 0) {
                    errorMessageDiv.textContent = 'At least one project must be selected.';
                    errorMessageDiv.style.display = 'block';
                    return;
                }

                loadingIndicator.style.display = 'block';

                const formData = new FormData();
                formData.append('action', 'search');
                formData.append('search_term', searchTerm);
                selectedProjectIds.forEach(id => formData.append('project_ids[]', id));

                try {
                    const response = await fetch('search.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    displaySearchResults(data, projectMap);
                } catch (error) {
                    console.error('Search error:', error);
                    errorMessageDiv.textContent = `Error performing search: ${error.message}`;
                    errorMessageDiv.style.display = 'block';
                } finally {
                    loadingIndicator.style.display = 'none';
                }
            });

            function displaySearchResults(data, currentProjectMap) {
                resultsDiv.innerHTML = ''; // Clear previous results
                resultsDiv.innerHTML = ''; // Clear previous results again just in case
                loadingIndicator.style.display = 'none';

                // Handle top-level error from backend (e.g., config missing, fatal error)
                if (data.error) {
                    errorMessageDiv.textContent = `Search failed: ${data.error}`;
                    errorMessageDiv.style.display = 'block';
                    // No return here, as errors_by_project might still have info
                }

                // Display per-project errors, if any
                if (data.errors_by_project && Array.isArray(data.errors_by_project) && data.errors_by_project.length > 0) {
                    const errorSummaryDiv = document.createElement('div');
                    errorSummaryDiv.className = 'error-summary';
                    errorSummaryDiv.innerHTML = '<h4>Search Errors Encountered:</h4>';
                    const errorList = document.createElement('ul');
                    data.errors_by_project.forEach(err => {
                        const projectInfo = currentProjectMap.get(err.project_id.toString());
                        const projectName = projectInfo ? projectInfo.name_with_namespace : `Project ID ${err.project_id}`;
                        const errorItem = document.createElement('li');
                        errorItem.textContent = `Project ${projectName} (${err.type}): ${err.message}`;
                        errorList.appendChild(errorItem);
                    });
                    errorSummaryDiv.appendChild(errorList);
                    resultsDiv.appendChild(errorSummaryDiv); // Add to top of results
                }

                // Display successful results
                if (data.results && Array.isArray(data.results)) {
                    if (data.results.length === 0 && (!data.errors_by_project || data.errors_by_project.length === 0)) {
                        // Only show "No results found" if there were no other errors reported
                        resultsDiv.innerHTML += '<p>No results found.</p>'; // Append, in case errors were displayed
                        return;
                    }

                    data.results.forEach(projectResult => {
                        const projectInfo = currentProjectMap.get(projectResult.project_id.toString());
                        const projectName = projectInfo ? projectInfo.name_with_namespace : `Project ID: ${projectResult.project_id}`;

                        const projectSection = document.createElement('div');
                        projectSection.className = 'result-project';
                        projectSection.innerHTML = `<h3>Project: ${projectName}</h3>`;

                        // Issues
                        const issuesSection = document.createElement('div');
                        issuesSection.className = 'result-type';
                        if (projectResult.issues && projectResult.issues.length > 0) {
                            issuesSection.innerHTML = '<h4>Issues:</h4>';
                            const issuesList = document.createElement('ul');
                            projectResult.issues.forEach(issue => {
                                const item = document.createElement('li');
                                item.className = 'result-item';
                                item.innerHTML = `<strong><a href="${issue.web_url}" target="_blank">${issue.title}</a></strong><p>${issue.excerpt}</p>`;
                                issuesList.appendChild(item);
                            });
                            issuesSection.appendChild(issuesList);
                        } else {
                             // Check if there was an error for this specific project and type; if not, then show "no results"
                            const hadErrorForIssues = data.errors_by_project && data.errors_by_project.some(e => e.project_id.toString() === projectResult.project_id.toString() && e.type === 'issues');
                            if (!hadErrorForIssues) {
                                issuesSection.innerHTML = '<h4>Issues:</h4><p>No issues found in this project for the search term.</p>';
                            } else {
                                issuesSection.innerHTML = '<h4>Issues:</h4><p>Could not retrieve issues (see errors above).</p>';
                            }
                        }
                        projectSection.appendChild(issuesSection);

                        // Wiki Pages
                        const wikiSection = document.createElement('div');
                        wikiSection.className = 'result-type';
                        if (projectResult.wiki_pages && projectResult.wiki_pages.length > 0) {
                            wikiSection.innerHTML = '<h4>Wiki Pages:</h4>';
                            const wikiList = document.createElement('ul');
                            projectResult.wiki_pages.forEach(wikiPage => {
                                const item = document.createElement('li');
                                item.className = 'result-item';
                                item.innerHTML = `<strong><a href="${wikiPage.web_url}" target="_blank">${wikiPage.title}</a></strong><p>${wikiPage.excerpt}</p>`;
                                wikiList.appendChild(item);
                            });
                            wikiSection.appendChild(wikiList);
                        } else {
                            const hadErrorForWiki = data.errors_by_project && data.errors_by_project.some(e => e.project_id.toString() === projectResult.project_id.toString() && e.type === 'wiki_pages');
                            if (!hadErrorForWiki) {
                                wikiSection.innerHTML = '<h4>Wiki Pages:</h4><p>No wiki pages found in this project for the search term.</p>';
                            } else {
                                 wikiSection.innerHTML = '<h4>Wiki Pages:</h4><p>Could not retrieve wiki pages (see errors above).</p>';
                            }
                        }
                        projectSection.appendChild(wikiSection);

                        resultsDiv.appendChild(projectSection);
                    });
                } else if (!data.error && (!data.errors_by_project || data.errors_by_project.length === 0)) {
                    // If no results, no top-level error, and no per-project errors, it might be an unexpected backend state
                    errorMessageDiv.textContent = 'Search returned no information.';
                    errorMessageDiv.style.display = 'block';
                    resultsDiv.innerHTML = '<p>Failed to display results due to unexpected response.</p>';
                }
            }
            // Initial fetch of projects when the page loads
            fetchProjects();
        });
    </script>
</body>
</html>
