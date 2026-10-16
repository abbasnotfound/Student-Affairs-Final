<?php
/**
 * Auth & Dashboard Integration Test
 */

declare(strict_types=1);

echo "=== Admin & Staff Authentication Test Suite ===" . PHP_EOL . PHP_EOL;

$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$baseUrl = 'http://127.0.0.1/Student-Affairs-1/admin.php';

// 1. Fetch Login Page and CSRF token
echo "[Test 1] Fetch Login Page & CSRF Token: ";
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', (string)$loginHtml, $matches);
$csrfToken = $matches[1] ?? '';

if (!empty($csrfToken)) {
    echo "PASSED (CSRF: " . substr($csrfToken, 0, 8) . "...)" . PHP_EOL;
} else {
    echo "FAILED: Could not find CSRF token on login page." . PHP_EOL;
    exit(1);
}

// 2. Test Invalid Login
echo "[Test 2] Invalid Credentials Attempt: ";
$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'login' => '1',
    'csrf_token' => $csrfToken,
    'username' => 'admin',
    'password' => 'WrongPassword123'
]));
$badResp = curl_exec($ch);
curl_close($ch);

if (str_contains((string)$badResp, 'Incorrect username or password')) {
    echo "PASSED (Rejected with correct error message)" . PHP_EOL;
} else {
    echo "FAILED: Unexpected response for invalid login." . PHP_EOL;
    exit(1);
}

// 3. Test Valid Admin Login
echo "[Test 3] Valid Admin Sign In: ";
// Get fresh CSRF token
preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', (string)$badResp, $matches);
$freshCsrf = $matches[1] ?? $csrfToken;

$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'login' => '1',
    'csrf_token' => $freshCsrf,
    'username' => 'admin',
    'password' => 'ChangeMe123!'
]));
$dashResp = curl_exec($ch);
curl_close($ch);

if (str_contains((string)$dashResp, 'Complaints Management') && str_contains((string)$dashResp, 'Signed in as')) {
    echo "PASSED (Authenticated & Dashboard Loaded)" . PHP_EOL;
} else {
    echo "FAILED: Dashboard not reached." . PHP_EOL;
    exit(1);
}

// 4. Test Create New Staff Member via Admin Dashboard
echo "[Test 4] Admin Creates Staff Account: ";
preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', (string)$dashResp, $matches);
$dashCsrf = $matches[1] ?? '';

$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'create_staff' => '1',
    'csrf_token' => $dashCsrf,
    'new_fullname' => 'Sarah Connor',
    'new_username' => 'sconnor',
    'new_role' => 'staff',
    'new_password' => 'SarahPass2024!'
]));
$createResp = curl_exec($ch);
curl_close($ch);

if (str_contains((string)$createResp, "created successfully") && str_contains((string)$createResp, "sconnor")) {
    echo "PASSED (Created staff member 'sconnor')" . PHP_EOL;
} else {
    echo "FAILED: Staff creation response did not contain success message." . PHP_EOL;
    exit(1);
}

// 5. Test Logout
echo "[Test 5] Secure Logout: ";
$ch = curl_init($baseUrl . '?logout=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$logoutResp = curl_exec($ch);
curl_close($ch);

if (str_contains((string)$logoutResp, 'Staff login') && str_contains((string)$logoutResp, 'safely signed out')) {
    echo "PASSED (Session terminated successfully)" . PHP_EOL;
} else {
    echo "FAILED: Logout did not return to login screen." . PHP_EOL;
    exit(1);
}

// Clean up
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
$pdo->prepare("DELETE FROM admins WHERE username = 'sconnor'")->execute();

echo PHP_EOL . "=== ALL AUTHENTICATION TESTS PASSED! ===" . PHP_EOL;
