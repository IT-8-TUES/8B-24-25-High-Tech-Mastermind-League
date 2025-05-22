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
$reward_filter = isset($_GET['reward']) ? intval($_GET['reward']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';

$allowed_sort_fields = ['created_at', 'status', 'username', 'reward_name', 'points_cost'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

$allowed_sort_dirs = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_dir), $allowed_sort_dirs)) {
    $sort_dir = 'DESC';
}

if ($sort_by === 'created_at') {
    $sort_by = 'r.created_at';
} elseif ($sort_by === 'status') {
    $sort_by = 'r.status';
} elseif ($sort_by === 'username') {
    $sort_by = 'u.username';
} elseif ($sort_by === 'reward_name') {
    $sort_by = 'rw.name';
} elseif ($sort_by === 'points_cost') {
    $sort_by = 'rw.points_cost';
}

$query = "
    SELECT r.*, u.username, rw.name as reward_name, rw.points_cost, rw.image as reward_image
    FROM redemptions r
    JOIN users u ON r.user_id = u.id
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE 1=1
";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (u.username LIKE ? OR r.redemption_code LIKE ? OR rw.name LIKE ?)";
}

if (!empty($status_filter)) {
    $query .= " AND r.status = ?";
}

if ($reward_filter > 0) {
    $query .= " AND r.reward_id = ?";
}

$query .= " ORDER BY $sort_by $sort_dir";

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = "
    SELECT COUNT(*) as total 
    FROM redemptions r
    JOIN users u ON r.user_id = u.id
    JOIN rewards rw ON r.reward_id = rw.id
    WHERE 1=1
";

if (!empty($search)) {
    $count_query .= " AND (u.username LIKE ? OR r.redemption_code LIKE ? OR rw.name LIKE ?)";
}

if (!empty($status_filter)) {
    $count_query .= " AND r.status = ?";
}

if ($reward_filter > 0) {
    $count_query .= " AND r.reward_id = ?";
}

$stmt = $conn->prepare($count_query);

$param_types = '';
$params = [];

if (!empty($search)) {
    $param_types .= 'sss';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($status_filter)) {
    $param_types .= 's';
    $params[] = $status_filter;
}

if ($reward_filter > 0) {
    $param_types .= 'i';
    $params[] = $reward_filter;
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
    $param_types .= 'sss';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($status_filter)) {
    $param_types .= 's';
    $params[] = $status_filter;
}

if ($reward_filter > 0) {
    $param_types .= 'i';
    $params[] = $reward_filter;
}

$param_types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$redemptions = $stmt->get_result();

$rewards_query = "SELECT * FROM rewards ORDER BY name ASC";
$rewards_result = $conn->query($rewards_query);
$rewards = [];
while ($reward = $rewards_result->fetch_assoc()) {
    $rewards[] = $reward;
}

$statuses = ['pending', 'completed', 'cancelled'];

$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $redemption_id = isset($_POST['redemption_id']) ? intval($_POST['redemption_id']) : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    if ($redemption_id > 0 && in_array($new_status, $statuses)) {
        $stmt = $conn->prepare("UPDATE redemptions SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $admin_notes, $redemption_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Redemption status updated successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating redemption status: " . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Redemptions - Game Challenge</title>
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
            <h1>Manage Redemptions</h1>
            <p>View and manage reward redemptions</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Redemptions</h2>
                <div class="card-actions">
                    <a href="add_reward.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Reward
                    </a>
                    <a href="manage_rewards.php" class="btn">
                        <i class="fas fa-gift"></i> Manage Rewards
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="filters">
                    <form action="manage_redemptions.php" method="get" class="filter-form">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search by username, code or reward" value="<?php echo htmlspecialchars($search); ?>">
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
                            <select name="reward">
                                <option value="0">All Rewards</option>
                                <?php foreach ($rewards as $reward): ?>
                                <option value="<?php echo $reward['id']; ?>" <?php echo $reward_filter === $reward['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($reward['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="sort">
                                <option value="created_at" <?php echo $sort_by === 'r.created_at' ? 'selected' : ''; ?>>Date</option>
                                <option value="status" <?php echo $sort_by === 'r.status' ? 'selected' : ''; ?>>Status</option>
                                <option value="username" <?php echo $sort_by === 'u.username' ? 'selected' : ''; ?>>Username</option>
                                <option value="reward_name" <?php echo $sort_by === 'rw.name' ? 'selected' : ''; ?>>Reward</option>
                                <option value="points_cost" <?php echo $sort_by === 'rw.points_cost' ? 'selected' : ''; ?>>Points Cost</option>
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
                            <a href="manage_redemptions.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Reward</th>
                                <th>Points</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($redemptions && $redemptions->num_rows > 0): ?>
                                <?php while ($redemption = $redemptions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $redemption['id']; ?></td>
                                    <td><?php echo htmlspecialchars($redemption['username']); ?></td>
                                    <td>
                                        <div class="reward-item">
                                            <?php if (!empty($redemption['reward_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($redemption['reward_image']); ?>" alt="<?php echo htmlspecialchars($redemption['reward_name']); ?>" class="reward-icon">
                                            <?php else: ?>
                                            <div class="reward-icon-placeholder">
                                                <i class="fas fa-gift"></i>
                                            </div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($redemption['reward_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($redemption['points_cost']); ?></td>
                                    <td>
                                        <?php if (!empty($redemption['redemption_code'])): ?>
                                        <code><?php echo htmlspecialchars($redemption['redemption_code']); ?></code>
                                        <?php else: ?>
                                        <span class="text-muted">No code</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($redemption['status'] === 'completed'): ?>
                                            <span class="status approved">Completed</span>
                                        <?php elseif ($redemption['status'] === 'cancelled'): ?>
                                            <span class="status rejected">Cancelled</span>
                                        <?php else: ?>
                                            <span class="status pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($redemption['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_redemption.php?id=<?php echo $redemption['id']; ?>" class="btn btn-sm" title="View Redemption">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm" title="Update Status" onclick="showStatusModal(<?php echo $redemption['id']; ?>, '<?php echo $redemption['status']; ?>', '<?php echo addslashes($redemption['admin_notes'] ?? ''); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No redemptions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $reward_filter > 0 ? '&reward=' . $reward_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $reward_filter > 0 ? '&reward=' . $reward_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $reward_filter > 0 ? '&reward=' . $reward_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $reward_filter > 0 ? '&reward=' . $reward_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $reward_filter > 0 ? '&reward=' . $reward_filter : ''; ?><?php echo '&sort=' . str_replace('.', '', $sort_by) . '&dir=' . $sort_dir; ?>" class="pagination-link">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Redemption Status</h2>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form action="manage_redemptions.php" method="post">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" id="redemption_id" name="redemption_id" value="">
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea id="admin_notes" name="admin_notes" rows="3" class="form-control"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
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
        function showStatusModal(redemptionId, currentStatus, adminNotes) {
            document.getElementById('redemption_id').value = redemptionId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('admin_notes').value = adminNotes || '';
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('statusModal');
            if (event.target == modal) {
                closeStatusModal();
            }
        }
    </script>
</body>
</html>