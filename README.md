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
- Node.js and npm (for frontend build)
- Access to a GitLab instance with API access

## Installation

1. Clone this repository or download the files
2. Run `composer install` to install dependencies
3. Copy `.env.dist` to `.env` and update with your GitLab instance URL and API key:

   ```
   GITLAB_URL=https://gitlab.example.com
   GITLAB_API_KEY=your_private_token_here
   ```

## Running in Development

You can run the application locally using PHP's built-in server:

```sh
php -S localhost:8080
```

Then open [http://localhost:8080](http://localhost:8080) in your browser.

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

## Building and Deploying

To build the frontend for production:

```sh
npm install
npm run build
```

After the build completes:

1. Upload the contents of the `dist` directory to your server's web root.
2. Make sure the `.htaccess` file is also uploaded to the web root.
3. Ensure your Apache configuration has `mod_rewrite` enabled and `AllowOverride All` set for your directory.

   Example Apache configuration:
   ```
   <Directory /var/www/html>
       AllowOverride All
   </Directory>
   ```

4. **If you're using Nginx instead of Apache**, add the following to your server block:

   ```
   location / {
       try_files $uri $uri/ /index.html;
   }

   location ~* \.(js|jsx|tsx|css|json)$ {
       add_header Content-Type $content_type;
       add_header Access-Control-Allow-Origin "*";
   }
   ```

## Project Structure

- `index.php` - Main entry point
- `src/` - PHP source files
- `assets/` - Frontend assets (CSS, JavaScript)
- `.env` - Configuration file (not in version control)
- `.env.dist` - Template for configuration file

## License

MIT