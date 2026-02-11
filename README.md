# 2bo Light PHP

> Hassle-Free PHP Micro Framework for simple and lightweight applications.

**2bo Light PHP** is a single-file, zero-dependency PHP micro framework designed for real-world hosting environments where VPS access might not be available or necessary. It focuses on simplicity, stability, and ease of deployment.

## Key Features

- **Single File:** The entire framework is contained in `2boLight.php`. Just drop it in and go.
- **Zero Dependencies:** No Composer required. Runs on standard shared hosting environments.
- **Production Ready:** Built-in logging (daily rotation) and maintenance mode.
- **Secure by Default:** Automatic CSRF protection, security headers, and input sanitization helpers.
- **Developer Friendly:** JSON responses, redirects, and simple routing.
- **Hassle-Free:** Minimal configuration. No complex setup.
- **Database Optional:** Simple PDO wrapper included, but validated only when needed.

## Installation

### Option 1: Direct Download (Recommended)

Simply download `2boLight.php` and include it in your project.

```php
require_once '2boLight.php';
````

### Option 2: via Composer (Optional)

If you prefer using Composer:

```bash
composer require 2bo-velocity/2bo-light-php
```

## Quick Start

Create an `index.php` file:

```php
<?php

require_once '2boLight.php';

$app = new TwoBoLight([
    'debug' => true
]);

// Define routes
$app->get('/', function() {
    echo "<h1>Hello from 2bo Light PHP!</h1>";
});

$app->get('/hello/:name', function($name) use ($app) {
    echo "Hello, " . $app->e($name);
});

// Run the application
$app->run();
```

## Configuration

You can pass an array of configuration options to the constructor:

```php
$app = new TwoBoLight([
    'debug' => true,                // Enable debug mode (show errors)
    'log_dir' => __DIR__ . '/logs', // Directory for daily logs
    'timezone' => 'Asia/Tokyo',     // Default timezone
    'security_headers' => true,     // Send security headers automatically
    'csrf_protection' => true,      // Enable automatic CSRF protection
    'csrf_exempt' => ['/api/*'],    // Paths to exempt from CSRF checks
]);
```

## Core Functionality

### Routing

Supports `GET`, `POST`, `PUT`, `DELETE`, `PATCH`. Capture parameters using `:paramName`.

```php
$app->get('/users/:id', function($id) use ($app) {
    // ...
});

$app->post('/api/data', function() use ($app) {
    // ...
});
```

### Security Helpers

#### Input Sanitization

* `e($string)`: Escape HTML special characters (XSS protection).
* `input($key, $default)`: Safely retrieve request parameters (`$_REQUEST`).

```php
$name = $app->input('name', 'Guest');
echo "Hello " . $app->e($name);
```

#### CSRF Protection

CSRF protection is **enabled by default** for all "unsafe" methods (POST, PUT, DELETE, PATCH).

1. **Include the token in your forms:**

   ```php
   <input type="hidden" name="csrf_token" value="<?php echo $app->csrf_token(); ?>">
   ```

2. **That's it!** The framework automatically validates the token. If validation fails, it returns a 403 error.

**Exemption:** To disable CSRF checks for specific routes (e.g., APIs), use the `csrf_exempt` config:

```php
'csrf_exempt' => ['/api/*', '/webhook'],
```

#### Security Headers

By default, the framework sends:

* `X-Content-Type-Options: nosniff`
* `X-Frame-Options: SAMEORIGIN`
* `X-XSS-Protection: 1; mode=block`

#### Bearer Authentication (API Token)

You can protect your application using Bearer Token authentication by configuring it in the constructor.
Authenticated requests **automatically bypass CSRF checks**, making it perfect for APIs.

```php
$app = new TwoBoLight([
    // ... other config
    'bearer_auth' => [
        'enabled' => true,
        'tokens'  => ['my-secret-token', 'another-token'],
        'exempt'  => ['/public', '/login'], // Paths to exempt from Bearer auth
    ],
]);
```

* **Enabled**: Set to `true` to activate Bearer authentication globally.
* **Tokens**: Array of valid tokens.
* **Exempt**: Array of path patterns (prefixes) to exclude from authentication.
* When active, non-exempt routes **MUST** have a valid `Authorization: Bearer <token>` header.
* If the token is valid, **CSRF checks are skipped** for that request.
* If the token is missing or invalid on a protected route, it returns `401 Unauthorized`.

### Response Helpers

#### JSON Response

Send JSON responses easily with proper headers.

```php
$app->json(['status' => 'ok', 'data' => [1, 2, 3]]);
```

#### Redirect

Redirect to another URL.

```php
$app->redirect('/login');
```

### Error Handling

#### Custom 404 / 500 Pages

Define custom handlers for Not Found (404) and Server Errors (500).

```php
$app->set404(function() {
    echo "<h1>Page Not Found</h1>";
});

$app->set500(function($e) {
    echo "<h1>Oops! Something went wrong.</h1>";
    // $e contains the functionality exception object
});
```

### Logging

Built-in daily log rotation. Logs are stored in `./logs/` by default.

```php
$app->log("Something happened", "INFO");
$app->log("Critical error!", "ERROR");
```

### Maintenance Mode

To enable maintenance mode, simply create a `.maintenance` file in the root directory.

```bash
touch .maintenance
```

The framework will automatically serve a 503 Maintenance page. You can customize this page by creating `maintenance.php`.

### Database (Optional)

Simple PDO wrapper. Only connects if configured and used.

```php
$app->config([
    'db' => [
        'dsn'      => 'mysql:host=localhost;dbname=mydb;charset=utf8mb4',
        'username' => 'user',
        'password' => 'pass'
    ]
]);

$app->get('/users', function() use ($app) {
    $stmt = $app->db()->query("SELECT * FROM users");
    $users = $stmt->fetchAll();
    $app->json($users);
});
```

## Server Configuration (For Pretty URLs / Routing)

To enable **clean URLs** and route all requests through `index.php` (required for parameterized routes), configure your web server as follows:

### Apache (`.htaccess`)

Place this `.htaccess` file in your project root:

```apache
RewriteEngine On

# Skip existing files and directories
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Redirect everything else to index.php
RewriteRule ^ index.php [QSA,L]
```

**Notes:**

* Requests for existing files (images, CSS, JS) will be served normally.
* All other requests are forwarded to `index.php`, where TwoBoLight handles routing.
* Make sure `mod_rewrite` is enabled on your Apache server.

### Nginx (Server Block Example)

Add this to your server block configuration:

```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/project;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000; # Adjust to your PHP-FPM socket or host
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

**Notes:**

* Requests for static files are served directly.
* All other requests are passed to `index.php`.
* Adjust `fastcgi_pass` according to your PHP-FPM setup.

**Optional Notes for Users**

* Shared hosting? `.htaccess` is usually sufficient.
* Nginx requires access to the server configuration.
* Once configured, routes like `/hello/:name` or `/api/status` will work as expected.

---

## License

MIT License. See [LICENSE](LICENSE) for details.

**Author:** [2bo](https://github.com/2bo-velocity)
**Copyright:** (c) 2026 2bo

```