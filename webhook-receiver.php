<?php
/**
 * Webhook Receiver for Forminator Form Submissions
 *
 * DESCRIPTION:
 * This script receives POST data from Forminator webhooks, processes form submissions,
 * determines the target Facebook page, and stores submissions in JSON for admin review.
 *
 * WORKFLOW:
 * 1. Receives POST data from Forminator webhook
 * 2. Parses form fields (supports both form-data and JSON)
 * 3. Dynamically detects message and email fields
 * 4. Reads ?page parameter to determine target Facebook Page
 * 5. Stores submission with status "pending" in submissions.json
 * 6. Returns JSON response to Forminator
 *
 * FORMINATOR SETUP:
 * In your Forminator form, add a Webhook integration:
 * URL: https://yoursite.com/fb-approval/webhook-receiver.php?page=page1
 * Method: POST
 *
 * URL PARAMETERS:
 * - ?page=page1 - Routes submission to page1 (configured in config.php)
 * - ?page=page2 - Routes submission to page2
 * - No parameter - Uses default page from $FORM_PAGE_MAPPING
 *
 * FIELD DETECTION:
 * The script automatically detects these field names:
 * Message fields: textarea-*, message, text-1, question, content
 * Email fields: *email*
 * Add more patterns in the detection logic below if needed.
 *
 * DATA STORAGE:
 * Submissions are appended to submissions.json with this structure:
 * {
 *   "id": "sub_xxx",
 *   "message": "User's message",
 *   "email": "user@example.com",
 *   "status": "pending",
 *   "target_page_key": "page1",
 *   "form_data": {...all form fields...},
 *   "created_at": "2024-10-31 12:34:56",
 *   "ip_address": "192.168.1.1",
 *   "fb_post_id": null,
 *   "published_at": null,
 *   "error": null
 * }
 *
 * ERROR HANDLING:
 * - Returns HTTP 400 if no data received
 * - Returns HTTP 500 if file write fails
 * - Logs all activity to webhook.log when DEBUG_MODE is enabled
 *
 * SECURITY:
 * - All output is JSON (no HTML rendering)
 * - Data is stored as-is (sanitization happens on display in queue.php)
 * - IP address is logged for tracking
 * - No authentication required (webhook endpoint is public)
 *
 * @version 2.0.0 (Added multi-page support)
 * @author Trou Idees Development Team
 */

require_once 'config.php';

/**
 * Log Message to Webhook Log File
 *
 * Writes timestamped log entries to webhook.log when DEBUG_MODE is enabled.
 * Used for debugging webhook submissions, field detection, and errors.
 *
 * Log file location: webhook.log (same directory as this script)
 * Log format: [YYYY-MM-DD HH:MM:SS] Message text
 *
 * @param string $message The message to log
 * @return void
 */
