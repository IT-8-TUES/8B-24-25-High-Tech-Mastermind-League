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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$game_filter = isset($_GET['game']) ? intval($_GET['game']) : 0;
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';

$allowed_sort_fields = ['created_at', 'status', 'username', 'challenge_title', 'game_name'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

$allowed_sort_dirs = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_dir), $allowed_sort_dirs)) {
    $sort_dir = 'DESC';
}

if ($sort_by === 'created_at') {
    $sort_by = 's.created_at';
} elseif ($sort_by === 'status') {
    $sort_by = 's.status';
} elseif ($sort_by === 'username') {
    $sort_by = 'u.username';
} elseif ($sort_by === 'challenge_title') {
    $sort_by = 'c.title';
} elseif ($sort_by === 'game_name') {
    $sort_by = 'g.name';
}

$query = "
    SELECT s.*, u.username, c.title as challenge_title, g.name as game_name, g.image as game_image
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN challenges c ON s.challenge_id = c.id
    JOIN games g ON c.game_id = g.id
    WHERE 1=1
";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (u.username LIKE ? OR c.title LIKE ?)";
}

if (!empty($status_filter)) {
    $query .= " AND s.status = ?";
}

if ($game_filter > 0) {
    $query .= " AND g.id = ?";
}

if ($user_filter > 0) {
    $query .= " AND u.id = ?";
}

$query .= " ORDER BY $sort_by $sort_dir";

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = "
    SELECT COUNT(*) as total 
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN challenges c ON s.challenge_id = c.id
    JOIN games g ON c.game_id = g.id
    WHERE 1=1
";

if (!empty($search)) {
    $count_query .= " AND (u.username LIKE ? OR c.title LIKE ?)";
}

if (!empty($status_filter)) {
    $count_query .= " AND s.status = ?";
}

if ($game_filter > 0) {
    $count_query .= " AND g.id = ?";
}

if ($user_filter > 0) {
    $count_query .= " AND u.id = ?";
}

$stmt = $conn->prepare($count_query);

$param_types = '';
$params = [];

if (!empty($search)) {
    $param_types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($status_filter)) {
    $param_types .= 's';
    $params[] = $status_filter;
}

if ($game_filter > 0) {
    $param_types .= 'i';
    $params[] = $game_filter;
}

