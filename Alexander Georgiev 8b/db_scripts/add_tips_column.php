<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$check_column = $conn->query("SHOW COLUMNS FROM challenges LIKE 'tips'");
if ($check_column->num_rows > 0) {
    $_SESSION['message'] = "The 'tips' column already exists in the challenges table.";
    $_SESSION['message_type'] = 'info';
    header("Location: admin.php");
    exit;
}

try {
    $conn->query("ALTER TABLE challenges ADD COLUMN tips TEXT AFTER points");
    
    $_SESSION['message'] = "The 'tips' column has been successfully added to the challenges table.";
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['message'] = "Error adding 'tips' column: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header("Location: admin.php");
exit;
?>