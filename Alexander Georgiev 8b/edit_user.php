<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $player_rank = $_POST['player_rank'];
    $score = intval($_POST['score']);
    
    if (empty($username) || empty($email) || empty($player_rank) || $score < 0) {
        $_SESSION['message'] = "All fields are required and score must be non-negative.";
        $_SESSION['message_type'] = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = "Username already exists.";
            $_SESSION['message_type'] = 'error';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Email already exists.";
                $_SESSION['message_type'] = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, player_rank = ?, score = ? WHERE id = ?");
                $stmt->bind_param("sssii", $username, $email, $player_rank, $score, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "User updated successfully!";
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = "Error updating user: " . $stmt->error;
                    $_SESSION['message_type'] = 'error';
                }
            }
        }
    }
    
    header("Location: admin.php");
    exit;
}

header("Location: admin.php");
exit;
?>