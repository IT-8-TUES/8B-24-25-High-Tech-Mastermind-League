<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'update_rank.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$player_rank = $_SESSION['player_rank'] ?? 'Rookie';

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (isset($user['score'])) {
    $updated_rank = updateUserRank($user_id, $user['score'], $conn);
    if ($updated_rank !== $player_rank) {
        $player_rank = $updated_rank;
        $_SESSION['player_rank'] = $player_rank;
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt = $conn->prepare("
SELECT 
    COUNT(DISTINCT s.challenge_id) as challenges_completed,
    SUM(s.points_awarded) as points_earned,
    COUNT(DISTINCT CASE WHEN c.difficulty = 'Easy' THEN s.challenge_id END) as easy_completed,
    COUNT(DISTINCT CASE WHEN c.difficulty = 'Medium' THEN s.challenge_id END) as medium_completed,
    COUNT(DISTINCT CASE WHEN c.difficulty = 'Hard' THEN s.challenge_id END) as hard_completed
FROM submissions s
JOIN challenges c ON s.challenge_id = c.id
WHERE s.user_id = ? AND s.status = 'approved'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

$stmt = $conn->prepare("
SELECT s.*, c.title as challenge_title, c.difficulty, g.name as game_name
FROM submissions s
JOIN challenges c ON s.challenge_id = c.id
JOIN games g ON c.game_id = g.id
WHERE s.user_id = ?
ORDER BY s.created_at DESC
LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_submissions = $stmt->get_result();

$stmt = $conn->prepare("
SELECT r.name as reward_name, r.points_cost, rd.status, rd.created_at, rd.redemption_code, rd.id as redemption_id
FROM redemptions rd
JOIN rewards r ON rd.reward_id = r.id
WHERE rd.user_id = ?
ORDER BY rd.created_at DESC
LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_redemptions = $stmt->get_result();

$stmt = $conn->prepare("
SELECT s.*, c.title as challenge_title, c.difficulty, g.name as game_name
FROM submissions s
JOIN challenges c ON s.challenge_id = c.id
JOIN games g ON c.game_id = g.id
WHERE s.user_id = ?
ORDER BY s.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_submissions = $stmt->get_result();

$stmt = $conn->prepare("
SELECT r.name as reward_name, r.points_cost, rd.status, rd.created_at, rd.redemption_code, rd.id as redemption_id
FROM redemptions rd
JOIN rewards r ON rd.reward_id = r.id
WHERE rd.user_id = ?
ORDER BY rd.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_redemptions = $stmt->get_result();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Email already in use by another account.";
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("ssi", $email, $bio, $user_id);
            $stmt->execute();
            
            if (!empty($current_password) && !empty($new_password)) {
                if (password_verify($current_password, $user['password_hash'])) {
                    if ($new_password === $confirm_password) {
                        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->bind_param("si", $password_hash, $user_id);
                        $stmt->execute();
                        
                        $message = "Profile updated successfully.";
                        $messageType = 'success';
                    } else {
                        $message = "New passwords do not match.";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Current password is incorrect.";
                    $messageType = 'error';
                }
            } else {
                $message = "Profile updated successfully.";
                $messageType = 'success';
            }
            
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; 

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error uploading file. Please try again.';
        $messageType = 'error';
    } elseif (!in_array($file['type'], $allowed_types)) {
        $message = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
        $messageType = 'error';
    } elseif ($file['size'] > $max_size) {
        $message = 'File is too large. Maximum size is 2MB.';
        $messageType = 'error';
    } else {
        if (!file_exists('uploads/avatars')) {
            mkdir('uploads/avatars', 0755, true);
        }
        
        $filename = uniqid('avatar_') . '_' . $user_id . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = 'uploads/avatars/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $filepath, $user_id);
            
            if ($stmt->execute()) {
                $message = "Profile picture updated successfully.";
                $messageType = 'success';
                
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $message = "Error updating profile picture: " . $stmt->error;
                $messageType = 'error';
            }
        } else {
            $message = "Error saving file. Please try again.";
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Game Challenge</title>
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
                <?php if (isset($_SESSION['player_rank']) && $_SESSION['player_rank'] === 'Admin'): ?>
                <li><a href="admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="user-actions">
            <a href="profile.php" class="profile-link active">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="badge <?php echo strtolower($player_rank); ?>"><?php echo $player_rank; ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>

    <main>
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($username); ?>">
                <?php else: ?>
                <div class="avatar-placeholder"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($username); ?></h1>
                <div class="profile-rank"><?php echo htmlspecialchars($user['player_rank']); ?></div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($user['score']); ?></div>
                        <div class="stat-label">Points</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($stats['challenges_completed'] ?? 0); ?></div>
                        <div class="stat-label">Challenges</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-value"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Joined</div>
                    </div>
                </div>
                
                <?php if (!empty($user['bio'])): ?>
                <div class="profile-bio">
                    <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs profile-tabs">
            <button class="tab active" data-tab="overview">Overview</button>
            <button class="tab" data-tab="submissions">Submissions</button>
            <button class="tab" data-tab="rewards">Rewards</button>
            <button class="tab" data-tab="settings">Settings</button>
        </div>
        
        <!-- Overview Tab -->
        <div class="tab-content active" id="overview">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h2>Challenge Statistics</h2>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-icon easy">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo number_format($stats['easy_completed'] ?? 0); ?></div>
                                    <div class="stat-label">Easy Challenges</div>
                                </div>
                            </div>
                            
                            <div class="stat-box">
                                <div class="stat-icon medium">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo number_format($stats['medium_completed'] ?? 0); ?></div>
                                    <div class="stat-label">Medium Challenges</div>
                                </div>
                            </div>
                            
                            <div class="stat-box">
                                <div class="stat-icon hard">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo number_format($stats['hard_completed'] ?? 0); ?></div>
                                    <div class="stat-label">Hard Challenges</div>
                                </div>
                            </div>
                            
                            <div class="stat-box">
                                <div class="stat-icon points">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo number_format($stats['points_earned'] ?? 0); ?></div>
                                    <div class="stat-label">Points Earned</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress-section mt-4">
                            <h3>Rank Progress</h3>
                            <?php
                            $ranks = [
                                ['name' => 'Rookie', 'min' => 0, 'max' => 1000],
                                ['name' => 'Bronze', 'min' => 1001, 'max' => 5000],
                                ['name' => 'Silver', 'min' => 5001, 'max' => 15000],
                                ['name' => 'Gold', 'min' => 15001, 'max' => 30000],
                                ['name' => 'Platinum', 'min' => 30001, 'max' => 50000],
                                ['name' => 'Diamond', 'min' => 50001, 'max' => PHP_INT_MAX]
                            ];
                            
                            $current_rank = 0;
                            foreach ($ranks as $index => $rank) {
                                if ($user['score'] >= $rank['min'] && $user['score'] <= $rank['max']) {
                                    $current_rank = $index;
                                    break;
                                }
                            }
                            
                            if ($current_rank < count($ranks) - 1) {
                                $next_rank = $ranks[$current_rank + 1];
                                $progress = ($user['score'] - $ranks[$current_rank]['min']) / ($next_rank['min'] - $ranks[$current_rank]['min']) * 100;
                                $points_needed = $next_rank['min'] - $user['score'];
                            } else {
                                $progress = 100;
                                $points_needed = 0;
                            }
                            ?>
                            
                            <div class="rank-progress">
                                <div class="current-rank"><?php echo $ranks[$current_rank]['name']; ?></div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo min(100, $progress); ?>%"></div>
                                </div>
                                <?php if ($current_rank < count($ranks) - 1): ?>
                                <div class="next-rank"><?php echo $ranks[$current_rank + 1]['name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($points_needed > 0): ?>
                            <div class="points-needed">
                                <p>You need <?php echo number_format($points_needed); ?> more points to reach <?php echo $ranks[$current_rank + 1]['name']; ?> rank.</p>
                            </div>
                            <?php else: ?>
                            <div class="points-needed">
                                <p>Congratulations! You've reached the highest rank!</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php if ($recent_submissions->num_rows > 0 || $recent_redemptions->num_rows > 0): ?>
                                <?php 
                                $activities = [];
                                
                                while ($submission = $recent_submissions->fetch_assoc()) {
                                    $activities[] = [
                                        'type' => 'submission',
                                        'date' => $submission['created_at'],
                                        'data' => $submission
                                    ];
                                }
                                
                                while ($redemption = $recent_redemptions->fetch_assoc()) {
                                    $activities[] = [
                                        'type' => 'redemption',
                                        'date' => $redemption['created_at'],
                                        'data' => $redemption
                                    ];
                                }
                                
                                usort($activities, function($a, $b) {
                                    return strtotime($b['date']) - strtotime($a['date']);
                                });
                                
                                $count = 0;
                                foreach ($activities as $activity):
                                    if ($count >= 5) break;
                                    $count++;
                                ?>
                                <div class="activity-item">
                                    <?php if ($activity['type'] === 'submission'): ?>
                                        <div class="activity-icon submission">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title">
                                                <?php if ($activity['data']['status'] === 'approved'): ?>
                                                    Completed challenge: <strong><?php echo htmlspecialchars($activity['data']['challenge_title']); ?></strong>
                                                <?php elseif ($activity['data']['status'] === 'rejected'): ?>
                                                    Challenge submission rejected: <strong><?php echo htmlspecialchars($activity['data']['challenge_title']); ?></strong>
                                                <?php else: ?>
                                                    Submitted challenge: <strong><?php echo htmlspecialchars($activity['data']['challenge_title']); ?></strong>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="game"><?php echo htmlspecialchars($activity['data']['game_name']); ?></span>
                                                <span class="difficulty <?php echo strtolower($activity['data']['difficulty']); ?>"><?php echo $activity['data']['difficulty']; ?></span>
                                                <?php if ($activity['data']['status'] === 'approved'): ?>
                                                <span class="points">+<?php echo number_format($activity['data']['points_awarded']); ?> points</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-date"><?php echo date('M j, Y g:i A', strtotime($activity['data']['created_at'])); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="activity-icon redemption">
                                            <i class="fas fa-gift"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title">
                                                Redeemed reward: <strong><?php echo htmlspecialchars($activity['data']['reward_name']); ?></strong>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="points">-<?php echo number_format($activity['data']['points_cost']); ?> points</span>
                                                <span class="status <?php echo strtolower($activity['data']['status']); ?>"><?php echo ucfirst($activity['data']['status']); ?></span>
                                            </div>
                                            <div class="activity-date"><?php echo date('M j, Y g:i A', strtotime($activity['data']['created_at'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-activity">
                                    <i class="fas fa-info-circle fa-2x"></i>
                                    <p>No recent activity found. Start completing challenges to see your activity here!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="submissions">
            <div class="card">
                <div class="card-header">
                    <h2>Your Challenge Submissions</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Challenge</th>
                                    <th>Game</th>
                                    <th>Difficulty</th>
                                    <th>Status</th>
                                    <th>Points</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_submissions->num_rows > 0): ?>
                                    <?php while ($submission = $all_submissions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($submission['challenge_title']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['game_name']); ?></td>
                                        <td>
                                            <span class="difficulty <?php echo strtolower($submission['difficulty']); ?>">
                                                <?php echo $submission['difficulty']; ?>
                                            </span>
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
                                        <td>
                                            <?php if ($submission['status'] === 'approved'): ?>
                                                <?php echo number_format($submission['points_awarded']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($submission['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm view-submission" data-id="<?php echo $submission['id']; ?>" data-screenshot="<?php echo htmlspecialchars($submission['screenshot_path']); ?>" data-feedback="<?php echo htmlspecialchars($submission['feedback'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No submissions found. Start completing challenges!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rewards Tab -->
        <div class="tab-content" id="rewards">
            <div class="card">
                <div class="card-header">
                    <h2>Your Reward Redemptions</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reward</th>
                                    <th>Points Cost</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_redemptions->num_rows > 0): ?>
                                    <?php while ($redemption = $all_redemptions->fetch_assoc()): ?>
                                    <tr>
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
                                        <td><?php echo date('M j, Y', strtotime($redemption['created_at'])); ?></td>
                                        <td>
                                            <?php if (!empty($redemption['redemption_code'])): ?>
                                                <button class="btn btn-sm view-code" data-code="<?php echo htmlspecialchars($redemption['redemption_code']); ?>" data-reward="<?php echo htmlspecialchars($redemption['reward_name']); ?>">
                                                    <i class="fas fa-eye"></i> View Code
                                                </button>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No redemptions found. Redeem rewards with your points!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="settings">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h2>Profile Settings</h2>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="post" id="profile-form">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" class="bio-textarea"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                <p class="form-hint">Tell others about yourself and your gaming interests.</p>
                            </div>
                            
                            <h3 class="settings-section-title">Change Password</h3>
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password">
                                <p class="form-hint">Leave blank if you don't want to change your password.</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Profile Picture</h2>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="post" enctype="multipart/form-data" id="avatar-form">
                            <div class="avatar-upload">
                                <div class="avatar-preview">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($username); ?>" id="avatar-preview-img">
                                    <?php else: ?>
                                    <div class="avatar-placeholder large" id="avatar-placeholder"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                                    <img src="/placeholder.svg" alt="Preview" id="avatar-preview-img" style="display: none;">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="avatar" class="btn btn-secondary">
                                        <i class="fas fa-upload"></i> Choose Image
                                    </label>
                                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" style="display: none;">
                                    <p class="form-hint">Max file size: 2MB (JPEG, PNG, GIF)</p>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" id="upload-avatar-btn" disabled>
                                        <i class="fas fa-save"></i> Upload Profile Picture
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <div id="submission-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Submission Details</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="submission-image">
                    <img src="/placeholder.svg" alt="Submission Screenshot" id="submission-image">
                </div>
                <div class="submission-feedback" id="submission-feedback-container">
                    <h3>Feedback</h3>
                    <p id="submission-feedback"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="close-submission-modal">Close</button>
            </div>
        </div>
    </div>
    
    <div id="code-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Redemption Code</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Your redemption code for <span id="code-reward-name"></span>:</p>
                <div class="redemption-code">
                    <span id="redemption-code-display"></span>
                    <button type="button" class="btn btn-sm" id="copy-code">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <p class="small">Keep this code safe and follow the instructions to redeem.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="close-code-modal">Close</button>
            </div>
        </div>
    </div>
    
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
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            const avatarInput = document.getElementById('avatar');
            const avatarPreviewImg = document.getElementById('avatar-preview-img');
            const avatarPlaceholder = document.getElementById('avatar-placeholder');
            const uploadAvatarBtn = document.getElementById('upload-avatar-btn');
            
            if (avatarInput) {
                avatarInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            if (avatarPlaceholder) {
                                avatarPlaceholder.style.display = 'none';
                            }
                            avatarPreviewImg.style.display = 'block';
                            avatarPreviewImg.src = e.target.result;
                            uploadAvatarBtn.disabled = false;
                        }
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
            
            const submissionModal = document.getElementById('submission-modal');
            const submissionImage = document.getElementById('submission-image');
            const submissionFeedback = document.getElementById('submission-feedback');
            const submissionFeedbackContainer = document.getElementById('submission-feedback-container');
            const closeSubmissionModal = document.getElementById('close-submission-modal');
            const viewSubmissionButtons = document.querySelectorAll('.view-submission');
            
            viewSubmissionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const screenshot = this.getAttribute('data-screenshot');
                    const feedback = this.getAttribute('data-feedback');
                    
                    submissionImage.src = screenshot;
                    
                    if (feedback && feedback.trim() !== '') {
                        submissionFeedback.textContent = feedback;
                        submissionFeedbackContainer.style.display = 'block';
                    } else {
                        submissionFeedbackContainer.style.display = 'none';
                    }
                    
                    submissionModal.style.display = 'block';
                });
            });
            
            if (closeSubmissionModal) {
                closeSubmissionModal.addEventListener('click', function() {
                    submissionModal.style.display = 'none';
                });
            }
            
            const codeModal = document.getElementById('code-modal');
            const codeRewardName = document.getElementById('code-reward-name');
            const redemptionCodeDisplay = document.getElementById('redemption-code-display');
            const closeCodeModal = document.getElementById('close-code-modal');
            const viewCodeButtons = document.querySelectorAll('.view-code');
            const copyCodeButton = document.getElementById('copy-code');
            
            viewCodeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const code = this.getAttribute('data-code');
                    const reward = this.getAttribute('data-reward');
                    
                    codeRewardName.textContent = reward;
                    redemptionCodeDisplay.textContent = code;
                    
                    codeModal.style.display = 'block';
                });
            });
            
            if (closeCodeModal) {
                closeCodeModal.addEventListener('click', function() {
                    codeModal.style.display = 'none';
                });
            }
            
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    submissionModal.style.display = 'none';
                    codeModal.style.display = 'none';
                });
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === submissionModal) {
                    submissionModal.style.display = 'none';
                }
                if (event.target === codeModal) {
                    codeModal.style.display = 'none';
                }
            });
            
            if (copyCodeButton) {
                copyCodeButton.addEventListener('click', function() {
                    const code = redemptionCodeDisplay.textContent;
                    
                    navigator.clipboard.writeText(code).then(() => {
                        const originalHTML = copyCodeButton.innerHTML;
                        copyCodeButton.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        
                        setTimeout(() => {
                            copyCodeButton.innerHTML = originalHTML;
                        }, 2000);
                    }).catch(err => {
                        console.error('Could not copy text: ', err);
                    });
                });
            }
            
            const hash = window.location.hash.substring(1);
            if (hash) {
                const tabToActivate = document.querySelector(`.tab[data-tab="${hash}"]`);
                if (tabToActivate) {
                    tabToActivate.click();
                }
            }
        });
    </script>
</body>
</html>