<?php
session_start(); // Used to store API key, workspace ID, and date offset

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SimpleClockifyPHPClient/1.0');

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
        return ['error' => true, 'message' => 'cURL Error: ' . $curlError, 'response_body' => $response];
    }

    $decodedResponse = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return $decodedResponse;
    } else {
        $errorMessage = "API request failed with HTTP status $httpCode.";
        if (isset($decodedResponse['message'])) $errorMessage .= " Clockify Message: " . $decodedResponse['message'];
        if (isset($decodedResponse['code'])) $errorMessage .= " (Code: " . $decodedResponse['code'] . ")";
        error_log("Clockify API Error: HTTP $httpCode, URL: $url, Message: " . $errorMessage . ", Response: " . $response);
        return ['error' => true, 'http_code' => $httpCode, 'message' => $errorMessage, 'response_body' => $response];
    }
}

// --- Main Logic ---
$step = 1; // 1: API Key, 2: Workspace & Date Select, 3: Show Actions

if (isset($_POST['submit_api_key']) && !empty($_POST['api_key'])) {
    $_SESSION['api_key'] = trim($_POST['api_key']);
    unset($_SESSION['workspace_id']);
    unset($_SESSION['date_offset']);
    $step = 2;
} elseif (isset($_SESSION['api_key']) && isset($_POST['submit_workspace_date']) && !empty($_POST['workspace_id']) && isset($_POST['date_offset'])) {
    $_SESSION['workspace_id'] = $_POST['workspace_id'];
    $_SESSION['date_offset'] = (int)$_POST['date_offset']; // 0 for today, -1 for yesterday
    $step = 3;
} elseif (isset($_SESSION['api_key']) && (!isset($_SESSION['workspace_id']) || !isset($_SESSION['date_offset']))) {
    $step = 2;
} elseif (isset($_SESSION['api_key']) && isset($_SESSION['workspace_id']) && isset($_SESSION['date_offset'])) {
    $step = 3;
}

