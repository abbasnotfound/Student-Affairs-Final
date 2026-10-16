<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'student_affairs');


/*
|--------------------------------------------------------------------------
| EMAIL / SMTP
|--------------------------------------------------------------------------
*/

define('MAIL_FROM_EMAIL', 'haseebsheikhgamingzone@gmail.com');
define('MAIL_FROM_NAME', 'Student Affairs Portal');

define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');

define('SMTP_USER', 'haseebsheikhgamingzone@gmail.com');


/*
|--------------------------------------------------------------------------
| LOAD LOCAL SMTP PASSWORD
|--------------------------------------------------------------------------
|
| The real Gmail App Password stays inside config.local.php.
|
*/

$localConfig = [];

$localConfigPath = APP_ROOT . '/config.local.php';

if (is_file($localConfigPath)) {
    $loadedConfig = require $localConfigPath;

    if (is_array($loadedConfig)) {
        $localConfig = $loadedConfig;
    }
}

define(
    'SMTP_PASS',
    (string) (
        $localConfig['SMTP_PASS']
        ?? (getenv('SMTP_PASS') ?: '')
    )
);


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {

        /*
         * Connect to MySQL server first.
         */

        $server = new PDO(
            'mysql:host=' . DB_HOST .
            ';port=' . DB_PORT .
            ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );

        /*
         * Create database if it does not exist.
         */

        $server->exec(
            "CREATE DATABASE IF NOT EXISTS `" .
            DB_NAME .
            "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        /*
         * Connect to the actual database.
         */

        $pdo = new PDO(
            'mysql:host=' . DB_HOST .
            ';port=' . DB_PORT .
            ';dbname=' . DB_NAME .
            ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );

        /*
         * Make sure required tables exist.
         */

        init_database_tables($pdo);

        return $pdo;

    } catch (PDOException $e) {

        error_log(
            'Database connection error: ' .
            $e->getMessage()
        );

        http_response_code(500);

        if (
            strpos(
                $_SERVER['REQUEST_URI'] ?? '',
                '/api/'
            ) !== false
        ) {

            header(
                'Content-Type: application/json; charset=utf-8'
            );

            echo json_encode([
                'success' => false,
                'message' =>
                    'Database connection failed. Make sure MySQL is running in XAMPP.'
            ]);

        } else {

            echo '<h2>Database connection failed</h2>';

            echo '<p>Make sure MySQL is running in XAMPP.</p>';

            echo '<pre>' .
                htmlspecialchars($e->getMessage()) .
                '</pre>';
        }

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| ADD MISSING COLUMN
|--------------------------------------------------------------------------
*/

function ensure_column(
    PDO $pdo,
    string $table,
    string $column,
    string $definition
): void {

    $query = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?'
    );

    $query->execute([
        DB_NAME,
        $table,
        $column
    ]);

    if (!$query->fetch()) {

        $pdo->exec(
            "ALTER TABLE `$table`
             ADD COLUMN `$column` $definition"
        );
    }
}


/*
|--------------------------------------------------------------------------
| CREATE DATABASE TABLES
|--------------------------------------------------------------------------
*/

function init_database_tables(PDO $pdo): void
{

    /*
     * ADMINS
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admins (

            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            username VARCHAR(80) NOT NULL UNIQUE,

            password_hash VARCHAR(255) NOT NULL,

            role ENUM('admin','staff')
                NOT NULL DEFAULT 'staff',

            full_name VARCHAR(120)
                NOT NULL DEFAULT '',

            created_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4"
    );


    ensure_column(
        $pdo,
        'admins',
        'role',
        "ENUM('admin','staff')
         NOT NULL DEFAULT 'staff'"
    );

    ensure_column(
        $pdo,
        'admins',
        'full_name',
        "VARCHAR(120) NOT NULL DEFAULT ''"
    );

    ensure_column(
        $pdo,
        'admins',
        'updated_at',
        "TIMESTAMP NOT NULL
         DEFAULT CURRENT_TIMESTAMP
         ON UPDATE CURRENT_TIMESTAMP"
    );


    /*
     * COMPLAINTS
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS complaints (

            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            tracking_code VARCHAR(32)
                NOT NULL UNIQUE,

            student_name VARCHAR(120)
                NOT NULL,

            student_class VARCHAR(60)
                NOT NULL,

            section_name VARCHAR(60)
                NOT NULL DEFAULT '',

            email VARCHAR(254)
                NOT NULL DEFAULT '',

            category VARCHAR(100)
                NOT NULL,

            subject VARCHAR(180)
                NOT NULL,

            description TEXT
                NOT NULL,

            status ENUM(
                'Pending',
                'In Progress',
                'Resolved',
                'Rejected'
            )
                NOT NULL DEFAULT 'Pending',

            admin_note TEXT
                DEFAULT NULL,

            assigned_to INT UNSIGNED
                DEFAULT NULL,

            created_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_status(status),

            INDEX idx_created_at(created_at),

            INDEX idx_category(category)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4"
    );


    $pdo->exec(
        "ALTER TABLE complaints
         MODIFY COLUMN tracking_code
         VARCHAR(32) NOT NULL"
    );


    $pdo->exec(
        "ALTER TABLE complaints
         MODIFY COLUMN status
         ENUM(
             'Pending',
             'In Progress',
             'Resolved',
             'Rejected'
         )
         NOT NULL DEFAULT 'Pending'"
    );


    ensure_column(
        $pdo,
        'complaints',
        'email',
        "VARCHAR(254) NOT NULL DEFAULT ''"
    );


    ensure_column(
        $pdo,
        'complaints',
        'assigned_to',
        "INT UNSIGNED DEFAULT NULL"
    );


    /*
     * COMPLAINT LOGS
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS complaint_logs (

            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            complaint_id INT UNSIGNED NOT NULL,

            changed_by INT UNSIGNED
                DEFAULT NULL,

            old_status VARCHAR(50)
                NOT NULL,

            new_status VARCHAR(50)
                NOT NULL,

            note TEXT
                DEFAULT NULL,

            created_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4"
    );


    /*
     * EMAIL LOGS
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_logs (

            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            complaint_id INT UNSIGNED
                DEFAULT NULL,

            recipient_email VARCHAR(254)
                NOT NULL,

            tracking_code VARCHAR(32)
                NOT NULL,

            subject VARCHAR(255)
                NOT NULL,

            body_preview TEXT
                NOT NULL,

            delivery_status ENUM(
                'Sent',
                'Logged',
                'Failed'
            )
                NOT NULL DEFAULT 'Sent',

            created_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4"
    );


    /*
     * DEFAULT ADMIN
     */

    $query = $pdo->prepare(
        'SELECT id
         FROM admins
         WHERE username = ?
         LIMIT 1'
    );

    $query->execute(['admin']);

    if (!$query->fetch()) {

        $insert = $pdo->prepare(
            'INSERT INTO admins
             (username, password_hash, role, full_name)
             VALUES (?, ?, ?, ?)'
        );

        $insert->execute([
            'admin',
            password_hash(
                'ChangeMe123!',
                PASSWORD_DEFAULT
            ),
            'admin',
            'System Administrator'
        ]);
    }


    /*
     * DEFAULT STAFF
     */

    $query = $pdo->prepare(
        'SELECT id
         FROM admins
         WHERE username = ?
         LIMIT 1'
    );

    $query->execute(['staff']);

    if (!$query->fetch()) {

        $insert = $pdo->prepare(
            'INSERT INTO admins
             (username, password_hash, role, full_name)
             VALUES (?, ?, ?, ?)'
        );

        $insert->execute([
            'staff',
            password_hash(
                'Staff@12345',
                PASSWORD_DEFAULT
            ),
            'staff',
            'Complaint Officer'
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| SESSION / AUTHENTICATION
|--------------------------------------------------------------------------
*/

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {

        ini_set(
            'session.use_strict_mode',
            '1'
        );

        session_start();
    }
}


function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


function verify_csrf_token(
    ?string $token
): bool {

    start_secure_session();

    return
        !empty($_SESSION['csrf_token']) &&
        !empty($token) &&
        hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
}


function is_authenticated(): bool
{
    start_secure_session();

    return !empty($_SESSION['admin_id']);
}


function current_user(): ?array
{
    start_secure_session();

    if (!is_authenticated()) {
        return null;
    }

    return [
        'id' =>
            $_SESSION['admin_id'],

        'username' =>
            $_SESSION['admin_username'] ?? '',

        'role' =>
            $_SESSION['admin_role'] ?? 'staff',

        'full_name' =>
            $_SESSION['admin_full_name'] ?? ''
    ];
}


function require_auth(
    ?string $requiredRole = null
): void {

    start_secure_session();

    if (!is_authenticated()) {

        header('Location: admin.php');

        exit;
    }

    if (
        $requiredRole !== null &&
        ($_SESSION['admin_role'] ?? '') !==
        $requiredRole
    ) {

        http_response_code(403);

        exit('403 Forbidden');
    }
}


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function json_response(
    array $data,
    int $statusCode = 200
): void {

    http_response_code($statusCode);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function sanitize_string(
    ?string $str
): string {

    return trim((string)$str);
}


/*
|--------------------------------------------------------------------------
| EMAIL LOG
|--------------------------------------------------------------------------
*/

function write_email_file_log(
    string $message
): void {

    $logDir = APP_ROOT . '/logs';

    if (!is_dir($logDir)) {

        @mkdir(
            $logDir,
            0775,
            true
        );
    }

    @file_put_contents(
        $logDir . '/emails.log',
        '[' .
        date('Y-m-d H:i:s') .
        '] ' .
        $message .
        PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}


/*
|--------------------------------------------------------------------------
| SEND TRACKING EMAIL
|--------------------------------------------------------------------------
*/

function send_tracking_code_email(
    string $recipientEmail,
    string $studentName,
    string $trackingCode,
    string $complaintSubject,
    string $category,
    ?int $complaintId = null
): bool {

    write_email_file_log(
        'ATTEMPT | To: ' .
        $recipientEmail .
        ' | Code: ' .
        $trackingCode .
        ' | SMTP_ENABLED=' .
        (SMTP_ENABLED ? 'true' : 'false') .
        ' | SMTP_USER=' .
        SMTP_USER .
        ' | PASS_SET=' .
        (SMTP_PASS !== '' ? 'yes' : 'NO')
    );


    if (
        !filter_var(
            $recipientEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        write_email_file_log(
            'FAILED | Invalid recipient email'
        );

        return false;
    }


    if (SMTP_PASS === '') {

        write_email_file_log(
            'FAILED | SMTP_PASS is empty. config.local.php is missing or has no SMTP_PASS.'
        );

        return false;
    }


    $emailSubject =
        'Student Affairs - Tracking Code: ' .
        $trackingCode;


    $host =
        $_SERVER['HTTP_HOST'] ??
        'localhost';


    $script =
        str_replace(
            '\\',
            '/',
            $_SERVER['SCRIPT_NAME'] ??
            '/Student-Affairs/api/submit.php'
        );


    $base =
        rtrim(
            dirname(
                dirname($script)
            ),
            '/.'
        );


    $trackUrl =
        'http://' .
        $host .
        $base .
        '/index.html#track';


    $name =
        htmlspecialchars(
            $studentName,
            ENT_QUOTES,
            'UTF-8'
        );


    $subject =
        htmlspecialchars(
            $complaintSubject,
            ENT_QUOTES,
            'UTF-8'
        );


    $cat =
        htmlspecialchars(
            $category,
            ENT_QUOTES,
            'UTF-8'
        );


    $code =
        htmlspecialchars(
            $trackingCode,
            ENT_QUOTES,
            'UTF-8'
        );


    $body = <<<HTML
<!doctype html>
<html>
<body style="font-family:Arial,sans-serif;padding:30px">

<h2>Student Affairs Complaint Confirmation</h2>

<p>Dear <strong>{$name}</strong>,</p>

<p>
Your complaint has been registered successfully.
</p>

<div style="
padding:18px;
text-align:center;
background:#f5f8ff;
border:1px solid #dbe6fa;
">

<div>Your Tracking Code</div>

<strong style="font-size:22px">
{$code}
</strong>

</div>

<p>
<strong>Category:</strong>
{$cat}
</p>

<p>
<strong>Subject:</strong>
{$subject}
</p>

<p>
<strong>Status:</strong>
Pending
</p>

<p>
<a href="{$trackUrl}">
Track your complaint
</a>
</p>

</body>
</html>
HTML;


    $sent =
        send_smtp_socket_email(
            $recipientEmail,
            $emailSubject,
            $body
        );


    if ($sent) {

        write_email_file_log(
            'ACCEPTED | Gmail SMTP accepted message | To: ' .
            $recipientEmail .
            ' | Code: ' .
            $trackingCode
        );

    } else {

        write_email_file_log(
            'FAILED | Gmail SMTP rejected/failed message | To: ' .
            $recipientEmail .
            ' | Code: ' .
            $trackingCode
        );
    }


    /*
     * Save email result to database.
     */

    try {

        $pdo =
            get_db_connection();

        $query = $pdo->prepare(
            'INSERT INTO email_logs
            (
                complaint_id,
                recipient_email,
                tracking_code,
                subject,
                body_preview,
                delivery_status
            )
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $query->execute([
            $complaintId,
            $recipientEmail,
            $trackingCode,
            $emailSubject,
            'Tracking code ' .
                $trackingCode .
                ' for ' .
                $complaintSubject,
            $sent ? 'Sent' : 'Failed'
        ]);

    } catch (Throwable $e) {

        write_email_file_log(
            'EMAIL_DB_LOG_FAILED | ' .
            $e->getMessage()
        );
    }


    return $sent;
}


/*
|--------------------------------------------------------------------------
| SMTP SENDER
|--------------------------------------------------------------------------
*/

function send_smtp_socket_email(
    string $to,
    string $subject,
    string $htmlBody
): bool {

    $password =
        preg_replace(
            '/\s+/',
            '',
            SMTP_PASS
        );


    $errno = 0;
    $errstr = '';


    write_email_file_log(
        'SMTP_CONNECT | ' .
        SMTP_HOST .
        ':' .
        SMTP_PORT .
        ' | User: ' .
        SMTP_USER
    );


    $socket =
        @stream_socket_client(
            'tcp://' .
            SMTP_HOST .
            ':' .
            SMTP_PORT,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );


    if (!$socket) {

        write_email_file_log(
            'SMTP_CONNECT_FAILED | ' .
            $errstr .
            ' (' .
            $errno .
            ')'
        );

        return false;
    }


    stream_set_timeout(
        $socket,
        20
    );


    $read = function () use ($socket) {

        $data = '';

        while (!feof($socket)) {

            $line =
                fgets(
                    $socket,
                    515
                );

            if ($line === false) {
                break;
            }

            $data .= $line;

            if (
                strlen($line) < 4 ||
                $line[3] === ' '
            ) {
                break;
            }
        }

        return $data;
    };


    $send = function (
        string $command
    ) use ($socket): void {

        fwrite(
            $socket,
            $command .
            "\r\n"
        );
    };


    /*
     * Greeting
     */

    $response = $read();

    if (
        strpos(
            $response,
            '220'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_GREETING_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * EHLO
     */

    $client =
        preg_replace(
            '/[^A-Za-z0-9.-]/',
            '-',
            gethostname() ??
            'localhost'
        );


    $send(
        'EHLO ' .
        $client
    );


    $response =
        $read();


    if (
        strpos(
            $response,
            '250'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_EHLO_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * STARTTLS
     */

    $send('STARTTLS');

    $response =
        $read();


    if (
        strpos(
            $response,
            '220'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_STARTTLS_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    $crypto =
        @stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );


    if ($crypto !== true) {

        write_email_file_log(
            'SMTP_TLS_FAILED'
        );

        fclose($socket);

        return false;
    }


    /*
     * EHLO again after TLS
     */

    $send(
        'EHLO ' .
        $client
    );


    $response =
        $read();


    if (
        strpos(
            $response,
            '250'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_EHLO_TLS_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * Gmail authentication
     */

    $send('AUTH LOGIN');

    $response =
        $read();


    if (
        strpos(
            $response,
            '334'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_AUTH_START_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    $send(
        base64_encode(
            SMTP_USER
        )
    );


    $response =
        $read();


    if (
        strpos(
            $response,
            '334'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_USERNAME_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    $send(
        base64_encode(
            $password
        )
    );


    $response =
        $read();


    if (
        strpos(
            $response,
            '235'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_AUTH_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * MAIL FROM
     */

    $send(
        'MAIL FROM:<' .
        MAIL_FROM_EMAIL .
        '>'
    );


    $response =
        $read();


    if (
        strpos(
            $response,
            '250'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_MAIL_FROM_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * RCPT TO
     */

    $send(
        'RCPT TO:<' .
        $to .
        '>'
    );


    $response =
        $read();


    if (
        strpos($response, '250') !== 0 &&
        strpos($response, '251') !== 0
    ) {

        write_email_file_log(
            'SMTP_RCPT_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * DATA
     */

    $send('DATA');

    $response =
        $read();


    if (
        strpos(
            $response,
            '354'
        ) !== 0
    ) {

        write_email_file_log(
            'SMTP_DATA_FAILED | ' .
            trim($response)
        );

        fclose($socket);

        return false;
    }


    /*
     * Email headers
     */

    $headers = [

        'From: ' .
        MAIL_FROM_NAME .
        ' <' .
        MAIL_FROM_EMAIL .
        '>',

        'To: <' .
        $to .
        '>',

        'Subject: =?UTF-8?B?' .
        base64_encode($subject) .
        '?=',

        'MIME-Version: 1.0',

        'Content-Type: text/html; charset=UTF-8',

        'Content-Transfer-Encoding: 8bit',

        'Date: ' .
        date('r'),

        'Message-ID: <' .
        bin2hex(
            random_bytes(16)
        ) .
        '@gmail.com>'
    ];


    $emailData =
        implode(
            "\r\n",
            $headers
        ) .
        "\r\n\r\n" .
        $htmlBody;


    /*
     * SMTP dot-stuffing
     */

    $emailData =
        preg_replace(
            '/^\./m',
            '..',
            $emailData
        );


    fwrite(
        $socket,
        $emailData .
        "\r\n.\r\n"
    );


    $response =
        $read();


    $accepted =
        strpos(
            $response,
            '250'
        ) === 0;


    if ($accepted) {

        write_email_file_log(
            'SMTP_DATA_ACCEPTED'
        );

    } else {

        write_email_file_log(
            'SMTP_DATA_REJECTED | ' .
            trim($response)
        );
    }


    $send('QUIT');

    fclose($socket);


    return $accepted;
}