<?php
/**
 * Simple Password Authentication System
 *
 * DESCRIPTION:
 * Provides lightweight session-based password authentication for the admin interface.
 * This is an alternative to WordPress authentication for standalone deployments.
 *
 * FEATURES:
 * - Single password for all admins
 * - Session-based (persists across page loads)
 * - Clean login/logout flow
 * - Styled login form matching Trou Idees branding
 * - Can be toggled on/off via USE_PASSWORD constant
 *
 * USAGE:
 * In queue.php, uncomment these lines to enable:
 *   require_once 'simple-auth.php';
 *   requireSimpleAuth();
 *
 * CONFIGURATION:
 * Set in config.php:
 *   define('USE_PASSWORD', true);          // Enable password protection
 *   define('ADMIN_PASSWORD', 'YourPass');  // Set password
 *
 * SECURITY CONSIDERATIONS:
 * - Password is stored in plain text in config.php (protect that file!)
 * - Single shared password (no user management)
 * - Session-based (vulnerable to session hijacking if not using HTTPS)
 * - No rate limiting (consider adding after X failed attempts)
 * - No password strength requirements
 * - Consider using WordPress auth or proper authentication system for production
 *
 * LOGOUT:
 * Add ?logout to any URL to log out: queue.php?logout
 *
 * @version 1.0.0
 * @author Trou Idees Development Team
 */

// ============================================================================
// SESSION INITIALIZATION
// ============================================================================

/**
 * Start PHP Session with Security Settings
 *
 * Sessions store the authentication state across page loads.
 * Only start if not already started (prevents "session already started" errors).
 *
 * Session data stored:
 * - $_SESSION['simple_auth'] = true (when logged in)
 * - $_SESSION['login_time'] = timestamp (when logged in)
 * - $_SESSION['last_activity'] = timestamp (last activity)
 * - $_SESSION['failed_attempts'] = int (failed login attempts)
 */
