<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$player_rank = $logged_in ? ($_SESSION['player_rank'] ?? 'Rookie') : '';

$game_id = isset($_GET['game']) ? intval($_GET['game']) : 0;
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT c.*, g.name as game_name, g.image as game_image 
          FROM challenges c 
          JOIN games g ON c.game_id = g.id 
          WHERE 1=1";
$params = [];
$types = "";

if ($game_id > 0) {
    $query .= " AND c.game_id = ?";
    $params[] = $game_id;
    $types .= "i";
}

if (!empty($difficulty) && in_array($difficulty, ['Easy', 'Medium', 'Hard'])) {
    $query .= " AND c.difficulty = ?";
    $params[] = $difficulty;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY c.is_featured DESC, c.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$challenges = $stmt->get_result();

$games_result = $conn->query("SELECT * FROM games ORDER BY name ASC");
$games = [];
while ($game = $games_result->fetch_assoc()) {
    $games[] = $game;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Challenges - Game Challenge</title>
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
                <?php if ($logged_in): ?>
                <li><a href="rewards.php">Rewards</a></li>
                <li><a href="ai-chat.php">AI Chat</a></li>
                <?php endif; ?>
                <?php if ($logged_in && $player_rank === 'Admin'): ?>
                <li><a href="admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="user-actions">
            <a href="profile.php" class="profile-link">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="challenges-header">
            <h1>Browse Challenges</h1>
            <p>Find and complete gaming challenges to earn points and rewards!</p>
        </div>
        
        <div class="filters-container">
            <form action="challenges.php" method="get" class="filters-form">
                <div class="filters">
                    <div class="filter-group">
                        <label for="game">Game</label>
                        <select id="game" name="game" onchange="this.form.submit()">
                            <option value="0">All Games</option>
                            <?php foreach ($games as $game): ?>
                            <option value="<?php echo $game['id']; ?>" <?php echo ($game_id == $game['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($game['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="difficulty">Difficulty</label>
                        <select id="difficulty" name="difficulty" onchange="this.form.submit()">
                            <option value="">All Difficulties</option>
                            <option value="Easy" <?php echo ($difficulty == 'Easy') ? 'selected' : ''; ?>>Easy</option>
                            <option value="Medium" <?php echo ($difficulty == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="Hard" <?php echo ($difficulty == 'Hard') ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <label for="search">Search</label>
                        <div class="search-input">
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search challenges...">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if ($game_id > 0 || !empty($difficulty) || !empty($search)): ?>
                <div class="active-filters">
                    <span>Active filters:</span>
                    <?php if ($game_id > 0): ?>
                        <?php foreach ($games as $game): ?>
                            <?php if ($game['id'] == $game_id): ?>
                                <div class="filter-tag">
                                    Game: <?php echo htmlspecialchars($game['name']); ?>
                                    <a href="challenges.php?<?php echo http_build_query(array_merge($_GET, ['game' => 0])); ?>" class="remove-filter">×</a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($difficulty)): ?>
                        <div class="filter-tag">
                            Difficulty: <?php echo htmlspecialchars($difficulty); ?>
                            <a href="challenges.php?<?php echo http_build_query(array_merge($_GET, ['difficulty' => ''])); ?>" class="remove-filter">×</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($search)): ?>
                        <div class="filter-tag">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="challenges.php?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="remove-filter">×</a>
                        </div>
                    <?php endif; ?>
                    
                    <a href="challenges.php" class="clear-filters">Clear all filters</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="challenges-grid">
            <?php if ($challenges->num_rows > 0): ?>
                <?php while ($challenge = $challenges->fetch_assoc()): ?>
                <div class="challenge-card">
                    <div class="challenge-img" style="background-image: url('<?php echo htmlspecialchars($challenge['game_image']); ?>')">
                        <div class="game-tag"><?php echo htmlspecialchars($challenge['game_name']); ?></div>
                        <?php if ($challenge['is_featured']): ?>
                        <div class="featured-tag">Featured</div>
                        <?php endif; ?>
                    </div>
                    <div class="challenge-content">
                        <h3><?php echo htmlspecialchars($challenge['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($challenge['description'], 0, 100) . (strlen($challenge['description']) > 100 ? '...' : '')); ?></p>
                        <div class="challenge-footer">
                            <span class="difficulty <?php echo strtolower($challenge['difficulty']); ?>">
                                <?php echo htmlspecialchars($challenge['difficulty']); ?>
                            </span>
                            <span class="points"><?php echo number_format($challenge['points']); ?> pts</span>
                        </div>
                        
                        <?php
                        $stmt = $conn->prepare("SELECT status FROM submissions WHERE user_id = ? AND challenge_id = ? AND status = 'approved' LIMIT 1");
                        $stmt->bind_param("ii", $user_id, $challenge['id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $completed = $result->num_rows > 0;
                        ?>
                        
                        <a href="challenge.php?id=<?php echo $challenge['id']; ?>" class="btn btn-block <?php echo $completed ? 'btn-secondary' : 'btn-primary'; ?>">
                            <?php if ($completed): ?>
                                <i class="fas fa-check-circle"></i> Completed
                            <?php else: ?>
                                View Challenge
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-challenges">
                    <i class="fas fa-search fa-3x"></i>
                    <h3>No challenges found</h3>
                    <p>Try adjusting your filters or search criteria.</p>
                    <a href="challenges.php" class="btn btn-primary">View All Challenges</a>
                </div>
            <?php endif; ?>
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