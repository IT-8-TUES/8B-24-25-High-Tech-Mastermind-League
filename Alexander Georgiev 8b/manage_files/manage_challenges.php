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
$game_filter = isset($_GET['game']) ? intval($_GET['game']) : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';

$allowed_sort_fields = ['title', 'game_id', 'difficulty', 'points', 'created_at'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

$allowed_sort_dirs = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_dir), $allowed_sort_dirs)) {
    $sort_dir = 'DESC';
}

$query = "
    SELECT c.*, g.name as game_name, g.image as game_image,
           (SELECT COUNT(*) FROM submissions s WHERE s.challenge_id = c.id) as submission_count
    FROM challenges c
    JOIN games g ON c.game_id = g.id
    WHERE 1=1
";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
}

if ($game_filter > 0) {
    $query .= " AND c.game_id = ?";
}

if (!empty($difficulty_filter)) {
    $query .= " AND c.difficulty = ?";
}

$query .= " ORDER BY $sort_by $sort_dir";

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(*) as total FROM challenges c JOIN games g ON c.game_id = g.id WHERE 1=1";

if (!empty($search)) {
    $count_query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
}

if ($game_filter > 0) {
    $count_query .= " AND c.game_id = ?";
}

if (!empty($difficulty_filter)) {
    $count_query .= " AND c.difficulty = ?";
}

$stmt = $conn->prepare($count_query);

$param_types = '';
$params = [];

if (!empty($search)) {
    $param_types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}

if ($game_filter > 0) {
    $param_types .= 'i';
    $params[] = $game_filter;
}

if (!empty($difficulty_filter)) {
    $param_types .= 's';
    $params[] = $difficulty_filter;
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_records = $row['total']; 
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

if ($game_filter > 0) {
    $param_types .= 'i';
    $params[] = $game_filter;
}

if (!empty($difficulty_filter)) {
    $param_types .= 's';
    $params[] = $difficulty_filter;
}

$param_types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$challenges = $stmt->get_result();

$games_query = "SELECT * FROM games ORDER BY name ASC";
$games_result = $conn->query($games_query);
$games = [];
while ($game = $games_result->fetch_assoc()) {
    $games[] = $game;
}

$difficulties = ['Easy', 'Medium', 'Hard', 'Expert'];

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
    <title>Manage Challenges - Game Challenge</title>
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
            <h1>Manage Challenges</h1>
            <p>View and manage gaming challenges</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Challenges</h2>
                <div class="card-actions">
                    <a href="add_challenge.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Challenge
                    </a>
                    <a href="generate_challenge.php" class="btn btn-secondary">
                        <i class="fas fa-magic"></i> Generate Challenge
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="filters">
                    <form action="manage_challenges.php" method="get" class="filter-form">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search by title or description" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <select name="game">
                                <option value="0">All Games</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo $game_filter === $game['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="difficulty">
                                <option value="">All Difficulties</option>
                                <?php foreach ($difficulties as $difficulty): ?>
                                <option value="<?php echo $difficulty; ?>" <?php echo $difficulty_filter === $difficulty ? 'selected' : ''; ?>>
                                    <?php echo $difficulty; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="sort">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title</option>
                                <option value="points" <?php echo $sort_by === 'points' ? 'selected' : ''; ?>>Points</option>
                                <option value="difficulty" <?php echo $sort_by === 'difficulty' ? 'selected' : ''; ?>>Difficulty</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="dir">
                                <option value="DESC" <?php echo $sort_dir === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $sort_dir === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn">Apply Filters</button>
                            <a href="manage_challenges.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Game</th>
                                <th>Difficulty</th>
                                <th>Points</th>
                                <th>Submissions</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($challenges && $challenges->num_rows > 0): ?>
                                <?php while ($challenge = $challenges->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $challenge['id']; ?></td>
                                    <td><?php echo htmlspecialchars($challenge['title']); ?></td>
                                    <td>
                                        <div class="game-item">
                                            <?php if (!empty($challenge['game_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($challenge['game_image']); ?>" alt="<?php echo htmlspecialchars($challenge['game_name']); ?>" class="game-icon">
                                            <?php else: ?>
                                            <div class="game-icon-placeholder">
                                                <i class="fas fa-gamepad"></i>
                                            </div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($challenge['game_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="difficulty <?php echo strtolower($challenge['difficulty']); ?>">
                                            <?php echo $challenge['difficulty']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($challenge['points']); ?></td>
                                    <td><?php echo number_format($challenge['submission_count']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($challenge['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="challenge.php?id=<?php echo $challenge['id']; ?>" class="btn btn-sm" title="View Challenge">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_challenge.php?id=<?php echo $challenge['id']; ?>" class="btn btn-sm" title="Edit Challenge">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_challenge.php?id=<?php echo $challenge['id']; ?>" class="btn btn-sm btn-danger" title="Delete Challenge" onclick="return confirm('Are you sure you want to delete this challenge? This will also delete all submissions for this challenge.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No challenges found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo !empty($difficulty_filter) ? '&difficulty=' . urlencode($difficulty_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo !empty($difficulty_filter) ? '&difficulty=' . urlencode($difficulty_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo !empty($difficulty_filter) ? '&difficulty=' . urlencode($difficulty_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo !empty($difficulty_filter) ? '&difficulty=' . urlencode($difficulty_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo !empty($difficulty_filter) ? '&difficulty=' . urlencode($difficulty_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
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