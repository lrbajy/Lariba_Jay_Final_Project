
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

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

function validDate($value) {
    if (!is_string($value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value;
}

$error = '';

if (!validDate($startDate) || !validDate($endDate)) {
    $error = 'Please select valid dates.';
} elseif ($startDate > $endDate) {
    $error = 'The start date cannot be later than the end date.';
}

$summary = [
    'total_records' => 0,
    'present_count' => 0,
    'absent_count' => 0
];

$studentReports = [];

if ($error === '') {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_records,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance
        WHERE attendance_date BETWEEN ? AND ?
    ");

    $stmt->execute([$startDate, $endDate]);
    $summary = $stmt->fetch();

    $summary['total_records'] = (int) ($summary['total_records'] ?? 0);
    $summary['present_count'] = (int) ($summary['present_count'] ?? 0);
    $summary['absent_count'] = (int) ($summary['absent_count'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT
            s.student_number,
            s.first_name,
            s.last_name,
            s.section,
            COUNT(a.id) AS total_records,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM students s
        LEFT JOIN attendance a
            ON a.student_id = s.id
            AND a.attendance_date BETWEEN ? AND ?
        GROUP BY
            s.id, s.student_number, s.first_name,
            s.last_name, s.section
        ORDER BY s.section, s.last_name, s.first_name
    ");

    $stmt->execute([$startDate, $endDate]);
    $studentReports = $stmt->fetchAll();
}

$totalRecords = $summary['total_records'];
$presentCount = $summary['present_count'];
$absentCount = $summary['absent_count'];

$attendanceRate = $totalRecords > 0
    ? round(($presentCount / $totalRecords) * 100, 1)
    : 0;

/* Export the filtered student summary to CSV. */
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $error === '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mateclass-attendance-report.csv"');

    $output = fopen('php://output', 'w');

    // Make exported text safer for spreadsheet applications.
    $safeCsv = function ($value) {
        $value = (string) $value;

        if (preg_match('/^[\s]*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    };

    fputcsv($output, [
        'Student Number',
        'Student Name',
        'Section',
        'Total Records',
        'Present',
        'Absent',
        'Attendance Rate (%)'
    ]);

    foreach ($studentReports as $student) {
        $total = (int) $student['total_records'];
        $present = (int) $student['present_count'];
        $absent = (int) $student['absent_count'];

        $rate = $total > 0
            ? round(($present / $total) * 100, 1)
            : 0;

        fputcsv($output, [
            $safeCsv($student['student_number']),
            $safeCsv($student['first_name'] . ' ' . $student['last_name']),
            $safeCsv($student['section']),
            $total,
            $present,
            $absent,
            $rate
        ]);
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | MateClass</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/logo-tab.png" type="image/x-icon">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body class="mc-dashboard">

<div class="mc-layout">

    <aside class="mc-sidebar" id="sidebar">
        <a href="dashboard.php" class="mc-brand">
            <img src="logo.png" alt="MateClass">
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

            <a href="records.php" class="mc-nav-link">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Attendance Records</span>
            </a>

            <a href="reports.php" class="mc-nav-link active">
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
                    <div class="mc-breadcrumb">MateClass / Reports</div>
                    <h1>Attendance Reports</h1>
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
                        <i class="fa-solid fa-chart-line"></i>
                        ATTENDANCE ANALYTICS
                    </span>

                    <h2>Attendance Reports</h2>

                    <p>
                        Check student attendance summaries and review
                        attendance performance within a selected period.
                    </p>

                    <a href="records.php" class="mc-primary-btn">
                        <i class="fa-solid fa-calendar-check"></i>
                        View Records
                    </a>
                </div>

                <div class="mc-welcome-art">
                    <div class="mc-art-circle">
                        <i class="fa-solid fa-chart-pie"></i>
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
                    <h2>Report Overview</h2>
                    <p>Summary based on your selected date range.</p>
                </div>

                <div class="mc-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?= e(date('M j, Y', strtotime($startDate))) ?>
                    –
                    <?= e(date('M j, Y', strtotime($endDate))) ?>
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
                    <p>Recorded attendance entries</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Present</span>
                        <div class="mc-stat-icon icon-green">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                    <h3><?= number_format($presentCount) ?></h3>
                    <p>Present attendance entries</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Absent</span>
                        <div class="mc-stat-icon icon-red">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </div>
                    <h3><?= number_format($absentCount) ?></h3>
                    <p>Absent attendance entries</p>
                </article>

                <article class="mc-stat-card">
                    <div class="mc-stat-top">
                        <span>Attendance Rate</span>
                        <div class="mc-stat-icon icon-purple">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                    </div>
                    <h3><?= number_format($attendanceRate, 1) ?>%</h3>
                    <p>Present out of recorded entries</p>
                </article>

            </section>

            <section class="mc-records">

                <div class="mc-records-header">
                    <div>
                        <h2>Filter Report</h2>
                        <p>Select the period you want to review.</p>
                    </div>
                </div>

                <form method="GET" action="reports.php"
                      class="mc-record-filter">

                    <div class="mc-record-filter-field">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date"
                               value="<?= e($startDate) ?>" required>
                    </div>

                    <div class="mc-record-filter-field">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date"
                               value="<?= e($endDate) ?>" required>
                    </div>

                    <button type="submit" class="mc-attendance-secondary-btn">
                        <i class="fa-solid fa-filter"></i>
                        Generate Report
                    </button>

                    <?php if ($error === ''): ?>
                        <a class="mc-report-export"
                           href="reports.php?start_date=<?= urlencode($startDate) ?>&amp;end_date=<?= urlencode($endDate) ?>&amp;export=csv">
                            <i class="fa-solid fa-file-csv"></i>
                            Export CSV
                        </a>

                        
                        <a href="export/attendance_xml.php?date=<?= urlencode($startDate) ?>"
                        class="mc-report-export">
                            <i class="fa-solid fa-file-code"></i>
                            Export XML
                        </a>

                    <?php endif; ?>

                </form>

                <div class="mc-records-header mc-report-table-heading">
                    <div>
                        <h2>Student Attendance Summary</h2>
                        <p>Attendance totals for each registered student.</p>
                    </div>
                </div>

                <div class="mc-table-wrap">
                    <table class="mc-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Section</th>
                                <th>Total Records</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($studentReports): ?>

                            <?php foreach ($studentReports as $student): ?>
                                <?php
                                $total = (int) $student['total_records'];
                                $present = (int) $student['present_count'];
                                $absent = (int) $student['absent_count'];

                                $rate = $total > 0
                                    ? round(($present / $total) * 100, 1)
                                    : 0;
                                ?>

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

                                    <td><?= number_format($total) ?></td>
                                    <td><?= number_format($present) ?></td>
                                    <td><?= number_format($absent) ?></td>

                                    <td>
                                        <div class="mc-report-rate">
                                            <div class="mc-report-rate-bar">
                                                <span style="width: <?= $rate ?>%"></span>
                                            </div>
                                            <strong><?= number_format($rate, 1) ?>%</strong>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="mc-empty">
                                        <div class="mc-empty-icon">
                                            <i class="fa-solid fa-chart-column"></i>
                                        </div>

                                        <strong>No student data found</strong>

                                        <p>
                                            Register students and record attendance
                                            to generate a report.
                                        </p>
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
