
<?php
session_start();

$_SESSION = [];
session_destroy();

session_start();
$_SESSION['logged_out'] = true;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

header('Location: index.php');
exit;
