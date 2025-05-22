<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $message = "Game name is required.";
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM games WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "A game with this name already exists.";
            $messageType = 'error';
        } else {
            $image_path = '';
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024;
                
                if (!in_array($_FILES['image']['type'], $allowed_types)) {
                    $message = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
                    $messageType = 'error';
                } elseif ($_FILES['image']['size'] > $max_size) {
                    $message = "File is too large. Maximum size is 5MB.";
                    $messageType = 'error';
                } else {
                    if (!file_exists('images/games')) {
                        mkdir('images/games', 0755, true);
                    }
                    
                    $filename = uniqid('game_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $image_path = 'images/games/' . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                    } else {
                        $message = "Error uploading image. Please try again.";
                        $messageType = 'error';
                        $image_path = '';
                    }
                }
            }
            
            if (empty($messageType)) {
                $stmt = $conn->prepare("INSERT INTO games (name, description, image, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("sss", $name, $description, $image_path);
                
                if ($stmt->execute()) {
                    $message = "Game added successfully!";
                    $messageType = 'success';
                    
                    $name = '';
                    $description = '';
                } else {
                    $message = "Error adding game: " . $stmt->error;
                    $messageType = 'error';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Game - Game Challenge</title>
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
                    <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="badge admin">Admin</span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="page-header">
            <h1>Add New Game</h1>
            <p>Add a new game to the challenge platform</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Game Details</h2>
            </div>
            <div class="card-body">
                <form action="add_game.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Game Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        <p class="form-hint">Provide a brief description of the game.</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Game Image</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                        <p class="form-hint">Recommended size: 800x450 pixels. Max file size: 5MB.</p>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin.php" class="btn">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Game</button>
                    </div>
                </form>
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
