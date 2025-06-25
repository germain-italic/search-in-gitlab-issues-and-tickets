// Wait for the DOM to be fully loaded before executing
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const searchForm = document.getElementById('searchForm');
    const projectFilter = document.getElementById('projectFilter');
    const projectDropdown = document.getElementById('projectDropdown');
    const selectedProjects = document.getElementById('selectedProjects');
    const selectedCount = document.getElementById('selectedCount');
    const searchButton = document.getElementById('searchButton');
    const results = document.getElementById('results');
    
    // State
    let projects = [];
    let selectedProjectIds = [];
    
    // Fetch projects on page load
    fetchProjects();
    
    // Event Listeners
    projectFilter.addEventListener('focus', showDropdown);
    projectFilter.addEventListener('input', filterProjects);
    searchForm.addEventListener('submit', handleSearch);
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!projectFilter.contains(event.target) && !projectDropdown.contains(event.target)) {
            projectDropdown.classList.remove('show');
        }
    });
    
    // Functions
    
    /**
     * Fetch projects from the GitLab API
     */
    function fetchProjects() {
        // Show loading state
        projectDropdown.innerHTML = `
            <div class="loading-projects">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading projects...</span>
            </div>
        `;
        
        // Fetch projects from API
        fetch('src/get_projects.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch projects');
                }
                return response.json();
            })
            .then(data => {
                projects = data;
                renderProjectOptions(projects);
            })
            .catch(error => {
                projectDropdown.innerHTML = `
                    <div class="loading-projects">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Error: ${error.message}</span>
                    </div>
                `;
                console.error('Error fetching projects:', error);
            });
    }
    
    /**
     * Get the display name for a project including its group path
     * @param {Object} project - The project object
     * @returns {string} The formatted display name
     */
    function getProjectDisplayName(project) {
        const pathParts = project.path_with_namespace.split('/');
        const projectName = pathParts.pop(); // Get the project name
        const groupPath = pathParts.join(' / '); // Join the remaining parts with slashes
        return groupPath ? `${groupPath} - ${project.name}` : project.name;
    }
    
    /**
     * Render project options in the dropdown
     * @param {Array} projectsToRender - Array of projects to display
     */
    function renderProjectOptions(projectsToRender) {
        if (projectsToRender.length === 0) {
            projectDropdown.innerHTML = `
                <div class="loading-projects">
                    <i class="fas fa-info-circle"></i>
                    <span>No projects found</span>
                </div>
            `;
            return;
        }
        
        projectDropdown.innerHTML = '';
        
        // Add select all option
        const selectAllOption = document.createElement('div');
        selectAllOption.className = 'project-option';
        selectAllOption.innerHTML = `
            <input type="checkbox" id="selectAll" ${projectsToRender.length === selectedProjectIds.length ? 'checked' : ''}>
            <label for="selectAll"><strong>Select All</strong></label>
        `;
        selectAllOption.addEventListener('click', function() {
            const selectAllCheckbox = this.querySelector('input');
            selectAllCheckbox.checked = !selectAllCheckbox.checked;
            
            if (selectAllCheckbox.checked) {
                // Select all projects
                selectedProjectIds = projectsToRender.map(project => project.id);
            } else {
                // Deselect all projects
                selectedProjectIds = [];
            }
            
            renderProjectOptions(projectsToRender);
            updateSelectedProjects();
        });
        projectDropdown.appendChild(selectAllOption);
        
        // Add divider
        const divider = document.createElement('div');
        divider.style.borderBottom = '1px solid var(--border-color)';
        divider.style.margin = '8px 0';
        projectDropdown.appendChild(divider);
        
        // Add project options
        projectsToRender.forEach(project => {
            const isSelected = selectedProjectIds.includes(project.id);
            const displayName = getProjectDisplayName(project);
            
            const option = document.createElement('div');
            option.className = 'project-option';
            option.innerHTML = `
                <input type="checkbox" id="project-${project.id}" ${isSelected ? 'checked' : ''}>
                <label for="project-${project.id}">${displayName}</label>
            `;
            
            option.addEventListener('click', function() {
                const checkbox = this.querySelector('input');
                checkbox.checked = !checkbox.checked;
                
                if (checkbox.checked) {
                    // Add to selected projects
                    if (!selectedProjectIds.includes(project.id)) {
                        selectedProjectIds.push(project.id);
                    }
                } else {
                    // Remove from selected projects
                    selectedProjectIds = selectedProjectIds.filter(id => id !== project.id);
                }
                
                updateSelectedProjects();
            });
            
            projectDropdown.appendChild(option);
        });
    }
    
    /**
     * Update the selected projects display and form input
     */
    function updateSelectedProjects() {
        // Update hidden input for form submission
        const existingInput = document.getElementById('selectedProjectsInput');
        if (existingInput) existingInput.remove();
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.id = 'selectedProjectsInput';
        input.name = 'projectIds';
        input.value = JSON.stringify(selectedProjectIds);
        searchForm.appendChild(input);
        
        // Update selected projects display
        selectedProjects.innerHTML = '';
        
        const selectedProjectsData = projects.filter(project => selectedProjectIds.includes(project.id));
        
        selectedProjectsData.forEach(project => {
            const displayName = getProjectDisplayName(project);
            const projectElement = document.createElement('div');
            projectElement.className = 'selected-project';
            projectElement.innerHTML = `
                <span>${displayName}</span>
                <i class="fas fa-times remove" data-id="${project.id}"></i>
            `;
            
            projectElement.querySelector('.remove').addEventListener('click', function() {
                const projectId = this.getAttribute('data-id');
                selectedProjectIds = selectedProjectIds.filter(id => id !== projectId);
                updateSelectedProjects();
                renderProjectOptions(filterProjectsList(projectFilter.value));
            });
            
            selectedProjects.appendChild(projectElement);
        });
        
        // Update count
        selectedCount.textContent = `(${selectedProjectIds.length})`;
        
        // Enable/disable search button
        searchButton.disabled = selectedProjectIds.length === 0;
    }
    
    /**
     * Show the project dropdown
     */
    function showDropdown() {
        projectDropdown.classList.add('show');
    }
    
    /**
     * Filter projects based on search input
     */
    function filterProjects() {
        const searchTerm = projectFilter.value.toLowerCase();
        const filteredProjects = filterProjectsList(searchTerm);
        renderProjectOptions(filteredProjects);
    }
    
    /**
     * Filter the projects list based on search term
     * @param {string} searchTerm - The search term to filter by
     * @returns {Array} Filtered array of projects
     */
    function filterProjectsList(searchTerm) {
        if (!searchTerm) return projects;
        
        return projects.filter(project => {
            const displayName = getProjectDisplayName(project);
            return displayName.toLowerCase().includes(searchTerm);
        });
    }
    
    /**
     * Handle the search form submission
     * @param {Event} event - The form submission event
     */
    function handleSearch(event) {
        event.preventDefault();
        
        // Check if any projects are selected
        if (selectedProjectIds.length === 0) {
            alert('Please select at least one project to search');
            return;
        }
        
        // Show loading state
        results.innerHTML = `
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Searching GitLab...</p>
            </div>
        `;
        
        // Collect form data
        const formData = new FormData(searchForm);
        
        // Send search request
        fetch('src/search.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Search failed');
            }
            return response.json();
        })
        .then(data => {
            renderResults(data);
        })
        .catch(error => {
            results.innerHTML = `
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Error: ${error.message}</p>
                </div>
            `;
            console.error('Search error:', error);
        });
    }
    
    /**
     * Render search results
     * @param {Object} data - The search results data
     */
    function renderResults(data) {
        if (!data || Object.keys(data).length === 0) {
            results.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <p>No results found. Try a different search term or select more projects.</p>
                </div>
            `;
            return;
        }
        
        results.innerHTML = '';
        
        // Iterate through projects
        Object.keys(data).forEach(projectId => {
            const projectData = data[projectId];
            const projectName = projectData.name;
            
            const projectElement = document.createElement('div');
            projectElement.className = 'project-results';
            
            projectElement.innerHTML = `<h2>${projectName}</h2>`;
            
            // Check if there are any results for this project
            let hasResults = false;
            
            // Issues section
            if (projectData.issues && projectData.issues.length > 0) {
                hasResults = true;
                
                const issuesSection = document.createElement('div');
                issuesSection.className = 'result-type';
                issuesSection.innerHTML = `<h3>Issues</h3>`;
                
                projectData.issues.forEach(issue => {
                    const issueElement = document.createElement('div');
                    issueElement.className = 'result-item';
                    
                    // Build labels HTML
                    let labelsHtml = '';
                    if (issue.labels && issue.labels.length > 0) {
                        labelsHtml = issue.labels.map(label => `<span class="badge bg-secondary ms-2">${label}</span>`).join('');
                    }

                    // Closed icon if needed
                    let closedIconHtml = '';
                    if (issue.state && issue.state.toLowerCase() === 'closed') {
                        closedIconHtml = '<i class="fas fa-lock text-danger me-1" title="Closed"></i>';
                    }

                    issueElement.innerHTML = `
                        <div class="result-item-header">
                            <div class="result-item-title">${closedIconHtml}#${issue.iid}: ${issue.title}${labelsHtml}</div>
                            <a href="${issue.web_url}" target="_blank" class="result-item-link">
                                View Issue <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <div class="result-excerpt">${highlightSearchTerm(issue.excerpt, projectData.searchString)}</div>
                    `;
                    
                    issuesSection.appendChild(issueElement);
                });
                
                projectElement.appendChild(issuesSection);
            }
            
            // Wiki section
            if (projectData.wiki && projectData.wiki.length > 0) {
                hasResults = true;
                
                const wikiSection = document.createElement('div');
                wikiSection.className = 'result-type';
                wikiSection.innerHTML = `<h3>Wiki</h3>`;
                
                projectData.wiki.forEach(page => {
                    const wikiElement = document.createElement('div');
                    wikiElement.className = 'result-item';
                    
                    wikiElement.innerHTML = `
                        <div class="result-item-header">
                            <div class="result-item-title">${page.title}</div>
                            <a href="${page.web_url}" target="_blank" class="result-item-link">
                                View Page <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <div class="result-excerpt">${highlightSearchTerm(page.excerpt, projectData.searchString)}</div>
                    `;
                    
                    wikiSection.appendChild(wikiElement);
                });
                
                projectElement.appendChild(wikiSection);
            }
            
            // Only add project to results if it has results
            if (hasResults) {
                results.appendChild(projectElement);
            }
        });
        
        // If no results were added
        if (results.innerHTML === '') {
            results.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <p>No results found. Try a different search term or select more projects.</p>
                </div>
            `;
        }
    }
    
    /**
     * Highlight search term in text
     * @param {string} text - The text to highlight
     * @param {string} searchTerm - The term to highlight
     * @returns {string} Text with highlighted search term
     */
    function highlightSearchTerm(text, searchTerm) {
        if (!text || !searchTerm) return text;
        
        // Escape special regex characters
        const escapedSearchTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        
        // Create regex with global and case-insensitive flags
        const regex = new RegExp(`(${escapedSearchTerm})`, 'gi');
        
        // Replace occurrences with highlighted version
        return text.replace(regex, '<span class="highlight">$1</span>');
    }
});