<?php
/**
 * Enhanced Approval Interface (WordPress Protected)
 * View and approve/reject form submissions with ALL Forminator fields displayed
 *
 * ACCESS: https://trouidees.co.za/fb-approval/approve-enhanced.php
 * AUTHENTICATION: Requires WordPress administrator login
 */

require_once 'config.php';
require_once 'simple-auth.php';

// Require authentication
requireSimpleAuth();

// Simple logging function when WordPress auth is disabled
function logWordPressActivity($action, $details) {
    // Log to file when WordPress auth is disabled
    if (DEBUG_MODE) {
        $logFile = __DIR__ . '/activity.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $action - $details\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}


// Handle actions (same as original)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $ids = $_POST['ids'] ?? []; // For batch actions

    // Load submissions
    $submissions = [];
    if (file_exists(DATA_FILE)) {
        $json = file_get_contents(DATA_FILE);
        $submissions = json_decode($json, true) ?: [];
    }

    // Handle batch actions
    if ($action && !empty($ids) && is_array($ids)) {
        // Log batch action (optional)
        // logWordPressActivity("Batch $action", count($ids) . " submissions");

        foreach ($submissions as $key => &$sub) {
            if (in_array($sub['id'], $ids)) {
                if ($action === 'batch_approve') {
                    $sub['status'] = 'approved';
                } elseif ($action === 'batch_reject') {
                    $sub['status'] = 'rejected';
                } elseif ($action === 'batch_delete') {
                    unset($submissions[$key]);
                } elseif ($action === 'batch_publish') {
                    // Publish to Facebook
                    $result = publishToFacebook($sub);
                    if ($result['success']) {
                        $sub['status'] = 'published';
                        $sub['fb_post_id'] = $result['post_id'];
                        $sub['published_at'] = date('Y-m-d H:i:s');
                        $sub['error'] = null;
                    } else {
                        $sub['error'] = $result['error'];
                    }
                }
            }
        }

        // Re-index array after deletion
        $submissions = array_values($submissions);

        // Save
        file_put_contents(DATA_FILE, json_encode($submissions, JSON_PRETTY_PRINT));

        // Redirect to avoid resubmission
        header('Location: queue-troues.php?updated=' . count($ids));
        exit;
    }

    // Handle single actions
    if ($action && $id) {
        // Log single action (optional)
        // logWordPressActivity("Single $action", "Submission ID: $id");

        foreach ($submissions as $key => &$sub) {
            if ($sub['id'] === $id) {
                if ($action === 'approve') {
                    $sub['status'] = 'approved';
                } elseif ($action === 'reject') {
                    $sub['status'] = 'rejected';
                } elseif ($action === 'delete') {
                    unset($submissions[$key]);
                } elseif ($action === 'publish') {
                    // Publish to Facebook
                    $result = publishToFacebook($sub);
                    if ($result['success']) {
                        $sub['status'] = 'published';
                        $sub['fb_post_id'] = $result['post_id'];
                        $sub['published_at'] = date('Y-m-d H:i:s');
                        $sub['error'] = null;
                    } else {
                        $sub['error'] = $result['error'];
                    }
                } elseif ($action === 'change_page') {
                    // Change target Facebook page
                    $newPageKey = $_POST['target_page_key'] ?? '';
                    if (isset($FACEBOOK_PAGES[$newPageKey])) {
                        $sub['target_page_key'] = $newPageKey;
                    }
                }
                break;
            }
        }

        // Re-index array after deletion
        $submissions = array_values($submissions);

        // Save
        file_put_contents(DATA_FILE, json_encode($submissions, JSON_PRETTY_PRINT));

        // Redirect to avoid resubmission
        header('Location: queue-troues.php?updated=1');
        exit;
    }
}

// Load submissions
$submissions = [];
if (file_exists(DATA_FILE)) {
    $json = file_get_contents(DATA_FILE);
    $submissions = json_decode($json, true) ?: [];
}

// Sort by newest first
usort($submissions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Filter by page (only show page2 submissions on this page - Troues & Funksies)
$submissions = array_filter($submissions, function($sub) use ($FORM_PAGE_MAPPING) {
    $targetPage = $sub['target_page_key'] ?? $FORM_PAGE_MAPPING['default'];
    return $targetPage === 'page2';
});

// Filter by status
$filter = $_GET['filter'] ?? 'all';
$filteredSubmissions = $submissions;

if ($filter !== 'all') {
    $filteredSubmissions = array_filter($submissions, function($sub) use ($filter) {
        return $sub['status'] === $filter;
    });
}

// Count by status
$counts = [
    'all' => count($submissions),
    'pending' => count(array_filter($submissions, fn($s) => $s['status'] === 'pending')),
    'approved' => count(array_filter($submissions, fn($s) => $s['status'] === 'approved')),
    'published' => count(array_filter($submissions, fn($s) => $s['status'] === 'published')),
    'rejected' => count(array_filter($submissions, fn($s) => $s['status'] === 'rejected')),
];

/* ==========================
   PAGINATION (ONLY ADDITION)
   ========================== */
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalResults = count($filteredSubmissions);
$totalPages = max(1, (int)ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Slice for current page
$pagedSubmissions = array_slice($filteredSubmissions, $offset, $perPage);

// Helper to build page URLs preserving existing query params (e.g., ?filter=...)
function build_page_query(int $pageNum): string {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

/**
 * Publish Submission to Facebook Page
 *
 * Posts an approved submission to the configured Facebook Page using Graph API.
 * Supports multi-page routing - posts to the page specified in submission data.
 *
 * WORKFLOW:
 * 1. Extract message from submission
 * 2. Determine target Facebook page (from submission or default)
 * 3. Validate page configuration exists
 * 4. Format message with page-specific prefix/suffix
 * 5. POST to Facebook Graph API /feed endpoint
 * 6. Parse response and return success/error
 *
 * MESSAGE FORMATTING:
 * Final message structure:
 *   [message_prefix]
 *
 *   [user's message]
 *
 *   [message_suffix]
 *
 * Example with prefix "Community Question:" and suffix "#TrouIdees":
 *   Community Question:
 *
 *   What time does the shop open?
 *
 *   #TrouIdees
 *
 * FACEBOOK API:
 * Endpoint: POST /v21.0/{page-id}/feed
 * Required params:
 *   - message: Text content to post
 *   - access_token: Page access token
 *
 * RETURN VALUE:
 * On success: ['success' => true, 'post_id' => '123456789_987654321']
 * On error: ['success' => false, 'error' => 'Error message from Facebook']
 *
 * COMMON ERRORS:
 * - Code 190: Invalid/expired access token → Regenerate token
 * - Code 200: Permission denied → Check token has pages_manage_posts permission
 * - Code 368: Temporarily blocked → Rate limit hit, wait 30 minutes
 * - Code 100: Invalid parameter → Check message content/length
 *
 * MULTI-PAGE SUPPORT:
 * Each submission has a 'target_page_key' field (e.g., 'page1', 'page2')
 * This function looks up the page configuration from $FACEBOOK_PAGES array
 * and uses that page's credentials and formatting settings.
 *
 * @param array $submission The submission array with message, target_page_key, etc.
 * @return array Result array with 'success' boolean and either 'post_id' or 'error'
 *
 * @version 2.0.0 (Added multi-page support)
 */
function publishToFacebook($submission) {
    // Access global configuration arrays
    global $FACEBOOK_PAGES, $FORM_PAGE_MAPPING;

    // Extract message content
    $message = $submission['message'];

    // ========================================================================
    // STEP 1: DETERMINE TARGET FACEBOOK PAGE
    // ========================================================================

    /**
     * Get target page key from submission data.
     * Falls back to default if not set (for legacy submissions).
     *
     * target_page_key is set by webhook-receiver.php based on URL parameter
     * or can be changed by admin in queue.php interface.
     */
    $targetPageKey = $submission['target_page_key'] ?? $FORM_PAGE_MAPPING['default'];

    // ========================================================================
    // STEP 2: VALIDATE PAGE CONFIGURATION EXISTS
    // ========================================================================

    /**
     * Verify the page key exists in $FACEBOOK_PAGES configuration.
     * If not found, return error immediately (prevents posting with invalid credentials).
     */
    if (!isset($FACEBOOK_PAGES[$targetPageKey])) {
        return [
            'success' => false,
            'error' => 'Invalid target page: ' . $targetPageKey
        ];
    }

    // Load page-specific configuration
    $pageConfig = $FACEBOOK_PAGES[$targetPageKey];

    // ========================================================================
    // STEP 3: FORMAT MESSAGE WITH PREFIX/SUFFIX
    // ========================================================================

    /**
     * Apply page-specific message formatting.
     *
     * NOTE: We only post the main message content, not additional form fields.
     * All form fields are stored in submissions.json but only 'message' goes to Facebook.
     *
     * Formatting:
     * - Prefix is added before message (optional)
     * - Suffix is added after message (usually hashtags)
     * - Both separated by double line breaks for clean formatting
     */

    // Add prefix if configured
    if (!empty($pageConfig['message_prefix'])) {
        $message = $pageConfig['message_prefix'] . "\n\n" . $message;
    }

    // Add suffix if configured (usually hashtags)
    if (!empty($pageConfig['message_suffix'])) {
        $message = $message . "\n\n" . $pageConfig['message_suffix'];
    }

    // ========================================================================
    // STEP 4: MAKE FACEBOOK GRAPH API CALL
    // ========================================================================

    /**
     * Facebook Graph API Request
     *
     * Endpoint: POST /v21.0/{page-id}/feed
     * Docs: https://developers.facebook.com/docs/graph-api/reference/v21.0/page/feed
     *
     * This creates a new post on the Facebook Page's timeline.
     */

    // Build API URL with page-specific page ID
    $url = 'https://graph.facebook.com/v21.0/' . $pageConfig['page_id'] . '/feed';

    // Prepare POST parameters
    $params = [
        'message' => $message,                          // The text content
        'access_token' => $pageConfig['access_token']   // Page-specific access token
    ];

    // Initialize CURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);                              // POST request
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));   // Add parameters
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                    // Return response as string
    // Note: SSL verification is enabled by default (secure)

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ========================================================================
    // STEP 5: PARSE RESPONSE AND RETURN RESULT
    // ========================================================================

    /**
     * Parse Facebook's JSON response
     *
     * Success response (HTTP 200):
     * {
     *   "id": "111290850258093_987654321"
     * }
     *
     * Error response (HTTP 400/500):
     * {
     *   "error": {
     *     "message": "Error message",
     *     "type": "OAuthException",
     *     "code": 190
     *   }
     * }
     */
    $data = json_decode($response, true);

    // Check for success
    if ($httpCode == 200 && isset($data['id'])) {
        // Success: Return post ID
        return [
            'success' => true,
            'post_id' => $data['id']  // Format: {page-id}_{post-id}
        ];
    } else {
        // Error: Extract error message
        $error = $data['error']['message'] ?? 'Unknown error';
        return [
            'success' => false,
            'error' => $error
        ];
    }
}

/**
 * Format field names for display
 */
function formatFieldName($fieldName) {
    // Remove common prefixes
    $fieldName = str_replace(['text-', 'email-', 'name-', 'phone-', 'select-', 'radio-', 'checkbox-'], '', $fieldName);
    // Convert to title case
    $fieldName = ucwords(str_replace(['-', '_'], ' ', $fieldName));
    return $fieldName;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trou Idees - Vra Jou Vraag Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

    <!-- UX Enhancement Assets -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/utilities.css">
    <link rel="stylesheet" href="assets/css/main.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #fef9f9;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
      :root{
  --bar-bg: #fff;
  --bar-border: #f0e6ea;
  --bar-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* Header spacing like the screenshot */
.site-header {
  background: transparent;
  padding-top: 24px;
}

/* Center the logo perfectly */
.logo-container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 12px 16px 20px;  /* space above the bar */
  text-align: center;       /* fallback if flex gets overridden */
}

.logo-link { 
  display: inline-flex; 
  align-items: center;
}

.logo-img {
  display: block;  /* removes inline gap */
  height: 72px;    /* adjust to your artwork to match screenshot scale */
  width: auto;
}

/* The white bar with thin border + subtle shadow under the logo */
.nav-bar {
  background: #fff;
  border: 1px solid #f0e6ea;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  min-height: 56px;
}

/* Optional: constrain the bar width on very large screens */
@media (min-width: 1280px) {
  .nav-bar {
    max-width: 1200px;
    margin: 0 auto;
  }
}


/* Optional sticky behavior (uncomment to stick the bar to the top) */
/*
.nav-bar {
  position: sticky;
  top: 0;
  z-index: 50;
}
*/



        /* Menu bar style filters - matching Trou Idees website */
        .filters {
            display: flex;
            gap: 0;
            margin-bottom: 30px;
            flex-wrap: wrap;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            justify-content: center;
        }
        .filter-btn {
            padding: 15px 30px;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        .filter-btn:hover {
            color: #d4849c;
            background: #fef9f9;
        }
        .filter-btn.active {
            background: white;
            color: #d4849c;
            border-bottom: 3px solid #d4849c;
        }
        .badge {
            background: #f0e6ea;
            color: #d4849c;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
            font-weight: 600;
        }
        .filter-btn.active .badge {
            background: #d4849c;
            color: white;
        }

        /* Batch selection controls */
        .batch-controls {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: none;
        }
        .batch-controls.active {
            display: block;
        }
        .batch-info {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
        .batch-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .select-all-container {
            background: white;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .select-all-container label {
            cursor: pointer;
            user-select: none;
            font-size: 14px;
            color: #666;
        }
        .submission {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-left: 4px solid #f0e6ea;
            transition: all 0.3s;
        }
        .submission:hover {
            box-shadow: 0 4px 12px rgba(212, 132, 156, 0.15);
            border-left-color: #d4849c;
        }
        .submission.selected {
            background: #fef9f9;
            border-left-color: #d4849c;
        }
        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .submission-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #d4849c;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-approved { background: #e8f5e9; color: #2e7d32; }
        .status-published { background: #e1f5fe; color: #0277bd; }
        .status-rejected { background: #ffebee; color: #c62828; }
        .message-container {
            background: #fafafa;
            padding: 12px 20px 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f0e6ea;
            position: relative;
            min-height: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .message {
            white-space: pre-line;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.55;
            font-size: 14px;
            color: #2c3e50;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            letter-spacing: 0.01em;
        }
        .message.collapsed {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            max-height: 65px; /* 3 lines × ~21.7px (14px × 1.55 line-height) */
        }
        .message.collapsed::after {
            content: '...';
            position: absolute;
            bottom: 0;
            right: 0;
            padding-left: 10px;
            background: linear-gradient(to right, transparent, #fafafa 10px);
        }
        .show-more-btn {
            display: inline-block;
            background: white;
            color: #d4849c;
            border: 1px solid #d4849c;
            padding: 5px 14px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
            transition: all 0.2s ease;
            position: relative;
            align-self: flex-start;
        }
        .show-more-btn:hover {
            background: #d4849c;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(212, 132, 156, 0.2);
        }
        .show-more-btn.show-less {
            border-color: #9e9e9e;
            color: #666;
        }
        .show-more-btn.show-less:hover {
            background: #9e9e9e;
            color: white;
            border-color: #757575;
        }
        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
            padding: 15px;
            background: #fef9f9;
            border-radius: 6px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .meta-item strong {
            color: #d4849c;
        }


        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        button, .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }
        .btn-approve {
            background: #66bb6a;
            color: white;
        }
        .btn-approve:hover {
            background: #4caf50;
        }
        .btn-reject {
            background: #ef5350;
            color: white;
        }
        .btn-reject:hover {
            background: #e53935;
        }
        .btn-publish {
            background: #d4849c;
            color: white;
        }
        .btn-publish:hover {
            background: #c26d88;
        }
        .btn-delete {
            background: #9e9e9e;
            color: white;
        }
        .btn-delete:hover {
            background: #757575;
        }
        .btn-batch {
            padding: 10px 20px;
            font-weight: 600;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 13px;
            border-left: 4px solid #c62828;
        }
        .fb-link {
            display: inline-block;
            margin-top: 10px;
            color: #d4849c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .fb-link:hover {
            text-decoration: underline;
            color: #c26d88;
        }
        .empty-state {
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            text-align: center;
            color: #888;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .empty-state h2 {
            color: #d4849c;
            margin-bottom: 15px;
        }
        .success-notice {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #66bb6a;
        }
        /* Page Navigation Menu */
        .page-nav {
            display: flex;
            justify-content: center;
            gap: 0;
            margin-bottom: 30px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .page-nav-btn {
            padding: 15px 40px;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            font-weight: 400;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .page-nav-btn:hover {
            color: #d4849c;
            background: #fef9f9;
        }
        .page-nav-btn.active {
            background: white;
            color: #d4849c;
            border-bottom: 3px solid #d4849c;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="site-header">
                <div class="logo-container">
                    <a href="https://trouidees.co.za/wp-admin" class="logo-link" aria-label="Open WordPress Admin">
                    <img
                        src="cropped-logo.png"
                        srcset="cropped-logo.png 1x"
                        alt="Trouidees"
                        class="logo-img"
                        width="180"
                        height="48"
                        decoding="async"
                        loading="lazy"
                     />
                    </a>
                    <?php if (USE_PASSWORD): ?>
                        <a href="?logout" class="logout-btn" aria-label="Logout from dashboard">
                            Logout
                        </a>
                    <?php endif; ?>
                </div>
        </header>

        <!-- Page Navigation -->
        <div class="page-nav">
            <a href="queue.php" class="page-nav-btn">
                Verhoudings & Leefstyl
            </a>
            <a href="queue-troues.php" class="page-nav-btn active">
                Troues & Funksies
            </a>
        </div>

        <nav class="filters" role="navigation" aria-label="Status filters">
            <a href="?filter=all"
               class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>"
               aria-label="Show all submissions"
               aria-current="<?= $filter === 'all' ? 'page' : 'false' ?>">
                All <span class="badge" aria-label="<?= $counts['all'] ?> items"><?= $counts['all'] ?></span>
            </a>
            <a href="?filter=pending"
               class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>"
               aria-label="Show pending submissions"
               aria-current="<?= $filter === 'pending' ? 'page' : 'false' ?>">
                Pending <span class="badge" aria-label="<?= $counts['pending'] ?> items"><?= $counts['pending'] ?></span>
            </a>
            <a href="?filter=approved"
               class="filter-btn <?= $filter === 'approved' ? 'active' : '' ?>"
               aria-label="Show approved submissions"
               aria-current="<?= $filter === 'approved' ? 'page' : 'false' ?>">
                Approved <span class="badge" aria-label="<?= $counts['approved'] ?> items"><?= $counts['approved'] ?></span>
            </a>
            <a href="?filter=published"
               class="filter-btn <?= $filter === 'published' ? 'active' : '' ?>"
               aria-label="Show published submissions"
               aria-current="<?= $filter === 'published' ? 'page' : 'false' ?>">
                Published <span class="badge" aria-label="<?= $counts['published'] ?> items"><?= $counts['published'] ?></span>
            </a>
            <a href="?filter=rejected"
               class="filter-btn <?= $filter === 'rejected' ? 'active' : '' ?>"
               aria-label="Show rejected submissions"
               aria-current="<?= $filter === 'rejected' ? 'page' : 'false' ?>">
                Rejected <span class="badge" aria-label="<?= $counts['rejected'] ?> items"><?= $counts['rejected'] ?></span>
            </a>
        </nav>

        <?php if (empty($filteredSubmissions)): ?>
            <div class="empty-state">
                <h2>No submissions yet</h2>
                <p>Form submissions will appear here once your Forminator webhook is configured.</p>
            </div>
        <?php else: ?>
            <!-- Select All -->
            <div class="select-all-container">
                <input type="checkbox" id="select-all" class="submission-checkbox">
                <label for="select-all">Select All</label>
            </div>

            <!-- Batch Controls -->
            <div class="batch-controls" id="batch-controls">
                <div class="batch-info">
                    <strong><span id="selected-count">0</span> submission(s) selected</strong>
                </div>
                <form method="post" id="batch-form">
                    <input type="hidden" name="action" id="batch-action" value="">
                    <div id="batch-ids-container"></div>
                    <div class="batch-actions">
                        <button type="button" onclick="submitBatchAction('batch_approve')" class="btn-approve btn-batch">✓ Approve Selected</button>
                        <button type="button" onclick="submitBatchAction('batch_reject')" class="btn-reject btn-batch">✗ Reject Selected</button>
                        <button type="button" onclick="submitBatchAction('batch_publish')" class="btn-publish btn-batch">📤 Publish Selected</button>
                        <button type="button" onclick="if(confirm('Delete selected submissions permanently?')) submitBatchAction('batch_delete')" class="btn-delete btn-batch">🗑 Delete Selected</button>
                    </div>
                </form>
            </div>

            <?php foreach ($pagedSubmissions as $sub): ?>
                <div class="submission" data-id="<?= htmlspecialchars($sub['id']) ?>">
                    <div class="submission-header">
                        <div class="header-left">
                            <input type="checkbox" class="submission-checkbox item-checkbox" data-id="<?= htmlspecialchars($sub['id']) ?>">
                            <span class="status status-<?= $sub['status'] ?>">
                                <?= htmlspecialchars($sub['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="meta">
                        <span class="meta-item">
                            <strong>Date:</strong> <?= htmlspecialchars($sub['created_at']) ?>
                        </span>
                        <?php if (!empty($sub['email'])): ?>
                            <span class="meta-item">
                                <strong>Email:</strong> <?= htmlspecialchars($sub['email']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="meta-item">
                            <strong>ID:</strong> <?= htmlspecialchars($sub['id']) ?>
                        </span>
                        <?php if (!empty($sub['ip_address'])): ?>
                            <span class="meta-item">
                                <strong>IP:</strong> <?= htmlspecialchars($sub['ip_address']) ?>
                            </span>
                        <?php endif; ?>
                        <?php
                            $targetPage = $sub['target_page_key'] ?? $FORM_PAGE_MAPPING['default'];
                            $pageName = $FACEBOOK_PAGES[$targetPage]['name'] ?? 'Unknown';
                        ?>
                        <span class="meta-item">
                            <strong>Target Page:</strong> <?= htmlspecialchars($pageName) ?>
                        </span>

                        <?php
                        // Extract wedding-specific fields from form_data
                        $formData = $sub['form_data'] ?? [];

                        // Area (name-1 field - combines all name parts)
                        $area = '';
                        if (!empty($formData['name-1'])) {
                            if (is_array($formData['name-1'])) {
                                $nameParts = array_filter([
                                    $formData['name-1']['prefix'] ?? '',
                                    $formData['name-1']['first-name'] ?? '',
                                    $formData['name-1']['middle-name'] ?? '',
                                    $formData['name-1']['last-name'] ?? ''
                                ]);
                                $area = implode(' ', $nameParts);
                            } else {
                                $area = $formData['name-1'];
                            }
                        }

                        // Budget (name-2 field - combines all name parts)
                        $budget = '';
                        if (!empty($formData['name-2'])) {
                            if (is_array($formData['name-2'])) {
                                $nameParts = array_filter([
                                    $formData['name-2']['prefix'] ?? '',
                                    $formData['name-2']['first-name'] ?? '',
                                    $formData['name-2']['middle-name'] ?? '',
                                    $formData['name-2']['last-name'] ?? ''
                                ]);
                                $budget = implode(' ', $nameParts);
                            } else {
                                $budget = $formData['name-2'];
                            }
                        }

                        // Wedding Date (phone-1 field)
                        $weddingDate = $formData['phone-1'] ?? '';
                        ?>

                        <?php if (!empty($area)): ?>
                            <span class="meta-item">
                                <strong>Area:</strong> <?= htmlspecialchars($area) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($budget)): ?>
                            <span class="meta-item">
                                <strong>Begroting:</strong> <?= htmlspecialchars($budget) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($weddingDate)): ?>
                            <span class="meta-item">
                                <strong>Datum van troue:</strong> <?= htmlspecialchars($weddingDate) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="message-container">
                        <div class="message" id="message-<?= htmlspecialchars($sub['id']) ?>">
                            <?= nl2br(htmlspecialchars($sub['message'])) ?>
                        </div>
                        <button class="show-more-btn" id="btn-<?= htmlspecialchars($sub['id']) ?>"
                                onclick="toggleMessage('<?= htmlspecialchars($sub['id']) ?>')"
                                style="display: none;">
                            Show more ↓
                        </button>
                    </div>

                    <?php if ($sub['status'] !== 'published'): ?>
                        <!-- Page Selector (only shown before publishing) -->
                        <div class="page-selector">
                            <form method="post">
                                <input type="hidden" name="action" value="change_page">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                <label for="page-select-<?= htmlspecialchars($sub['id']) ?>" class="page-selector-label">
                                    📄 Post to:
                                </label>
                                <select name="target_page_key"
                                        id="page-select-<?= htmlspecialchars($sub['id']) ?>"
                                        aria-label="Select target Facebook page">
                                    <?php
                                        $currentTarget = $sub['target_page_key'] ?? $FORM_PAGE_MAPPING['default'];
                                        foreach ($FACEBOOK_PAGES as $key => $page):
                                    ?>
                                        <option value="<?= htmlspecialchars($key) ?>"
                                                <?= $key === $currentTarget ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($page['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit"
                                        class="page-selector-btn"
                                        aria-label="Update target Facebook page">
                                    Update Page
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($sub['status'] === 'published' && !empty($sub['fb_post_id'])): ?>
                        <a href="https://facebook.com/<?= htmlspecialchars($sub['fb_post_id']) ?>"
                           target="_blank"
                           class="fb-link">
                            ✓ View on Facebook →
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($sub['error'])): ?>
                        <div class="error">
                            ❌ Error: <?= htmlspecialchars($sub['error']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="actions">
                        <?php if ($sub['status'] === 'pending'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                <button type="submit"
                                        class="btn-approve"
                                        aria-label="Approve submission from <?= htmlspecialchars($sub['email']) ?>"
                                        data-loading-text="Approving...">
                                    ✓ Approve
                                </button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                <button type="submit"
                                        class="btn-reject"
                                        aria-label="Reject submission from <?= htmlspecialchars($sub['email']) ?>"
                                        data-loading-text="Rejecting...">
                                    ✗ Reject
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($sub['status'] === 'approved'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="publish">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                <button type="submit"
                                        class="btn-publish"
                                        aria-label="Publish submission to Facebook"
                                        data-loading-text="Publishing...">
                                    📤 Publish to Facebook
                                </button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                <button type="submit"
                                        class="btn-reject"
                                        aria-label="Reject submission from <?= htmlspecialchars($sub['email']) ?>"
                                        data-loading-text="Rejecting...">
                                    ✗ Reject
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                            <button type="submit"
                                    class="btn-delete"
                                    aria-label="Delete submission permanently"
                                    data-loading-text="Deleting..."
                                    onclick="return confirm('Delete this submission permanently?')">
                                🗑 Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            

            <!-- PAGINATION (ONLY ADDITION) -->
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Pagination" style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a
                          href="<?= htmlspecialchars(build_page_query($i)) ?>"
                          class="page-link<?= $i === $page ? ' active' : '' ?>"
                          style="display:inline-block;padding:6px 10px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#555;background:#fff;<?= $i === $page ? 'background:#d4849c;color:#fff;border-color:#d4849c;' : '' ?>">
                          <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- UX Enhancement Scripts -->
    <script src="assets/js/toast.js"></script>
    <script src="assets/js/modal.js"></script>
    <script src="assets/js/loading.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
