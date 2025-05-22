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

$stmt = $conn->prepare("SELECT score FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$available_points = $user['score'];

$rewards_query = "SELECT * FROM rewards ORDER BY points_cost ASC";
$rewards_result = $conn->query($rewards_query);
$rewards = [];
while ($reward = $rewards_result->fetch_assoc()) {
    $rewards[] = $reward;
}

$redemption_success = false;
$redemption_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward'])) {
    $reward_id = isset($_POST['reward_id']) ? intval($_POST['reward_id']) : 0;
    
    if ($reward_id <= 0) {
        $redemption_error = "Invalid reward selected.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->bind_param("i", $reward_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $redemption_error = "Reward not found.";
        } else {
            $reward = $result->fetch_assoc();
            
            if ($available_points < $reward['points_cost']) {
                $redemption_error = "You don't have enough points to redeem this reward.";
            } else {
                $conn->begin_transaction();
                
                try {
                    $redemption_code = generateRedemptionCode($reward['name']);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO redemptions (user_id, reward_id, points_cost, redemption_code, status, created_at)
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->bind_param("iiis", $user_id, $reward_id, $reward['points_cost'], $redemption_code);
                    $stmt->execute();
                    $redemption_id = $conn->insert_id;
                    
                    $stmt = $conn->prepare("UPDATE users SET score = score - ? WHERE id = ?");
                    $stmt->bind_param("ii", $reward['points_cost'], $user_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    
                    $available_points -= $reward['points_cost'];
                    
                    $redemption_success = true;
                    
                    header("Location: redemption_success.php?id=" . $redemption_id);
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    
                    $redemption_error = "Error processing redemption: " . $e->getMessage();
                }
            }
        }
    }
}

function generateRedemptionCode($reward_name) {
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $reward_name), 0, 3));
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    return $prefix . '-' . $random;
}

$stmt = $conn->prepare("
    SELECT r.*, rw.name as reward_name, rw.image as reward_image
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_redemptions = $stmt->get_result();

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
    <title>Rewards - Game Challenge</title>
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
            <h1>Rewards</h1>
            <p>Exchange your hard-earned points for exciting gaming rewards</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($redemption_error): ?>
        <div class="error-message mb-4">
            <?php echo $redemption_error; ?>
        </div>
        <?php endif; ?>
        
        <div class="points-banner">
            <div class="points-info">
                <i class="fas fa-star"></i>
                <span class="points-value"><?php echo number_format($available_points); ?></span>
                <span class="points-label">Available Points</span>
            </div>
            
            <div class="points-actions">
                <a href="challenges.php" class="btn btn-primary">
                    <i class="fas fa-trophy"></i> Earn More Points
                </a>
                <a href="redemption_history.php" class="btn">
                    <i class="fas fa-history"></i> View Redemption History
                </a>
            </div>
        </div>
        
        <h2 class="section-title">Available Rewards</h2>
        
        <div class="rewards-grid">
            <?php if (count($rewards) > 0): ?>
                <?php foreach ($rewards as $reward): ?>
                <div class="reward-card">
                    <div class="reward-header">
                        <?php if (!empty($reward['image'])): ?>
                        <img src="<?php echo htmlspecialchars($reward['image']); ?>" alt="<?php echo htmlspecialchars($reward['name']); ?>" class="reward-image">
                        <?php else: ?>
                        <div class="reward-image-placeholder">
                            <i class="fas fa-gift"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="reward-cost">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($reward['points_cost']); ?></span>
                        </div>
                    </div>
                    
                    <div class="reward-body">
                        <h3><?php echo htmlspecialchars($reward['name']); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($reward['description'] ?? '')); ?></p>
                    </div>
                    
                    <div class="reward-footer">
                        <?php if ($available_points >= $reward['points_cost']): ?>
                        <form action="rewards.php" method="post">
                            <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                            <button type="submit" name="redeem_reward" class="btn btn-primary" onclick="return confirm('Are you sure you want to redeem this reward for <?php echo number_format($reward['points_cost']); ?> points?');">
                                <i class="fas fa-gift"></i> Redeem Reward
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <i class="fas fa-lock"></i> Not Enough Points
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-gift fa-3x"></i>
                    <p>No rewards available at the moment. Check back later!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($recent_redemptions && $recent_redemptions->num_rows > 0): ?>
        <div class="recent-redemptions">
            <h2 class="section-title">Recent Redemptions</h2>
            
            <div class="redemptions-list">
                <?php while ($redemption = $recent_redemptions->fetch_assoc()): ?>
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
                                <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($redemption['created_at'])); ?>
                            </span>
                            <span class="redemption-points">
                                <i class="fas fa-star"></i> <?php echo number_format($redemption['points_cost']); ?> points
                            </span>
                            <span class="redemption-status <?php echo $redemption['status']; ?>">
                                <?php echo ucfirst($redemption['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="redemption-actions">
                        <a href="view_redemption.php?id=<?php echo $redemption['id']; ?>" class="btn btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <div class="view-all-link">
                <a href="redemption_history.php" class="btn">View All Redemptions</a>
            </div>
        </div>
        <?php endif; ?>
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