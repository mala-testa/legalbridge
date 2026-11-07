<?php

// Log file (outside web root in production)
define('LB_ERROR_LOG', __DIR__ . '/../logs/error.log');

// Ensure log directory exists
if (!is_dir(dirname(LB_ERROR_LOG))) {
    mkdir(dirname(LB_ERROR_LOG), 0775, true);
}

// Generic safe message for users
function lb_safe_error_message() {
    echo "<div class='lb-error' style='margin:20px;'>
            <h3>An unexpected error occurred.</h3>
            <p>Please try again later.</p>
          </div>";
}

// Log details privately
function lb_log_error($message)
{
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;

    // Try to write to LB_ERROR_LOG
    $written = @file_put_contents(LB_ERROR_LOG, $entry, FILE_APPEND);

    if ($written === false) {
        // Fallback: write to PHP system log so the incident is visible
        error_log("LB_LOG_WRITE_FAILED: Could not write to " . LB_ERROR_LOG . " -- Original: " . $message);
    }
}

// Handle normal PHP errors
set_error_handler(function ($severity, $message, $file, $line) {
    lb_log_error("Error: $message in $file on line $line");
    lb_safe_error_message();
    return true; // prevent PHP default handler
});

// Handle uncaught exceptions
set_exception_handler(function ($exception) {
    lb_log_error("Exception: " . $exception->getMessage() .
                 " in " . $exception->getFile() .
                 " on line " . $exception->getLine());
    lb_safe_error_message();
});

// Handle fatal errors (shutdown)
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        lb_log_error("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
        lb_safe_error_message();
    }
});