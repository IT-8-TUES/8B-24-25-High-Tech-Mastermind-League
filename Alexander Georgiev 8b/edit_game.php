<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['game_id'])) {
    $game_id = intval($_POST['game_id']);
    $game_name = trim($_POST['game_name']);
    
    if (empty($game_name)) {
        $_SESSION['message'] = "Game name is required.";
        $_SESSION['message_type'] = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM games WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $game_name, $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = "A game with this name already exists.";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE games SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $game_name, $game_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Game updated successfully!";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error updating game: " . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
        }
    }
    
    header("Location: admin.php");
    exit;
}

header("Location: admin.php");
exit;
?>