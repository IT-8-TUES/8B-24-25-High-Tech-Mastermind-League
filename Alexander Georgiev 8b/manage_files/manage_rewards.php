<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$rewards_query = "
    SELECT r.*, g.name as game_name, COUNT(rd.id) as redemptions_count
    FROM rewards r
    LEFT JOIN games g ON r.game_id = g.id
    LEFT JOIN redemptions rd ON r.id = rd.reward_id
    GROUP BY r.id
    ORDER BY r.points_cost ASC
";
$rewards = $conn->query($rewards_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rewards - Game Challenge</title>
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
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="badge admin">Admin</span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="page-header">
            <h1>Manage Rewards</h1>
            <p>Create, edit, and delete rewards for players</p>
        </div>
        
        <?php if ($message): ?>
        <div class="container">
            <div class="<?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
                <?php echo $message; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="container">
            <div class="actions-bar">
                <h2 class="section-title">All Rewards</h2>
                <a href="add_reward.php" class="btn btn-primary">Add New Reward</a>
            </div>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Points Cost</th>
                        <th>Stock</th>
                        <th>Game</th>
                        <th>Redemptions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rewards) > 0): ?>
                        <?php foreach ($rewards as $reward): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reward['name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($reward['description'], 0, 50)) . (strlen($reward['description']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo number_format($reward['points_cost']); ?></td>
                                <td>
                                    <?php if ($reward['stock'] == -1): ?>
                                        <span class="status approved">Unlimited</span>
                                    <?php elseif ($reward['stock'] == 0): ?>
                                        <span class="status rejected">Out of Stock</span>
                                    <?php elseif ($reward['stock'] <= 5): ?>
                                        <span class="status pending"><?php echo $reward['stock']; ?> left</span>
                                    <?php else: ?>
                                        <?php echo $reward['stock']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $reward['game_name'] ? htmlspecialchars($reward['game_name']) : 'N/A'; ?></td>
                                <td><?php echo number_format($reward['redemptions_count']); ?></td>
                                <td class="actions">
                                    <a href="edit_reward.php?id=<?php echo $reward['id']; ?>" class="btn btn-sm">Edit</a>
                                    <form action="delete_reward.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this reward?');">
                                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No rewards found. <a href="add_reward.php">Add your first reward</a>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>Recent Redemptions</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Reward</th>
                                    <th>Points</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $redemptions_query = "
                                    SELECT rd.*, r.name as reward_name, r.points_cost, u.username
                                    FROM redemptions rd
                                    JOIN rewards r ON rd.reward_id = r.id
                                    JOIN users u ON rd.user_id = u.id
                                    ORDER BY rd.created_at DESC
                                    LIMIT 10
                                ";
                                $redemptions = $conn->query($redemptions_query)->fetch_all(MYSQLI_ASSOC);
                                
                                if (count($redemptions) > 0):
                                    foreach ($redemptions as $redemption):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($redemption['username']); ?></td>
                                    <td><?php echo htmlspecialchars($redemption['reward_name']); ?></td>
                                    <td><?php echo number_format($redemption['points_cost']); ?></td>
                                    <td>
                                        <?php if ($redemption['status'] === 'completed'): ?>
                                            <span class="status approved">Completed</span>
                                        <?php elseif ($redemption['status'] === 'cancelled'): ?>
                                            <span class="status rejected">Cancelled</span>
                                        <?php else: ?>
                                            <span class="status pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($redemption['created_at'])); ?></td>
                                    <td class="actions">
                                        <?php if ($redemption['status'] === 'pending'): ?>
                                        <form action="update_redemption.php" method="post" style="display: inline;">
                                            <input type="hidden" name="redemption_id" value="<?php echo $redemption['id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn btn-sm btn-secondary">Complete</button>
                                        </form>
                                        <form action="update_redemption.php" method="post" style="display: inline;">
                                            <input type="hidden" name="redemption_id" value="<?php echo $redemption['id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-secondary">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center">No redemptions found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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