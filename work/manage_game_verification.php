<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'game_verification_config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$player_rank = $_SESSION['player_rank'];

$games_query = "SELECT * FROM games ORDER BY name ASC";
$games_result = $conn->query($games_query);
$games = [];
while ($game = $games_result->fetch_assoc()) {
    $games[] = $game;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_criteria'])) {
    $game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : '';
    $verification_instructions = isset($_POST['verification_instructions']) ? trim($_POST['verification_instructions']) : '';
    $expected_evidence = isset($_POST['expected_evidence']) ? trim($_POST['expected_evidence']) : '';
    $verification_points = isset($_POST['verification_points']) ? trim($_POST['verification_points']) : '';
    
    if (empty($game_name) || empty($verification_instructions) || empty($expected_evidence) || empty($verification_points)) {
        $message = "All fields are required.";
        $messageType = 'error';
    } else {
        $points_array = array_filter(array_map('trim', explode("\n", $verification_points)));
        
        $criteria = [
            'verification_instructions' => $verification_instructions,
            'expected_evidence' => $expected_evidence,
            'verification_points' => $points_array,
            'common_challenges' => []
        ];
        
        if (isset($_POST['common_challenges']) && !empty($_POST['common_challenges'])) {
            $common_challenges = trim($_POST['common_challenges']);
            $challenges_lines = array_filter(array_map('trim', explode("\n", $common_challenges)));
            
            foreach ($challenges_lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = array_map('trim', explode(':', $line, 2));
                    $criteria['common_challenges'][$key] = $value;
                }
            }
        }
        
        if (addGameVerificationCriteria($game_name, $criteria)) {
            $message = "Verification criteria for $game_name added successfully.";
            $messageType = 'success';
            
            saveGameVerificationCriteria();
        } else {
            $message = "Failed to add verification criteria.";
            $messageType = 'error';
        }
    }
}

