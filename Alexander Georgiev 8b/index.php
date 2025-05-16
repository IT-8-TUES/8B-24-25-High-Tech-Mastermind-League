<?php
require_once 'session_config.php';
require_once 'config.php';

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $logged_in ? $_SESSION['username'] : '';
$player_rank = $logged_in ? ($_SESSION['player_rank'] ?? 'Rookie') : '';
$user_id = $logged_in ? $_SESSION['user_id'] : 0;

// echo "<pre>Session: " . print_r($_SESSION, true) . "</pre>";

$featured_query = "
    SELECT c.*, g.name as game_name, g.image as game_image 
    FROM challenges c
    JOIN games g ON c.game_id = g.id
    ORDER BY c.created_at DESC
    LIMIT 4
";
$featured_result = $conn->query($featured_query);
$featured_challenges = [];
while ($row = $featured_result->fetch_assoc()) {
    $featured_challenges[] = $row;
}

$games_query = "SELECT * FROM games ORDER BY name ASC";
$games_result = $conn->query($games_query);
$games = [];
while ($row = $games_result->fetch_assoc()) {
    $games[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Challenge - Test Your Gaming Skills</title>
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
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="challenges.php">Challenges</a></li>
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
    
    <div class="hero">
        <div class="hero-content">
            <h1>Challenge Your Gaming Skills</h1>
            <p>Complete challenges, earn points, climb the leaderboard, and redeem rewards in your favorite games.</p>
            <?php if (!$logged_in): ?>
            <div class="hero-buttons">
                <a href="register.html" class="btn btn-primary btn-lg">Get Started</a>
                <a href="challenges.php" class="btn btn-secondary btn-lg">Browse Challenges</a>
            </div>
            <?php else: ?>
            <div class="hero-buttons">
                <a href="challenges.php" class="btn btn-primary btn-lg">Browse Challenges</a>
                <a href="rewards.php" class="btn btn-secondary btn-lg">View Rewards</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <main>
        <section class="featured-section">
            <div class="section-header">
                <h2>Featured Challenges</h2>
                <a href="challenges.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="challenges-grid">
                <?php if (count($featured_challenges) > 0): ?>
                    <?php foreach ($featured_challenges as $challenge): ?>
                    <div class="challenge-card">
                        <div class="challenge-img" style="background-image: url('<?php echo !empty($challenge['game_image']) ? htmlspecialchars($challenge['game_image']) : 'images/games/default.jpg'; ?>')">
                            <div class="difficulty <?php echo strtolower($challenge['difficulty']); ?>">
                                <?php echo htmlspecialchars($challenge['difficulty']); ?>
                            </div>
                        </div>
                        <div class="challenge-content">
                            <h3><?php echo htmlspecialchars($challenge['title']); ?></h3>
                            <div class="challenge-meta">
                                <div class="meta-item">
                                    <i class="fas fa-gamepad"></i>
                                    <span><?php echo htmlspecialchars($challenge['game_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-trophy"></i>
                                    <span><?php echo number_format($challenge['points']); ?> points</span>
                                </div>
                            </div>
                            <p class="challenge-excerpt"><?php echo substr(htmlspecialchars($challenge['description']), 0, 100) . '...'; ?></p>
                            <a href="challenge.php?id=<?php echo $challenge['id']; ?>" class="btn btn-block">View Challenge</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-challenges">
                        <i class="fas fa-exclamation-circle fa-3x"></i>
                        <h3>No featured challenges found</h3>
                        <p>Check back later for new challenges or browse all challenges.</p>
                        <a href="challenges.php" class="btn btn-primary">Browse All Challenges</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <section class="games-section">
            <div class="section-header">
                <h2>Game Sections</h2>
            </div>
            
            <div class="games-grid">
                <?php if (count($games) > 0): ?>
                    <?php foreach ($games as $game): ?>
                    <a href="challenges.php?game=<?php echo $game['id']; ?>" class="game-card">
                        <div class="game-img" style="background-image: url('<?php echo !empty($game['image']) ? htmlspecialchars($game['image']) : 'images/games/default.jpg'; ?>')">
                            <div class="game-overlay">
                                <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-games">
                        <i class="fas fa-gamepad fa-3x"></i>
                        <h3>No games found</h3>
                        <p>Check back later for new game sections.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <section class="how-it-works">
            <div class="section-header">
                <h2>How It Works</h2>
            </div>
            
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Find Challenges</h3>
                    <p>Browse through various gaming challenges across different games and difficulty levels.</p>
                </div>
                
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h3>Complete Challenges</h3>
                    <p>Play your favorite games and complete the challenges according to the requirements.</p>
                </div>
                
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Submit Proof</h3>
                    <p>Upload screenshots or videos as proof of your challenge completion.</p>
                </div>
                
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Earn Points</h3>
                    <p>Get points for each completed challenge and climb the global leaderboard.</p>
                </div>
                
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3>Redeem Rewards</h3>
                    <p>Exchange your earned points for exciting gaming rewards and prizes.</p>
                </div>
                
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Get AI Help</h3>
                    <p>Stuck on a challenge? Ask our AI assistant for tips and strategies.</p>
                </div>
            </div>
        </section>
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