if (!session_id()) {
    // Enhanced session security settings
    ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
    ini_set('session.use_only_cookies', 1); // Only use cookies, not URL parameters
    ini_set('session.cookie_samesite', 'Strict'); // CSRF protection

    // If using HTTPS, enable secure cookie
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();

    // Session timeout: 8 hours of inactivity
    $timeout = 8 * 60 * 60; // 8 hours in seconds
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

/**
 * Check if User is Authenticated
 *
 * Checks if the current user has a valid authentication session.
 *
 * LOGIC:
 * 1. If USE_PASSWORD is false → always return true (no auth required)
 * 2. If $_SESSION['simple_auth'] exists and is true → return true (logged in)
 * 3. Otherwise → return false (not logged in)
 *
 * @return bool True if authenticated or auth disabled, false if not authenticated
 */
function checkSimpleAuth() {
    // If password protection is disabled, always return true
    if (!USE_PASSWORD) {
        return true;
    }

    // Check if already logged in via session
    if (isset($_SESSION['simple_auth']) && $_SESSION['simple_auth'] === true) {
        return true;
    }

    return false;
}

/**
 * Require Authentication or Show Login Form
 *
 * This is the main authentication gate function.
 * Call this at the top of any protected page.
 *
 * BEHAVIOR:
 * - If USE_PASSWORD is false: Returns immediately (no auth required)
 * - If user is logged in: Returns immediately (allow access)
 * - If user submits correct password: Logs them in and redirects
 * - If user submits wrong password: Shows login form with error
 * - If user is not logged in: Shows login form
 * - If ?logout parameter present: Destroys session and redirects
 *
 * SECURITY NOTES:
 * - Password compared with simple === (no hashing)
 * - Session regeneration not implemented (consider adding)
 * - No CSRF protection on login form (low risk for this use case)
 * - No rate limiting (consider adding after X failed attempts)
 *
 * @return void|bool Returns true if auth disabled, otherwise shows form or redirects
 */
function requireSimpleAuth() {
    // If no password required, just return
    if (!USE_PASSWORD) {
        return true;
    }

    // ========================================================================
    // HANDLE LOGIN FORM SUBMISSION
    // ========================================================================

    /**
     * Process Login Attempt with Rate Limiting
     *
     * When user submits the login form:
     * 1. Check rate limiting (max 5 attempts per 15 minutes)
     * 2. Check if password matches ADMIN_PASSWORD constant
     * 3. If correct: Set session variable, regenerate ID, and redirect
     * 4. If incorrect: Increment failed attempts and show error
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        // Initialize failed attempts tracking
        if (!isset($_SESSION['failed_attempts'])) {
            $_SESSION['failed_attempts'] = 0;
            $_SESSION['first_failed_time'] = time();
        }

        // Rate limiting: Max 5 failed attempts per 15 minutes
        $maxAttempts = 5;
        $lockoutPeriod = 15 * 60; // 15 minutes

        if ($_SESSION['failed_attempts'] >= $maxAttempts) {
            $timeElapsed = time() - $_SESSION['first_failed_time'];
            if ($timeElapsed < $lockoutPeriod) {
                $remainingTime = ceil(($lockoutPeriod - $timeElapsed) / 60);
                $error = "Too many failed attempts. Please try again in {$remainingTime} minute(s).";
            } else {
                // Reset after lockout period
                $_SESSION['failed_attempts'] = 0;
                $_SESSION['first_failed_time'] = time();
            }
        }

        // Only process login if not locked out
        if (!isset($error)) {
            if ($_POST['password'] === ADMIN_PASSWORD) {
                // Success: Set session, regenerate ID for security, and redirect
                $_SESSION['simple_auth'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['failed_attempts'] = 0; // Reset failed attempts

                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Redirect to same page (removes POST data, prevents resubmission)
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                // Failure: Increment failed attempts and show error
                $_SESSION['failed_attempts']++;
                if ($_SESSION['failed_attempts'] == 1) {
                    $_SESSION['first_failed_time'] = time();
                }

                $remainingAttempts = $maxAttempts - $_SESSION['failed_attempts'];
                if ($remainingAttempts > 0) {
                    $error = "Incorrect password. {$remainingAttempts} attempt(s) remaining.";
                } else {
                    $error = "Too many failed attempts. Account locked for 15 minutes.";
                }
            }
        }
    }

    // ========================================================================
    // HANDLE LOGOUT REQUEST
    // ========================================================================

    /**
     * Process Logout
     *
     * If ?logout is in URL:
     * 1. Destroy session (logs user out)
     * 2. Redirect to same page without query string
     *
     * Usage: Add link in admin interface: <a href="?logout">Logout</a>
     */
    if (isset($_GET['logout'])) {
        session_destroy();

        // Redirect to same page without query parameters
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // ========================================================================
    // SHOW LOGIN FORM IF NOT AUTHENTICATED
    // ========================================================================

    /**
     * Display Login Form
     *
     * If user is not authenticated, show login form and exit script.
     * This prevents the protected page content from being rendered.
     *
     * The form posts to itself, which is handled by the code above.
     */
    if (!checkSimpleAuth()) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Required - Trou Idees Approval</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .login-container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 400px;
                    width: 100%;
                    padding: 40px;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .logo h1 {
                    color: #d4849c;
                    font-size: 28px;
                    font-weight: 300;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                }
                .logo p {
                    color: #888;
                    font-size: 14px;
                    margin-top: 5px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                label {
                    display: block;
                    margin-bottom: 8px;
                    color: #333;
                    font-weight: 500;
                    font-size: 14px;
                }
                input[type="password"] {
                    width: 100%;
                    padding: 12px;
                    border: 2px solid #e0e0e0;
                    border-radius: 5px;
                    font-size: 16px;
                    transition: border-color 0.3s;
                }
                input[type="password"]:focus {
                    outline: none;
                    border-color: #d4849c;
                }
                button {
                    width: 100%;
                    padding: 12px;
                    background: #d4849c;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.3s;
                }
                button:hover {
                    background: #c26d88;
                }
                .error {
                    background: #fee;
                    color: #c33;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    text-align: center;
                    font-size: 14px;
                }
                .hint {
                    text-align: center;
                    margin-top: 20px;
                    color: #999;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="logo">
                    <h1>Trou Idees</h1>
                    <p>Approval Dashboard</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password"
                               id="password"
                               name="password"
                               required
                               autofocus
                               placeholder="Enter password">
                    </div>
                    <button type="submit">Login</button>
                </form>

                <div class="hint">
                    Enter the admin password to access the approval dashboard
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// ============================================================================
// USER INFORMATION FUNCTION
// ============================================================================

/**
 * Get Simple User Information
 *
 * Returns a generic admin user array for compatibility with code that expects
 * user information (like the old WordPress authentication system).
 *
 * Since this simple auth system doesn't have individual user accounts,
 * this always returns the same generic "admin" user data.
 *
 * USAGE:
 * $user = getSimpleUser();
 * echo $user['display_name'];  // "Administrator"
 *
 * NOTE: This is a stub function for compatibility.
 * For multi-user systems, replace with proper user management.
 *
 * @return array Generic admin user information
 */
function getSimpleUser() {
    return [
        'id' => 1,                                  // Generic admin ID
        'username' => 'admin',                      // Generic username
        'email' => 'admin@trouidees.co.za',        // Admin email
        'display_name' => 'Administrator',          // Display name
    ];
}