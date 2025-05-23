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
    $_SESSION['message'] = "Invalid redemption ID.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT r.*, rw.name as reward_name, rw.description as reward_description, 
           rw.points_cost, rw.image as reward_image, u.username
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $redemption_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Redemption not found.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

$redemption = $result->fetch_assoc();

if ($redemption['user_id'] != $user_id && $_SESSION['player_rank'] !== 'Admin') {
    $_SESSION['message'] = "You are not authorized to view this redemption.";
    $_SESSION['message_type'] = 'error';
    header("Location: rewards.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $_SESSION['player_rank'] === 'Admin') {
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    if (in_array($new_status, ['pending', 'completed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE redemptions SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $admin_notes, $redemption_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Redemption status updated successfully.";
            $_SESSION['message_type'] = 'success';
            
            header("Location: view_redemption.php?id=" . $redemption_id);
            exit;
        } else {
            $_SESSION['message'] = "Error updating redemption status: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
    }
}

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
    <title>View Redemption - Game Challenge</title>
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
        <div class="page-header">
            <h1>Reward Redemption</h1>
            <p>View your reward redemption details</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2><?php echo htmlspecialchars($redemption['reward_name']); ?></h2>
                <div class="status-badge <?php echo $redemption['status']; ?>">
                    <?php echo ucfirst($redemption['status']); ?>
                </div>
            </div>
            <div class="card-body">
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
                        <div class="info-group">
                            <label>Reward:</label>
                            <span><?php echo htmlspecialchars($redemption['reward_name']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Description:</label>
                            <span><?php echo htmlspecialchars($redemption['reward_description'] ?? 'No description available.'); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Points Cost:</label>
                            <span><?php echo number_format($redemption['points_cost']); ?> points</span>
                        </div>
                        
                        <div class="info-group">
                            <label>Redeemed By:</label>
                            <span><?php echo htmlspecialchars($redemption['username']); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Redemption Date:</label>
                            <span><?php echo date('F j, Y g:i A', strtotime($redemption['created_at'])); ?></span>
                        </div>
                        
                        <div class="info-group">
                            <label>Status:</label>
                            <span class="status <?php echo $redemption['status']; ?>"><?php echo ucfirst($redemption['status']); ?></span>
                        </div>
                        
                        <?php if (!empty($redemption['redemption_code'])): ?>
                        <div class="info-group">
                            <label>Redemption Code:</label>
                            <div class="redemption-code">
                                <code><?php echo htmlspecialchars($redemption['redemption_code']); ?></code>
                                <button class="btn btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($redemption['redemption_code']); ?>')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($redemption['admin_notes'])): ?>
                        <div class="info-group">
                            <label>Admin Notes:</label>
                            <div class="admin-notes">
                                <?php echo nl2br(htmlspecialchars($redemption['admin_notes'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($_SESSION['player_rank'] === 'Admin'): ?>
                <div class="admin-actions">
                    <h3>Admin Actions</h3>
                    <form action="view_redemption.php?id=<?php echo $redemption_id; ?>" method="post" class="admin-form">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="form-group">
                            <label for="status">Update Status:</label>
                            <select id="status" name="status" class="form-control">
                                <option value="pending" <?php echo $redemption['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $redemption['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $redemption['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_notes">Admin Notes:</label>
                            <textarea id="admin_notes" name="admin_notes" rows="3" class="form-control"><?php echo htmlspecialchars($redemption['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Redemption</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="card-actions">
                    <a href="rewards.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Back to Rewards
                    </a>
                    
                    <?php if ($_SESSION['player_rank'] === 'Admin'): ?>
                    <a href="manage_redemptions.php" class="btn">
                        <i class="fas fa-list"></i> Manage Redemptions
                    </a>
                    <?php endif; ?>
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