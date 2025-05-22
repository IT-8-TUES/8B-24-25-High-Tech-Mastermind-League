<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (isset($_GET['id'])) {
    $challenge_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT * FROM challenges WHERE id = ?");
    $stmt->bind_param("i", $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $challenge = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($challenge);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Challenge not found']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No challenge ID provided']);
}
?>