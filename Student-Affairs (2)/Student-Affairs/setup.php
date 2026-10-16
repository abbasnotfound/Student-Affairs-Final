<?php
/**
 * Student Affairs - Database & Environment Setup Script
 * Can be run via Web Browser (http://localhost/Student-Affairs-1/setup.php)
 * or via CLI (php setup.php).
 */

declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');

function output_line(string $text, string $status = 'info', bool $isCli = false): void {
    if ($isCli) {
        $prefix = match($status) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . $text . PHP_EOL;
    } else {
        $color = match($status) {
            'success' => '#15803d',
            'error'   => '#b91c1c',
            'warning' => '#b45309',
            default   => '#3b82f6'
        };
        echo "<p style='margin:6px 0; color:{$color};'><strong>" . htmlspecialchars($text) . "</strong></p>";
    }
}

if (!$isCli) {
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Database Setup | Student Affairs</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;padding:30px;color:#1e293b;}";
    echo ".card{max-width:650px;margin:auto;background:white;padding:30px;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 10px 25px rgba(0,0,0,0.04);}";
    echo "h1{font-size:22px;margin-top:0;color:#0f172a;}pre{background:#f1f5f9;padding:12px;border-radius:8px;font-size:12px;}";
    echo ".btn{display:inline-block;padding:10px 18px;background:#2563eb;color:white;text-decoration:none;border-radius:6px;font-weight:600;margin-top:15px;}";
    echo "</style></head><body><div class='card'><h1>Student Affairs — Database Setup</h1>";
}

output_line("Starting database setup...", 'info', $isCli);

// 1. Check PHP Version & Extensions
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    output_line("PHP 8.0 or higher is required. Current version: " . PHP_VERSION, 'error', $isCli);
    exit;
}
output_line("PHP Version: " . PHP_VERSION . " (Compatible)", 'success', $isCli);

$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        output_line("Missing required PHP extension: {$ext}", 'error', $isCli);
        exit;
    }
}
output_line("All required PHP extensions are installed.", 'success', $isCli);

require_once __DIR__ . '/config.php';

try {
    $pdo = get_db_connection();
    output_line("Successfully connected to MySQL database: " . DB_NAME, 'success', $isCli);

    // Verify tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    output_line("Active database tables: " . implode(', ', $tables), 'info', $isCli);

    // Verify Admin user
    $adminCheck = $pdo->prepare("SELECT id, username, role, full_name FROM admins WHERE username = 'admin' LIMIT 1");
    $adminCheck->execute();
    $admin = $adminCheck->fetch();

    if ($admin) {
        output_line("Default Administrator verified: Username [admin], Role [{$admin['role']}]", 'success', $isCli);
    }

    // Verify Staff user
    $staffCheck = $pdo->prepare("SELECT id, username, role, full_name FROM admins WHERE username = 'staff' LIMIT 1");
    $staffCheck->execute();
    $staff = $staffCheck->fetch();

    if ($staff) {
        output_line("Default Staff Handler verified: Username [staff], Role [{$staff['role']}]", 'success', $isCli);
    }

    output_line("Database setup completed successfully!", 'success', $isCli);

    if (!$isCli) {
        echo "<hr style='margin:20px 0; border:0; border-top:1px solid #e2e8f0;'>";
        echo "<h3 style='margin-bottom:8px;'>Default Credentials for Local Testing:</h3>";
        echo "<ul style='line-height:1.7;font-size:14px;'>";
        echo "<li><strong>Admin:</strong> <code>admin</code> / <code>ChangeMe123!</code> (Role: Administrator)</li>";
        echo "<li><strong>Staff:</strong> <code>staff</code> / <code>Staff@12345</code> (Role: Complaint Handler)</li>";
        echo "</ul>";
        echo "<a class='btn' href='index.html'>Open Student Website →</a> ";
        echo "<a class='btn' style='background:#475569;margin-left:8px;' href='admin.php'>Open Staff Dashboard →</a>";
        echo "</div></body></html>";
    }

} catch (Exception $e) {
    output_line("Setup failed: " . $e->getMessage(), 'error', $isCli);
}
