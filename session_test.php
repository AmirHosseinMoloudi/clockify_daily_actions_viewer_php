<?php
// Test 1: Must be the VERY first line
error_reporting(E_ALL); // Enable error reporting for this test
ini_set('display_errors', 1); // Display errors for this test

session_start();

echo "<!DOCTYPE html><html><head><title>PHP Session Test</title>";
echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">';
echo "</head><body class='container mt-3'>";
echo "<h1>PHP Session Test</h1>";
echo "<p><em>Script execution time: " . date("Y-m-d H:i:s") . "</em></p><hr>";

// Test 2: Check session.save_path
$save_path = session_save_path();
echo "<h2>Session Configuration:</h2>";
echo "<p><strong>session.save_path:</strong> <code>" . htmlspecialchars($save_path) . "</code></p>";

if (empty($save_path)) {
    echo "<p class='alert alert-warning'><strong>Warning:</strong> session.save_path is empty. This might indicate a problem with PHP's session configuration. Defaulting to a system temporary directory might work, or it might fail silently if that directory isn't writable.</p>";
} elseif (is_dir($save_path) && is_writable($save_path)) {
    echo "<p class='alert alert-success'><strong>OK:</strong> Session save path exists and is writable.</p>";
} else {
    if (!is_dir($save_path)) {
        echo "<p class='alert alert-danger'><strong>ERROR: Session save path directory does NOT exist: <code>" . htmlspecialchars($save_path) . "</code></strong></p>";
    } else if (!is_writable($save_path)) {
        echo "<p class='alert alert-danger'><strong>ERROR: Session save path IS NOT WRITABLE: <code>" . htmlspecialchars($save_path) . "</code></strong></p>";
    }
    echo "<p class='alert alert-danger'><strong>This is very likely the cause of your session problems. Contact your hosting provider to fix the permissions or path for <code>session.save_path</code>.</strong></p>";
}
echo "<p><strong>session.use_cookies:</strong> <code>" . ini_get('session.use_cookies') . "</code> (Should be 1 or On)</p>";
echo "<p><strong>session.use_only_cookies:</strong> <code>" . ini_get('session.use_only_cookies') . "</code> (Should be 1 or On)</p>";
echo "<p><strong>session.cookie_httponly:</strong> <code>" . ini_get('session.cookie_httponly') . "</code> (Should be 1 or On)</p>";
echo "<p><strong>Session Name (PHPSESSID usually):</strong> <code>" . session_name() . "</code></p>";
echo "<p><strong>Session ID (if active):</strong> <code>" . (session_id() ? session_id() : 'No active session ID') . "</code></p>";
echo "<hr>";


// Test 3: Set a session variable
echo "<h2>Session Variable Test:</h2>";
if (isset($_POST['my_value'])) {
    $_SESSION['my_value'] = $_POST['my_value'];
    $_SESSION['timestamp'] = time();
    echo "<p class='alert alert-info'>Session variable 'my_value' was just set to: <strong>" . htmlspecialchars($_SESSION['my_value']) . "</strong></p>";
}

// Test 4: Display current session data
echo "<h3>Current Session Data (<code>\$_SESSION</code>):</h3>";
echo "<pre class='alert alert-secondary'>";
print_r($_SESSION);
echo "</pre>";

// Test 5: Check if a session variable persists
if (isset($_SESSION['my_value'])) {
    echo "<p class='alert alert-success'>The value <strong>'" . htmlspecialchars($_SESSION['my_value']) . "'</strong> (set at " . date('H:i:s', $_SESSION['timestamp']) . ") was remembered from the session!</p>";
} else {
    echo "<p class='alert alert-warning'>Session variable 'my_value' is not currently set. Please enter a value below and submit.</p>";
}
?>

<hr>
<form method="POST" action="">
    <div class="form-group">
        <label for="value_input">Enter a value to store in session:</label>
        <input type="text" class="form-control" id="value_input" name="my_value" value="<?php echo isset($_SESSION['my_value']) ? htmlspecialchars($_SESSION['my_value']) : 'TestValue123'; ?>">
    </div>
    <button type="submit" class="btn btn-primary">Set Value & Reload Page</button>
</form>

<p class="mt-3"><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?reset_session=1" class="btn btn-warning btn-sm">Reset Session & Start Test Over</a></p>

<?php
if (isset($_GET['reset_session'])) {
    session_destroy(); // Destroy all session data
    // Unset all of the session variables.
    // $_SESSION = array(); // Alternative to destroy and useful if you want to keep the session ID but clear data
    echo "<p class='alert alert-warning mt-2'><strong>Session has been destroyed.</strong> Please reload the page or submit a new value.</p>";
    // It's good practice to redirect after destroying session to avoid resubmission issues
    // but for this test, we'll just show a message.
    // echo "<script>window.location.href='" . strtok($_SERVER["REQUEST_URI"], '?') . "';</script>";
}

echo "</body></html>";
?>