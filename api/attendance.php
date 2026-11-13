
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond(401, [
        'success' => false,
        'message' => 'Please log in first.'
    ]);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $studentId = $_GET['student_id'] ?? null;

        $checkDate = DateTime::createFromFormat('!Y-m-d', $date);

        if (!$checkDate || $checkDate->format('Y-m-d') !== $date) {
            respond(400, [
                'success' => false,
                'message' => 'Invalid attendance date.'
            ]);
        }

        $sql = "SELECT
                    a.id,
                    s.student_number,
                    s.first_name,
                    s.last_name,
                    s.section,
                    a.attendance_date,
                    a.status,
                    u.username AS recorded_by
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.id
                INNER JOIN users u ON a.recorded_by = u.id
                WHERE a.attendance_date = ?";

        $params = [$date];

        if ($studentId !== null) {
            if (!filter_var($studentId, FILTER_VALIDATE_INT)
                || (int) $studentId < 1) {
                respond(400, [
                    'success' => false,
                    'message' => 'Invalid student ID.'
                ]);
            }

            $sql .= " AND a.student_id = ?";
            $params[] = (int) $studentId;
        }

        $sql .= " ORDER BY s.last_name, s.first_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        respond(200, [
            'success' => true,
            'data' => $stmt->fetchAll()
        ]);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            respond(400, [
                'success' => false,
                'message' => 'Invalid JSON data.'
            ]);
        }

        $studentId = filter_var(
            $data['student_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $date = $data['attendance_date'] ?? '';
        $status = $data['status'] ?? '';

        $checkDate = DateTime::createFromFormat('!Y-m-d', $date);

        if (!$studentId || $studentId < 1) {
            respond(400, [
                'success' => false,
                'message' => 'A valid student ID is required.'
            ]);
        }

        if (!$checkDate || $checkDate->format('Y-m-d') !== $date) {
            respond(400, [
                'success' => false,
                'message' => 'A valid attendance date is required.'
            ]);
        }

        if (!in_array($status, ['Present', 'Absent'], true)) {
            respond(400, [
                'success' => false,
                'message' => 'Status must be Present or Absent.'
            ]);
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM students WHERE id = ?"
        );
        $stmt->execute([$studentId]);

        if (!$stmt->fetch()) {
            respond(404, [
                'success' => false,
                'message' => 'Student not found.'
            ]);
        }

        if ($method === 'POST') {
            $stmt = $pdo->prepare(
                "INSERT INTO attendance
                    (student_id, attendance_date, status, recorded_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    recorded_by = VALUES(recorded_by)"
            );

            $stmt->execute([
                $studentId,
                $date,
                $status,
                $_SESSION['user_id']
            ]);

            respond(201, [
                'success' => true,
                'message' => 'Attendance saved successfully.'
            ]);
        }

        $attendanceId = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$attendanceId || $attendanceId < 1) {
            respond(400, [
                'success' => false,
                'message' => 'A valid attendance ID is required for PUT.'
            ]);
        }

        $stmt = $pdo->prepare(
            "UPDATE attendance
             SET student_id = ?,
                 attendance_date = ?,
                 status = ?,
                 recorded_by = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $studentId,
            $date,
            $status,
            $_SESSION['user_id'],
            $attendanceId
        ]);

        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare(
                "SELECT id FROM attendance WHERE id = ?"
            );
            $stmt->execute([$attendanceId]);

            if (!$stmt->fetch()) {
                respond(404, [
                    'success' => false,
                    'message' => 'Attendance record not found.'
                ]);
            }
        }

        respond(200, [
            'success' => true,
            'message' => 'Attendance updated successfully.'
        ]);
    }

    if ($method === 'DELETE') {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            respond(400, [
                'success' => false,
                'message' => 'A valid attendance ID is required.'
            ]);
        }

        $stmt = $pdo->prepare(
            "DELETE FROM attendance WHERE id = ?"
        );
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            respond(404, [
                'success' => false,
                'message' => 'Attendance record not found.'
            ]);
        }

        respond(200, [
            'success' => true,
            'message' => 'Attendance deleted successfully.'
        ]);
    }

    header('Allow: GET, POST, PUT, DELETE');

    respond(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === '23000') {
        respond(409, [
            'success' => false,
            'message' => 'Attendance data conflicts with an existing record.'
        ]);
    }

    respond(500, [
        'success' => false,
        'message' => 'A database error occurred.'
    ]);
}
