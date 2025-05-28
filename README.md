# GitLab Search Portal

A web application that allows you to search for strings across GitLab issues and wikis in a self-hosted GitLab instance.

## Features

- Project filtering with autocomplete dropdown and multi-select checkboxes
- String search across GitLab issues and wikis in selected projects
- Secure API key management using .env files
- Organized results display by project and content type (issues/wiki)
- Content excerpts with highlighted search terms
- Direct links to original GitLab items

## Requirements

- PHP 7.4 or higher
- Composer
- Access to a GitLab instance with API access

## Installation

1. Clone this repository or download the files
2. Run `composer install` to install dependencies
3. Copy `.env.dist` to `.env` and update with your GitLab instance URL and API key:

```
GITLAB_URL=https://gitlab.example.com
GITLAB_API_KEY=your_private_token_here
```

4. Serve the application using your preferred web server (Apache, Nginx, or PHP's built-in server)

## Usage

1. Open the application in your web browser
2. Enter a search string in the search field
3. Select projects from the dropdown
4. Choose which content types to search (issues, wiki, or both)
5. Click the "Search" button to see results

## Development

The application uses:

- PHP for backend processing
- JavaScript for frontend interactions
- CSS for styling
- GitLab REST API for data retrieval

## Project Structure

- `index.php` - Main entry point
- `src/` - PHP source files
- `assets/` - Frontend assets (CSS, JavaScript)
- `.env` - Configuration file (not in version control)
- `.env.dist` - Template for configuration file

## License

MIT