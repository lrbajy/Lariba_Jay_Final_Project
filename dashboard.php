
<?php
session_start();

require_once 'config/database.php';

// Prevent the dashboard from being cached by the browser
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get total students
$totalStudents = $pdo->query(
    "SELECT COUNT(*) FROM students"
)->fetchColumn();

$today = date('Y-m-d');

// Get students marked present today
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM attendance
     WHERE attendance_date = ? AND status = 'Present'"
);
$stmt->execute([$today]);
$presentToday = $stmt->fetchColumn();

// Get students marked absent today
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM attendance
     WHERE attendance_date = ? AND status = 'Absent'"
);
$stmt->execute([$today]);
$absentToday = $stmt->fetchColumn();

// Get total attendance records today
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM attendance
     WHERE attendance_date = ?"
);
$stmt->execute([$today]);
$recordedToday = $stmt->fetchColumn();

// Get the latest five attendance records
$recentRecords = $pdo->query(
    "SELECT s.student_number, s.first_name, s.last_name,
            a.attendance_date, a.status
     FROM attendance a
     JOIN students s ON s.id = a.student_id
     ORDER BY a.attendance_date DESC, a.id DESC
     LIMIT 5"
)->fetchAll();

// Escape output for security
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | MateClass</title>

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
            <a href="dashboard.php" class="mc-nav-link active">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="students.php" class="mc-nav-link">
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
                    <div class="mc-breadcrumb">MateClass / Dashboard</div>
                    <h1>Dashboard</h1>
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
                        <i class="fa-solid fa-sun"></i>
                        YOUR ATTENDANCE OVERVIEW
                    </span>

                    <h2>Hello, <?= e($_SESSION['username']) ?>!</h2>

                    <p>
                        Welcome back to MateClass. Here's what's happening
                        with your class attendance today.
                    </p>

                    <a href="attendance.php" class="mc-primary-btn">
                        <i class="fa-solid fa-plus"></i>
                        Take Attendance
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

            <div class="mc-section-heading">
                <div>
                    <h2>Attendance Overview</h2>
                    <p>Here's your summary for today.</p>
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

                    <h3><?= number_format((int) $totalStudents) ?></h3>
                    <p><i class="fa-solid fa-graduation-cap"></i> Registered students</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Present Today</span>
                        <div class="mc-stat-icon icon-green">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>

                    <h3><?= number_format((int) $presentToday) ?></h3>
                    <p><i class="fa-solid fa-circle-check"></i> Marked present</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Absent Today</span>
                        <div class="mc-stat-icon icon-red">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </div>

                    <h3><?= number_format((int) $absentToday) ?></h3>
                    <p><i class="fa-solid fa-circle-exclamation"></i> Marked absent</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Records Today</span>
                        <div class="mc-stat-icon icon-purple">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>

                    <h3><?= number_format((int) $recordedToday) ?></h3>
                    <p><i class="fa-solid fa-database"></i> Attendance entries</p>
                </article>

            </section>

            <section class="mc-records">

                <div class="mc-records-header">
                    <div>
                        <h2>Recent Attendance</h2>
                        <p>Latest attendance entries in the system.</p>
                    </div>

                    <a href="records.php" class="mc-view-link">
                        View All <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="mc-table-wrap">
                    <table class="mc-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($recentRecords)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="mc-empty">
                                        <div class="mc-empty-icon">
                                            <i class="fa-regular fa-clipboard"></i>
                                        </div>
                                        <strong>No attendance records yet</strong>
                                        <p>Records will appear here after attendance is saved.</p>
                                        <a href="attendance.php">Take attendance</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentRecords as $record): ?>
                                <tr>
                                    <td>
                                        <div class="mc-student-name">
                                            <div class="mc-student-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <strong>
                                                <?= e($record['first_name'] . ' ' . $record['last_name']) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td><?= e($record['student_number']) ?></td>

                                    <td><?= e(date('M j, Y', strtotime($record['attendance_date']))) ?></td>

                                    <td>
                                        <span class="mc-status <?= strtolower(e($record['status'])) ?>">
                                            <i class="fa-solid <?= $record['status'] === 'Present' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                            <?= e($record['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>

            <footer class="mc-footer">
                <span>&copy; <?= date('Y') ?> MateClass</span>
                <span>Attendance made easier <i class="fa-solid fa-heart"></i></span>
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