if ($user_filter > 0) {
    $param_types .= 'i';
    $params[] = $user_filter;
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

if (!empty($status_filter)) {
    $param_types .= 's';
    $params[] = $status_filter;
}

if ($game_filter > 0) {
    $param_types .= 'i';
    $params[] = $game_filter;
}

if ($user_filter > 0) {
    $param_types .= 'i';
    $params[] = $user_filter;
}

$param_types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$submissions = $stmt->get_result();

$games_query = "SELECT * FROM games ORDER BY name ASC";
$games_result = $conn->query($games_query);
$games = [];
while ($game = $games_result->fetch_assoc()) {
    $games[] = $game;
}

$statuses = ['pending', 'approved', 'rejected'];

$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$filtered_user = null;
if ($user_filter > 0) {
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_filter);
    $user_stmt->execute();
    $filtered_user = $user_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Submissions - Game Challenge</title>
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
            <h1>Manage Submissions</h1>
            <p>View and verify challenge submissions</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($filtered_user): ?>
        <div class="user-filter-banner">
            <div class="user-filter-info">
                <div class="user-avatar">
                    <?php if (!empty($filtered_user['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($filtered_user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($filtered_user['username']); ?>">
                    <?php else: ?>
                    <div class="avatar-placeholder"><?php echo strtoupper(substr($filtered_user['username'], 0, 1)); ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h3>Viewing submissions for: <?php echo htmlspecialchars($filtered_user['username']); ?></h3>
                    <div class="user-stats">
                        <span class="badge <?php echo strtolower($filtered_user['player_rank']); ?>"><?php echo $filtered_user['player_rank']; ?></span>
                        <span><?php echo number_format($filtered_user['score']); ?> points</span>
                    </div>
                </div>
                <a href="manage_submissions.php" class="btn">Clear Filter</a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Submissions</h2>
                <?php if ($status_filter === 'pending'): ?>
                <div class="card-actions">
                    <a href="#" class="btn btn-primary" onclick="batchVerify('approve'); return false;">
                        <i class="fas fa-check"></i> Approve Selected
                    </a>
                    <a href="#" class="btn btn-danger" onclick="batchVerify('reject'); return false;">
                        <i class="fas fa-times"></i> Reject Selected
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="filters">
                    <form action="manage_submissions.php" method="get" class="filter-form">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search by username or challenge" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <select name="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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
                            <select name="sort">
                                <option value="created_at" <?php echo $sort_by === 's.created_at' ? 'selected' : ''; ?>>Date</option>
                                <option value="status" <?php echo $sort_by === 's.status' ? 'selected' : ''; ?>>Status</option>
                                <option value="username" <?php echo $sort_by === 'u.username' ? 'selected' : ''; ?>>Username</option>
                                <option value="challenge_title" <?php echo $sort_by === 'c.title' ? 'selected' : ''; ?>>Challenge</option>
                                <option value="game_name" <?php echo $sort_by === 'g.name' ? 'selected' : ''; ?>>Game</option>
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
                            <a href="manage_submissions.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <form id="batchForm" action="batch_verify.php" method="post">
                    <input type="hidden" id="batch_action" name="action" value="">
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <?php if ($status_filter === 'pending'): ?>
                                    <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                                    <?php endif; ?>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Challenge</th>
                                    <th>Game</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($submissions && $submissions->num_rows > 0): ?>
                                    <?php while ($submission = $submissions->fetch_assoc()): ?>
                                    <tr>
                                        <?php if ($status_filter === 'pending'): ?>
                                        <td>
                                            <input type="checkbox" name="submission_ids[]" value="<?php echo $submission['id']; ?>" class="submission-checkbox">
                                        </td>
                                        <?php endif; ?>
                                        <td><?php echo $submission['id']; ?></td>
                                        <td><?php echo htmlspecialchars($submission['username']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['challenge_title']); ?></td>
                                        <td>
                                            <div class="game-item">
                                                <?php if (!empty($submission['game_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($submission['game_image']); ?>" alt="<?php echo htmlspecialchars($submission['game_name']); ?>" class="game-icon">
                                                <?php else: ?>
                                                <div class="game-icon-placeholder">
                                                    <i class="fas fa-gamepad"></i>
                                                </div>
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($submission['game_name']); ?></span>
                                            </div>
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
                                        <td><?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="btn btn-sm" title="View Submission">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($submission['status'] === 'pending'): ?>
                                                <a href="verify_submission.php?id=<?php echo $submission['id']; ?>&action=approve" class="btn btn-sm btn-success" title="Approve" onclick="return confirm('Are you sure you want to approve this submission?');">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="verify_submission.php?id=<?php echo $submission['id']; ?>&action=reject" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Are you sure you want to reject this submission?');">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $status_filter === 'pending' ? '8' : '7'; ?>" class="text-center">No submissions found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id=' . $user_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id=' . $user_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id=' . $user_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id=' . $user_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $game_filter > 0 ? '&game=' . $game_filter : ''; ?><?php echo $user_filter > 0 ? '&user_id=' . $user_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
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
    
    <script>
        function toggleSelectAll() {
            var selectAll = document.getElementById('selectAll');
            var checkboxes = document.getElementsByClassName('submission-checkbox');
            
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = selectAll.checked;
            }
        }
        
        function batchVerify(action) {
            var checkboxes = document.getElementsByClassName('submission-checkbox');
            var selected = false;
            
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) {
                    selected = true;
                    break;
                }
            }
            
            if (!selected) {
                alert('Please select at least one submission.');
                return;
            }
            
            if (confirm('Are you sure you want to ' + action + ' the selected submissions?')) {
                document.getElementById('batch_action').value = action;
                document.getElementById('batchForm').submit();
            }
        }
    </script>
</body>
</html>