# Clockify Daily Actions Viewer (PHP) / clockify_daily_actions_viewer_php

A simple PHP script designed for deployment on limited shared hosting environments. It allows users to input their Clockify API key, select a workspace, choose to view data for "Today" or "Yesterday" (calculated based on Tehran timezone), and then displays detailed time entries for all active users in that workspace for the selected day.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT) <!-- Optional: Add a license badge -->

---

## Table of Contents

*   [Features](#features)
*   [Why This Script?](#why-this-script)
*   [Demo (Placeholder)](#demo-placeholder)
*   [Prerequisites](#prerequisites)
*   [Installation](#installation)
*   [Usage](#usage)
*   [Configuration](#configuration)
*   [How It Works](#how-it-works)
*   [Troubleshooting](#troubleshooting)
*   [Security Considerations](#security-considerations)
*   [Contributing](#contributing)
*   [License](#license)
*   [Acknowledgements](#acknowledgements)

---

## Features

*   **API Key Input:** Securely (via session) handles user's Clockify API key.
*   **Workspace Selection:** Fetches and lists available workspaces for the given API key.
*   **Date Selection (Tehran Time):** Allows users to choose between:
    *   "Today" (current day in Asia/Tehran timezone)
    *   "Yesterday" (previous day in Asia/Tehran timezone)
*   **Detailed Time Entries:** For the selected workspace and date, it fetches and displays:
    *   Time entry description
    *   Project name (and client name if available)
    *   Task name
    *   Start time (converted to server's local time, with timezone indicator)
    *   End time (converted to server's local time, or shows "Running")
    *   Duration
*   **User-Specific Breakdown:** Shows entries grouped by active users within the workspace.
*   **Tehran Timezone Centric:** All date calculations for "Today" and "Yesterday" are based on the Asia/Tehran timezone.
*   **Shared Hosting Friendly:** Written in simple PHP with minimal dependencies, suitable for environments with cURL and JSON extensions.
*   **Responsive UI:** Basic styling for readability on different screen sizes.
*   **Reset Functionality:** Allows users to easily clear their session and start over.

---

## Why This Script?

This script was developed to provide a quick and easy way for individuals or small teams to get an overview of daily or previous day's activities logged in Clockify, especially when:

*   Direct access to the Clockify web app is cumbersome for a quick check.
*   A simplified view is needed for specific users or managers.
*   Operating in a shared hosting environment where complex setups are not feasible.
*   The primary operational timezone is Asia/Tehran, and reports need to align with this local day.

---

## Demo (Placeholder)

**Example Workflow:**

1.  User enters API Key.
2.  User selects a Workspace and chooses "Today" or "Yesterday".
3.  User views the detailed time entries.

---

## Prerequisites

Before you can use this script, ensure your hosting environment (or local server) meets the following requirements:

*   **PHP:** Version 7.0 or higher is recommended (tested with PHP 7.4 and 8.x). Basic functionality might work on PHP 5.6+ but is not guaranteed.
*   **PHP cURL Extension:** Required for making HTTP requests to the Clockify API. This is usually enabled by default on most shared hosts.
*   **PHP JSON Extension:** Required for encoding and decoding API responses. Also typically enabled by default.
*   **Web Server:** Apache, Nginx, or any other web server capable of executing PHP scripts.
*   **Clockify Account:** You need an active Clockify account.
*   **Clockify API Key:** Generate an API key from your Clockify Profile Settings.

---

## Installation

1.  **Download or Clone:**
    *   **Download:** Download the `index.php` file from this repository.
    *   **Clone (if you use Git):**
        ```bash
        git clone https://github.com/AmirHosseinMoloudi/clockify_daily_actions_viewer_php.git
        cd clockify_daily_actions_viewer_php
        ```

2.  **Upload to Server:**
    Upload the PHP script file to a web-accessible directory on your shared hosting account (e.g., `public_html`, `www`, `htdocs`, or a subdirectory within).

3.  **Set Permissions (If Necessary):**
    In most cases, default file permissions (e.g., 644 for files) should be sufficient. If you encounter permission errors, ensure the web server has read access to the script.

4.  **Access in Browser:**
    Open the script in your web browser by navigating to its URL (e.g., `http://yourdomain.com/path/to/index.php`).

---

## Usage

1.  **Enter API Key:**
    *   When you first access the script, you'll be prompted to enter your Clockify API Key.
    *   Enter your key and click "Next: Select Workspace".
    *   The API key is stored in your PHP session for the duration of your browser session or until you reset.

2.  **Select Workspace & Report Date:**
    *   If the API key is valid, a dropdown list of your Clockify workspaces will appear.
    *   Select the desired workspace.
    *   Choose whether you want the report for "Today" or "Yesterday" (relative to Tehran time).
    *   Click "Get User Actions".

3.  **View Report:**
    *   The script will fetch time entries for all active users in the selected workspace for the chosen date (Tehran time).
    *   The report will be displayed, showing time entries grouped by user.
    *   Information includes description, project, task, start/end times (in your server's local time), and duration.

4.  **Reset:**
    *   Click the "Reset & Start Over" link at the top right at any time to clear your API key, workspace selection, and date choice from the session and return to the API key input screen.

---

## Configuration

The script is designed to be mostly plug-and-play. However, there are a couple of internal variables you might want to be aware of or adjust if necessary (by editing the PHP file directly):

*   `$apiBaseUrl = 'https://api.clockify.me/api/v1';`
    *   This is the base URL for the Clockify API. It's unlikely you'll need to change this unless Clockify significantly changes its API structure.
*   `$tehranTimezoneIdentifier = 'Asia/Tehran';`
    *   This defines the timezone used for calculating "Today" and "Yesterday". Change this if you need a different reference timezone for the day's definition.
*   **Time Display:** The script currently displays the start and end times of entries converted to the **server's default timezone**. If you want to force the display of these times in a specific timezone (e.g., always Tehran time, regardless of server location), you can modify this line within the Step 3 display logic:
    ```php
    // Current:
    $displayTimezone = new DateTimeZone(date_default_timezone_get()); // Server's default
    // To force Tehran time display:
    // $displayTimezone = $tehranTz; // $tehranTz is already defined as 'Asia/Tehran'
    ```

---

## How It Works

1.  **Session Management:** PHP sessions (`$_SESSION`) are used to store the user's API key, selected workspace ID, and chosen date offset across different steps/page loads.
2.  **API Interaction:**
    *   A helper function `callClockifyAPI()` handles all communication with the Clockify API.
    *   It uses cURL to make GET requests.
    *   The `X-Api-Key` header is used for authentication.
    *   JSON responses from the API are decoded into PHP arrays.
3.  **Step-by-Step Workflow:**
    *   **Step 1 (API Key):** User submits API key. It's stored in the session.
    *   **Step 2 (Workspace & Date):**
        *   `/workspaces` endpoint is called to fetch user's workspaces.
        *   User selects a workspace and a date offset (0 for today, -1 for yesterday). These are stored in the session.
    *   **Step 3 (Report):**
        *   The target date (today or yesterday in Tehran time) is calculated.
        *   Start and end timestamps for this target date are converted to UTC format (`YYYY-MM-DDTHH:MM:SSZ`) as required by the Clockify API.
        *   `/workspaces/{workspaceId}/users` endpoint is called to get active users.
        *   For each user, `/workspaces/{workspaceId}/user/{userId}/time-entries` endpoint is called with the calculated UTC start/end times and `hydrated=true` (to get project/task details).
        *   The fetched time entries are then formatted and displayed in an HTML table.
4.  **Error Handling:** Basic error handling is in place to display messages if API calls fail or if data is not found. More detailed errors are logged to the server's error log.

---

## Troubleshooting

*   **Blank Page or PHP Errors:**
    *   Ensure your server meets the PHP version and extension prerequisites.
    *   Check your web server's error logs for specific PHP error messages.
    *   Temporarily enable PHP error display for debugging (NOT recommended for production):
        ```php
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        ```
        (Add this at the top of your PHP script, and remove it once resolved).

*   **"Error fetching workspaces" / API Key Issues:**
    *   Double-check that you have entered the correct Clockify API key.
    *   Ensure the API key has the necessary permissions if you have restricted its scope (though default keys usually work fine).
    *   Verify your server has outbound internet connectivity to `api.clockify.me`.

*   **No Workspaces Listed (but you have them):**
    *   This is likely an API key issue or a temporary Clockify API problem.

*   **No Time Entries Shown:**
    *   Confirm there are actual time entries in Clockify for the selected users, workspace, and date (Tehran time).
    *   Check if the users are "active" in the workspace.
    *   The script filters by date range. If entries are just outside this range (e.g., started at 11:59 PM the day before in Tehran), they won't show.

*   **Incorrect Times Displayed:**
    *   The script converts API UTC times to your *server's* default timezone for display in the table. If this is not desired, see the [Configuration](#configuration) section to force a specific display timezone.
    *   Ensure your server's system time and timezone are correctly configured.

---

## Security Considerations

*   **API Key Handling:**
    *   The API key is stored in a PHP session. Session data is typically stored on the server, making it more secure than client-side storage.
    *   Ensure your PHP session handling is secure (e.g., using secure session cookies if on HTTPS, appropriate session save path permissions).
    *   **Always use HTTPS** for the page hosting this script to protect the API key during its initial submission and to secure session cookies.
*   **Input Sanitization:**
    *   User inputs (like API key, workspace ID) are used in API calls or displayed. The script uses `htmlspecialchars()` when displaying data to prevent XSS vulnerabilities. Workspace IDs and API keys are generally not directly vulnerable to XSS when used in API calls but it's good practice.
*   **Error Reporting:**
    *   In a production environment, ensure `display_errors` is turned OFF in your `php.ini` or via `ini_set('display_errors', 0);` to prevent leaking sensitive information or server paths. Errors should be logged to server logs instead.
*   **File Permissions:**
    *   Ensure the PHP script file itself is not writable by the web server user after deployment, if possible (e.g., permissions 644 or 444).
*   **Shared Hosting:**
    *   Be aware of the inherent risks of shared hosting. If other sites on the same server are compromised, it could potentially impact your script or session data. This script is designed for simplicity and convenience on such platforms, not for high-security enterprise environments.
*   **Rate Limiting:** The Clockify API has rate limits (e.g., 10 requests per second per IP, and 50 per second by X-Addon-Token). This simple script is unlikely to hit these for individual use but be mindful if adapting it for many users or frequent automated requests.

---

## Contributing

Contributions are welcome! If you have suggestions, bug fixes, or improvements, please follow these steps:

1.  **Fork the repository.**
2.  **Create a new branch** for your feature or fix:
    ```bash
    git checkout -b feature/your-feature-name
    ```
    or
    ```bash
    git checkout -b fix/issue-description
    ```
3.  **Make your changes and commit them** with clear, descriptive messages.
4.  **Push your changes** to your forked repository.
5.  **Open a Pull Request** to the main repository, detailing the changes you've made.

Please also consider opening an issue first to discuss significant changes.

---

## License

This project is licensed under the **MIT License**. See the [LICENSE.txt](LICENSE.txt) file for details.

---

## Acknowledgements

*   **Clockify Team:** For providing a great time tracking service and a public API.
*   Users and contributors who help improve this script.

---

*If you have any questions or run into issues, feel free to open an issue on GitHub.*