<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';
require_once 'api.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!isset($_POST['reward_id']) || empty($_POST['reward_id'])) {
    $_SESSION['message'] = "Invalid request. No reward specified.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

$reward_id = intval($_POST['reward_id']);

$stmt = $conn->prepare("SELECT score FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$user_score = $user_data['score'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM rewards WHERE id = ?");
$stmt->bind_param("i", $reward_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Reward not found.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

$reward = $result->fetch_assoc();

if ($reward['stock'] === 0) {
    $_SESSION['message'] = "This reward is out of stock.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

if ($user_score < $reward['points_cost']) {
    $_SESSION['message'] = "You don't have enough points to redeem this reward.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO redemptions (user_id, reward_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $reward_id);
    $stmt->execute();
    $redemption_id = $conn->insert_id;
    
    $stmt = $conn->prepare("UPDATE users SET score = score - ? WHERE id = ?");
    $stmt->bind_param("ii", $reward['points_cost'], $user_id);
    $stmt->execute();
    
    if ($reward['stock'] > 0) {
        $stmt = $conn->prepare("UPDATE rewards SET stock = stock - 1 WHERE id = ?");
        $stmt->bind_param("i", $reward_id);
        $stmt->execute();
    }
    
    if ($reward['stock'] !== -1) { 
        $redemption_code = generateRedemptionCode();
        $stmt = $conn->prepare("UPDATE redemptions SET redemption_code = ? WHERE id = ?");
        $stmt->bind_param("si", $redemption_code, $redemption_id);
        $stmt->execute();
    }
    

    $conn->commit();
    
    $prompt = "Generate a short, enthusiastic congratulatory message for a user who just redeemed a gaming reward called '{$reward['name']}' for {$reward['points_cost']} points. Keep it under 2 sentences.";
    $ai_message = callOpenAI($prompt);
    
    if (strlen($ai_message) > 200 || empty($ai_message)) {
        $ai_message = "Congratulations! You've successfully redeemed the " . htmlspecialchars($reward['name']) . " reward.";
    }
    
    $_SESSION['message'] = $ai_message;
    $_SESSION['message_type'] = 'success';
    $_SESSION['redemption_id'] = $redemption_id;
    
    header("Location: redemption_success.php");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    
    $_SESSION['message'] = "Error processing redemption: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
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