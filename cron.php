<?php

require_once '2boLight.php';

$app = new TwoBoLight([
    'debug' => true
]);

// Register batch jobs
$app->batch('sendEmails', function() use ($app) {
    echo "Sending emails...\n";
    // Simulate some work
    sleep(1);
    $app->log("Emails sent successfully.");
});

$app->batch('cleanupLogs', function() use ($app) {
    echo "Cleaning up logs...\n";
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir)) {
        $files = glob($logDir . '/*.log');
        foreach ($files as $file) {
            // Keep today's log
            if (basename($file) !== date('Y-m-d') . '.log') {
                echo "Deleting " . basename($file) . "\n";
                // unlink($file); // Commented out for safety in this sample
            }
        }
    }
    $app->log("Logs cleaned up.");
});

// Run application (automatically handles CLI batch mode)
$app->run();
