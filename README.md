# GitLab Multi-Project Issue and Wiki Search

## Overview

This tool provides a web interface to search for issues and wiki pages across multiple selected projects within a GitLab instance. It allows users to quickly find relevant information without manually browsing through each project.

## Features

*   **Multi-Project Search:** Search across several GitLab projects simultaneously.
*   **Dual Scope Search:** Searches in both project Issues (titles and descriptions) and project Wiki pages.
*   **Project Selection:** Users can select which projects to include in the search from a filterable list of their GitLab projects.
*   **Search Term Highlighting:** The search term is highlighted in the excerpts of the results.
*   **Direct Links:** Results provide direct links to the found issues or wiki pages.
*   **Simple Web Interface:** Easy-to-use interface for initiating searches and viewing results.

## Requirements

*   **PHP:** Version 7.4 or higher is recommended. (The script uses `getenv()` which is standard).
*   **Web Server:**
    *   A standard web server like Apache or Nginx with PHP configured.
    *   Alternatively, PHP's built-in web server can be used for development.
*   **PHP cURL Extension:** The `php_curl` extension must be enabled for making API requests to GitLab.
*   **Composer:** The PHP dependency manager, used to install `phpdotenv`.
*   **GitLab Instance & API Key:** Access to a GitLab instance and a Personal Access Token.

## Setup Instructions

1.  **Clone the Repository:**
    ```bash
    git clone <repository_url>
    cd <repository_directory>
    ```

2.  **Install Dependencies:**
    This project uses Composer to manage PHP dependencies. Install the required libraries (including `phpdotenv` for environment variable management):
    ```bash
    composer install
    ```

3.  **Configure Environment Variables:**
    This project uses a `.env` file to store your GitLab URL and API Key, loaded by the `phpdotenv` library.
    *   Copy the example environment file:
        ```bash
        cp .env.dist .env
        ```
    *   **Edit the `.env` file:**
        Open `.env` in a text editor and set the following variables:
        *   `GITLAB_URL`: The base URL of your GitLab instance (e.g., `https://gitlab.yourcompany.com` or `https://gitlab.com`). Do not include a trailing slash.
        *   `GITLAB_API_KEY`: Your GitLab Personal Access Token.

    *   **Generating a GitLab Personal Access Token:**
        1.  Log in to your GitLab account.
        2.  Go to your User Settings (click your avatar in the top right corner, then "Edit profile").
        3.  Navigate to "Access Tokens" in the left sidebar.
        4.  Give your token a name (e.g., "Multi-Project Search Tool").
        5.  Select the `api` scope. This scope provides read access to the API, which is necessary for fetching projects, issues, and wiki pages.
        6.  Click "Create personal access token".
        7.  **Important:** Copy the generated token immediately. You will not be able to see it again. Paste this token into the `GITLAB_API_KEY` field in your `.env` file.

    *   **Note on Environment Variables:**
        The `search.php` script now uses the `vlucas/phpdotenv` library to load `GITLAB_URL` and `GITLAB_API_KEY` directly from the `.env` file. This means you typically do not need to set these as system-wide environment variables or configure them in your web server if the `.env` file is present and correctly configured. The script will fall back to `getenv()` or `$_ENV` if the `.env` file is not found or a variable is not set within it, allowing for server-level environment variables as an alternative.

4.  **Running the Project:**
    *   **Using a Standard Web Server (Apache/Nginx):**
        Place the project files in a directory served by your web server. Ensure your web server is configured to point to the project's root where `index.php` and the `vendor` directory are located. Point your browser to `index.php` (e.g., `http://localhost/gitlab-search/index.php`).
    *   **Using PHP's Built-in Web Server (for development):**
        Navigate to the project's root directory in your terminal and run:
        ```bash
        php -S localhost:8000
        ```
        Then open `http://localhost:8000` in your web browser.

## Usage

1.  **Load Projects:** When you first open `index.php`, the application will attempt to fetch a list of your GitLab projects. A loading indicator will be shown.
2.  **Filter and Select Projects:**
    *   Once projects are loaded, they will appear in a list with checkboxes.
    *   You can use the "Filter Projects" input box to dynamically filter the list of projects by name.
    *   Check the boxes next to the projects you want to include in your search.
3.  **Enter Search Term:** Type the keyword or phrase you want to search for in the "Search Term" input field.
4.  **Perform Search:** Click the "Search" button.
5.  **Interpret Results:**
    *   Search results will be displayed below the search button, grouped by project.
    *   Each project section will list matching "Issues" and "Wiki Pages".
    *   Results include the title (linked to the item in GitLab) and a short excerpt with the search term highlighted.
    *   If no results are found for a particular project or type, a message will indicate this.
    *   Error messages will be displayed if issues occur during the search.

## API Endpoints (`search.php`)

The `search.php` file handles backend logic and can be interacted with via these actions:

*   **`search.php?action=get_projects`**
    *   **Method:** GET
    *   **Description:** Fetches the list of projects accessible by the configured API key. Returns a JSON array of projects.
*   **`search.php`**
    *   **Method:** POST
    *   **Parameters (form-data):**
        *   `action=search`
        *   `search_term`: The term to search for.
        *   `project_ids[]`: An array of selected project IDs.
    *   **Description:** Performs the search for issues and wiki pages in the specified projects. Returns a JSON object containing the results, grouped by project ID.

## Limitations

*   **Wiki Search Scope:** The search for wiki pages uses GitLab's `search?scope=wiki_blobs` API endpoint. The depth and comprehensiveness of this search within wiki page content might be limited compared to a dedicated full-text search engine or cloning the wiki repository and searching locally. It typically searches page titles and content.
*   **Performance:** Searching across a very large number of projects or using very broad search terms can be slow, as it involves making multiple API calls to GitLab sequentially.
*   **API Rate Limits:** Be mindful of GitLab API rate limits, especially on shared GitLab instances. Excessive use might lead to temporary blocking. The tool currently does not have explicit rate limit handling.
*   **Error Handling:** While basic error handling is in place, complex API errors or network issues might not always be gracefully handled or reported with full detail to the user.

---
This README provides a basic guide. Feel free to contribute to improve its clarity or add more details.
