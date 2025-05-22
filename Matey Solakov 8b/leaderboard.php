<?php
require_once 'session_config.php';
require_once 'config.php';

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $logged_in ? $_SESSION['username'] : '';
$player_rank = $logged_in ? ($_SESSION['player_rank'] ?? 'Rookie') : '';
$user_id = $logged_in ? $_SESSION['user_id'] : 0;
$user_score = $logged_in ? ($_SESSION['score'] ?? 0) : 0;

// echo "<pre>Session: " . print_r($_SESSION, true) . "</pre>";

$game_id = isset($_GET['game']) ? intval($_GET['game']) : 0;
$time_period = isset($_GET['period']) ? $_GET['period'] : 'all';

$query = "
    SELECT u.id, u.username, u.score, u.player_rank, u.profile_picture,
           COUNT(DISTINCT s.challenge_id) as challenges_completed
    FROM users u
    LEFT JOIN submissions s ON u.id = s.user_id AND s.status = 'approved'
";

if ($time_period === 'week') {
    $query .= " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($time_period === 'month') {
    $query .= " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}

if ($game_id > 0) {
    $query .= " LEFT JOIN challenges c ON s.challenge_id = c.id
                WHERE c.game_id = $game_id";
}

$query .= " GROUP BY u.id
            ORDER BY u.score DESC
            LIMIT 100";

$result = $conn->query($query);
$leaderboard = [];
$user_rank = 0;
$rank = 0;
$prev_score = -1;
$same_rank_count = 0;

while ($row = $result->fetch_assoc()) {
    $rank++;
    
    if ($prev_score === $row['score']) {
        $same_rank_count++;
        $display_rank = $rank - $same_rank_count;
    } else {
        $same_rank_count = 0;
        $display_rank = $rank;
        $prev_score = $row['score'];
    }
    
    $row['rank'] = $display_rank;
    $leaderboard[] = $row;
    
    if ($logged_in && $row['id'] == $user_id) {
        $user_rank = $display_rank;
    }
}

if ($logged_in && $user_rank === 0) {
    $user_rank_query = "
        SELECT COUNT(*) + 1 as user_rank
        FROM users
        WHERE score > (SELECT score FROM users WHERE id = ?)
    ";
    $stmt = $conn->prepare($user_rank_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_rank_data = $result->fetch_assoc();
    $user_rank = $user_rank_data['user_rank'];
}

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
    <title>Leaderboard - Game Challenge</title>
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
                <li><a href="leaderboard.php" class="active">Leaderboard</a></li>
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
            <?php if ($logged_in): ?>
            <a href="profile.php" class="profile-link">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="badge <?php echo strtolower($player_rank); ?>"><?php echo $player_rank; ?></span>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
            <?php else: ?>
            <a href="login.html" class="btn">Login</a>
            <a href="register.html" class="btn btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </header>
    
    <main>
        <div class="page-header">
            <h1>Leaderboard</h1>
            <p>See who's leading the pack in gaming challenges</p>
        </div>
        
        <?php if ($logged_in): ?>
        <div class="user-rank-banner">
            <div class="user-rank-info">
                <div class="user-rank-position">
                    <span class="rank-number"><?php echo $user_rank; ?></span>
                    <span class="rank-label">Your Rank</span>
                </div>
                <div class="user-rank-details">
                    <div class="user-rank-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-rank-score"><?php echo number_format($user_score); ?> points</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="filters-container">
            <form action="leaderboard.php" method="get" class="filters-form">
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
                        <label for="period">Time Period</label>
                        <select id="period" name="period" onchange="this.form.submit()">
                            <option value="all" <?php echo ($time_period === 'all') ? 'selected' : ''; ?>>All Time</option>
                            <option value="month" <?php echo ($time_period === 'month') ? 'selected' : ''; ?>>This Month</option>
                            <option value="week" <?php echo ($time_period === 'week') ? 'selected' : ''; ?>>This Week</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="leaderboard-container">
            <div class="leaderboard-top">
                <?php for ($i = 0; $i < min(3, count($leaderboard)); $i++): ?>
                <div class="top-player top-<?php echo $i + 1; ?>">
                    <div class="rank-badge"><?php echo $leaderboard[$i]['rank']; ?></div>
                    <div class="player-avatar">
                        <?php if (!empty($leaderboard[$i]['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($leaderboard[$i]['profile_picture']); ?>" alt="<?php echo htmlspecialchars($leaderboard[$i]['username']); ?>">
                        <?php else: ?>
                        <div class="avatar-placeholder"><?php echo strtoupper(substr($leaderboard[$i]['username'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="player-info">
                        <div class="player-name"><?php echo htmlspecialchars($leaderboard[$i]['username']); ?></div>
                        <div class="player-rank"><?php echo htmlspecialchars($leaderboard[$i]['player_rank']); ?></div>
                    </div>
                    <div class="player-score"><?php echo number_format($leaderboard[$i]['score']); ?> points</div>
                </div>
                <?php endfor; ?>
            </div>
            
            <div class="leaderboard-table">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Challenges</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $player): ?>
                        <tr class="<?php echo ($logged_in && $player['id'] == $user_id) ? 'current-user' : ''; ?>">
                            <td class="rank"><?php echo $player['rank']; ?></td>
                            <td class="player">
                                <div class="player-avatar small">
                                    <?php if (!empty($player['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($player['profile_picture']); ?>" alt="<?php echo htmlspecialchars($player['username']); ?>">
                                    <?php else: ?>
                                    <div class="avatar-placeholder small"><?php echo strtoupper(substr($player['username'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="player-details">
                                    <div class="player-name"><?php echo htmlspecialchars($player['username']); ?></div>
                                    <div class="player-badge <?php echo strtolower($player['player_rank']); ?>"><?php echo $player['player_rank']; ?></div>
                                </div>
                            </td>
                            <td class="challenges"><?php echo number_format($player['challenges_completed']); ?></td>
                            <td class="score"><?php echo number_format($player['score']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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