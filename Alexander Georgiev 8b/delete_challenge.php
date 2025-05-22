<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
!isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    $_SESSION['message'] = "Invalid challenge ID.";
    $_SESSION['message_type'] = 'error';
    header("Location: admin.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->bind_param("i", $challenge_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Challenge not found.";
    $_SESSION['message_type'] = 'error';
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("DELETE FROM submissions WHERE challenge_id = ?");
        $stmt->bind_param("i", $challenge_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM challenges WHERE id = ?");
        $stmt->bind_param("i", $challenge_id);
        $stmt->execute();
        
        $conn->commit();
        
        $_SESSION['message'] = "Challenge deleted successfully.";
        $_SESSION['message_type'] = 'success';
        header("Location: manage_challenges.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        
        $_SESSION['message'] = "Error deleting challenge: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header("Location: manage_challenges.php");
        exit;
    }
} else {
    $challenge = $result->fetch_assoc();

    $stmt = $conn->prepare("SELECT name FROM games WHERE id = ?");
    $stmt->bind_param("i", $challenge['game_id']);
    $stmt->execute();
    $game_result = $stmt->get_result();
    $game = $game_result->fetch_assoc();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE challenge_id = ?");
    $stmt->bind_param("i", $challenge_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $submission_count = $count_result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Challenge - Game Challenge</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>Game<span>Challenge</span></h1>
        </div>
        
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="challenges.php">Challenges</a></li>
                <li><a href="leaderboard.php">Leaderboard</a></li>
                <li><a href="rewards.php">Rewards</a></li>
                <li><a href="ai-chat.php">AI Chat</a></li>
                <li><a href="admin.php" class="active">Admin</a></li>
            </ul>
        </nav>
        
        <div class="user-actions">
            <a href="profile.php" class="profile-link">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="badge admin"><?php echo $_SESSION['player_rank']; ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>

    <main>
        <div class="page-header">
            <h1>Delete Challenge</h1>
            <p>Permanently delete a challenge</p>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Confirm Deletion</h2>
            </div>
            <div class="card-body">
                <div class="delete-confirmation">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    
                    <div class="confirmation-message">
                        <h3>Are you sure you want to delete this challenge?</h3>
                        <p>You are about to delete the challenge <strong>"<?php echo htmlspecialchars($challenge['title']); ?>"</strong> for the game <strong><?php echo htmlspecialchars($game['name']); ?></strong>.</p>
                        
                        <div class="warning-details">
                            <p><strong>Warning:</strong> This action cannot be undone. Deleting this challenge will also delete:</p>
                            <ul>
                                <li>All submissions for this challenge (<?php echo number_format($submission_count); ?> submissions)</li>
                                <li>All associated data and records</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <form method="post">
                        <input type="hidden" name="confirm_delete" value="1">
                        <a href="manage_challenges.php" class="btn">Cancel</a>
                        <button type="submit" class="btn btn-danger">Delete Challenge</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <h2>Game<span>Challenge</span></h2>
                <p>The ultimate gaming challenge platform</p>
            </div>
            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="challenges.php">Challenges</a></li>
                    <li><a href="leaderboard.php">Leaderboard</a></li>
                    <li><a href="rewards.php">Rewards</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h3>Support</h3>
                <ul>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Game Challenge. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>