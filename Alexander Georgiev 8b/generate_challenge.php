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

$games_query = "SELECT * FROM games ORDER BY name ASC";
$games_result = $conn->query($games_query);
$games = [];
while ($game = $games_result->fetch_assoc()) {
    $games[] = $game;
}

$difficulties = ['Easy', 'Medium', 'Hard', 'Expert'];

$generated_challenge = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
    $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : '';
    $prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';
    
    if ($game_id <= 0) {
        $error = "Please select a game.";
    } elseif (!in_array($difficulty, $difficulties)) {
        $error = "Please select a valid difficulty level.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        
        if (!$game) {
            $error = "Selected game not found.";
        } else {
            try {
                $generated_challenge = generateChallengeWithAI($game, $difficulty, $prompt);
            } catch (Exception $e) {
                $error = "Error generating challenge: " . $e->getMessage();
            }
        }
    }
}

function generateChallengeWithAI($game, $difficulty, $prompt = '') {
    global $api_key;
    
    if (empty($api_key)) {
        throw new Exception("API key not configured. Please set the API key in config.php.");
    }
    
    $system_prompt = "You are an expert game challenge designer for the game '{$game['name']}'. Your task is to create engaging, creative, and achievable challenges for players. The challenges should be specific, measurable, and appropriate for the selected difficulty level.";
    
    $user_prompt = "Create a gaming challenge for {$game['name']} with {$difficulty} difficulty level.\n\n";
    
    if (!empty($prompt)) {
        $user_prompt .= "Additional requirements: {$prompt}\n\n";
    }
    
    $user_prompt .= "Please provide the following in your response:
1. Challenge Title: A catchy, concise title for the challenge.
2. Description: A brief overview of what the challenge entails.
3. Requirements: Specific, measurable criteria that must be met to complete the challenge.
4. Points: A suggested point value for completing this challenge (between 100-5000 depending on difficulty).
5. Tips: Optional hints or strategies to help players complete the challenge.

Format your response as a JSON object with the following structure:
{
  \"title\": \"Challenge Title\",
  \"description\": \"Challenge description...\",
  \"requirements\": \"Detailed requirements...\",
  \"points\": 1000,
  \"tips\": \"Optional tips...\"
}";
    
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    $data = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    $max_retries = 3;
    $retry_delay = 2;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("cURL Error: " . $curl_error);
            if ($attempt < $max_retries) {
                sleep($retry_delay);
                $retry_delay *= 2;
                continue;
            }
            throw new Exception("Failed to connect to OpenAI API: " . $curl_error);
        }
        
        if ($http_code !== 200) {
            error_log("API Error (HTTP $http_code): " . $response);
            if ($http_code === 429 && $attempt < $max_retries) {
                sleep($retry_delay);
                $retry_delay *= 2; 
                continue;
            }
            throw new Exception("API Error (HTTP $http_code): " . $response);
        }
        
        break; 
    }
    
    $response_data = json_decode($response, true);
    
    if (isset($response_data['error'])) {
        throw new Exception("API Error: " . $response_data['error']['message']);
    }
    
    $ai_response = $response_data['choices'][0]['message']['content'];
    
    preg_match('/{.*}/s', $ai_response, $matches);
    
    if (empty($matches)) {
        throw new Exception("Failed to parse AI response. Please try again.");
    }
    
    $challenge_json = $matches[0];
    $challenge = json_decode($challenge_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse challenge JSON: " . json_last_error_msg());
    }
    
    if (empty($challenge['title']) || empty($challenge['description']) || empty($challenge['requirements'])) {
        throw new Exception("Generated challenge is missing required fields.");
    }
    
    if (!isset($challenge['points']) || !is_numeric($challenge['points'])) {
        switch ($difficulty) {
            case 'Easy':
                $challenge['points'] = 500;
                break;
            case 'Medium':
                $challenge['points'] = 1000;
                break;
            case 'Hard':
                $challenge['points'] = 2000;
                break;
            case 'Expert':
                $challenge['points'] = 3500;
                break;
            default:
                $challenge['points'] = 1000;
        }
    }
    
    $challenge['game_id'] = $game['id'];
    $challenge['game_name'] = $game['name'];
    $challenge['difficulty'] = $difficulty;
    
    return $challenge;
}

