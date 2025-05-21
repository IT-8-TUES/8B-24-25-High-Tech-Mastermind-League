<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['game_id'])) {
    $game_id = intval($_POST['game_id']);
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("SELECT id FROM challenges WHERE game_id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $challenge_ids = [];
        
        while ($row = $result->fetch_assoc()) {
            $challenge_ids[] = $row['id'];
        }
        
        if (!empty($challenge_ids)) {
            $placeholders = implode(',', array_fill(0, count($challenge_ids), '?'));
            $stmt = $conn->prepare("DELETE FROM submissions WHERE challenge_id IN ($placeholders)");
            $types = str_repeat('i', count($challenge_ids));
            $stmt->bind_param($types, ...$challenge_ids);
            $stmt->execute();
        }
        
        $stmt = $conn->prepare("DELETE FROM challenges WHERE game_id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        
        $conn->commit();
        
        $_SESSION['message'] = "Game and all related challenges deleted successfully!";
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Error deleting game: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header("Location: admin.php");
    exit;
} else {
    header("Location: admin.php");
    exit;
}
?>