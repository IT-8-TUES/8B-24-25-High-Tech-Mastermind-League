<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'ai_prompt'");
$ai_prompt_exists = $result->num_rows > 0;

$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: admin.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->bind_param("i", $challenge_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin.php");
    exit;
}

$challenge = $result->fetch_assoc();

$games = $conn->query("SELECT * FROM games ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $game_id = intval($_POST['game_id']);
    $difficulty = $_POST['difficulty'];
    $points = intval($_POST['points']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    if (empty($title) || empty($description) || empty($requirements) || $game_id <= 0 || empty($difficulty) || $points <= 0) {
        $error = "All fields are required and points must be greater than zero.";
    } else {
        if ($ai_prompt_exists) {
            $ai_prompt = trim($_POST['ai_prompt'] ?? '');
            $stmt = $conn->prepare("
                UPDATE challenges 
                SET title = ?, description = ?, requirements = ?, game_id = ?, 
                    difficulty = ?, points = ?, is_featured = ?, ai_prompt = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("sssisiiis", $title, $description, $requirements, $game_id, 
                            $difficulty, $points, $is_featured, $ai_prompt, $challenge_id);
        } else {
            $stmt = $conn->prepare("
                UPDATE challenges 
                SET title = ?, description = ?, requirements = ?, game_id = ?, 
                    difficulty = ?, points = ?, is_featured = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssisiii", $title, $description, $requirements, $game_id, 
                            $difficulty, $points, $is_featured, $challenge_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Challenge updated successfully!";
            $_SESSION['message_type'] = 'success';
            header("Location: admin.php");
            exit;
        } else {
            $error = "Error updating challenge: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Challenge - Game Challenge</title>
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
            <h1>Edit Challenge</h1>
            <p>Update challenge details</p>
        </div>
        
        <div class="container">
            <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Challenge Details</h2>
                </div>
                <div class="card-body">
                    <form action="edit_challenge.php?id=<?php echo $challenge_id; ?>" method="post">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($challenge['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($challenge['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="requirements">Requirements</label>
                            <textarea id="requirements" name="requirements" rows="4" required><?php echo htmlspecialchars($challenge['requirements']); ?></textarea>
                            <div class="form-hint">List the specific requirements players need to meet to complete this challenge.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="game_id">Game</label>
                            <select id="game_id" name="game_id" required>
                                <option value="">Select a game</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo ($challenge['game_id'] == $game['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="difficulty">Difficulty</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="Easy" <?php echo ($challenge['difficulty'] == 'Easy') ? 'selected' : ''; ?>>Easy</option>
                                <option value="Medium" <?php echo ($challenge['difficulty'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="Hard" <?php echo ($challenge['difficulty'] == 'Hard') ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" value="<?php echo $challenge['points']; ?>" min="1" required>
                            <div class="form-hint">Points awarded for completing this challenge.</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_featured" <?php echo ($challenge['is_featured'] == 1) ? 'checked' : ''; ?>>
                                Feature this challenge on the homepage
                            </label>
                        </div>
                        
                        <?php if ($ai_prompt_exists): ?>
                        <div class="form-group">
                            <label for="ai_prompt">AI Prompt (Optional)</label>
                            <textarea id="ai_prompt" name="ai_prompt" rows="3"><?php echo htmlspecialchars($challenge['ai_prompt'] ?? ''); ?></textarea>
                            <div class="form-hint">Custom prompt for the AI assistant when helping users with this challenge.</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <a href="admin.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Challenge</button>
                        </div>
                    </form>
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