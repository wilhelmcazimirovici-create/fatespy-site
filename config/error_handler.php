<?php
/**
 * FateSpy — Global Error Handler
 * Include this at top of every PHP file that serves web requests.
 * Prevents stack traces, source code paths, and debug info from leaking.
 */

// ── Custom error handler ──────────────────────────────────
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno))
        return false;

    $level_map = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
    ];
    $level = $level_map[$errno] ?? 'UNKNOWN';

    // Log to file (never to browser)
    $log_dir = __DIR__ . '/../logs/';
    if (!is_dir($log_dir))
        @mkdir($log_dir, 0750, true);
    @file_put_contents(
        $log_dir . 'php_errors_' . date('Y-m') . '.log',
        date('Y-m-d H:i:s') . " [{$level}] {$errstr} in {$errfile}:{$errline}\n",
        FILE_APPEND
    );

    return true; // suppress default PHP error output
});

// ── Exception handler ─────────────────────────────────────
set_exception_handler(function (\Throwable $e): void {
    $log_dir = __DIR__ . '/../logs/';
    if (!is_dir($log_dir))
        @mkdir($log_dir, 0750, true);
    @file_put_contents(
        $log_dir . 'php_errors_' . date('Y-m') . '.log',
        date('Y-m-d H:i:s') . " [EXCEPTION] " . get_class($e) . ': ' . $e->getMessage() .
        " in " . $e->getFile() . ':' . $e->getLine() . "\n" .
        $e->getTraceAsString() . "\n",
        FILE_APPEND
    );

    // Return generic error — never expose internals
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => 'An unexpected error occurred. Please try again.',
    ]);
    exit;
});

// ── Fatal error shutdown handler ─────────────────────────
register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log_dir = __DIR__ . '/../logs/';
        @mkdir($log_dir, 0750, true);
        @file_put_contents(
            $log_dir . 'php_errors_' . date('Y-m') . '.log',
            date('Y-m-d H:i:s') . " [FATAL] {$error['message']} in {$error['file']}:{$error['line']}\n",
            FILE_APPEND
        );
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
        }
    }
});

// ── Ensure display_errors is off ─────────────────────────
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL); // log everything, display nothing
