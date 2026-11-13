
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Please log in first.'
    ]);
    exit;
}

function respond($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $search = trim($_GET['search'] ?? '');

        if ($search !== '') {
            $keyword = '%' . $search . '%';

            $stmt = $pdo->prepare("
                SELECT id, student_number, first_name, last_name, section
                FROM students
                WHERE student_number LIKE ?
                   OR first_name LIKE ?
                   OR last_name LIKE ?
                   OR section LIKE ?
                ORDER BY last_name, first_name
            ");

            $stmt->execute([
                $keyword,
                $keyword,
                $keyword,
                $keyword
            ]);
        } else {
            $stmt = $pdo->query("
                SELECT id, student_number, first_name, last_name, section
                FROM students
                ORDER BY last_name, first_name
            ");
        }

        respond(true, 'Students retrieved successfully.', $stmt->fetchAll());
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            respond(false, 'Invalid JSON data.', null, 400);
        }

        $studentNumber = trim($input['student_number'] ?? '');
        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $section = trim($input['section'] ?? '');

        if (
            $studentNumber === '' ||
            $firstName === '' ||
            $lastName === '' ||
            $section === ''
        ) {
            respond(false, 'All student fields are required.', null, 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO students
                (student_number, first_name, last_name, section)
            VALUES (?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $studentNumber,
                $firstName,
                $lastName,
                $section
            ]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                respond(false, 'Student number already exists.', null, 409);
            }

            throw $ex;
        }

        respond(true, 'Student added successfully.', [
            'id' => (int) $pdo->lastInsertId(),
            'student_number' => $studentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'section' => $section
        ], 201);
    }

    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            respond(false, 'Invalid JSON data.', null, 400);
        }

        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $studentNumber = trim($input['student_number'] ?? '');
        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $section = trim($input['section'] ?? '');

        if (
            !$id || $id < 1 ||
            $studentNumber === '' ||
            $firstName === '' ||
            $lastName === '' ||
            $section === ''
        ) {
            respond(false, 'Valid student ID and all fields are required.', null, 422);
        }

        $stmt = $pdo->prepare("
            UPDATE students
            SET student_number = ?,
                first_name = ?,
                last_name = ?,
                section = ?
            WHERE id = ?
        ");

        try {
            $stmt->execute([
                $studentNumber,
                $firstName,
                $lastName,
                $section,
                $id
            ]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                respond(false, 'Student number already exists.', null, 409);
            }

            throw $ex;
        }

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM students WHERE id = ?');
            $check->execute([$id]);

            if (!$check->fetch()) {
                respond(false, 'Student not found.', null, 404);
            }
        }

        respond(true, 'Student updated successfully.', [
            'id' => $id,
            'student_number' => $studentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'section' => $section
        ]);
    }

    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            respond(false, 'Invalid JSON data.', null, 400);
        }

        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            respond(false, 'A valid student ID is required.', null, 422);
        }

        $stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            respond(false, 'Student not found.', null, 404);
        }

        respond(true, 'Student deleted successfully.');
    }

    header('Allow: GET, POST, PUT, DELETE');

    respond(false, 'Method not allowed.', null, 405);

} catch (Throwable $ex) {
    error_log($ex->getMessage());

    respond(false, 'A server error occurred.', null, 500);
}
