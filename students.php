
<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $student_number = trim($_POST['student_number'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $student_id = (int) ($_POST['student_id'] ?? 0);

        if ($student_number === '' || $first_name === '' ||
            $last_name === '' || $section === '') {
            $error = 'Please fill in all fields.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO students
                        (student_number, first_name, last_name, section)
                        VALUES (?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $student_number, $first_name,
                        $last_name, $section
                    ]);

                    header('Location: students.php?success=added');
                    exit;
                }

                if ($student_id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE students
                         SET student_number = ?, first_name = ?,
                             last_name = ?, section = ?
                         WHERE id = ?'
                    );

                    $stmt->execute([
                        $student_number, $first_name,
                        $last_name, $section, $student_id
                    ]);

                    header('Location: students.php?success=updated');
                    exit;
                }

                $error = 'Invalid student selected.';
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $error = 'Student number already exists.';
                } else {
                    $error = 'Unable to save student information.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $student_id = (int) ($_POST['student_id'] ?? 0);

        try {
            $stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
            $stmt->execute([$student_id]);

            header('Location: students.php?success=deleted');
            exit;
        } catch (PDOException $ex) {
            $error = 'Unable to delete this student.';
        }
    }
}

$messages = [
    'added' => 'Student added successfully.',
    'updated' => 'Student updated successfully.',
    'deleted' => 'Student deleted successfully.'
];

if (isset($_GET['success'])) {
    $success = $messages[$_GET['success']] ?? '';
}

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM students
         WHERE student_number LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
            OR section LIKE ?
         ORDER BY last_name, first_name'
    );

    $term = '%' . $search . '%';
    $stmt->execute([$term, $term, $term, $term]);
} else {
    $stmt = $pdo->query(
        'SELECT * FROM students ORDER BY last_name, first_name'
    );
}

$students = $stmt->fetchAll();

$totalStudents = (int) $pdo->query(
    'SELECT COUNT(*) FROM students'
)->fetchColumn();

$editStudent = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editStudent = $stmt->fetch() ?: null;
}

$formStudent = $editStudent ?: [
    'id' => '',
    'student_number' => '',
    'first_name' => '',
    'last_name' => '',
    'section' => ''
];

$editing = $editStudent !== null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students | MateClass</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/logo-tab.png" type="image/x-icon">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="mc-dashboard">

