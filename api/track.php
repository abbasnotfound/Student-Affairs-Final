<?php
/**
 * Student Affairs - Track Complaint API Endpoint
 * Accepts GET or POST requests with a tracking code.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Ensure CORS / Content-Type headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$trackingCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $trackingCode = trim((string)($_GET['code'] ?? $_GET['tracking_code'] ?? ''));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        $input = json_decode($rawBody, true) ?? [];
        $trackingCode = trim((string)($input['code'] ?? $input['tracking_code'] ?? ''));
    } else {
        $trackingCode = trim((string)($_POST['code'] ?? $_POST['tracking_code'] ?? ''));
    }
} else {
    json_response([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ], 405);
}

$trackingCode = strtoupper($trackingCode);

if ($trackingCode === '') {
    json_response([
        'success' => false,
        'message' => 'Tracking code is required.'
    ], 400);
}

// Basic length/format validation
if (mb_strlen($trackingCode) < 6 || mb_strlen($trackingCode) > 32) {
    json_response([
        'success' => false,
        'message' => 'Invalid tracking code format.'
    ], 400);
}

$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare('
        SELECT
            id,
            tracking_code,
            category,
            subject,
            description,
            status,
            admin_note,
            created_at,
            updated_at
        FROM complaints
        WHERE tracking_code = ?
        LIMIT 1
    ');
    $stmt->execute([$trackingCode]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        json_response([
            'success' => false,
            'message' => 'No complaint was found with that tracking code. Please verify the code and try again.'
        ], 404);
    }

    // Fetch status history logs
    $logStmt = $pdo->prepare('
        SELECT old_status, new_status, note, created_at
        FROM complaint_logs
        WHERE complaint_id = ?
        ORDER BY created_at ASC
    ');
    $logStmt->execute([$complaint['id']]);
    $history = $logStmt->fetchAll();

    json_response([
        'success' => true,
        'data' => [
            'tracking_code' => $complaint['tracking_code'],
            'category'      => $complaint['category'],
            'subject'       => $complaint['subject'],
            'status'        => $complaint['status'],
            'admin_note'    => $complaint['admin_note'] ?: null,
            'created_at'    => $complaint['created_at'],
            'updated_at'    => $complaint['updated_at'],
            'history'       => $history
        ]
    ]);

} catch (Exception $e) {
    error_log("Complaint tracking error: " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Failed to retrieve complaint details due to a server error.'
    ], 500);
}
