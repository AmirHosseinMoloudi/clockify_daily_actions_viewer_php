<?php
// VERY TOP: Configure error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to the user
ini_set('log_errors', 1);    // Log errors
ini_set('error_log', __DIR__ . '/php_error.log'); // Log to a file in the same directory

ob_start(); // Start output buffering

// Session start
if (session_status() === PHP_SESSION_NONE) {
    if (headers_sent($file, $line)) {
        error_log("CRITICAL ERROR: Headers already sent in {$file} on line {$line} before session_start()! Sessions will FAIL.");
    } else {
        if (!session_start()) {
            error_log("CRITICAL ERROR: session_start() FAILED. Check PHP error logs and session.save_path.");
        }
    }
}

// --- Configuration ---
$apiBaseUrl = 'https://api.clockify.me/api/v1';
$tehranTimezoneIdentifier = 'Asia/Tehran';

// --- Helper Function to call Clockify API ---
function callClockifyAPI($apiKey, $endpoint, $method = 'GET', $queryParams = [], $postData = null) {
    global $apiBaseUrl; 
    $url = $apiBaseUrl . $endpoint;

    if (!empty($queryParams) && $method === 'GET') {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init();
    $headers = [
        'X-Api-Key: ' . $apiKey,
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EnhancedClockifyPHPClient/1.5-daysoffset');

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("cURL Error for $url: " . $curlError);
        return ['error' => true, 'message' => 'A network error occurred while contacting the API. Please try again.', 'response_body' => ''];
    }

    $decodedResponse = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return $decodedResponse;
    } else {
        $errorMessage = "API request failed (HTTP $httpCode).";
        $userMessage = 'An error occurred while fetching data from Clockify.';
        if (isset($decodedResponse['message'])) {
            $errorMessage .= " Clockify: " . $decodedResponse['message'];
            if ($httpCode == 401) $userMessage = 'Invalid API Key or insufficient permissions.';
        }
        if (isset($decodedResponse['code'])) $errorMessage .= " (Code: " . $decodedResponse['code'] . ")";
        
        error_log("Clockify API Error: HTTP $httpCode, URL: $url, Message: " . $errorMessage . ", Response: " . $response);
        return ['error' => true, 'http_code' => $httpCode, 'message' => $userMessage, 'response_body' => ''];
    }
}

// --- Reset functionality ---
if (isset($_GET['reset'])) {
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_unset(); 
    session_destroy(); 
    $_SESSION = array(); 
    
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
    exit; 
}

// --- Main Logic for Step Determination ---
$step = 1; 

