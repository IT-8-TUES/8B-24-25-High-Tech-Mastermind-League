<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$player_rank = $_SESSION['player_rank'];

$stats = [
    'users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'challenges' => $conn->query("SELECT COUNT(*) as count FROM challenges")->fetch_assoc()['count'],
    'submissions' => $conn->query("SELECT COUNT(*) as count FROM submissions")->fetch_assoc()['count'],
    'games' => $conn->query("SELECT COUNT(*) as count FROM games")->fetch_assoc()['count'],
    'rewards' => $conn->query("SELECT COUNT(*) as count FROM rewards")->fetch_assoc()['count'],
    'redemptions' => $conn->query("SELECT COUNT(*) as count FROM redemptions")->fetch_assoc()['count'],
    'pending_submissions' => $conn->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'")->fetch_assoc()['count'],
    'pending_redemptions' => $conn->query("SELECT COUNT(*) as count FROM redemptions WHERE status = 'pending'")->fetch_assoc()['count']
];

$recent_activity_query = "
    (SELECT 'submission' as type, s.id, s.created_at, u.username, c.title as item_name, s.status
     FROM submissions s
     JOIN users u ON s.user_id = u.id
     JOIN challenges c ON s.challenge_id = c.id
     ORDER BY s.created_at DESC
     LIMIT 5)
    UNION
    (SELECT 'redemption' as type, r.id, r.created_at, u.username, rw.name as item_name, r.status
     FROM redemptions r
     JOIN users u ON r.user_id = u.id
     JOIN rewards rw ON r.reward_id = rw.id
     ORDER BY r.created_at DESC
     LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 10
";
$recent_activity = $conn->query($recent_activity_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Game Challenge</title>
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
                    <span class="badge admin"><?php echo $player_rank; ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Manage your gaming challenge platform</p>
        </div>
        
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
                    <div class="stat-label">Users</div>
                </div>
                <a href="manage_users.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['challenges']); ?></div>
                    <div class="stat-label">Challenges</div>
                </div>
                <a href="manage_challenges.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['games']); ?></div>
                    <div class="stat-label">Games</div>
                </div>
                <a href="manage_games.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['rewards']); ?></div>
                    <div class="stat-label">Rewards</div>
                </div>
                <a href="manage_rewards.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['submissions']); ?></div>
                    <div class="stat-label">Submissions</div>
                </div>
                <a href="manage_submissions.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($stats['redemptions']); ?></div>
                    <div class="stat-label">Redemptions</div>
                </div>
                <a href="manage_redemptions.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="admin-grid">
            <div class="admin-card">
                <div class="card-header">
                    <h2>Pending Approvals</h2>
                </div>
                <div class="card-body">
                    <div class="approval-stats">
                        <div class="approval-item">
                            <div class="approval-icon">
                                <i class="fas fa-image"></i>
                            </div>
                            <div class="approval-info">
                                <div class="approval-value"><?php echo number_format($stats['pending_submissions']); ?></div>
                                <div class="approval-label">Pending Submissions</div>
                            </div>
                            <a href="manage_submissions.php?status=pending" class="btn btn-sm">Review</a>
                        </div>
                        
                        <div class="approval-item">
                            <div class="approval-icon">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div class="approval-info">
                                <div class="approval-value"><?php echo number_format($stats['pending_redemptions']); ?></div>
                                <div class="approval-label">Pending Redemptions</div>
                            </div>
                            <a href="manage_redemptions.php?status=pending" class="btn btn-sm">Review</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="admin-card">
                <div class="card-header">
                    <h2>Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="add_challenge.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Challenge
                        </a>
                        <a href="add_game.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Game
                        </a>
                        <a href="add_reward.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Reward
                        </a>
                        <a href="generate_challenge.php" class="btn btn-secondary">
                            <i class="fas fa-magic"></i> Generate Challenge
                        </a>
                        <a href="system_settings.php" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> System Settings
                        </a>
                        <a href="backup_database.php" class="btn btn-secondary">
                            <i class="fas fa-database"></i> Backup Database
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="admin-card full-width">
                <div class="card-header">
                    <h2>Recent Activity</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>User</th>
                                    <th>Item</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                                    <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($activity['type'] === 'submission'): ?>
                                                <span class="badge submission">Submission</span>
                                            <?php else: ?>
                                                <span class="badge redemption">Redemption</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['item_name']); ?></td>
                                        <td>
                                            <?php if ($activity['status'] === 'approved'): ?>
                                                <span class="status approved">Approved</span>
                                            <?php elseif ($activity['status'] === 'rejected'): ?>
                                                <span class="status rejected">Rejected</span>
                                            <?php elseif ($activity['status'] === 'completed'): ?>
                                                <span class="status approved">Completed</span>
                                            <?php elseif ($activity['status'] === 'cancelled'): ?>
                                                <span class="status rejected">Cancelled</span>
                                            <?php else: ?>
                                                <span class="status pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></td>
                                        <td>
                                            <?php if ($activity['type'] === 'submission'): ?>
                                                <a href="view_submission.php?id=<?php echo $activity['id']; ?>" class="btn btn-sm">View</a>
                                            <?php else: ?>
                                                <a href="view_redemption.php?id=<?php echo $activity['id']; ?>" class="btn btn-sm">View</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No recent activity found.</td>
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