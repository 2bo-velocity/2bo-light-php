<?php

declare(strict_types=1);

/**
 * 2bo Light PHP Framework
 * Hassle-Free PHP Micro Framework for simple and lightweight applications.
 *
 * @author 2bo <https://github.com/2bo-velocity>
 * @license MIT
 * @version 1.1.0
 */
class TwoBoLight
{
    private array $routes = [];
    private array $batchJobs = [];
    private array $config = [];
    private ?PDO $db = null;
    private $notFoundHandler = null;
    private $errorHandler = null;

    /**
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'log_dir' => __DIR__ . '/logs',
            'debug' => false,
            'db' => [],
            'timezone' => 'UTC',
            'security_headers' => true,
            'csrf_protection' => true,
            'csrf_exempt' => [], // Paths to exempt from CSRF check
            'bearer_auth' => [
                'enabled' => false,
                'tokens' => [],
                'exempt' => [], // Paths to exempt from Bearer auth protection
            ],
            'cors' => [
                'enabled' => false,
                'allowed_origins' => [], // Allowed domains, e.g. ['https://example.com'] or ['*'] for all.
                'exempt' => [], // Paths to exempt from sending CORS headers
            ],
        ], $config);

        date_default_timezone_set($this->config['timezone']);

        if ($this->config['csrf_protection'] && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Handle CORS
     */
    private function handleCors(): void
    {
        $cors = $this->config['cors'] ?? [];
        if (empty($cors['enabled'])) {
            return;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Remove trailing slash if not root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = substr($uri, 0, -1);
        }

        // Check exemptions
        if (!empty($cors['exempt'])) {
            foreach ($cors['exempt'] as $exemptPath) {
                $pattern = "#^" . str_replace('*', '.*', $exemptPath) . "$#";
                if (preg_match($pattern, $uri)) {
                    return;
                }
            }
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = $cors['allowed_origins'] ?? [];
        $isAllowed = false;

        if (in_array('*', $allowedOrigins)) {
            $isAllowed = true;
            if (!empty($origin)) {
                // If specific origin is present, echo it back for better compatibility with credentials
                // providing allowed_origins is just '*'
                header("Access-Control-Allow-Origin: $origin");
            } else {
                 header("Access-Control-Allow-Origin: *");
            }
        } elseif (in_array($origin, $allowedOrigins)) {
            $isAllowed = true;
            header("Access-Control-Allow-Origin: $origin");
        }

        if ($isAllowed) {
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-KEY");
            header("Access-Control-Max-Age: 86400"); // Cache for 1 day
        }

        // Handle Preflight Options
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if ($isAllowed) {
                http_response_code(200);
            } else {
                http_response_code(403);
            }
            exit;
        }
    }

    /**
     * Validate Bearer Token
     */
    private function validateBearer(string $uri): bool
    {
        $bearer = $this->config['bearer_auth'];
        if (!$bearer['enabled']) {
            return true;
        }

        // Check exemptions (public paths)
        foreach ($bearer['exempt'] as $exemptPath) {
             $pattern = "#^" . str_replace('*', '.*', $exemptPath) . "$#";
             if (preg_match($pattern, $uri)) {
                 return true;
             }
        }

        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (stripos($auth, 'Bearer ') !== 0) {
            return false;
        }

        $token = substr($auth, 7);
        return in_array($token, $bearer['tokens'], true);
    }

    /**
     * Define a GET route
     */
    public function get(string $path, callable $callback): void
    {
        $this->addRoute('GET', $path, $callback);
    }

    /**
     * Define a POST route
     */
    public function post(string $path, callable $callback): void
    {
        $this->addRoute('POST', $path, $callback);
    }

    /**
     * Define a PUT route
     */
    public function put(string $path, callable $callback): void
    {
        $this->addRoute('PUT', $path, $callback);
    }

    /**
     * Define a DELETE route
     */
    public function delete(string $path, callable $callback): void
    {
        $this->addRoute('DELETE', $path, $callback);
    }

    /**
     * Define a PATCH route
     */
    public function patch(string $path, callable $callback): void
    {
        $this->addRoute('PATCH', $path, $callback);
    }