if (isset($_POST['form_action']) && $_POST['form_action'] === 'submit_api_key_action' && !empty($_POST['api_key'])) {
    $_SESSION['api_key'] = trim($_POST['api_key']);
    if(isset($_SESSION['workspace_id'])) { unset($_SESSION['workspace_id']); }
    if(isset($_SESSION['date_offset_days'])) { unset($_SESSION['date_offset_days']); } // Changed from date_offset
    $step = 2; 
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
    exit;
} 
elseif (isset($_SESSION['api_key']) && isset($_POST['form_action']) && $_POST['form_action'] === 'submit_workspace_date_action' && !empty($_POST['workspace_id']) && isset($_POST['date_offset_days'])) {
    $_SESSION['workspace_id'] = $_POST['workspace_id'];
    $_SESSION['date_offset_days'] = abs((int)$_POST['date_offset_days']); // Ensure positive integer, 0 for today
    $step = 3; 
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
    exit;
} 
elseif (isset($_SESSION['api_key']) && (!isset($_SESSION['workspace_id']) || !isset($_SESSION['date_offset_days']))) {
    $step = 2; 
} 
elseif (isset($_SESSION['api_key']) && isset($_SESSION['workspace_id']) && isset($_SESSION['date_offset_days'])) {
    $step = 3; 
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Clockify Actions Viewer (Tehran Time)</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; }
        .container-main { margin-top: 20px; margin-bottom: 50px; }
        .card-header { background-color: #007bff; color: white; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .table-responsive { margin-top: 15px; }
        .user-block { margin-bottom: 1.5rem; }
        .user-block .card-header { background-color: #6c757d; }
        .reset-link { font-size: 0.9em; }
        .loading-spinner { width: 1rem; height: 1rem; margin-left: 5px; }
        #main-loader .spinner-border { width: 3rem; height: 3rem; }
        .info-bar p { margin-bottom: 0.5rem; }
        .navbar-brand { font-weight: bold; }
        @media (max-width: 768px) {
            .info-bar { flex-direction: column; align-items: flex-start !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?')); ?>">Clockify Viewer</a>
            <a href="?reset=true" class="btn btn-outline-light btn-sm reset-link">Reset & Start Over</a>
        </div>
    </nav>

    <div class="container container-main">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">

                <?php if ($step === 1): ?>
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Step 1: Enter Your Clockify API Key</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return showFormLoader(this, 'submit_api_key_action');">
                                <input type="hidden" name="form_action" value=""> 
                                <div class="form-group">
                                    <label for="api_key">API Key:</label>
                                    <input type="text" class="form-control" id="api_key" name="api_key" required>
                                </div>
                                <button type="submit" name="submit_api_key_button" class="btn btn-primary"> 
                                    Fetch Workspaces
                                    <span class="spinner-border spinner-border-sm ml-1 d-none loading-spinner" role="status" aria-hidden="true"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                if ($step === 2):
                    if (!isset($_SESSION['api_key'])) {
                        error_log("Attempted to render Step 2 without API key in session.");
                        echo "<div class='alert alert-danger'>Session error. Please <a href='?reset=true' class='alert-link'>start over</a>.</div>";
                    } else {
                        $apiKey = $_SESSION['api_key'];
                        $workspaces = callClockifyAPI($apiKey, '/workspaces');

                        if (isset($workspaces['error']) || !$workspaces) {
                            $errorMessage = isset($workspaces['message']) ? htmlspecialchars($workspaces['message']) : 'Could not fetch workspaces. Please check your API key and network connection.';
                            if (isset($workspaces['http_code']) && $workspaces['http_code'] == 401) {
                                $errorMessage = 'Invalid API Key. Please check and try again.';
                            }
                            echo "<div class='alert alert-danger' role='alert'>{$errorMessage}</div>";
                            echo "<p><a href='?reset=true' class='btn btn-secondary btn-sm'>Use a different API Key</a></p>";
                        } elseif (empty($workspaces)) {
                            echo "<div class='alert alert-warning' role='alert'>No workspaces found for this API key.</div>";
                            echo "<p><a href='?reset=true' class='btn btn-secondary btn-sm'>Use a different API Key</a></p>";
                        } else {
                ?>
                    <div class="card shadow-sm">
                        <div class="card-header">
                             <h5 class="mb-0">Step 2: Select Workspace & Report Date</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return showFormLoader(this, 'submit_workspace_date_action');">
                                <input type="hidden" name="form_action" value=""> 
                                <div class="form-group">
                                    <label for="workspace_id">Choose a workspace:</label>
                                    <select class="form-control" id="workspace_id" name="workspace_id" required>
                                        <?php foreach ($workspaces as $ws): ?>
                                            <option value="<?php echo htmlspecialchars($ws['id']); ?>" <?php echo (isset($_SESSION['workspace_id']) && $_SESSION['workspace_id'] == $ws['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ws['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="date_offset_days">Days to go back (Tehran Time):</label>
                                    <input type="number" class="form-control" id="date_offset_days" name="date_offset_days" 
                                           value="<?php echo isset($_SESSION['date_offset_days']) ? htmlspecialchars($_SESSION['date_offset_days']) : '0'; ?>" 
                                           min="0" required>
                                    <small class="form-text text-muted">Enter 0 for today, 1 for yesterday, 2 for two days ago, etc.</small>
                                </div>
                                <button type="submit" name="submit_workspace_date_button" class="btn btn-primary"> 
                                    Get User Actions
                                    <span class="spinner-border spinner-border-sm ml-1 d-none loading-spinner" role="status" aria-hidden="true"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php
                        } 
                    } 
                endif; 
                ?>

                <?php
                if ($step === 3):
                    if (!isset($_SESSION['api_key']) || !isset($_SESSION['workspace_id']) || !isset($_SESSION['date_offset_days'])) {
                         error_log("Attempted to render Step 3 without complete session data.");
                         echo "<div class='alert alert-danger'>Session error or required data missing. Please <a href='?reset=true' class='alert-link'>start over</a>.</div>";
                    } else {
                        $apiKey = $_SESSION['api_key'];
                        $workspaceId = $_SESSION['workspace_id'];
                        $dateOffsetDays = abs((int)$_SESSION['date_offset_days']); // Ensure positive integer

                        echo '<div id="main-loader" class="text-center my-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2">Preparing your report... Please wait.</p>
                              </div>
                              <div id="report-content" style="display:none;">';

                        if (ob_get_level() > 0) { ob_flush(); }
                        flush(); 

                        $currentWorkspaceDetails = callClockifyAPI($apiKey, "/workspaces/{$workspaceId}");
                        $workspaceName = isset($currentWorkspaceDetails['name']) ? htmlspecialchars($currentWorkspaceDetails['name']) : htmlspecialchars($workspaceId);
                        if(isset($currentWorkspaceDetails['error'])) {
                             echo "<div class='alert alert-warning'>Could not fetch workspace name. Using ID.</div>";
                        }

                        $tehranTz = new DateTimeZone($tehranTimezoneIdentifier);
                        $utcTz = new DateTimeZone('UTC');
                        $targetDateInTehran = new DateTime('now', $tehranTz);
                        if ($dateOffsetDays > 0) {
                            $targetDateInTehran->modify("-{$dateOffsetDays} days");
                        }
                        $tehranDateForDisplay = $targetDateInTehran->format('Y-m-d');
                        
                        $dayNameForDisplay = "";
                        if ($dateOffsetDays === 0) {
                            $dayNameForDisplay = "Today";
                        } elseif ($dateOffsetDays === 1) {
                            $dayNameForDisplay = "Yesterday";
                        } else {
                            $dayNameForDisplay = $dateOffsetDays . " days ago";
                        }

                        $startOfDayTehran = new DateTime($targetDateInTehran->format('Y-m-d') . ' 00:00:00', $tehranTz);
                        $startOfDayUtc = clone $startOfDayTehran; $startOfDayUtc->setTimezone($utcTz);
                        $apiStartTime = $startOfDayUtc->format('Y-m-d\TH:i:s\Z');

                        $endOfDayTehran = new DateTime($targetDateInTehran->format('Y-m-d') . ' 23:59:59', $tehranTz);
                        $endOfDayUtc = clone $endOfDayTehran; $endOfDayUtc->setTimezone($utcTz);
                        $apiEndTime = $endOfDayUtc->format('Y-m-d\TH:i:s\Z');

                        echo "<div class='card shadow-sm mb-4'>
                                <div class='card-header'>
                                    <h5 class='mb-0'>Report for " . htmlspecialchars($tehranDateForDisplay) . " (" . htmlspecialchars($dayNameForDisplay) . ", Tehran Time)</h5>
                                </div>
                                <div class='card-body'>
                                    <div class='d-flex justify-content-between flex-wrap info-bar'>
                                        <p><strong>Workspace:</strong> " . $workspaceName . "</p>
                                        <p><strong>Reporting Date:</strong> " . htmlspecialchars($tehranDateForDisplay) . " (" . htmlspecialchars($dayNameForDisplay) . ", Tehran)</p>
                                    </div>
                                </div>
                              </div>";
                        
                        $users = callClockifyAPI($apiKey, "/workspaces/{$workspaceId}/users", 'GET', ['status' => 'ACTIVE']);

                        if (isset($users['error']) || !$users) {
                            $errorMessage = isset($users['message']) ? htmlspecialchars($users['message']) : 'Could not fetch users for the workspace.';
                            echo "<div class='alert alert-danger' role='alert'>{$errorMessage}</div>";
                        } elseif (empty($users)) {
                            echo "<div class='alert alert-info' role='alert'>No active users found in this workspace.</div>";
                        } else {
                            $anyActionsFoundOverall = false;
                            echo "<h5>Detailed Actions by User:</h5>";

                            $totalUsers = count($users);
                            $usersProcessed = 0;
                            echo '<div class="progress mb-3" style="height: 5px;">
                                    <div id="user-progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                  </div>';

                            foreach ($users as $user) {
                                $usersProcessed++;
                                $progressPercent = ($totalUsers > 0) ? round(($usersProcessed / $totalUsers) * 100) : 0;
                                echo "<script>var el = document.getElementById('user-progress-bar'); if(el) { el.style.width = '{$progressPercent}%'; el.setAttribute('aria-valuenow', '{$progressPercent}'); }</script>";
                                if (ob_get_level() > 0) { ob_flush(); } flush();

                                if (!isset($user['id']) || !isset($user['name'])) {
                                    error_log("User data incomplete in Step 3 loop: " . json_encode($user));
                                    echo "<div class='alert alert-warning'>Skipping a user due to incomplete data.</div>";
                                    continue;
                                }
                                $userId = $user['id'];
                                $userName = htmlspecialchars($user['name']);

                                echo "<div class='card user-block shadow-sm'>";
                                echo "<div class='card-header'><h6 class='mb-0'>User: {$userName} (ID: " . htmlspecialchars($userId) . ")</h6></div>";
                                echo "<div class='card-body'>";
                                
                                $timeEntriesParams = [
                                    'start' => $apiStartTime, 'end' => $apiEndTime,
                                    'hydrated' => 'true', 'consider-duration-format' => 'true'
                                ];
                                $timeEntries = callClockifyAPI($apiKey, "/workspaces/{$workspaceId}/user/{$userId}/time-entries", 'GET', $timeEntriesParams);

                                if (isset($timeEntries['error'])) {
                                    $errorMessage = isset($timeEntries['message']) ? htmlspecialchars($timeEntries['message']) : "Could not fetch time entries for {$userName}.";
                                    echo "<div class='alert alert-danger' role='alert'>{$errorMessage}</div>";
                                } elseif (empty($timeEntries)) {
                                    echo "<p class='text-muted'>No time entries found for {$userName} on " . htmlspecialchars($tehranDateForDisplay) . ".</p>";
                                } else {
                                    $anyActionsFoundOverall = true;
                                    echo "<div class='table-responsive'>";
                                    echo "<table class='table table-striped table-bordered table-hover table-sm'>";
                                    echo "<thead class='thead-light'><tr><th>Desc.</th><th>Project</th><th>Task</th><th>Start (Local)</th><th>End (Local)</th><th>Duration</th></tr></thead><tbody>";
                                    foreach ($timeEntries as $entry) {
                                        $description = !empty($entry['description']) ? htmlspecialchars($entry['description']) : '<em>-</em>';
                                        $projectName = isset($entry['project']['name']) ? htmlspecialchars($entry['project']['name']) : '<em>-</em>';
                                        if (isset($entry['project']['clientName']) && !empty($entry['project']['clientName'])) {
                                            $projectName .= " <small class='text-muted'>(" . htmlspecialchars($entry['project']['clientName']) . ")</small>";
                                        }
                                        $taskName = isset($entry['task']['name']) ? htmlspecialchars($entry['task']['name']) : '<em>-</em>';

                                        $durationStr = '<em>-</em>';
                                        if (isset($entry['timeInterval']['duration'])) {
                                            try {
                                                if ($entry['timeInterval']['duration'] === null && $entry['timeInterval']['end'] === null) {
                                                    $durationStr = '<span class="badge badge-info">Running</span>';
                                                } else if ($entry['timeInterval']['duration'] !== null) {
                                                    $interval = new DateInterval($entry['timeInterval']['duration']);
                                                    $formattedDuration = '';
                                                    if ($interval->h > 0) $formattedDuration .= $interval->h . 'h ';
                                                    if ($interval->i > 0) $formattedDuration .= $interval->i . 'm ';
                                                    if ($interval->s > 0 || empty($formattedDuration)) $formattedDuration .= $interval->s . 's';
                                                    $durationStr = trim($formattedDuration);
                                                    if(empty($durationStr)) $durationStr = "0s";
                                                }
                                            } catch (Exception $e) {
                                                 $durationStr = htmlspecialchars($entry['timeInterval']['duration']);
                                                 error_log("Error parsing duration '{$entry['timeInterval']['duration']}': " . $e->getMessage());
                                            }
                                        }
                                        
                                        $displayTimezone = new DateTimeZone(date_default_timezone_get());
                                        $startTime = '<em>-</em>';
                                        if (isset($entry['timeInterval']['start'])) {
                                            $dtStart = new DateTime($entry['timeInterval']['start'], $utcTz);
                                            $dtStart->setTimezone($displayTimezone);
                                            $startTime = $dtStart->format('H:i:s') . ' <small class="text-muted">(' . $dtStart->format('T') . ')</small>';
                                        }

                                        $endTime = '<em>-</em>';
                                        if (isset($entry['timeInterval']['end'])) {
                                            $dtEnd = new DateTime($entry['timeInterval']['end'], $utcTz);
                                            $dtEnd->setTimezone($displayTimezone);
                                            $endTime = $dtEnd->format('H:i:s') . ' <small class="text-muted">(' . $dtEnd->format('T') . ')</small>';
                                        } elseif ($durationStr === '<span class="badge badge-info">Running</span>') {
                                            $endTime = '<span class="badge badge-info">Running</span>';
                                        }

                                        echo "<tr>";
                                        echo "<td>" . $description . "</td>";
                                        echo "<td>" . $projectName . "</td>";
                                        echo "<td>" . $taskName . "</td>";
                                        echo "<td>" . $startTime . "</td>";
                                        echo "<td>" . $endTime . "</td>";
                                        echo "<td>" . $durationStr . "</td>";
                                        echo "</tr>";
                                    }
                                    echo "</tbody></table></div>";
                                }
                                echo "</div></div>"; 
                            } 

                            if (!$anyActionsFoundOverall && count($users) > 0) {
                                echo "<div class='alert alert-success' role='alert'>All active users checked. No time entries recorded on " . htmlspecialchars($tehranDateForDisplay) . ".</div>";
                            }
                            echo "<script>var el = document.getElementById('user-progress-bar'); if(el) { el.style.width = '100%'; el.classList.add('bg-success'); }</script>";
                        } 
                        echo '</div>'; 
                        echo '<script>
                                var mainLoader = document.getElementById("main-loader");
                                if(mainLoader) mainLoader.style.display = "none";
                                var reportContent = document.getElementById("report-content");
                                if(reportContent) reportContent.style.display = "block";
                                console.log("Debug JS: Main loader hidden, report content shown by JS.");
                              </script>';
                    } 
                endif; 
                ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function showFormLoader(formElement, actionValue) {
            console.log("Debug JS: showFormLoader called for form:", formElement, "Attempting to set action to:", actionValue);
            
            const hiddenActionInput = formElement.querySelector('input[name="form_action"]');
            if (hiddenActionInput) {
                hiddenActionInput.value = actionValue;
                console.log("Debug JS: Set hidden input 'form_action' to:", hiddenActionInput.value);
            } else {
                console.error("Debug JS: Hidden input 'form_action' NOT FOUND in form!");
                alert("A critical form configuration error occurred. Please contact support. (Hidden action input missing)");
                return false; 
            }

            const button = formElement.querySelector('button[type="submit"]');
            if (button) {
                console.log("Debug JS: Disabling button and showing spinner.");
                button.disabled = true;
                const spinner = button.querySelector('.loading-spinner');
                if (spinner) {
                    spinner.classList.remove('d-none');
                }
            }
            return true; 
        }
        console.log("Debug JS: Main page scripts loaded.");
    </script>
<?php
ob_end_flush(); // Send the output buffer
?>