function saveGameVerificationCriteria() {
    global $GAME_VERIFICATION_CRITERIA;
    
    $file_content = "<?php\n/**\n * Game Verification Configuration\n * \n * This file contains verification criteria for different games.\n * Add new games here to automatically support them in the verification system.\n */\n\n";
    $file_content .= "// Game verification criteria\n";
    $file_content .= "\$GAME_VERIFICATION_CRITERIA = " . var_export($GAME_VERIFICATION_CRITERIA, true) . ";\n\n";
    
    $file_content .= "/**\n * Get verification criteria for a specific game\n * \n * @param string \$game_name The name of the game\n * @return array Verification criteria for the game\n */\n";
    $file_content .= "function getGameVerificationCriteria(\$game_name) {\n";
    $file_content .= "    global \$GAME_VERIFICATION_CRITERIA;\n";
    $file_content .= "    \n";
    $file_content .= "    // Default criteria\n";
    $file_content .= "    \$default_criteria = [\n";
    $file_content .= "        'verification_instructions' => 'Look for clear evidence that the player has completed the challenge requirements.',\n";
    $file_content .= "        'expected_evidence' => 'Screenshots showing completion of the challenge requirements.',\n";
    $file_content .= "        'verification_points' => [\n";
    $file_content .= "            'The image should clearly show the game interface',\n";
    $file_content .= "            'The image should show evidence of completing the specific challenge requirements',\n";
    $file_content .= "            'The image should not be edited or manipulated',\n";
    $file_content .= "            'The image should not be a generic or stock image'\n";
    $file_content .= "        ],\n";
    $file_content .= "        'common_challenges' => []\n";
    $file_content .= "    ];\n";
    $file_content .= "    \n";
    $file_content .= "    // Return game-specific criteria if available, otherwise return default\n";
    $file_content .= "    return isset(\$GAME_VERIFICATION_CRITERIA[\$game_name]) ? \$GAME_VERIFICATION_CRITERIA[\$game_name] : \$default_criteria;\n";
    $file_content .= "}\n\n";
    
    $file_content .= "/**\n * Add a new game to the verification system\n * \n * @param string \$game_name The name of the game\n * @param array \$criteria Verification criteria for the game\n * @return bool True if the game was added successfully\n */\n";
    $file_content .= "function addGameVerificationCriteria(\$game_name, \$criteria) {\n";
    $file_content .= "    global \$GAME_VERIFICATION_CRITERIA;\n";
    $file_content .= "    \n";
    $file_content .= "    // Validate criteria\n";
    $file_content .= "    if (!isset(\$criteria['verification_instructions']) || !isset(\$criteria['expected_evidence']) || !isset(\$criteria['verification_points'])) {\n";
    $file_content .= "        return false;\n";
    $file_content .= "    }\n";
    $file_content .= "    \n";
    $file_content .= "    // Add the game\n";
    $file_content .= "    \$GAME_VERIFICATION_CRITERIA[\$game_name] = \$criteria;\n";
    $file_content .= "    \n";
    $file_content .= "    return true;\n";
    $file_content .= "}\n";
    $file_content .= "?>";
    
    file_put_contents('game_verification_config.php', $file_content);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Game Verification - Game Challenge</title>
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
            <h1>Manage Game Verification</h1>
            <p>Configure verification criteria for different games</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>Current Game Verification Criteria</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Game</th>
                                    <th>Verification Points</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($GAME_VERIFICATION_CRITERIA as $game => $criteria): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($game); ?></td>
                                    <td><?php echo count($criteria['verification_points']); ?> points</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm" onclick="viewCriteria('<?php echo htmlspecialchars(addslashes($game)); ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="editCriteria('<?php echo htmlspecialchars(addslashes($game)); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Add Game Verification Criteria</h2>
                </div>
                <div class="card-body">
                    <form action="manage_game_verification.php" method="post" class="form">
                        <input type="hidden" name="add_criteria" value="1">
                        
                        <div class="form-group">
                            <label for="game_name">Game Name</label>
                            <select id="game_name" name="game_name" required>
                                <option value="">Select a Game</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo htmlspecialchars($game['name']); ?>">
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="verification_instructions">Verification Instructions</label>
                            <textarea id="verification_instructions" name="verification_instructions" rows="4" required></textarea>
                            <div class="form-help">Instructions for the AI on how to verify submissions for this game.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="expected_evidence">Expected Evidence</label>
                            <textarea id="expected_evidence" name="expected_evidence" rows="4" required></textarea>
                            <div class="form-help">What kind of evidence is typically expected for this game's challenges.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="verification_points">Verification Points</label>
                            <textarea id="verification_points" name="verification_points" rows="6" required></textarea>
                            <div class="form-help">List of specific points to check when verifying submissions (one per line).</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="common_challenges">Common Challenges (Optional)</label>
                            <textarea id="common_challenges" name="common_challenges" rows="4"></textarea>
                            <div class="form-help">List of common challenge types and verification instructions (format: challenge_type: instruction).</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Add Verification Criteria</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="criteriaModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="criteriaModalTitle">Game Verification Criteria</h2>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body" id="criteriaModalBody">
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
        function viewCriteria(game) {
            const criteria = <?php echo json_encode($GAME_VERIFICATION_CRITERIA); ?>;
            const gameCriteria = criteria[game];
            
            if (!gameCriteria) {
                alert('Criteria not found for this game.');
                return;
            }
            
            document.getElementById('criteriaModalTitle').textContent = game + ' Verification Criteria';
            
            let content = '<div class="criteria-details">';
            
            content += '<div class="criteria-section">';
            content += '<h3>Verification Instructions</h3>';
            content += '<p>' + gameCriteria.verification_instructions + '</p>';
            content += '</div>';
            
            content += '<div class="criteria-section">';
            content += '<h3>Expected Evidence</h3>';
            content += '<p>' + gameCriteria.expected_evidence.replace(/\n/g, '<br>') + '</p>';
            content += '</div>';
            
            content += '<div class="criteria-section">';
            content += '<h3>Verification Points</h3>';
            content += '<ul>';
            gameCriteria.verification_points.forEach(point => {
                content += '<li>' + point + '</li>';
            });
            content += '</ul>';
            content += '</div>';
            
            if (gameCriteria.common_challenges && Object.keys(gameCriteria.common_challenges).length > 0) {
                content += '<div class="criteria-section">';
                content += '<h3>Common Challenges</h3>';
                content += '<ul>';
                for (const [challenge, instruction] of Object.entries(gameCriteria.common_challenges)) {
                    content += '<li><strong>' + challenge + ':</strong> ' + instruction + '</li>';
                }
                content += '</ul>';
                content += '</div>';
            }
            
            content += '</div>';
            
            document.getElementById('criteriaModalBody').innerHTML = content;
            document.getElementById('criteriaModal').style.display = 'block';
        }
        
        function editCriteria(game) {
            const criteria = <?php echo json_encode($GAME_VERIFICATION_CRITERIA); ?>;
            const gameCriteria = criteria[game];
            
            if (!gameCriteria) {
                alert('Criteria not found for this game.');
                return;
            }
            
            document.getElementById('game_name').value = game;
            document.getElementById('verification_instructions').value = gameCriteria.verification_instructions;
            document.getElementById('expected_evidence').value = gameCriteria.expected_evidence;
            document.getElementById('verification_points').value = gameCriteria.verification_points.join('\n');
            
            if (gameCriteria.common_challenges && Object.keys(gameCriteria.common_challenges).length > 0) {
                let commonChallenges = '';
                for (const [challenge, instruction] of Object.entries(gameCriteria.common_challenges)) {
                    commonChallenges += challenge + ': ' + instruction + '\n';
                }
                document.getElementById('common_challenges').value = commonChallenges;
            } else {
                document.getElementById('common_challenges').value = '';
            }
            
            document.querySelector('.card:nth-child(2)').scrollIntoView({ behavior: 'smooth' });
        }
        
        function closeModal() {
            document.getElementById('criteriaModal').style.display = 'none';
        }
        

        window.onclick = function(event) {
            const modal = document.getElementById('criteriaModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>