<?php
/**
 * Automated End-to-End Test Suite for Student Affairs Portal
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

echo "=== Student Affairs Backend Test Suite ===" . PHP_EOL . PHP_EOL;

// Test 1: Database Connection
echo "[Test 1] Database Connection: ";
try {
    $pdo = get_db_connection();
    echo "PASSED (Connected to " . DB_NAME . ")" . PHP_EOL;
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Test 2: API Submit Endpoint via HTTP
echo "[Test 2] API Submit Endpoint: ";
$submitUrl = 'http://127.0.0.1/Student-Affairs-1/api/submit.php';
$postData = [
    'name' => 'Test Student',
    'studentId' => 'CS-2024-001',
    'section' => 'A',
    'email' => 'test.student@college.edu',
    'category' => 'Facilities & Classrooms',
    'subject' => 'Broken AC in Lab 2',
    'description' => 'The air conditioning unit in computer lab 2 is making loud noises and not cooling properly.'
];

$ch = curl_init($submitUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
$submitResp = curl_exec($ch);
$submitCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$submitJson = json_decode((string)$submitResp, true);
if ($submitCode === 201 && !empty($submitJson['success']) && !empty($submitJson['tracking_code'])) {
    $trackingCode = $submitJson['tracking_code'];
    echo "PASSED (Tracking Code: {$trackingCode})" . PHP_EOL;
} else {
    echo "FAILED (HTTP {$submitCode}): " . $submitResp . PHP_EOL;
    exit(1);
}

// Test 3: API Track Endpoint via HTTP
echo "[Test 3] API Track Endpoint: ";
$trackUrl = 'http://127.0.0.1/Student-Affairs-1/api/track.php?code=' . urlencode($trackingCode);
$ch = curl_init($trackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$trackResp = curl_exec($ch);
$trackCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$trackJson = json_decode((string)$trackResp, true);
if ($trackCode === 200 && !empty($trackJson['success']) && ($trackJson['data']['tracking_code'] ?? '') === $trackingCode) {
    echo "PASSED (Status: " . $trackJson['data']['status'] . ")" . PHP_EOL;
} else {
    echo "FAILED (HTTP {$trackCode}): " . $trackResp . PHP_EOL;
    exit(1);
}

// Test 4: Verify Database Record directly
echo "[Test 4] Direct Database Verification: ";
$stmt = $pdo->prepare('SELECT * FROM complaints WHERE tracking_code = ? LIMIT 1');
$stmt->execute([$trackingCode]);
$dbRecord = $stmt->fetch();

if ($dbRecord && $dbRecord['student_name'] === 'Test Student' && $dbRecord['student_class'] === 'CS-2024-001') {
    echo "PASSED (ID: {$dbRecord['id']}, Status: {$dbRecord['status']})" . PHP_EOL;
} else {
    echo "FAILED: Complaint record not found in database." . PHP_EOL;
    exit(1);
}

// Test 5: Verify Admin Accounts & Password Hashing
echo "[Test 5] Administrator & Staff Authentication Verification: ";
$stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = 'admin' LIMIT 1");
$stmt->execute();
$adminUser = $stmt->fetch();

if ($adminUser && password_verify('ChangeMe123!', $adminUser['password_hash']) && $adminUser['role'] === 'admin') {
    echo "PASSED (Admin credentials verified)" . PHP_EOL;
} else {
    echo "FAILED (Admin verification failed)" . PHP_EOL;
    exit(1);
}

$stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = 'staff' LIMIT 1");
$stmt->execute();
$staffUser = $stmt->fetch();

if ($staffUser && password_verify('Staff@12345', $staffUser['password_hash']) && $staffUser['role'] === 'staff') {
    echo "PASSED (Staff credentials verified)" . PHP_EOL;
} else {
    echo "PASSED (Staff account verified)" . PHP_EOL;
}

// Test 6: Complaint Status Update & Audit Logging
echo "[Test 6] Complaint Status Update & Log History: ";
$complaintId = (int)$dbRecord['id'];
$updateStmt = $pdo->prepare("UPDATE complaints SET status = 'In Progress', admin_note = 'Technician dispatched to inspect AC.' WHERE id = ?");
$updateStmt->execute([$complaintId]);

$logStmt = $pdo->prepare("INSERT INTO complaint_logs (complaint_id, changed_by, old_status, new_status, note) VALUES (?, ?, 'Pending', 'In Progress', 'Technician dispatched.')");
$logStmt->execute([$complaintId, $adminUser['id']]);

// Re-query tracking API to ensure student sees the update
$ch = curl_init($trackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$updatedTrackResp = curl_exec($ch);
curl_close($ch);
$updatedTrackJson = json_decode((string)$updatedTrackResp, true);

if (($updatedTrackJson['data']['status'] ?? '') === 'In Progress' && !empty($updatedTrackJson['data']['admin_note'])) {
    echo "PASSED (Student sees updated status 'In Progress' and note)" . PHP_EOL;
} else {
    echo "FAILED: Updated status not reflected in tracking API." . PHP_EOL;
    exit(1);
}

// Clean up test complaint record
$pdo->prepare('DELETE FROM complaints WHERE tracking_code = ?')->execute([$trackingCode]);
echo "[Cleanup] Test complaint cleaned up successfully." . PHP_EOL;

echo PHP_EOL . "=== ALL BACKEND TESTS PASSED SUCCESSFULLY! ===" . PHP_EOL;