// Reset functionality
if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clockify User Actions (Tehran Time)</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; margin: 0; background-color: #f0f2f5; color: #1c1e21; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .container { width: 100%; max-width: 900px; background-color: #fff; border: 1px solid #dddfe2; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1), 0 8px 16px rgba(0,0,0,0.1); padding: 25px; }
        .error { color: #D8000C; background-color: #FFD2D2; border: 1px solid #D8000C; padding: 12px; margin-bottom: 18px; border-radius: 4px; font-size: 0.95em; }
        .success { color: #4F8A10; background-color: #DFF2BF; border: 1px solid #4F8A10; padding: 12px; margin-bottom: 18px; border-radius: 4px; font-size: 0.95em; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #606770; }
        input[type="text"], select { width: 100%; padding: 12px; margin-bottom: 18px; border: 1px solid #ccd0d5; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        input[type="submit"] { width: auto; padding: 12px 24px; background-color: #1877f2; color: white; border: none; cursor: pointer; border-radius: 6px; font-size: 16px; font-weight: bold; margin-top:10px; }
        input[type="submit"]:hover { background-color: #166fe5; }
        h1, h2, h3, h4 { color: #1c1e21; }
        h1 { text-align: center; margin-bottom: 25px; font-size: 28px; }
        h2 { border-bottom: 1px solid #dddfe2; padding-bottom: 12px; margin-top: 30px; margin-bottom: 20px; font-size: 22px;}
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #dddfe2; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background-color: #f5f6f7; font-weight: 600; }
        .user-block { margin-bottom: 30px; padding: 20px; border: 1px solid #e0e0e0; border-radius: 6px; background-color: #f9f9f9; }
        .user-block h4 { margin-top: 0; color: #333; }
        .reset-link { display: inline-block; text-align: right; margin-bottom:20px; font-size: 0.9em; color: #1877f2; text-decoration: none; float: right; }
        .reset-link:hover { text-decoration: underline; }
        .info-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .info-bar p { margin: 0 10px 5px 0; font-size: 0.95em; }
        .no-entries { padding: 10px; background-color: #f0f0f0; border-radius: 4px; text-align: center; }
        td em { color: #777; }
        .date-choice-label { margin-top: 15px; margin-bottom: 5px; }
        .date-choice input[type="radio"] { margin-right: 5px; vertical-align: middle; }
        .date-choice label { display: inline; margin-right: 15px; font-weight: normal; }
    </style>
</head>
<body>
    <div class="container">
        <a href="?reset=true" class="reset-link">Reset & Start Over</a>
        <h1>Clockify User Actions (Tehran Time)</h1>

        <?php if ($step === 1): ?>
            <h2>Step 1: Enter Your Clockify API Key</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <label for="api_key">API Key:</label>
                <input type="text" id="api_key" name="api_key" required>
                <input type="submit" name="submit_api_key" value="Next: Select Workspace">
            </form>
        <?php endif; ?>

        <?php
        if ($step === 2 && isset($_SESSION['api_key'])):
            $apiKey = $_SESSION['api_key'];
            $workspaces = callClockifyAPI($apiKey, '/workspaces');

            if (isset($workspaces['error']) || !$workspaces) {
                $errorDetails = htmlspecialchars(is_string($workspaces['message']) ? $workspaces['message'] : json_encode($workspaces));
                if (isset($workspaces['response_body'])) {
                     $errorDetails .= "<br><small>Raw Response: " . htmlspecialchars(is_string($workspaces['response_body']) ? $workspaces['response_body'] : json_encode($workspaces['response_body'])) . "</small>";
                }
                echo "<p class='error'>Error fetching workspaces. Please check your API key or network connection. <br>Details: " . $errorDetails . "</p>";
                echo "<p><a href='?reset=true'>Try again with a new API Key</a></p>";
            } elseif (empty($workspaces)) {
                echo "<p class='error'>No workspaces found for this API key.</p>";
                echo "<p><a href='?reset=true'>Try again with a new API Key</a></p>";
            } else {
        ?>
            <h2>Step 2: Select Workspace & Report Date</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <label for="workspace_id">Choose a workspace:</label>
                <select id="workspace_id" name="workspace_id" required>
                    <?php foreach ($workspaces as $ws): ?>
                        <option value="<?php echo htmlspecialchars($ws['id']); ?>">
                            <?php echo htmlspecialchars($ws['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="date-choice-label">Choose report date (Tehran Time):</p>
                <div class="date-choice">
                    <input type="radio" id="date_today" name="date_offset" value="0" checked>
                    <label for="date_today">Today</label>

                    <input type="radio" id="date_yesterday" name="date_offset" value="-1">
                    <label for="date_yesterday">Yesterday</label>
                </div>

                <input type="submit" name="submit_workspace_date" value="Get User Actions">
            </form>
        <?php
            }
        endif;
        ?>

        <?php
        if ($step === 3 && isset($_SESSION['api_key']) && isset($_SESSION['workspace_id']) && isset($_SESSION['date_offset'])):
            $apiKey = $_SESSION['api_key'];
            $workspaceId = $_SESSION['workspace_id'];
            $dateOffset = $_SESSION['date_offset']; // 0 for today, -1 for yesterday

            $currentWorkspaceDetails = callClockifyAPI($apiKey, "/workspaces/{$workspaceId}");
            $workspaceName = isset($currentWorkspaceDetails['name']) ? $currentWorkspaceDetails['name'] : $workspaceId;

            // --- Tehran Timezone Logic for selected date ---
            $tehranTz = new DateTimeZone($tehranTimezoneIdentifier);
            $utcTz = new DateTimeZone('UTC');

            // Get base date in Tehran (today)
            $targetDateInTehran = new DateTime('now', $tehranTz);

            // Modify if yesterday was selected
            if ($dateOffset === -1) {
                $targetDateInTehran->modify('-1 day');
            }
            $tehranDateForDisplay = $targetDateInTehran->format('Y-m-d');
            $dayNameForDisplay = ($dateOffset === 0) ? "Today" : "Yesterday";


            // Calculate start of the selected day in Tehran
            $startOfDayTehran = new DateTime($targetDateInTehran->format('Y-m-d') . ' 00:00:00', $tehranTz);
            $startOfDayUtc = clone $startOfDayTehran;
            $startOfDayUtc->setTimezone($utcTz);
            $apiStartTime = $startOfDayUtc->format('Y-m-d\TH:i:s\Z');

            // Calculate end of the selected day in Tehran
            $endOfDayTehran = new DateTime($targetDateInTehran->format('Y-m-d') . ' 23:59:59', $tehranTz);
            $endOfDayUtc = clone $endOfDayTehran;
            $endOfDayUtc->setTimezone($utcTz);
            $apiEndTime = $endOfDayUtc->format('Y-m-d\TH:i:s\Z');
            // --- End Tehran Timezone Logic ---

            echo "<h2>Step 3: User Actions for " . htmlspecialchars($dayNameForDisplay) . " (" . htmlspecialchars($tehranDateForDisplay) . " Tehran Time)</h2>";
            echo "<div class='info-bar'>";
            echo "<p><strong>Workspace:</strong> " . htmlspecialchars($workspaceName) . "</p>";
            echo "<p><strong>Reporting Date:</strong> " . htmlspecialchars($tehranDateForDisplay) . " (" . htmlspecialchars($dayNameForDisplay) . ", Tehran)</p>";
            echo "</div>";


            $users = callClockifyAPI($apiKey, "/workspaces/{$workspaceId}/users", 'GET', ['status' => 'ACTIVE']);

            if (isset($users['error']) || !$users) {
                 $errorDetails = htmlspecialchars(is_string($users['message']) ? $users['message'] : json_encode($users));
                 if (isset($users['response_body'])) {
                     $errorDetails .= "<br><small>Raw Response: " . htmlspecialchars(is_string($users['response_body']) ? $users['response_body'] : json_encode($users['response_body'])) . "</small>";
                 }
                echo "<p class='error'>Error fetching users. <br>Details: " . $errorDetails . "</p>";
            } elseif (empty($users)) {
                 echo "<p class='success'>No active users found in this workspace.</p>";
            } else {
                $anyActionsFoundOverall = false;

                echo "<h3>Detailed Actions by User:</h3>";

                foreach ($users as $user) {
                    if (!isset($user['id']) || !isset($user['name'])) {
                        error_log("User data incomplete: " . json_encode($user));
                        echo "<div class='user-block'><p class='error'>User data is incomplete for an entry, skipping.</p></div>";
                        continue;
                    }
                    $userId = $user['id'];
                    $userName = $user['name'];
                    
                    echo "<div class='user-block'>";
                    echo "<h4>User: " . htmlspecialchars($userName) . " (ID: " . htmlspecialchars($userId) . ")</h4>";

                    $timeEntriesParams = [
                        'start' => $apiStartTime,
                        'end' => $apiEndTime,
                        'hydrated' => 'true',
                        'consider-duration-format' => 'true'
                    ];
                    $timeEntries = callClockifyAPI($apiKey, "/workspaces/{$workspaceId}/user/{$userId}/time-entries", 'GET', $timeEntriesParams);

                    if (isset($timeEntries['error'])) {
                        $errorDetails = htmlspecialchars(is_string($timeEntries['message']) ? $timeEntries['message'] : json_encode($timeEntries));
                        if (isset($timeEntries['response_body'])) {
                             $errorDetails .= "<br><small>Raw Response: " . htmlspecialchars(is_string($timeEntries['response_body']) ? $timeEntries['response_body'] : json_encode($timeEntries['response_body'])) . "</small>";
                        }
                        echo "<p class='error'>Error fetching time entries for user {$userName}. <br>Details: " . $errorDetails . "</p>";
                    } elseif (empty($timeEntries)) {
                        echo "<p class='no-entries'>No time entries found for " . htmlspecialchars($userName) . " on " . htmlspecialchars($tehranDateForDisplay) . ".</p>";
                    } else {
                        $anyActionsFoundOverall = true;
                        echo "<table>";
                        echo "<thead><tr><th>Description</th><th>Project</th><th>Task</th><th>Start Time (Local)</th><th>End Time (Local)</th><th>Duration</th></tr></thead><tbody>";
                        foreach ($timeEntries as $entry) {
                            $description = !empty($entry['description']) ? htmlspecialchars($entry['description']) : '<em>(No description)</em>';
                            $projectName = isset($entry['project']['name']) ? htmlspecialchars($entry['project']['name']) : '<em>N/A</em>';
                             if (isset($entry['project']['clientName']) && !empty($entry['project']['clientName'])) {
                                $projectName .= " (" . htmlspecialchars($entry['project']['clientName']) . ")";
                            }
                            $taskName = isset($entry['task']['name']) ? htmlspecialchars($entry['task']['name']) : '<em>N/A</em>';

                            $durationStr = '<em>N/A</em>';
                            if (isset($entry['timeInterval']['duration'])) {
                                try {
                                    if ($entry['timeInterval']['duration'] === null && $entry['timeInterval']['end'] === null) {
                                        $durationStr = '<em>Running</em>';
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
                            
                            $displayTimezone = new DateTimeZone(date_default_timezone_get()); // Server's default. Or use $tehranTz for consistent Tehran time display.
                            $startTime = '<em>N/A</em>';
                            if (isset($entry['timeInterval']['start'])) {
                                $dtStart = new DateTime($entry['timeInterval']['start'], $utcTz);
                                $dtStart->setTimezone($displayTimezone);
                                $startTime = $dtStart->format('H:i:s') . ' (' . $dtStart->format('T') . ')';
                            }

                            $endTime = '<em>(Running)</em>';
                            if (isset($entry['timeInterval']['end'])) {
                                $dtEnd = new DateTime($entry['timeInterval']['end'], $utcTz);
                                $dtEnd->setTimezone($displayTimezone);
                                $endTime = $dtEnd->format('H:i:s') . ' (' . $dtEnd->format('T') . ')';
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
                        echo "</tbody></table>";
                    }
                    echo "</div>"; 
                }

                if (!$anyActionsFoundOverall && count($users) > 0) {
                    echo "<p class='success'>All active users checked. No time entries recorded for any user on " . htmlspecialchars($tehranDateForDisplay) . ".</p>";
                }
            }
        endif;
        ?>
    </div>
</body>
</html>