<?php
/**
 * Medical Records API Endpoint
 * This file redirects to medical_records_handler.php for backward compatibility
 */

// Suppress errors and warnings to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any existing output buffer
while (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/medical_records_handler.php';

