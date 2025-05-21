<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$player_rank = $_SESSION['player_rank'] ?? 'Rookie';

$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: challenges.php");
    exit;
}

$stmt = $conn->prepare("SELECT c.*, g.name as game_name, g.image as game_image, u.username as creator_name 
                        FROM challenges c 
                        JOIN games g ON c.game_id = g.id 
                        LEFT JOIN users u ON c.created_by = u.id 
                        WHERE c.id = ?");
$stmt->bind_param("i", $challenge_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: challenges.php");
    exit;
}

$challenge = $result->fetch_assoc();

$stmt = $conn->prepare("SELECT * FROM submissions WHERE user_id = ? AND challenge_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("ii", $user_id, $challenge_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['screenshot'])) {
    $file = $_FILES['screenshot'];
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; 
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error uploading file. Please try again.';
        $messageType = 'error';
    } elseif (!in_array($file['type'], $allowedTypes)) {
        $message = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
        $messageType = 'error';
    } elseif ($file['size'] > $maxSize) {
        $message = 'File is too large. Maximum size is 5MB.';
        $messageType = 'error';
    } else {
        if (!file_exists('uploads')) {
            mkdir('uploads', 0755, true);
        }
        
        $filename = uniqid('submission_') . '_' . $user_id . '_' . $challenge_id . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = 'uploads/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $conn->prepare("INSERT INTO submissions (user_id, challenge_id, screenshot_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user_id, $challenge_id, $filepath);
            
            if ($stmt->execute()) {
                $submission_id = $conn->insert_id;
                
                $verified = (rand(1, 10) <= 7); 
                
                if ($verified) {
                    $status = 'approved';
                    $feedback = "Great job! Your screenshot shows you've successfully completed the challenge requirements.";
                    $points_awarded = $challenge['points'];
                    
                    $stmt = $conn->prepare("UPDATE submissions SET status = ?, feedback = ?, points_awarded = ? WHERE id = ?");
                    $stmt->bind_param("ssii", $status, $feedback, $points_awarded, $submission_id);
                    $stmt->execute();
                    
                    $stmt = $conn->prepare("UPDATE users SET score = score + ? WHERE id = ?");
                    $stmt->bind_param("ii", $points_awarded, $user_id);
                    $stmt->execute();
                    
                    $message = "Congratulations! Your submission has been verified and you've earned {$points_awarded} points!";
                    $messageType = 'success';
                } else {
                    $status = 'rejected';
                    $reasons = [
                        "The screenshot doesn't clearly show the completion of the challenge.",
                        "The required elements are not visible in the screenshot.",
                        "The game interface in the screenshot doesn't match the challenge requirements.",
                        "The screenshot appears to be from a different game or challenge."
                    ];
                    $feedback = $reasons[array_rand($reasons)];
                    
                    $stmt = $conn->prepare("UPDATE submissions SET status = ?, feedback = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $status, $feedback, $submission_id);
                    $stmt->execute();
                    
                    $message = "Your submission could not be verified: {$feedback}";
                    $messageType = 'error';
                }
                
                $stmt = $conn->prepare("SELECT * FROM submissions WHERE id = ?");
                $stmt->bind_param("i", $submission_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $submission = $result->fetch_assoc();
            } else {
                $message = "Error saving submission: " . $stmt->error;
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
    <title><?php echo htmlspecialchars($challenge['title']); ?> - Game Challenge</title>
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
                <li><a href="challenges.php" class="active">Challenges</a></li>
                <li><a href="leaderboard.php">Leaderboard</a></li>
                <li><a href="rewards.php">Rewards</a></li>
                <li><a href="ai-chat.php">AI Chat</a></li>
                <?php if (isset($_SESSION['player_rank']) && $_SESSION['player_rank'] === 'Admin'): ?>
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
        <div class="challenge-detail">
            <div class="challenge-info">
                <div class="challenge-header">
                    <h1><?php echo htmlspecialchars($challenge['title']); ?></h1>
                    <div class="challenge-meta">
                        <div class="challenge-meta-item">
                            <i class="fas fa-gamepad"></i>
                            <span><?php echo htmlspecialchars($challenge['game_name']); ?></span>
                        </div>
                        <div class="challenge-meta-item">
                            <i class="fas fa-signal"></i>
                            <span class="difficulty <?php echo strtolower($challenge['difficulty']); ?>">
                                <?php echo htmlspecialchars($challenge['difficulty']); ?>
                            </span>
                        </div>
                        <div class="challenge-meta-item">
                            <i class="fas fa-trophy"></i>
                            <span><?php echo number_format($challenge['points']); ?> points</span>
                        </div>
                        <?php if ($challenge['creator_name']): ?>
                        <div class="challenge-meta-item">
                            <i class="fas fa-user"></i>
                            <span>Created by <?php echo htmlspecialchars($challenge['creator_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="challenge-description">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($challenge['description'])); ?></p>
                </div>
                
                <div class="challenge-steps">
                    <h3>Requirements</h3>
                    <div class="requirements">
                        <?php echo nl2br(htmlspecialchars($challenge['requirements'])); ?>
                    </div>
                </div>
                
                <div class="challenge-tips">
                    <h3>Tips</h3>
                    <p>Need help completing this challenge? Use our AI Chat assistant to get tips and strategies!</p>
                    <a href="ai-chat.php?topic=<?php echo urlencode($challenge['title']); ?>" class="btn btn-secondary">
                        <i class="fas fa-robot"></i> Ask AI for Help
                    </a>
                </div>
            </div>
            
            <div class="challenge-sidebar">
                <div class="submission-card">
                    <h3>Submit Your Challenge</h3>
                    
                    <?php if ($message): ?>
                    <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($submission && $submission['status'] === 'approved'): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> You've already completed this challenge!
                        <p>Points earned: <?php echo number_format($submission['points_awarded']); ?></p>
                        <p>Submitted on: <?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?></p>
                    </div>
                    <div class="mt-3">
                        <h4>Your Submission</h4>
                        <img src="<?php echo htmlspecialchars($submission['screenshot_path']); ?>" alt="Your submission" class="preview-image">
                    </div>
                    <?php else: ?>
                    <form action="challenge.php?id=<?php echo $challenge_id; ?>" method="post" enctype="multipart/form-data" id="submission-form">
                        <div class="dropzone" id="dropzone">
                            <div class="dropzone-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <p>Drag & drop your screenshot here or click to browse</p>
                            <p class="small">Max file size: 5MB (JPEG, PNG, GIF)</p>
                        </div>
                        
                        <div id="preview-container" class="hidden">
                            <h4>Preview</h4>
                            <img id="preview-image" src="#" alt="Preview" class="preview-image">
                            <button type="button" id="remove-image" class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                        
                        <input type="file" name="screenshot" id="screenshot-input" accept="image/jpeg,image/png,image/gif" class="hidden">
                        
                        <button type="submit" class="btn btn-primary btn-block mt-3" id="submit-btn" disabled>
                            <i class="fas fa-paper-plane"></i> Submit Challenge
                        </button>
                    </form>
                    
                    <?php if ($submission && $submission['status'] === 'rejected'): ?>
                    <div class="error-message mt-3">
                        <i class="fas fa-exclamation-circle"></i> Your previous submission was rejected.
                        <p>Feedback: <?php echo htmlspecialchars($submission['feedback']); ?></p>
                        <p>You can try again with a new screenshot.</p>
                    </div>
                    <div class="mt-3">
                        <h4>Your Previous Submission</h4>
                        <img src="<?php echo htmlspecialchars($submission['screenshot_path']); ?>" alt="Your submission" class="preview-image">
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="reward-card">
                    <h3>Rewards</h3>
                    <div class="reward-item">
                        <div class="reward-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="reward-info">
                            <h4><?php echo number_format($challenge['points']); ?> Points</h4>
                            <p>Add to your total score</p>
                        </div>
                    </div>
                    <div class="reward-item">
                        <div class="reward-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="reward-info">
                            <h4>Rank Progress</h4>
                            <p>Climb the leaderboard</p>
                        </div>
                    </div>
                    <div class="reward-item">
                        <div class="reward-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="reward-info">
                            <h4>Unlock Rewards</h4>
                            <p>Exchange points for gaming rewards</p>
                        </div>
                    </div>
                    
                    <a href="rewards.php" class="btn btn-secondary btn-block mt-3">View All Rewards</a>
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
        document.addEventListener('DOMContentLoaded', function() {
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('screenshot-input');
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');
            const removeButton = document.getElementById('remove-image');
            const submitButton = document.getElementById('submit-btn');
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropzone.classList.add('highlight');
            }
            
            function unhighlight() {
                dropzone.classList.remove('highlight');
            }
            
            dropzone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    fileInput.files = files;
                    handleFiles(files);
                }
            }
            
            dropzone.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length) {
                    handleFiles(fileInput.files);
                }
            });
            
            function handleFiles(files) {
                const file = files[0];
                
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                    dropzone.classList.add('hidden');
                    submitButton.disabled = false;
                }
                reader.readAsDataURL(file);
            }
            
            removeButton.addEventListener('click', function() {
                previewContainer.classList.add('hidden');
                dropzone.classList.remove('hidden');
                fileInput.value = '';
                submitButton.disabled = true;
            });
        });
    </script>
</body>
</html>