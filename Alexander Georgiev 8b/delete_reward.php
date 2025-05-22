<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_id'])) {
    $reward_id = intval($_POST['reward_id']);
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM redemptions WHERE reward_id = ?");
        $stmt->bind_param("i", $reward_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $redemptions_count = $result->fetch_assoc()['count'];
        
        if ($redemptions_count > 0) {
            $stmt = $conn->prepare("UPDATE redemptions SET status = 'cancelled' WHERE reward_id = ? AND status = 'pending'");
            $stmt->bind_param("i", $reward_id);
            $stmt->execute();
        }
        
        $stmt = $conn->prepare("DELETE FROM rewards WHERE id = ?");
        $stmt->bind_param("i", $reward_id);
        $stmt->execute();
        
        $conn->commit();
        
        $_SESSION['message'] = "Reward deleted successfully!";
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Error deleting reward: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header("Location: manage_rewards.php");
    exit;
} else {
    header("Location: manage_rewards.php");
    exit;
}
?>