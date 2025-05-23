<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$player_rank = $_SESSION['player_rank'];

$redemption_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($redemption_id <= 0) {
    header("Location: rewards.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT r.*, rw.name as reward_name, rw.description as reward_description, 
           rw.image as reward_image
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->bind_param("ii", $redemption_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: rewards.php");
    exit;
}

$redemption = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redemption Successful - Game Challenge</title>
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
                <li><a href="rewards.php" class="active">Rewards</a></li>
                <?php if ($_SESSION['player_rank'] === 'Admin'): ?>
                <li><a href="ai-chat.php">AI Chat</a></li>
                <li><a href="admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="user-actions">
            <a href="profile.php" class="profile-link">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="badge <?php echo strtolower($player_rank); ?>"><?php echo $player_rank; ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1>Redemption Successful!</h1>
            <p>Your reward has been successfully redeemed.</p>
            
            <div class="redemption-details">
                <div class="redemption-image">
                    <?php if (!empty($redemption['reward_image'])): ?>
                    <img src="<?php echo htmlspecialchars($redemption['reward_image']); ?>" alt="<?php echo htmlspecialchars($redemption['reward_name']); ?>">
                    <?php else: ?>
                    <div class="reward-placeholder">
                        <i class="fas fa-gift"></i>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="redemption-info">
                    <h2><?php echo htmlspecialchars($redemption['reward_name']); ?></h2>
                    
                    <?php if (!empty($redemption['reward_description'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($redemption['reward_description'])); ?></p>
                    <?php endif; ?>
                    
                    <div class="redemption-meta">
                        <div class="meta-item">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($redemption['points_cost']); ?> points</span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('F j, Y g:i A', strtotime($redemption['created_at'])); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span>Status: <strong><?php echo ucfirst($redemption['status']); ?></strong></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($redemption['redemption_code'])): ?>
                    <div class="redemption-code">
                        <h3>Your Redemption Code</h3>
                        <div class="code-display">
                            <code><?php echo htmlspecialchars($redemption['redemption_code']); ?></code>
                            <button class="btn btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($redemption['redemption_code']); ?>')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="redemption-note">
                        <p>Your redemption is currently being processed. You will receive your reward soon.</p>
                        <p>You can view the status of your redemption in your <a href="redemption_history.php">redemption history</a>.</p>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="rewards.php" class="btn btn-primary">
                    <i class="fas fa-gift"></i> Browse More Rewards
                </a>
                <a href="redemption_history.php" class="btn">
                    <i class="fas fa-history"></i> View Redemption History
                </a>
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
    
    <script>
        function copyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            alert('Redemption code copied to clipboard!');
        }
    </script>
</body>
</html>