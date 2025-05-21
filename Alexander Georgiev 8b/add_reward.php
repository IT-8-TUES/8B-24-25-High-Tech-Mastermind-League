<?php
require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['player_rank']) || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$games = $conn->query("SELECT * FROM games ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $points_cost = intval($_POST['points_cost']);
    $stock = isset($_POST['unlimited_stock']) ? -1 : intval($_POST['stock']);
    $game_id = !empty($_POST['game_id']) ? intval($_POST['game_id']) : null;
    $image = trim($_POST['image']);
    
    if (empty($name) || empty($description) || $points_cost <= 0) {
        $error = "Name, description, and points cost are required. Points cost must be greater than zero.";
    } else {
        if ($game_id) {
            $stmt = $conn->prepare("
                INSERT INTO rewards (name, description, points_cost, stock, game_id, image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssiiss", $name, $description, $points_cost, $stock, $game_id, $image);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO rewards (name, description, points_cost, stock, image)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssiis", $name, $description, $points_cost, $stock, $image);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Reward created successfully!";
            $_SESSION['message_type'] = 'success';
            header("Location: manage_rewards.php");
            exit;
        } else {
            $error = "Error creating reward: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Reward - Game Challenge</title>
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
            <h1>Add New Reward</h1>
            <p>Create a new reward for players to redeem</p>
        </div>
        
        <div class="container">
            <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Reward Details</h2>
                </div>
                <div class="card-body">
                    <form action="add_reward.php" method="post">
                        <div class="form-group">
                            <label for="name">Reward Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-hint">Describe what the player will receive when redeeming this reward.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="points_cost">Points Cost</label>
                            <input type="number" id="points_cost" name="points_cost" value="<?php echo htmlspecialchars($_POST['points_cost'] ?? '500'); ?>" min="1" required>
                            <div class="form-hint">How many points a player needs to redeem this reward.</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="unlimited_stock" id="unlimited_stock" <?php echo (isset($_POST['unlimited_stock'])) ? 'checked' : ''; ?>>
                                Unlimited Stock
                            </label>
                            <div class="form-hint">Check this if the reward has unlimited availability.</div>
                        </div>
                        
                        <div class="form-group" id="stock_group">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" id="stock" name="stock" value="<?php echo htmlspecialchars($_POST['stock'] ?? '10'); ?>" min="1">
                            <div class="form-hint">How many of this reward are available.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="game_id">Game (Optional)</label>
                            <select id="game_id" name="game_id">
                                <option value="">Not game-specific</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo (isset($_POST['game_id']) && $_POST['game_id'] == $game['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Select a game if this reward is specific to a particular game.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="image">Image URL (Optional)</label>
                            <input type="text" id="image" name="image" value="<?php echo htmlspecialchars($_POST['image'] ?? ''); ?>">
                            <div class="form-hint">URL to an image representing this reward. Leave blank to use the default image.</div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="manage_rewards.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Reward</button>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const unlimitedCheckbox = document.getElementById('unlimited_stock');
            const stockGroup = document.getElementById('stock_group');
            
            function toggleStockField() {
                if (unlimitedCheckbox.checked) {
                    stockGroup.style.display = 'none';
                } else {
                    stockGroup.style.display = 'block';
                }
            }
            
            unlimitedCheckbox.addEventListener('change', toggleStockField);
            toggleStockField();
        });
    </script>
</body>
</html>