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

$stmt = $conn->prepare("
    SELECT r.*, rw.name as reward_name, rw.image as reward_image
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$redemptions = $stmt->get_result();

$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redemption History - Game Challenge</title>
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
                <li><a href="ai-chat.php">AI Chat</a></li>
                <?php if ($_SESSION['player_rank'] === 'Admin'): ?>
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
        <div class="page-header">
            <h1>Redemption History</h1>
            <p>View your reward redemption history</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Your Redemptions</h2>
                <a href="rewards.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Rewards
                </a>
            </div>
            <div class="card-body">
                <?php if ($redemptions && $redemptions->num_rows > 0): ?>
                <div class="redemptions-list">
                    <?php while ($redemption = $redemptions->fetch_assoc()): ?>
                    <div class="redemption-item">
                        <div class="redemption-image">
                            <?php if (!empty($redemption['reward_image'])): ?>
                            <img src="<?php echo htmlspecialchars($redemption['reward_image']); ?>" alt="<?php echo htmlspecialchars($redemption['reward_name']); ?>" class="reward-icon">
                            <?php else: ?>
                            <div class="reward-icon-placeholder">
                                <i class="fas fa-gift"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="redemption-details">
                            <h3><?php echo htmlspecialchars($redemption['reward_name']); ?></h3>
                            <div class="redemption-meta">
                                <span class="redemption-date">
                                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y g:i A', strtotime($redemption['created_at'])); ?>
                                </span>
                                <span class="redemption-points">
                                    <i class="fas fa-star"></i> <?php echo number_format($redemption['points_cost']); ?> points
                                </span>
                                <span class="redemption-status <?php echo $redemption['status']; ?>">
                                    <?php echo ucfirst($redemption['status']); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($redemption['redemption_code'])): ?>
                            <div class="redemption-code">
                                <strong>Code:</strong> <code><?php echo htmlspecialchars($redemption['redemption_code']); ?></code>
                                <button class="btn btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($redemption['redemption_code']); ?>')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="redemption-actions">
                            <a href="view_redemption.php?id=<?php echo $redemption['id']; ?>" class="btn">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history fa-3x"></i>
                    <p>You haven't redeemed any rewards yet.</p>
                    <a href="rewards.php" class="btn btn-primary">Browse Rewards</a>
                </div>
                <?php endif; ?>
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