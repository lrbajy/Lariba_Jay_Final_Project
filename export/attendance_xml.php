
<?php
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Please log in first.');
}

$date = $_GET['date'] ?? date('Y-m-d');

$checkDate = DateTime::createFromFormat('!Y-m-d', $date);

if (!$checkDate || $checkDate->format('Y-m-d') !== $date) {
    http_response_code(400);
    exit('Invalid attendance date.');
}

$stmt = $pdo->prepare(
    "SELECT
        s.student_number,
        s.first_name,
        s.last_name,
        s.section,
        a.attendance_date,
        a.status
     FROM attendance a
     INNER JOIN students s ON a.student_id = s.id
     WHERE a.attendance_date = ?
     ORDER BY s.last_name, s.first_name"
);

$stmt->execute([$date]);
$records = $stmt->fetchAll();

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$root = $xml->createElement('attendance_records');
$root->setAttribute('date', $date);
$xml->appendChild($root);

foreach ($records as $record) {
    $student = $xml->createElement('student');

    foreach ($record as $key => $value) {
        $element = $xml->createElement($key);
        $element->appendChild(
            $xml->createTextNode((string) $value)
        );

        $student->appendChild($element);
    }

    $root->appendChild($student);
}

header('Content-Type: application/xml; charset=utf-8');
header(
    'Content-Disposition: attachment; filename="attendance_' .
    $date . '.xml"'
);

echo $xml->saveXML();
exit;
