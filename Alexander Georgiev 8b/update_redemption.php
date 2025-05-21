<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redemption_id']) && isset($_POST['action'])) {
    $redemption_id = intval($_POST['redemption_id']);
    $action = $_POST['action'];
    
    if ($action !== 'complete' && $action !== 'cancel') {
        $_SESSION['message'] = "Invalid action.";
        $_SESSION['message_type'] = 'error';
        header("Location: manage_rewards.php");
        exit;
    }
    
    $stmt = $conn->prepare("
        SELECT rd.*, r.points_cost, r.stock, u.id as user_id
        FROM redemptions rd
        JOIN rewards r ON rd.reward_id = r.id
        JOIN users u ON rd.user_id = u.id
        WHERE rd.id = ?
    ");
    $stmt->bind_param("i", $redemption_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Redemption not found.";
        $_SESSION['message_type'] = 'error';
        header("Location: manage_rewards.php");
        exit;
    }
    
    $redemption = $result->fetch_assoc();
    
    if ($redemption['status'] !== 'pending') {
        $_SESSION['message'] = "This redemption has already been processed.";
        $_SESSION['message_type'] = 'error';
        header("Location: manage_rewards.php");
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        if ($action === 'complete') {
            $status = 'completed';
            $stmt = $conn->prepare("UPDATE redemptions SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $redemption_id);
            $stmt->execute();
            
            $redemption_code = generateRedemptionCode();
            $stmt = $conn->prepare("UPDATE redemptions SET redemption_code = ? WHERE id = ?");
            $stmt->bind_param("si", $redemption_code, $redemption_id);
            $stmt->execute();
            
            $_SESSION['message'] = "Redemption marked as completed.";
            $_SESSION['message_type'] = 'success';
        } else { 
            $status = 'cancelled';
            $stmt = $conn->prepare("UPDATE redemptions SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $redemption_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE users SET score = score + ? WHERE id = ?");
            $stmt->bind_param("ii", $redemption['points_cost'], $redemption['user_id']);
            $stmt->execute();
            
            if ($redemption['stock'] > 0) {
                $stmt = $conn->prepare("UPDATE rewards SET stock = stock + 1 WHERE id = ?");
                $stmt->bind_param("i", $redemption['reward_id']);
                $stmt->execute();
            }
            
            $_SESSION['message'] = "Redemption cancelled and points refunded to user.";
            $_SESSION['message_type'] = 'success';
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Error updating redemption: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header("Location: manage_rewards.php");
    exit;
} else {
    header("Location: manage_rewards.php");
    exit;
}

function generateRedemptionCode() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}
?>