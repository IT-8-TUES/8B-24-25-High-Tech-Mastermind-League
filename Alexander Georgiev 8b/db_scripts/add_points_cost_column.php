<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$check_column = $conn->query("SHOW COLUMNS FROM redemptions LIKE 'points_cost'");
if ($check_column->num_rows > 0) {
    $_SESSION['message'] = "The 'points_cost' column already exists in the redemptions table.";
    $_SESSION['message_type'] = 'info';
    header("Location: admin.php");
    exit;
}

try {
    $conn->query("ALTER TABLE redemptions ADD COLUMN points_cost INT NOT NULL AFTER reward_id");
    
    $_SESSION['message'] = "The 'points_cost' column has been successfully added to the redemptions table.";
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['message'] = "Error adding 'points_cost' column: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header("Location: admin.php");
exit;
?>