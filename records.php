
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

$search = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$error = '';

foreach (['start_date' => $startDate, 'end_date' => $endDate] as $field => $value) {
    if ($value !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            $error = 'Please enter a valid date.';
            break;
        }
    }
}

if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    $error = 'The start date cannot be later than the end date.';
}

$sql = "
    SELECT
        a.id,
        a.attendance_date,
        a.status,
        a.created_at,
        s.student_number,
        s.first_name,
        s.last_name,
        s.section,
        u.username AS recorded_by
    FROM attendance a
    INNER JOIN students s ON a.student_id = s.id
    INNER JOIN users u ON a.recorded_by = u.id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            s.student_number LIKE ?
            OR s.first_name LIKE ?
            OR s.last_name LIKE ?
            OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
            OR s.section LIKE ?
        )
    ";

    $keyword = '%' . $search . '%';

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

if ($startDate !== '') {
    $sql .= " AND a.attendance_date >= ?";
    $params[] = $startDate;
}

if ($endDate !== '') {
    $sql .= " AND a.attendance_date <= ?";
    $params[] = $endDate;
}

$sql .= " ORDER BY a.attendance_date DESC, s.last_name ASC, s.first_name ASC";

$records = [];

if ($error === '') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
}

$totalRecords = count($records);
$presentCount = 0;
$absentCount = 0;

foreach ($records as $record) {
    if ($record['status'] === 'Present') {
        $presentCount++;
    } elseif ($record['status'] === 'Absent') {
        $absentCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance Records | MateClass</title>
    <link rel="shortcut icon" href="assets/images/logo-tab.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/style.css">
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

            <a href="students.php" class="mc-nav-link">
                <i class="fa-solid fa-user-group"></i>
                <span>Students</span>
            </a>

            <a href="attendance.php" class="mc-nav-link">
                <i class="fa-solid fa-clipboard-check"></i>
                <span>Take Attendance</span>
            </a>

            <a href="records.php" class="mc-nav-link active">
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
                        MateClass / Attendance Records
                    </div>
                    <h1>Attendance Records</h1>
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
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        ATTENDANCE HISTORY
                    </span>

                    <h2>Attendance Records</h2>

                    <p>
                        View and search saved student attendance records
                        using student details and attendance dates.
                    </p>

                    <a href="attendance.php" class="mc-primary-btn">
                        <i class="fa-solid fa-plus"></i>
                        Take Attendance
                    </a>
                </div>

                <div class="mc-welcome-art">
                    <div class="mc-art-circle">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <span class="mc-art-dot dot-one"></span>
                    <span class="mc-art-dot dot-two"></span>
                    <span class="mc-art-dot dot-three"></span>
                </div>
            </section>

            <?php if ($error !== ''): ?>
                <div class="mc-attendance-message mc-attendance-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <div class="mc-section-heading">
                <div>
                    <h2>Records Overview</h2>
                    <p>Summary of the records matching your filters.</p>
                </div>

                <div class="mc-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= e(date('F j, Y')) ?>
                </div>
            </div>

            <section class="mc-stats">

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Total Records</span>
                        <div class="mc-stat-icon icon-blue">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                    <h3><?= number_format($totalRecords) ?></h3>
                    <p>Matching attendance records</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Present</span>
                        <div class="mc-stat-icon icon-green">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                    <h3><?= number_format($presentCount) ?></h3>
                    <p>Students marked present</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Absent</span>
                        <div class="mc-stat-icon icon-red">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </div>
                    <h3><?= number_format($absentCount) ?></h3>
                    <p>Students marked absent</p>
                </article>

            </section>

            <section class="mc-records">

                <div class="mc-records-header">
                    <div>
                        <h2>All Attendance Records</h2>
                        <p>Search student information or filter by date.</p>
                    </div>
                </div>

                <form method="GET" action="records.php"
                      class="mc-record-filter">

                    <div class="mc-record-filter-field mc-record-search">
                        <label for="search">Search Student</label>
                        <div class="mc-record-search-input">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="search" name="search"
                                   placeholder="Name, number, or section"
                                   value="<?= e($search) ?>">
                        </div>
                    </div>

                    <div class="mc-record-filter-field">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date"
                               value="<?= e($startDate) ?>">
                    </div>

                    <div class="mc-record-filter-field">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date"
                               value="<?= e($endDate) ?>">
                    </div>

                    <button type="submit" class="mc-attendance-secondary-btn">
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <a href="records.php" class="mc-record-clear">
                        Clear
                    </a>

                </form>

                <div class="mc-record-result-count">
                    Showing <strong><?= number_format($totalRecords) ?></strong>
                    record(s)
                </div>

                <div class="mc-table-wrap">
                    <table class="mc-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Section</th>
                                <th>Attendance Date</th>
                                <th>Status</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($records): ?>

                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <div class="mc-student-name">
                                            <div class="mc-student-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>

                                            <strong>
                                                <?= e(
                                                    $record['first_name'] . ' ' .
                                                    $record['last_name']
                                                ) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td><?= e($record['student_number']) ?></td>

                                    <td>
                                        <span class="mc-attendance-section">
                                            <?= e($record['section']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e(date(
                                            'M j, Y',
                                            strtotime($record['attendance_date'])
                                        )) ?>
                                    </td>

                                    <td>
                                        <span class="mc-status <?= $record['status'] === 'Present' ? 'status-present' : 'status-absent' ?>">
                                            <i class="fa-solid <?= $record['status'] === 'Present' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                            <?= e($record['status']) ?>
                                        </span>
                                    </td>

                                    <td><?= e($record['recorded_by']) ?></td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="mc-empty">
                                        <div class="mc-empty-icon">
                                            <i class="fa-solid fa-folder-open"></i>
                                        </div>

                                        <strong>No attendance records found</strong>

                                        <p>
                                            Try changing your search or date filters,
                                            or record attendance first.
                                        </p>

                                        <a href="attendance.php">
                                            Take Attendance
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>

            <footer class="mc-footer">
                <span>&copy; <?= date('Y') ?> MateClass</span>
                <span>Attendance made easier</span>
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
