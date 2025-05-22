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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sort_dir = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';

$allowed_sort_fields = ['name', 'created_at', 'challenge_count'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'name';
}

$allowed_sort_dirs = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_dir), $allowed_sort_dirs)) {
    $sort_dir = 'ASC';
}

$query = "
    SELECT g.*,
           (SELECT COUNT(*) FROM challenges c WHERE c.game_id = g.id) as challenge_count
    FROM games g
    WHERE 1=1
";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (g.name LIKE ? OR g.description LIKE ?)";
}

if ($sort_by === 'challenge_count') {
    $query .= " ORDER BY challenge_count $sort_dir, name ASC";
} else {
    $query .= " ORDER BY $sort_by $sort_dir";
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = str_replace("SELECT g.*,\n           (SELECT COUNT(*) FROM challenges c WHERE c.game_id = g.id) as challenge_count", "SELECT COUNT(*) as count", $query);
$stmt = $conn->prepare($count_query);

$param_types = '';
$params = [];

if (!empty($search)) {
    $param_types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_records / $per_page);

$query .= " LIMIT ?, ?";
$stmt = $conn->prepare($query);

$param_types = '';
$params = [];

if (!empty($search)) {
    $param_types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}

$param_types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$games = $stmt->get_result();

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
    <title>Manage Games - Game Challenge</title>
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
            <h1>Manage Games</h1>
            <p>View and manage games for challenges</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Games</h2>
                <div class="card-actions">
                    <a href="add_game.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Game
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="filters">
                    <form action="manage_games.php" method="get" class="filter-form">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search by name or description" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <select name="sort">
                                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                                <option value="challenge_count" <?php echo $sort_by === 'challenge_count' ? 'selected' : ''; ?>>Challenge Count</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="dir">
                                <option value="ASC" <?php echo $sort_dir === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo $sort_dir === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn">Apply Filters</button>
                            <a href="manage_games.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <div class="games-grid">
                    <?php if ($games && $games->num_rows > 0): ?>
                        <?php while ($game = $games->fetch_assoc()): ?>
                        <div class="game-card">
                            <div class="game-header">
                                <?php if (!empty($game['image'])): ?>
                                <img src="<?php echo htmlspecialchars($game['image']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>" class="game-image">
                                <?php else: ?>
                                <div class="game-image-placeholder">
                                    <i class="fas fa-gamepad"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="game-body">
                                <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                                <div class="game-stats">
                                    <div class="game-stat">
                                        <i class="fas fa-trophy"></i>
                                        <span><?php echo number_format($game['challenge_count']); ?> Challenges</span>
                                    </div>
                                    <div class="game-stat">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Added <?php echo date('M j, Y', strtotime($game['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="game-description">
                                    <?php echo nl2br(htmlspecialchars(substr($game['description'] ?? '', 0, 100) . (strlen($game['description'] ?? '') > 100 ? '...' : ''))); ?>
                                </div>
                                <div class="game-actions">
                                    <a href="challenges.php?game=<?php echo $game['id']; ?>" class="btn btn-sm">
                                        <i class="fas fa-trophy"></i> View Challenges
                                    </a>
                                    <a href="edit_game.php?id=<?php echo $game['id']; ?>" class="btn btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="delete_game.php?id=<?php echo $game['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this game? This will also delete all challenges for this game.');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-gamepad fa-3x"></i>
                            <p>No games found.</p>
                            <a href="add_game.php" class="btn btn-primary">Add Game</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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