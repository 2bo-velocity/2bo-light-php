<?php

require_once __DIR__ . '/2boLight.php';

$app = new TwoBoLight([
    'debug' => true,
    'security_headers' => true,
    'csrf_protection' => true,
    'csrf_exempt' => ['/api/public*'], // Public API needs explicit CSRF exemption
    'bearer_auth' => [
        'enabled' => true,
        'tokens' => ['secret-token-123'],
        'exempt' => ['/api/public*', '/', '/form', '/hello*', '/missing', '/error', '/api/status'], // Exempt public pages and specific APIs
    ],
]);

// Note: /api/private* is NOT exempt, so it requires Bearer Token.
// Once authenticated, it bypasses CSRF check automatically.

// Custom 404 Handler
$app->set404(function() {
    echo "<h1>Custom 404: Page Not Found</h1>";
});

// Basic Route
$app->get('/', function() use ($app) {
    echo "<h1>Welcome to 2bo Light PHP v1.3</h1>";
    echo "<ul>
        <li><a href='/hello/World'>Hello World (Param)</a></li>
        <li><a href='/api/status'>API Status (JSON)</a></li>
        <li><a href='/form'>CSRF Form Test</a></li>
        <li><a href='/error'>Trigger 500 Error</a></li>
        <li><a href='/missing'>Trigger 404 Error</a></li>
    </ul>";
});

// Route with parameter & sanitization
$app->get('/hello/:name', function($name) use ($app) {
    echo "Hello, " . $app->e($name);
});

// JSON Response Helper
$app->get('/api/status', function() use ($app) {
    $app->json([
        'status' => 'ok', 
        'framework' => '2bo Light PHP', 
        'version' => '1.0.0'
    ]);
});

// CSRF Form Test (GET)
$app->get('/form', function() use ($app) {
    echo "
    <h2>CSRF Protection Test</h2>
    <form method='POST' action='/form'>
        <input type='hidden' name='csrf_token' value='" . $app->csrf_token() . "'>
        <input type='text' name='message' placeholder='Enter message'>
        <button type='submit'>Submit</button>
    </form>
    ";
});

// CSRF Form Test (POST) - Automatic Validation
$app->post('/form', function() use ($app) {
    $message = $app->input('message');
    echo "Form Submitted! Message: " . $app->e($message);
    echo "<br><a href='/'>Back to Home</a>";
});

// API POST Test (Public - Exempt from CSRF)
$app->post('/api/public', function() use ($app) {
    $app->json(['status' => 'public data', 'data' => $_POST]);
});

// API POST Test (Private - Protected by Bearer)
$app->post('/api/private', function() use ($app) {
    $app->json(['status' => 'private data', 'user' => 'authorized']);
});

// Error simulation
$app->get('/error', function() {
    throw new Exception("This is a simulated critical error!");
});

$app->run();
