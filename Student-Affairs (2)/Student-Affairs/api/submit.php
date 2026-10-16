<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');


/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    json_response([
        'success' => false,
        'message' =>
            'Method not allowed. Only POST requests are accepted.'
    ], 405);
}


/*
|--------------------------------------------------------------------------
| READ INPUT
|--------------------------------------------------------------------------
*/

$inputData = [];

$contentType =
    $_SERVER['CONTENT_TYPE'] ?? '';


if (
    strpos(
        $contentType,
        'application/json'
    ) !== false
) {

    $rawBody =
        file_get_contents(
            'php://input'
        );

    $inputData =
        json_decode(
            $rawBody,
            true
        ) ?? [];

} else {

    $inputData =
        $_POST;
}


/*
|--------------------------------------------------------------------------
| INPUT FIELDS
|--------------------------------------------------------------------------
*/

$name =
    trim(
        (string)(
            $inputData['name'] ?? ''
        )
    );


$studentClass =
    trim(
        (string)(
            $inputData['studentId']
            ??
            $inputData['student_class']
            ??
            ''
        )
    );


$section =
    trim(
        (string)(
            $inputData['section'] ?? ''
        )
    );


$email =
    trim(
        (string)(
            $inputData['email'] ?? ''
        )
    );


$category =
    trim(
        (string)(
            $inputData['category'] ?? ''
        )
    );


$subject =
    trim(
        (string)(
            $inputData['subject'] ?? ''
        )
    );


$description =
    trim(
        (string)(
            $inputData['description'] ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

$errors = [];


if (
    $name === '' ||
    strlen($name) > 120
) {

    $errors[] =
        'Full name is required and must not exceed 120 characters.';
}


if (
    $studentClass === '' ||
    strlen($studentClass) > 60
) {

    $errors[] =
        'Class / Student ID is required and must not exceed 60 characters.';
}


if (
    strlen($section) > 60
) {

    $errors[] =
        'Section must not exceed 60 characters.';
}


if (
    $email === '' ||
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) ||
    strlen($email) > 254
) {

    $errors[] =
        'A valid email address is required.';
}


$allowedCategories = [

    'Facilities & Classrooms',

    'Cleanliness & Washrooms',

    'Teaching & Academics',

    'Administration',

    'Transport',

    'IT & Equipment',

    'Safety or Harassment',

    'Suggestion',

    'Other'
];


if (
    $category === '' ||
    !in_array(
        $category,
        $allowedCategories,
        true
    )
) {

    $errors[] =
        'Please select a valid complaint category.';
}


if (
    $subject === '' ||
    strlen($subject) > 180
) {

    $errors[] =
        'Subject is required and must not exceed 180 characters.';
}


$descriptionLength =
    strlen($description);


if (
    $descriptionLength < 10 ||
    $descriptionLength > 3000
) {

    $errors[] =
        'Description must be between 10 and 3,000 characters.';
}


if (!empty($errors)) {

    json_response([
        'success' => false,
        'message' =>
            implode(
                ' ',
                $errors
            ),
        'errors' => $errors
    ], 400);
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$pdo =
    get_db_connection();


/*
|--------------------------------------------------------------------------
| TRACKING CODE
|--------------------------------------------------------------------------
*/

function generate_unique_code(
    PDO $pdo
): string {

    for (
        $attempt = 0;
        $attempt < 10;
        $attempt++
    ) {

        $code =
            'SA-' .
            strtoupper(
                bin2hex(
                    random_bytes(8)
                )
            );


        $query =
            $pdo->prepare(
                'SELECT id
                 FROM complaints
                 WHERE tracking_code = ?
                 LIMIT 1'
            );


        $query->execute([
            $code
        ]);


        if (!$query->fetch()) {

            return $code;
        }
    }


    return
        'SA-' .
        strtoupper(
            bin2hex(
                random_bytes(8)
            )
        );
}


$trackingCode =
    generate_unique_code(
        $pdo
    );


/*
|--------------------------------------------------------------------------
| SAVE COMPLAINT
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    $query =
        $pdo->prepare(
            '
            INSERT INTO complaints
            (
                tracking_code,
                student_name,
                student_class,
                section_name,
                email,
                category,
                subject,
                description,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            '
        );


    $query->execute([

        $trackingCode,

        $name,

        $studentClass,

        $section,

        $email,

        $category,

        $subject,

        $description,

        'Pending'
    ]);


    $complaintId =
        (int)$pdo->lastInsertId();


    /*
     * Initial complaint history
     */

    $logQuery =
        $pdo->prepare(
            '
            INSERT INTO complaint_logs
            (
                complaint_id,
                changed_by,
                old_status,
                new_status,
                note
            )
            VALUES (?, NULL, ?, ?, ?)
            '
        );


    $logQuery->execute([

        $complaintId,

        'None',

        'Pending',

        'Complaint submitted by student.'
    ]);


    $pdo->commit();


    /*
     |--------------------------------------------------------------------------
     | SEND EMAIL
     |--------------------------------------------------------------------------
     */

    write_email_file_log(
        'SUBMIT API | Calling tracking email function | ' .
        'Complaint ID: ' .
        $complaintId .
        ' | Code: ' .
        $trackingCode .
        ' | To: ' .
        $email
    );


    $emailSent =
        send_tracking_code_email(

            $email,

            $name,

            $trackingCode,

            $subject,

            $category,

            $complaintId
        );


    write_email_file_log(

        $emailSent

        ? (
            'SUBMIT API | Email function returned TRUE | Code: ' .
            $trackingCode
        )

        : (
            'SUBMIT API | Email function returned FALSE | Code: ' .
            $trackingCode
        )
    );


    /*
     |--------------------------------------------------------------------------
     | RESPONSE
     |--------------------------------------------------------------------------
     */

    json_response([

        'success' => true,

        'message' =>

            $emailSent

            ? (
                'Complaint submitted successfully! ' .
                'Your tracking code was sent to ' .
                $email .
                '.'
            )

            : (
                'Complaint submitted successfully, but the tracking email ' .
                'could not be sent. Check logs/emails.log.'
            ),

        'tracking_code' =>
            $trackingCode,

        'email_sent' =>
            $emailSent,

        'recipient_email' =>
            $email,

        'data' => [

            'tracking_code' =>
                $trackingCode,

            'subject' =>
                $subject,

            'category' =>
                $category,

            'status' =>
                'Pending',

            'created_at' =>
                date(
                    'Y-m-d H:i:s'
                )
        ]

    ], 201);


} catch (Throwable $e) {


    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    error_log(
        'Complaint insertion error: ' .
        $e->getMessage()
    );


    write_email_file_log(
        'SUBMIT API ERROR | ' .
        $e->getMessage()
    );


    json_response([

        'success' => false,

        'message' =>
            'Failed to save complaint due to a server error. Please try again.'

    ], 500);
}