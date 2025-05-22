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
$rank_filter = isset($_GET['rank']) ? $_GET['rank'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';

$allowed_sort_fields = ['username', 'email', 'player_rank', 'score', 'created_at'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

$allowed_sort_dirs = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_dir), $allowed_sort_dirs)) {
    $sort_dir = 'DESC';
}

$query = "SELECT * FROM users WHERE 1=1";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (username LIKE ? OR email LIKE ?)";
}

if (!empty($rank_filter)) {
    $query .= " AND player_rank = ?";
}

$query .= " ORDER BY $sort_by $sort_dir";


$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = str_replace("SELECT *", "SELECT COUNT(*) as count", $query);
$stmt = $conn->prepare($count_query);

$param_types = '';
$params = [];

if (!empty($search)) {
    $param_types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($rank_filter)) {
    $param_types .= 's';
    $params[] = $rank_filter;
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

if (!empty($rank_filter)) {
    $param_types .= 's';
    $params[] = $rank_filter;
}

$param_types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$users = $stmt->get_result();

$ranks_query = "SELECT DISTINCT player_rank FROM users ORDER BY 
    CASE 
        WHEN player_rank = 'Admin' THEN 1
        WHEN player_rank = 'Diamond' THEN 2
        WHEN player_rank = 'Platinum' THEN 3
        WHEN player_rank = 'Gold' THEN 4
        WHEN player_rank = 'Silver' THEN 5
        WHEN player_rank = 'Bronze' THEN 6
        WHEN player_rank = 'Rookie' THEN 7
        ELSE 8
    END";
$ranks_result = $conn->query($ranks_query);
$ranks = [];
while ($rank = $ranks_result->fetch_assoc()) {
    $ranks[] = $rank['player_rank'];
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
    <title>Manage Users - Game Challenge</title>
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
            <h1>Manage Users</h1>
            <p>View and manage user accounts</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Users</h2>
                <div class="card-actions">
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="filters">
                    <form action="manage_users.php" method="get" class="filter-form">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search by username or email" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <select name="rank">
                                <option value="">All Ranks</option>
                                <?php foreach ($ranks as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo $rank_filter === $r ? 'selected' : ''; ?>>
                                    <?php echo $r; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="sort">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Join Date</option>
                                <option value="username" <?php echo $sort_by === 'username' ? 'selected' : ''; ?>>Username</option>
                                <option value="score" <?php echo $sort_by === 'score' ? 'selected' : ''; ?>>Score</option>
                                <option value="player_rank" <?php echo $sort_by === 'player_rank' ? 'selected' : ''; ?>>Rank</option>
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
                            <a href="manage_users.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Rank</th>
                                <th>Score</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-item">
                                            <?php if (!empty($user['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
                                            <?php else: ?>
                                            <div class="user-avatar-placeholder">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($user['player_rank']); ?>">
                                            <?php echo $user['player_rank']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($user['score']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm" title="View User">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != $user_id): // Don't allow deleting yourself ?>
                                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Delete User" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($rank_filter) ? '&rank=' . urlencode($rank_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($rank_filter) ? '&rank=' . urlencode($rank_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($rank_filter) ? '&rank=' . urlencode($rank_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($rank_filter) ? '&rank=' . urlencode($rank_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($rank_filter) ? '&rank=' . urlencode($rank_filter) : ''; ?><?php echo '&sort=' . $sort_by . '&dir=' . $sort_dir; ?>" class="pagination-link">
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