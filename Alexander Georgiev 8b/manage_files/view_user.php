<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'];
$admin_rank = $_SESSION['player_rank'];

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    $_SESSION['message'] = "Invalid user ID.";
    $_SESSION['message_type'] = 'error';
    header("Location: manage_users.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "User not found.";
    $_SESSION['message_type'] = 'error';
    header("Location: manage_users.php");
    exit;
}

$user = $result->fetch_assoc();

$stmt = $conn->prepare("
    SELECT s.*, c.title as challenge_title, g.name as game_name, g.image as game_image
    FROM submissions s
    JOIN challenges c ON s.challenge_id = c.id
    JOIN games g ON c.game_id = g.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$submissions = $stmt->get_result();

$stmt = $conn->prepare("
    SELECT r.*, rw.name as reward_name, rw.image as reward_image
    FROM redemptions r
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$redemptions = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $new_username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $new_email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $new_rank = isset($_POST['player_rank']) ? $_POST['player_rank'] : '';
    $new_score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    $errors = [];
    
    if (empty($new_username)) {
        $errors[] = "Username cannot be empty.";
    }
    
    if (empty($new_email)) {
        $errors[] = "Email cannot be empty.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (!in_array($new_rank, ['Rookie', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Admin'])) {
        $errors[] = "Invalid player rank.";
    }
    
    if ($new_score < 0) {
        $errors[] = "Score cannot be negative.";
    }
    
    if (!in_array($new_status, ['active', 'suspended', 'banned'])) {
        $errors[] = "Invalid account status.";
    }
    
    if (empty($errors)) {
        if ($new_username !== $user['username']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $new_username, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "Username is already taken.";
            }
        }
        
        if ($new_email !== $user['email']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $new_email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "Email is already taken.";
            }
        }
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, email = ?, player_rank = ?, score = ?, status = ?, admin_notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssissi", $new_username, $new_email, $new_rank, $new_score, $new_status, $admin_notes, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User updated successfully.";
            $_SESSION['message_type'] = 'success';
            
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $_SESSION['message'] = "Error updating user: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "Error: " . implode(" ", $errors);
        $_SESSION['message_type'] = 'error';
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
    <title>View User - Game Challenge</title>
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
                    <span class="username"><?php echo htmlspecialchars($admin_username); ?></span>
                    <span class="badge admin"><?php echo $admin_rank; ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="page-header">
            <h1>User Profile: <?php echo htmlspecialchars($user['username']); ?></h1>
            <p>View and manage user details</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>User Information</h2>
                </div>
                <div class="card-body">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                            <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-details">
                            <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                            
                            <div class="user-meta">
                                <div class="meta-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo number_format($user['score']); ?> points</span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-medal"></i>
                                    <span class="badge <?php echo strtolower($user['player_rank']); ?>"><?php echo $user['player_rank']; ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Joined: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <i class="fas fa-circle"></i>
                                    <span class="status <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($user['bio'])): ?>
                    <div class="user-bio">
                        <h4>Bio</h4>
                        <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['admin_notes'])): ?>
                    <div class="admin-notes">
                        <h4>Admin Notes</h4>
                        <div class="notes-content">
                            <?php echo nl2br(htmlspecialchars($user['admin_notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Edit User</h2>
                </div>
                <div class="card-body">
                    <form action="view_user.php?id=<?php echo $user_id; ?>" method="post" class="form">
                        <input type="hidden" name="update_user" value="1">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="player_rank">Rank</label>
                            <select id="player_rank" name="player_rank">
                                <option value="Rookie" <?php echo $user['player_rank'] === 'Rookie' ? 'selected' : ''; ?>>Rookie</option>
                                <option value="Bronze" <?php echo $user['player_rank'] === 'Bronze' ? 'selected' : ''; ?>>Bronze</option>
                                <option value="Silver" <?php echo $user['player_rank'] === 'Silver' ? 'selected' : ''; ?>>Silver</option>
                                <option value="Gold" <?php echo $user['player_rank'] === 'Gold' ? 'selected' : ''; ?>>Gold</option>
                                <option value="Platinum" <?php echo $user['player_rank'] === 'Platinum' ? 'selected' : ''; ?>>Platinum</option>
                                <option value="Diamond" <?php echo $user['player_rank'] === 'Diamond' ? 'selected' : ''; ?>>Diamond</option>
                                <option value="Admin" <?php echo $user['player_rank'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="score">Score</label>
                            <input type="number" id="score" name="score" value="<?php echo $user['score']; ?>" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Account Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_notes">Admin Notes</label>
                            <textarea id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($user['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="manage_users.php" class="btn">Back to Users</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Recent Submissions</h2>
                <a href="manage_submissions.php?user_id=<?php echo $user_id; ?>" class="btn">
                    <i class="fas fa-list"></i> View All Submissions
                </a>
            </div>
            <div class="card-body">
                <?php if ($submissions && $submissions->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Challenge</th>
                                <th>Game</th>
                                <th>Status</th>
                                <th>Points</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($submission = $submissions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $submission['id']; ?></td>
                                <td><?php echo htmlspecialchars($submission['challenge_title']); ?></td>
                                <td>
                                    <div class="game-item">
                                        <?php if (!empty($submission['game_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($submission['game_image']); ?>" alt="<?php echo htmlspecialchars($submission['game_name']); ?>" class="game-icon">
                                        <?php else: ?>
                                        <div class="game-icon-placeholder">
                                            <i class="fas fa-gamepad"></i>
                                        </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($submission['game_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($submission['status'] === 'approved'): ?>
                                        <span class="status approved">Approved</span>
                                    <?php elseif ($submission['status'] === 'rejected'): ?>
                                        <span class="status rejected">Rejected</span>
                                    <?php else: ?>
                                        <span class="status pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($submission['points_awarded']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($submission['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm" title="View Submission">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($submission['status'] === 'pending'): ?>
                                        <a href="verify_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-primary" title="Verify Submission">
                                            <i class="fas fa-check-circle"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt fa-3x"></i>
                    <p>This user has not submitted any challenges yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Recent Redemptions</h2>
                <a href="manage_redemptions.php?user_id=<?php echo $user_id; ?>" class="btn">
                    <i class="fas fa-list"></i> View All Redemptions
                </a>
            </div>
            <div class="card-body">
                <?php if ($redemptions && $redemptions->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reward</th>
                                <th>Points</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($redemption = $redemptions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $redemption['id']; ?></td>
                                <td>
                                    <div class="reward-item">
                                        <?php if (!empty($redemption['reward_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($redemption['reward_image']); ?>" alt="<?php echo htmlspecialchars($redemption['reward_name']); ?>" class="reward-icon">
                                        <?php else: ?>
                                        <div class="reward-icon-placeholder">
                                            <i class="fas fa-gift"></i>
                                        </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($redemption['reward_name']); ?></span>
                                    </div>
                                </td>
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
                                <td><?php echo date('M j, Y', strtotime($redemption['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_redemption.php?id=<?php echo $redemption['id']; ?>" class="btn btn-sm" title="View Redemption">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-gift fa-3x"></i>
                    <p>This user has not redeemed any rewards yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-actions">
            <a href="manage_users.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            
            <?php if ($user['status'] !== 'banned'): ?>
            <a href="ban_user.php?id=<?php echo $user_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to ban this user? This will prevent them from logging in.');">
                <i class="fas fa-ban"></i> Ban User
            </a>
            <?php else: ?>
            <a href="unban_user.php?id=<?php echo $user_id; ?>" class="btn btn-success" onclick="return confirm('Are you sure you want to unban this user?');">
                <i class="fas fa-user-check"></i> Unban User
            </a>
            <?php endif; ?>
            
            <a href="reset_password.php?id=<?php echo $user_id; ?>" class="btn btn-warning" onclick="return confirm('Are you sure you want to reset this user\'s password?');">
                <i class="fas fa-key"></i> Reset Password
            </a>
            
            <a href="delete_user.php?id=<?php echo $user_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                <i class="fas fa-trash"></i> Delete User
            </a>
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