<div class="mc-layout">

    <aside class="mc-sidebar" id="sidebar">

        <a href="dashboard.php" class="mc-brand">
            <img src="assets/images/logo.png" alt="MateClass">
        </a>

        <div class="mc-sidebar-label">MAIN MENU</div>

        <nav class="mc-nav">
            <a href="dashboard.php" class="mc-nav-link">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="students.php" class="mc-nav-link active">
                <i class="fa-solid fa-user-group"></i>
                <span>Students</span>
            </a>

            <a href="attendance.php" class="mc-nav-link">
                <i class="fa-solid fa-clipboard-check"></i>
                <span>Take Attendance</span>
            </a>

            <a href="records.php" class="mc-nav-link">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Attendance Records</span>
            </a>

            <a href="reports.php" class="mc-nav-link">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reports</span>
            </a>
        </nav>

        <div class="mc-sidebar-bottom">
            <div class="mc-help-card">
                <i class="fa-solid fa-graduation-cap"></i>
                <strong>MateClass</strong>
                <p>Making class attendance easier.</p>
            </div>

            <a href="logout.php" class="mc-nav-link mc-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Log Out</span>
            </a>
        </div>

    </aside>

    <div class="mc-main">

        <header class="mc-topbar">
            <div class="mc-topbar-left">
                <button type="button" class="mc-menu-toggle"
                        id="menuToggle" aria-label="Toggle navigation"
                        aria-controls="sidebar" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <div class="mc-breadcrumb">MateClass / Students</div>
                    <h1>Students</h1>
                </div>
            </div>

            <div class="mc-user">
                <div class="mc-user-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="mc-user-info">
                    <strong><?= e($_SESSION['username']) ?></strong>
                    <span><?= e(ucfirst($_SESSION['role'])) ?></span>
                </div>
            </div>
        </header>

        <main class="mc-content">

            <section class="mc-welcome">
                <div class="mc-welcome-text">
                    <span class="mc-welcome-tag">
                        <i class="fa-solid fa-users"></i>
                        STUDENT MANAGEMENT
                    </span>

                    <h2>Manage Your Students</h2>

                    <p>
                        Keep your student information organized.
                        Add new students and update their records easily.
                    </p>

                    <a href="#studentForm" class="mc-primary-btn">
                        <i class="fa-solid fa-user-plus"></i>
                        Add New Student
                    </a>
                </div>

                <div class="mc-welcome-art">
                    <div class="mc-art-circle">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <span class="mc-art-dot dot-one"></span>
                    <span class="mc-art-dot dot-two"></span>
                    <span class="mc-art-dot dot-three"></span>
                </div>
            </section>

            <?php if ($success !== ''): ?>
                <div class="mc-student-message mc-student-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="mc-student-message mc-student-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <div class="mc-section-heading">
                <div>
                    <h2>Student Overview</h2>
                    <p>Summary of registered students.</p>
                </div>

                <div class="mc-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= date('F j, Y') ?>
                </div>
            </div>

            <section class="mc-stats">

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Total Students</span>
                        <div class="mc-stat-icon icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>

                    <h3><?= number_format($totalStudents) ?></h3>
                    <p>
                        <i class="fa-solid fa-graduation-cap"></i>
                        Registered students
                    </p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Search Results</span>
                        <div class="mc-stat-icon icon-green">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                    </div>

                    <h3><?= number_format(count($students)) ?></h3>
                    <p>
                        <i class="fa-solid fa-list"></i>
                        Students displayed
                    </p>
                </article>

            </section>

            <section class="mc-student-form-card" id="studentForm">

                <div class="mc-records-header">
                    <div>
                        <h2>
                            <?= $editing ? 'Edit Student' : 'Add New Student' ?>
                        </h2>
                        <p>
                            <?= $editing
                                ? 'Update the selected student information.'
                                : 'Enter the details of the student below.' ?>
                        </p>
                    </div>

                    <div class="mc-stat-icon icon-green">
                        <i class="fa-solid <?= $editing
                            ? 'fa-pen-to-square'
                            : 'fa-user-plus' ?>"></i>
                    </div>
                </div>

                <form method="POST" action="students.php" class="mc-student-form">

                    <input type="hidden" name="action"
                           value="<?= $editing ? 'edit' : 'add' ?>">

                    <?php if ($editing): ?>
                        <input type="hidden" name="student_id"
                               value="<?= (int) $formStudent['id'] ?>">
                    <?php endif; ?>

                    <div class="mc-student-fields">

                        <div class="mc-student-field">
                            <label for="student_number">Student Number</label>
                            <input type="text" id="student_number"
                                   name="student_number" maxlength="30"
                                   placeholder="Enter student number" required
                                   value="<?= e($formStudent['student_number']) ?>">
                        </div>

                        <div class="mc-student-field">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name"
                                   name="first_name" maxlength="100"
                                   placeholder="Enter first name" required
                                   value="<?= e($formStudent['first_name']) ?>">
                        </div>

                        <div class="mc-student-field">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name"
                                   name="last_name" maxlength="100"
                                   placeholder="Enter last name" required
                                   value="<?= e($formStudent['last_name']) ?>">
                        </div>

                        <div class="mc-student-field">
                            <label for="section">Section</label>
                            <input type="text" id="section"
                                   name="section" maxlength="100"
                                   placeholder="e.g. BSIT-3K" required
                                   value="<?= e($formStudent['section']) ?>">
                        </div>

                    </div>

                    <div class="mc-student-form-actions">
                        <button type="submit" class="mc-primary-btn">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <?= $editing ? 'Update Student' : 'Save Student' ?>
                        </button>

                        <?php if ($editing): ?>
                            <a href="students.php" class="mc-student-cancel">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </section>

            <section class="mc-records">

                <div class="mc-records-header">
                    <div>
                        <h2>Student List</h2>
                        <p>View, search, and manage registered students.</p>
                    </div>

                    <div class="mc-stat-icon icon-blue">
                        <i class="fa-solid fa-address-book"></i>
                    </div>
                </div>

                <form method="GET" action="students.php"
                      class="mc-student-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input type="search" name="search"
                           placeholder="Search name, student number, or section..."
                           value="<?= e($search) ?>">

                    <button type="submit">
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="students.php" class="mc-student-clear">
                            Clear
                        </a>
                    <?php endif; ?>

                </form>

                <div class="mc-table-wrap">
                    <table class="mc-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Section</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="mc-empty">
                                        <div class="mc-empty-icon">
                                            <i class="fa-solid fa-users"></i>
                                        </div>

                                        <strong>No students found</strong>

                                        <p>
                                            Add a new student or try another search.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="mc-student-name">
                                            <div class="mc-student-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>

                                            <strong>
                                                <?= e(
                                                    $student['first_name'] . ' ' .
                                                    $student['last_name']
                                                ) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td><?= e($student['student_number']) ?></td>

                                    <td>
                                        <span class="mc-section-tag">
                                            <?= e($student['section']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="mc-student-actions">
                                            <a href="students.php?edit=<?= (int) $student['id'] ?>#studentForm"
                                               class="mc-student-edit"
                                               title="Edit student"
                                               aria-label="Edit student">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>

                                            <form method="POST" action="students.php"
                                                  onsubmit="return confirm('Delete this student and their attendance records?');">

                                                <input type="hidden" name="action"
                                                       value="delete">

                                                <input type="hidden" name="student_id"
                                                       value="<?= (int) $student['id'] ?>">

                                                <button type="submit"
                                                        class="mc-student-delete"
                                                        title="Delete student"
                                                        aria-label="Delete student">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mc-student-list-footer">
                    Showing <?= count($students) ?> of
                    <?= number_format($totalStudents) ?> registered students
                </div>

            </section>

            <footer class="mc-footer">
                <span>&copy; <?= date('Y') ?> MateClass</span>
                <span>
                    Attendance made easier
                    <i class="fa-solid fa-heart"></i>
                </span>
            </footer>

        </main>
    </div>

</div>

<div class="mc-overlay" id="sidebarOverlay"></div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');

function closeSidebar() {
    sidebar.classList.remove('mc-sidebar-open');
    overlay.classList.remove('mc-overlay-visible');
    menuToggle.setAttribute('aria-expanded', 'false');
}

menuToggle.addEventListener('click', function () {
    const isOpen = sidebar.classList.toggle('mc-sidebar-open');

    overlay.classList.toggle('mc-overlay-visible', isOpen);
    menuToggle.setAttribute('aria-expanded', String(isOpen));
});

overlay.addEventListener('click', closeSidebar);

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeSidebar();
    }
});
</script>

</body>
</html>