    private function addRoute(string $method, string $path, callable $callback): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }

    /**
     * Register a batch job
     * 
     * @param string $name Job name
     * @param callable $callback Job logic
     */
    public function batch(string $name, callable $callback): void
    {
        $this->batchJobs[$name] = $callback;
    }

    /**
     * Set custom 404 Not Found handler
     */
    public function set404(callable $callback): void
    {
        $this->notFoundHandler = $callback;
    }

    /**
     * Set custom 500 Error handler
     */
    public function set500(callable $callback): void
    {
        $this->errorHandler = $callback;
    }

    /**
     * Check for maintenance mode and exit if active
     */
    private function checkMaintenance(): void
    {
        if (file_exists(__DIR__ . '/.maintenance')) {
            http_response_code(503);
            if (file_exists(__DIR__ . '/maintenance.php')) {
                require __DIR__ . '/maintenance.php';
            } else {
                echo "<h1>503 Service Unavailable</h1><p>We are currently undergoing maintenance. Please check back later.</p>";
            }
            exit;
        }
    }

    /**
     * Send default security headers
     */
    private function sendSecurityHeaders(): void
    {
        if ($this->config['security_headers']) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }

    /**
     * Generate or retrieve CSRF token
     */
    public function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token automatically
     * Returns true if valid or exempt, false otherwise
     */
    private function validateCsrf(string $uri, string $method): bool
    {
        if (!$this->config['csrf_protection']) {
            return true;
        }

        // Safe methods don't need CSRF
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        // Check exemptions
        foreach ($this->config['csrf_exempt'] as $exemptPath) {
            // Simple wildcard matching
            $pattern = "#^" . str_replace('*', '.*', $exemptPath) . "$#";
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * HTML Escape Helper
     */
    public function e(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get input data safely
     */
    public function input(string $key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }

    /**
     * Send JSON response and exit
     */
    public function json(array $data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to another URL and exit
     */
    public function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Log a message to the daily log file
     */
    public function log(string $message, string $level = 'INFO'): void
    {
        $logDir = $this->config['log_dir'];
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');
        $logFile = $logDir . '/' . $date . '.log';
        $logEntry = sprintf("[%s] [%s] %s\n", $time, strtoupper($level), $message);

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Get the PDO database connection
     */
    public function db(): ?PDO
    {
        if ($this->db === null && !empty($this->config['db'])) {
            try {
                $dsn = $this->config['db']['dsn'] ?? '';
                $username = $this->config['db']['username'] ?? '';
                $password = $this->config['db']['password'] ?? '';
                $options = $this->config['db']['options'] ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $this->db = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                $this->log("Database Connection Error: " . $e->getMessage(), 'ERROR');
                if ($this->config['debug']) {
                    throw $e;
                }
                return null;
            }
        }
        return $this->db;
    }

    /**
     * Run the application
     */
    /**
     * Run a batch job based on CLI arguments
     */
    public function runBatch(): void
    {
        global $argv;
        $jobName = $argv[1] ?? null;

        if (!$jobName) {
            echo "Available batch jobs:\n";
            foreach (array_keys($this->batchJobs) as $name) {
                echo " - $name\n";
            }
            exit;
        }

        if (!isset($this->batchJobs[$jobName])) {
            echo "Batch job '$jobName' not found.\n";
            exit(1);
        }

        try {
            call_user_func($this->batchJobs[$jobName]);
        } catch (Throwable $e) {
            echo "Error in batch job '$jobName': " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        try {
            // Check for CLI execution
            if (php_sapi_name() === 'cli') {
                $this->runBatch();
                return;
            }

            $this->checkMaintenance();
            $this->sendSecurityHeaders();
            $this->handleCors();

            $method = $_SERVER['REQUEST_METHOD'];
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            
            // Remove trailing slash if not root
            if ($uri !== '/' && substr($uri, -1) === '/') {
                $uri = substr($uri, 0, -1);
            }

            // Bearer Authentication Check
            if (!$this->validateBearer($uri)) {
                http_response_code(401);
                $this->json(['error' => 'Unauthorized'], 401);
                return;
            }

            // Check if request was authenticated via Bearer token to potentially bypass CSRF
            $isBearerAuthenticated = false;
            // Only check if enabled
            if (($this->config['bearer_auth']['enabled'] ?? false)) {
                $headers = getallheaders();
                $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                // Since we passed validateBearer above, if header is present and valid, we are authenticated.
                // Note: validateBearer returns true if Exempt too. We need strict token check for CSRF bypass.
                if (stripos($auth, 'Bearer ') === 0) {
                     $token = substr($auth, 7);
                     if (in_array($token, $this->config['bearer_auth']['tokens'], true)) {
                         $isBearerAuthenticated = true;
                     }
                }
            }

            // Automatic CSRF Check (Skip if Authenticated via Bearer)
            if (!$isBearerAuthenticated && !$this->validateCsrf($uri, $method)) {
                $this->log("CSRF Validation Failed: " . $method . " " . $uri, 'WARNING');
                http_response_code(403);
                if ($this->errorHandler) {
                     call_user_func($this->errorHandler, new Exception("CSRF Validation Failed", 403));
                } else {
                     $this->json(['error' => 'CSRF Validation Failed'], 403);
                }
                return;
            }

            foreach ($this->routes as $route) {
                if ($route['method'] !== $method) {
                    continue;
                }

                // Convert route path to regex
                // /user/:id -> #^/user/([^/]+)$#
                $pattern = preg_replace('#:([\w]+)#', '([^/]+)', $route['path']);
                $pattern = "#^" . $pattern . "$#";

                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches); // Remove clear match
                    call_user_func_array($route['callback'], $matches);
                    return;
                }
            }

            // 404 Not Found
            http_response_code(404);
            $this->log("404 Not Found: " . $method . " " . $uri, 'WARNING');

            if ($this->notFoundHandler) {
                call_user_func($this->notFoundHandler);
            } else {
                echo "<h1>404 Not Found</h1>";
            }

        } catch (Throwable $e) {
            $this->log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), 'ERROR');
            $this->log($e->getTraceAsString(), 'ERROR');

            http_response_code(500);
            
            if ($this->errorHandler) {
                call_user_func($this->errorHandler, $e);
            } else {
                if ($this->config['debug']) {
                    echo "<h1>500 Internal Server Error</h1>";
                    echo "<p>" . $this->e($e->getMessage()) . "</p>";
                    echo "<pre>" . $this->e($e->getTraceAsString()) . "</pre>";
                } else {
                    echo "<h1>500 Internal Server Error</h1><p>Something went wrong.</p>";
                }
            }
        }
    }
}
