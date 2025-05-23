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

$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($submission_id <= 0) {
    header("Location: manage_submissions.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT s.*, 
           c.title as challenge_title, c.description as challenge_description, 
           c.requirements, c.difficulty, c.points,
           u.username, u.player_rank, u.profile_picture,
           g.name as game_name, g.image as game_image
    FROM submissions s
    JOIN challenges c ON s.challenge_id = c.id
    JOIN users u ON s.user_id = u.id
    JOIN games g ON c.game_id = g.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_submissions.php");
    exit;
}

$submission = $result->fetch_assoc();

$stmt = $conn->prepare("
    SELECT * FROM submissions 
    WHERE user_id = ? AND challenge_id = ? AND id != ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("iii", $submission['user_id'], $submission['challenge_id'], $submission_id);
$stmt->execute();
$other_submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Review Submission - Game Challenge</title>
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
            <h1>Review Submission</h1>
            <p>Review and verify user challenge submission</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>Submission Details</h2>
                </div>
                <div class="card-body">
                    <div class="submission-details">
                        <div class="submission-meta">
                            <div class="submission-meta-item">
                                <strong>Challenge:</strong> <?php echo htmlspecialchars($submission['challenge_title']); ?>
                            </div>
                            <div class="submission-meta-item">
                                <strong>Game:</strong> <?php echo htmlspecialchars($submission['game_name']); ?>
                            </div>
                            <div class="submission-meta-item">
                                <strong>Difficulty:</strong> 
                                <span class="difficulty <?php echo strtolower($submission['difficulty']); ?>">
                                    <?php echo htmlspecialchars($submission['difficulty']); ?>
                                </span>
                            </div>
                            <div class="submission-meta-item">
                                <strong>Points:</strong> <?php echo number_format($submission['points']); ?>
                            </div>
                            <div class="submission-meta-item">
                                <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?>
                            </div>
                            <div class="submission-meta-item">
                                <strong>Status:</strong> 
                                <?php if ($submission['status'] === 'approved'): ?>
                                    <span class="status approved">Approved</span>
                                <?php elseif ($submission['status'] === 'rejected'): ?>
                                    <span class="status rejected">Rejected</span>
                                <?php else: ?>
                                    <span class="status pending">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="submission-requirements">
                            <h3>Challenge Requirements</h3>
                            <div class="requirements">
                                <?php echo nl2br(htmlspecialchars($submission['requirements'])); ?>
                            </div>
                        </div>
                        
                        <div class="submission-image">
                            <h3>Submission Screenshot</h3>
                            <img src="<?php echo htmlspecialchars($submission['screenshot_path']); ?>" alt="Submission Screenshot" class="full-width-image">
                        </div>
                        
                        <?php if (!empty($submission['feedback'])): ?>
                        <div class="submission-feedback">
                            <h3>Feedback</h3>
                            <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($submission['status'] === 'pending'): ?>
                        <div class="submission-actions">
                            <h3>Review Actions</h3>
                            <form action="verify_submission.php" method="post">
                                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                                
                                <div class="form-group">
                                    <label for="feedback">Feedback (optional)</label>
                                    <textarea id="feedback" name="feedback" rows="4" placeholder="Provide feedback to the user about their submission"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject Submission
                                    </button>
                                    <button type="submit" name="action" value="approve" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Approve Submission
                                    </button>
                                    <button type="submit" name="action" value="auto_verify" class="btn btn-secondary">
                                        <i class="fas fa-robot"></i> Auto-Verify with AI
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>User Information</h2>
                </div>
                <div class="card-body">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php if (!empty($submission['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($submission['profile_picture']); ?>" alt="<?php echo htmlspecialchars($submission['username']); ?>">
                            <?php else: ?>
                            <div class="avatar-placeholder"><?php echo strtoupper(substr($submission['username'], 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-info">
                            <h3><?php echo htmlspecialchars($submission['username']); ?></h3>
                            <div class="user-rank">
                                <span class="badge <?php echo strtolower($submission['player_rank']); ?>"><?php echo $submission['player_rank']; ?></span>
                            </div>
                            
                            <div class="user-stats">
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT 
                                        COUNT(DISTINCT s.challenge_id) as challenges_completed,
                                        SUM(s.points_awarded) as points_earned
                                    FROM submissions s
                                    WHERE s.user_id = ? AND s.status = 'approved'
                                ");
                                $stmt->bind_param("i", $submission['user_id']);
                                $stmt->execute();
                                $user_stats = $stmt->get_result()->fetch_assoc();
                                ?>
                                
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($user_stats['challenges_completed'] ?? 0); ?></div>
                                    <div class="stat-label">Challenges</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($user_stats['points_earned'] ?? 0); ?></div>
                                    <div class="stat-label">Points</div>
                                </div>
                            </div>
                            
                            <div class="user-actions">
                                <a href="view_user.php?id=<?php echo $submission['user_id']; ?>" class="btn btn-sm">
                                    <i class="fas fa-user"></i> View Profile
                                </a>
                                <a href="manage_submissions.php?user_id=<?php echo $submission['user_id']; ?>" class="btn btn-sm">
                                    <i class="fas fa-list"></i> View Submissions
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($other_submissions) > 0): ?>
                    <div class="other-submissions">
                        <h3>Other Submissions for this Challenge</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($other_submissions as $other): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($other['created_at'])); ?></td>
                                        <td>
                                            <?php if ($other['status'] === 'approved'): ?>
                                                <span class="status approved">Approved</span>
                                            <?php elseif ($other['status'] === 'rejected'): ?>
                                                <span class="status rejected">Rejected</span>
                                            <?php else: ?>
                                                <span class="status pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_submission.php?id=<?php echo $other['id']; ?>" class="btn btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
</body>
</html>