$check_tips_column = $conn->query("SHOW COLUMNS FROM challenges LIKE 'tips'");
$tips_column_exists = $check_tips_column->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_challenge']) && $generated_challenge) {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $requirements = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';
    $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
    $tips = isset($_POST['tips']) ? trim($_POST['tips']) : '';
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
    $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : '';
    
    if (empty($title)) {
        $error = "Challenge title is required.";
    } elseif (empty($description)) {
        $error = "Challenge description is required.";
    } elseif (empty($requirements)) {
        $error = "Challenge requirements are required.";
    } elseif ($points <= 0) {
        $error = "Challenge points must be greater than zero.";
    } elseif ($game_id <= 0) {
        $error = "Please select a valid game.";
    } elseif (!in_array($difficulty, $difficulties)) {
        $error = "Please select a valid difficulty level.";
    } else {
        if ($tips_column_exists) {
            $stmt = $conn->prepare("
                INSERT INTO challenges (
                    game_id, title, description, requirements, difficulty, points, tips, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issssiis", $game_id, $title, $description, $requirements, $difficulty, $points, $tips, $user_id);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO challenges (
                    game_id, title, description, requirements, difficulty, points, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issssii", $game_id, $title, $description, $requirements, $difficulty, $points, $user_id);
        }
        
        if ($stmt->execute()) {
            $challenge_id = $conn->insert_id;
            
            $_SESSION['message'] = "Challenge created successfully.";
            $_SESSION['message_type'] = 'success';
            
            header("Location: challenge.php?id=" . $challenge_id);
            exit;
        } else {
            $error = "Error creating challenge: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Challenge - Game Challenge</title>
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
            <h1>Generate Challenge</h1>
            <p>Use AI to generate a new gaming challenge</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-message mb-4">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$tips_column_exists): ?>
        <div class="warning-message mb-4">
            <strong>Note:</strong> The 'tips' column is missing from your challenges table. Tips will not be saved with challenges.
            <p>To add the tips column, run this SQL query: <code>ALTER TABLE challenges ADD COLUMN tips TEXT AFTER points;</code></p>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h2>Challenge Generator</h2>
                </div>
                <div class="card-body">
                    <form action="generate_challenge.php" method="post" class="form">
                        <div class="form-group">
                            <label for="game_id">Game</label>
                            <select id="game_id" name="game_id" required>
                                <option value="">Select a Game</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo isset($_POST['game_id']) && $_POST['game_id'] == $game['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="difficulty">Difficulty</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="">Select Difficulty</option>
                                <?php foreach ($difficulties as $diff): ?>
                                <option value="<?php echo $diff; ?>" <?php echo isset($_POST['difficulty']) && $_POST['difficulty'] == $diff ? 'selected' : ''; ?>>
                                    <?php echo $diff; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="prompt">Additional Requirements (Optional)</label>
                            <textarea id="prompt" name="prompt" rows="4" placeholder="Enter any specific requirements or themes for the challenge..."><?php echo isset($_POST['prompt']) ? htmlspecialchars($_POST['prompt']) : ''; ?></textarea>
                            <div class="form-help">
                                <p>Examples: "Make it involve teamwork", "Focus on stealth mechanics", "Include building structures"</p>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-magic"></i> Generate Challenge
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($generated_challenge): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Generated Challenge</h2>
                </div>
                <div class="card-body">
                    <form action="generate_challenge.php" method="post" class="form">
                        <input type="hidden" name="save_challenge" value="1">
                        <input type="hidden" name="game_id" value="<?php echo $generated_challenge['game_id']; ?>">
                        <input type="hidden" name="difficulty" value="<?php echo $generated_challenge['difficulty']; ?>">
                        
                        <div class="generated-challenge">
                            <div class="challenge-header">
                                <div class="challenge-game">
                                    <span class="game-label">Game:</span>
                                    <span class="game-name"><?php echo htmlspecialchars($generated_challenge['game_name']); ?></span>
                                </div>
                                <div class="challenge-difficulty">
                                    <span class="difficulty-label">Difficulty:</span>
                                    <span class="difficulty <?php echo strtolower($generated_challenge['difficulty']); ?>"><?php echo $generated_challenge['difficulty']; ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="title">Challenge Title</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($generated_challenge['title']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($generated_challenge['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="requirements">Requirements</label>
                                <textarea id="requirements" name="requirements" rows="4" required><?php echo htmlspecialchars($generated_challenge['requirements']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="points">Points</label>
                                <input type="number" id="points" name="points" value="<?php echo intval($generated_challenge['points']); ?>" min="100" max="10000" required>
                            </div>
                            
                            <?php if ($tips_column_exists): ?>
                            <div class="form-group">
                                <label for="tips">Tips (Optional)</label>
                                <textarea id="tips" name="tips" rows="3"><?php echo isset($generated_challenge['tips']) ? htmlspecialchars($generated_challenge['tips']) : ''; ?></textarea>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='generate_challenge.php'">
                                    <i class="fas fa-redo"></i> Generate Another
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Challenge
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
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