function logMessage($message) {
    if (DEBUG_MODE) {
        $logFile = __DIR__ . '/webhook.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

// ============================================================================
// STEP 1: RECEIVE AND PARSE INCOMING WEBHOOK DATA
// ============================================================================

logMessage("Webhook received from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

/**
 * Get POST data from Forminator
 *
 * Forminator can send data in two formats:
 * 1. application/x-www-form-urlencoded (standard POST - in $_POST array)
 * 2. application/json (JSON payload - in php://input stream)
 *
 * We try $_POST first, then fall back to parsing JSON from php://input.
 */
$rawData = file_get_contents('php://input');
logMessage("Raw data: " . $rawData);

// Try both POST array and raw JSON
$data = $_POST;
if (empty($data) && !empty($rawData)) {
    $data = json_decode($rawData, true);
}

logMessage("Parsed data: " . print_r($data, true));

/**
 * Validate Data Exists
 *
 * If no data received (neither $_POST nor valid JSON), return error.
 * This prevents empty submissions from being stored.
 */
if (empty($data)) {
    logMessage("ERROR: No data received");
    http_response_code(400); // Bad Request
    die(json_encode(['success' => false, 'error' => 'No data received']));
}

// ============================================================================
// STEP 2: EXTRACT MESSAGE AND EMAIL FIELDS
// ============================================================================

/**
 * Dynamic Field Detection
 *
 * Forminator field names vary based on form configuration.
 * We search for common patterns rather than hardcoding field names.
 *
 * Message field patterns searched (in order):
 * - Contains "textarea" (e.g., textarea-1, textarea-2)
 * - Exactly "message"
 * - Exactly "text-1"
 * - Exactly "question"
 * - Exactly "content"
 *
 * Email field patterns:
 * - Contains "email" anywhere in field name (e.g., email-1, user-email)
 *
 * If your form uses different field names, add them to the patterns below.
 */
$message = '';
$email = '';
$formData = [];

// First pass: Search all fields for message and email using pattern matching
foreach ($data as $key => $value) {
    // Store all form fields for reference
    $formData[$key] = $value;

    // Look for textarea field (primary message field)
    if (strpos($key, 'textarea') !== false || $key === 'message' || $key === 'text-1') {
        $message = $value;
        logMessage("Found message in field: $key");
    }

    // Look for email field (any field containing 'email')
    if (strpos($key, 'email') !== false) {
        $email = $value;
        logMessage("Found email in field: $key");
    }
}

/**
 * Fallback Message Detection
 *
 * If message wasn't found in first pass, try exact field name matches.
 * These are common field names used in Forminator forms.
 *
 * To add more: extend the $possibleFields array.
 */
if (empty($message)) {
    $possibleFields = ['textarea-1', 'text-1', 'message', 'question', 'content'];
    foreach ($possibleFields as $field) {
        if (isset($data[$field])) {
            $message = $data[$field];
            logMessage("Found message in field: $field");
            break;
        }
    }
}

/**
 * Log Warning if Message Not Found
 *
 * If no message detected, log available field names for debugging.
 * The submission will still be stored - admin can see all fields in queue.php.
 */
if (empty($message)) {
    logMessage("WARNING: No message content found in submission");
    logMessage("Available fields: " . implode(', ', array_keys($data)));
}

// ============================================================================
// STEP 3: DETERMINE TARGET FACEBOOK PAGE
// ============================================================================

/**
 * Page Routing Logic
 *
 * Determines which Facebook Page this submission should post to based on:
 * 1. URL parameter: ?page=page1
 * 2. Configuration: $FACEBOOK_PAGES array (from config.php)
 * 3. Fallback: $FORM_PAGE_MAPPING['default']
 *
 * ROUTING RULES:
 * - If ?page parameter exists AND matches a key in $FACEBOOK_PAGES → use that page
 * - If ?page parameter is missing or invalid → use default page
 * - Invalid page keys are logged for debugging
 *
 * EXAMPLES:
 * webhook-receiver.php?page=page1 → target: page1
 * webhook-receiver.php?page=page2 → target: page2
 * webhook-receiver.php           → target: default (page1)
 * webhook-receiver.php?page=xyz  → target: default (page1), logs warning
 */
$pageParam = $_GET['page'] ?? null;
$targetPageKey = $FORM_PAGE_MAPPING['default']; // Start with default

if ($pageParam && isset($FACEBOOK_PAGES[$pageParam])) {
    // Valid page parameter provided - use it
    $targetPageKey = $pageParam;
    logMessage("Target page set to: $targetPageKey (from URL parameter)");
} else {
    // No parameter or invalid parameter - use default
    logMessage("Using default page: $targetPageKey" .
               ($pageParam ? " (invalid parameter: $pageParam)" : " (no parameter provided)"));
}

// ============================================================================
// STEP 4: LOAD EXISTING SUBMISSIONS
// ============================================================================

/**
 * Load submissions.json
 *
 * This file contains all previous submissions.
 * If the file doesn't exist (first submission), we start with an empty array.
 *
 * File format: JSON array of submission objects
 * Location: Defined by DATA_FILE constant in config.php
 */
$submissions = [];
if (file_exists(DATA_FILE)) {
    $json = file_get_contents(DATA_FILE);
    $submissions = json_decode($json, true) ?: [];
}

// ============================================================================
// STEP 5: CREATE SUBMISSION OBJECT
// ============================================================================

/**
 * Build Submission Data Structure
 *
 * Creates a standardized submission object with:
 * - Unique ID (uniqid with prefix 'sub_')
 * - Extracted message and email
 * - Status: Always starts as 'pending'
 * - Target page key for multi-page routing
 * - Complete form data (all fields)
 * - Metadata: timestamp, IP address
 * - Publishing fields: Initially null, filled when published
 *
 * STATUS VALUES:
 * - pending: Awaiting admin review (initial state)
 * - approved: Approved by admin, ready to publish
 * - published: Successfully posted to Facebook
 * - rejected: Rejected by admin, will not be published
 */
$submission = [
    'id' => uniqid('sub_', true),              // Unique identifier
    'message' => $message,                      // Main message content
    'email' => $email,                          // User email (if provided)
    'status' => 'pending',                      // Workflow status
    'target_page_key' => $targetPageKey,        // Which Facebook page to post to
    'form_data' => $formData,                   // All form fields (raw data)
    'created_at' => date('Y-m-d H:i:s'),        // When submitted
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown', // Submitter IP
    'fb_post_id' => null,                       // Facebook post ID (filled after publishing)
    'published_at' => null,                     // When published (filled after publishing)
    'error' => null                             // Error message if publishing fails
];

// ============================================================================
// STEP 6: SAVE TO DATA FILE
// ============================================================================

/**
 * Append submission to array and save
 *
 * Add new submission to the end of the submissions array,
 * then write the entire array back to submissions.json.
 *
 * NOTE: This is a simple append operation. For high-volume systems,
 * consider implementing file locking to prevent race conditions:
 * $fp = fopen(DATA_FILE, 'c+');
 * flock($fp, LOCK_EX);
 * ... read, modify, write ...
 * flock($fp, LOCK_UN);
 * fclose($fp);
 */
$submissions[] = $submission;

/**
 * Write to file
 *
 * JSON_PRETTY_PRINT makes the file human-readable for debugging.
 * Remove this flag in high-volume production for slightly better performance.
 */
$saved = file_put_contents(DATA_FILE, json_encode($submissions, JSON_PRETTY_PRINT));

/**
 * Verify Save Success
 *
 * If file_put_contents returns false, something went wrong:
 * - Directory not writable
 * - Disk full
 * - Permissions issue
 *
 * Return HTTP 500 error to Forminator.
 */
if ($saved === false) {
    logMessage("ERROR: Could not save submission to file");
    http_response_code(500); // Internal Server Error
    die(json_encode(['success' => false, 'error' => 'Could not save submission']));
}

logMessage("SUCCESS: Submission saved with ID: " . $submission['id']);

// ============================================================================
// STEP 7: RETURN SUCCESS RESPONSE
// ============================================================================

/**
 * Send JSON Response to Forminator
 *
 * Forminator expects a JSON response indicating success.
 * We return the submission ID for potential tracking/debugging.
 *
 * HTTP 200 = Success
 * Content-Type: application/json
 *
 * Forminator will show success message to user after receiving this response.
 */
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Submission received',
    'id' => $submission['id']
]);
