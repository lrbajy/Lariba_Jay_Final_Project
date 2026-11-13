
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

$today = date('Y-m-d');
$selectedDate = $_POST['attendance_date']
    ?? $_GET['date']
    ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $today;
}

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) {
    $selectedDate = $today;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'save') {

    $statuses = $_POST['status'] ?? [];

    try {
        $pdo->beginTransaction();

        $studentQuery = $pdo->query('SELECT id FROM students');
        $studentIds = $studentQuery->fetchAll(PDO::FETCH_COLUMN);

        $check = $pdo->prepare(
            'SELECT id FROM attendance
             WHERE student_id = ? AND attendance_date = ?'
        );

        $insert = $pdo->prepare(
            'INSERT INTO attendance
             (student_id, attendance_date, status, recorded_by)
             VALUES (?, ?, ?, ?)'
        );

        $update = $pdo->prepare(
            'UPDATE attendance
             SET status = ?, recorded_by = ?
             WHERE student_id = ? AND attendance_date = ?'
        );

        foreach ($studentIds as $studentId) {
            $studentId = (int) $studentId;

            if (!isset($statuses[$studentId])) {
                continue;
            }

            $status = $statuses[$studentId];

            if (!in_array($status, ['Present', 'Absent'], true)) {
                continue;
            }

            $check->execute([$studentId, $selectedDate]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                $update->execute([
                    $status,
                    (int) $_SESSION['user_id'],
                    $studentId,
                    $selectedDate
                ]);
            } else {
                $insert->execute([
                    $studentId,
                    $selectedDate,
                    $status,
                    (int) $_SESSION['user_id']
                ]);
            }
        }

        $pdo->commit();

        header(
            'Location: attendance.php?date=' .
            urlencode($selectedDate) . '&success=saved'
        );
        exit;

    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($ex->getMessage());
        $error = 'Unable to save attendance. Please try again.';
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'saved') {
    $success = 'Attendance saved successfully.';
}

$stmt = $pdo->prepare(
    'SELECT s.id, s.student_number, s.first_name, s.last_name,
            s.section, a.status
     FROM students s
     LEFT JOIN attendance a
       ON a.student_id = s.id
      AND a.attendance_date = ?
     ORDER BY s.section, s.last_name, s.first_name'
);

$stmt->execute([$selectedDate]);
$students = $stmt->fetchAll();

$totalStudents = count($students);
$presentCount = 0;
$absentCount = 0;
$unmarkedCount = 0;

foreach ($students as $student) {
    if ($student['status'] === 'Present') {
        $presentCount++;
    } elseif ($student['status'] === 'Absent') {
        $absentCount++;
    } else {
        $unmarkedCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Take Attendance | MateClass</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="shortcut icon" href="assets/images/logo-tab.png" type="image/x-icon">
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

            <a href="students.php" class="mc-nav-link">
                <i class="fa-solid fa-user-group"></i>
                <span>Students</span>
            </a>

            <a href="attendance.php" class="mc-nav-link active">
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
                    <div class="mc-breadcrumb">
                        MateClass / Take Attendance
                    </div>
                    <h1>Take Attendance</h1>
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
                        <i class="fa-solid fa-clipboard-check"></i>
                        CLASS ATTENDANCE
                    </span>

                    <h2>Record Attendance</h2>

                    <p>
                        Select a date and mark each student's attendance.
                        Your changes will be saved to the database.
                    </p>

                    <a href="records.php" class="mc-primary-btn">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        View Records
                    </a>
                </div>

                <div class="mc-welcome-art">
                    <div class="mc-art-circle">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <span class="mc-art-dot dot-one"></span>
                    <span class="mc-art-dot dot-two"></span>
                    <span class="mc-art-dot dot-three"></span>
                </div>
            </section>

            <?php if ($success !== ''): ?>
                <div class="mc-attendance-message mc-attendance-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="mc-attendance-message mc-attendance-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <div class="mc-section-heading">
                <div>
                    <h2>Attendance Overview</h2>
                    <p>Review the attendance status for your selected date.</p>
                </div>

                <div class="mc-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= e(date('F j, Y', strtotime($selectedDate))) ?>
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
                    <p><i class="fa-solid fa-graduation-cap"></i> Registered students</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Present</span>
                        <div class="mc-stat-icon icon-green">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                    <h3><?= number_format($presentCount) ?></h3>
                    <p><i class="fa-solid fa-circle-check"></i> Marked present</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Absent</span>
                        <div class="mc-stat-icon icon-red">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </div>
                    <h3><?= number_format($absentCount) ?></h3>
                    <p><i class="fa-solid fa-circle-exclamation"></i> Marked absent</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Unmarked</span>
                        <div class="mc-stat-icon icon-purple">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                    <h3><?= number_format($unmarkedCount) ?></h3>
                    <p><i class="fa-solid fa-clock"></i> Not yet recorded</p>
                </article>

            </section>

            <section class="mc-records">

                <div class="mc-records-header">
                    <div>
                        <h2>Student Attendance</h2>
                        <p>Choose a date and set each student's status.</p>
                    </div>
                </div>

                <form method="GET" action="attendance.php"
                      class="mc-attendance-date-form">

                    <label for="attendanceDate">
                        <i class="fa-regular fa-calendar"></i>
                        Attendance Date
                    </label>

                    <input type="date" id="attendanceDate" name="date"
                           value="<?= e($selectedDate) ?>" required>

                    <button type="submit" class="mc-attendance-secondary-btn">
                        <i class="fa-solid fa-filter"></i>
                        Load Date
                    </button>
                </form>

                <?php if ($totalStudents > 0): ?>

                    <form method="POST" action="attendance.php">

                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="attendance_date"
                               value="<?= e($selectedDate) ?>">

                        <div class="mc-attendance-toolbar">
                            <div>
                                <strong><?= $totalStudents ?> students</strong>
                                <span> · <?= e(date('M j, Y', strtotime($selectedDate))) ?></span>
                            </div>

                            <div class="mc-attendance-bulk">
                                <button type="button" id="markAllPresent">
                                    Mark All Present
                                </button>
                                <button type="button" id="markAllAbsent">
                                    Mark All Absent
                                </button>
                            </div>
                        </div>

                        <div class="mc-table-wrap">
                            <table class="mc-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Student Number</th>
                                        <th>Section</th>
                                        <th>Attendance Status</th>
                                    </tr>
                                </thead>

                                <tbody>
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
                                            <span class="mc-attendance-section">
                                                <?= e($student['section']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="mc-attendance-options">
                                                <label class="mc-choice mc-choice-present">
                                                    <input type="radio"
                                                           name="status[<?= (int) $student['id'] ?>]"
                                                           value="Present"
                                                           <?= $student['status'] === 'Present' ? 'checked' : '' ?>>
                                                    <span>
                                                        <i class="fa-solid fa-check"></i>
                                                        Present
                                                    </span>
                                                </label>

                                                <label class="mc-choice mc-choice-absent">
                                                    <input type="radio"
                                                           name="status[<?= (int) $student['id'] ?>]"
                                                           value="Absent"
                                                           <?= $student['status'] === 'Absent' ? 'checked' : '' ?>>
                                                    <span>
                                                        <i class="fa-solid fa-xmark"></i>
                                                        Absent
                                                    </span>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mc-attendance-save">
                            <p>
                                <i class="fa-solid fa-circle-info"></i>
                                Only selected statuses will be saved.
                            </p>

                            <button type="submit" class="mc-primary-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Attendance
                            </button>
                        </div>

                    </form>

                <?php else: ?>

                    <div class="mc-empty">
                        <div class="mc-empty-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>

                        <strong>No students registered yet</strong>

                        <p>Add students before recording attendance.</p>

                        <a href="students.php">Go to Students</a>
                    </div>

                <?php endif; ?>

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

document.getElementById('markAllPresent')?.addEventListener('click', function () {
    document.querySelectorAll(
        'input[type="radio"][value="Present"]'
    ).forEach(function (input) {
        input.checked = true;
    });
});

document.getElementById('markAllAbsent')?.addEventListener('click', function () {
    document.querySelectorAll(
        'input[type="radio"][value="Absent"]'
    ).forEach(function (input) {
        input.checked = true;
    });
});
</script>

</body>
</html>
