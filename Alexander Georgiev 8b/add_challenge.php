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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $requirements = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';
    $tips = isset($_POST['tips']) ? trim($_POST['tips']) : '';
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
    $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : '';
    $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $use_ai = isset($_POST['use_ai']) && $_POST['use_ai'] === 'yes';
    $ai_prompt = isset($_POST['ai_prompt']) ? trim($_POST['ai_prompt']) : '';
    $ai_difficulty = isset($_POST['ai_difficulty']) ? $_POST['ai_difficulty'] : 'easy';
    $ai_game_id = isset($_POST['ai_game_id']) ? intval($_POST['ai_game_id']) : 0;
    
    $errors = [];
    
    if ($use_ai) {
        if (empty($ai_prompt)) {
            $errors[] = "AI prompt is required.";
        }
        
        if ($ai_game_id <= 0) {
            $errors[] = "Please select a game for the AI-generated challenge.";
        }
        
        if (!in_array($ai_difficulty, ['easy', 'medium', 'hard', 'expert'])) {
            $errors[] = "Invalid difficulty level for AI-generated challenge.";
        }
    } else {
        if (empty($title)) {
            $errors[] = "Challenge title is required.";
        }
        
        if (empty($description)) {
            $errors[] = "Challenge description is required.";
        }
        
        if (empty($requirements)) {
            $errors[] = "Challenge requirements are required.";
        }
        
        if ($game_id <= 0) {
            $errors[] = "Please select a game.";
        }
        
        if (!in_array($difficulty, ['easy', 'medium', 'hard', 'expert'])) {
            $errors[] = "Invalid difficulty level.";
        }
        
        if ($points <= 0) {
            $errors[] = "Points must be greater than zero.";
        }
    }
    
    if (empty($errors)) {
        if ($use_ai) {
            try {
                $system_prompt = "You are a creative gaming challenge designer. Your task is to create engaging and fun challenges for players of various games. The challenges should be clear, achievable, and appropriate for the specified difficulty level.";
                
                $user_prompt = "Create a gaming challenge with the following parameters:
Game: " . getGameName($conn, $ai_game_id) . "
Difficulty: " . ucfirst($ai_difficulty) . "

Additional instructions: " . $ai_prompt . "

Please format your response as a JSON object with the following structure:
{
\"title\": \"Challenge Title\",
\"description\": \"A detailed description of the challenge\",
\"requirements\": \"Specific requirements to complete the challenge\",
\"tips\": \"Optional tips to help players complete the challenge\",
\"points\": A number representing the points value of this challenge (between 100-2000 depending on difficulty)
}";
                
                $response = callOpenAI($api_key, $system_prompt, $user_prompt);
                
                preg_match('/{.*}/s', $response, $matches);
                
                if (empty($matches)) {
                    throw new Exception("Failed to parse AI response. Please try again.");
                }
                
                $challenge_json = $matches[0];
                $challenge_data = json_decode($challenge_json, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Failed to parse challenge JSON: " . json_last_error_msg());
                }
                
                if (!isset($challenge_data['title']) || !isset($challenge_data['description']) || !isset($challenge_data['requirements'])) {
                    throw new Exception("AI response is missing required fields.");
                }
                
                $title = $challenge_data['title'];
                $description = $challenge_data['description'];
                $requirements = $challenge_data['requirements'];
                $tips = $challenge_data['tips'] ?? '';
                $points = isset($challenge_data['points']) ? intval($challenge_data['points']) : getDefaultPoints($ai_difficulty);
                $game_id = $ai_game_id;
                $difficulty = $ai_difficulty;
                
                $stmt = $conn->prepare("
                    INSERT INTO challenges (title, description, requirements, tips, game_id, difficulty, points, is_featured, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("ssssssiis", $title, $description, $requirements, $tips, $game_id, $difficulty, $points, $is_featured, $user_id);
                
                if ($stmt->execute()) {
                    $challenge_id = $conn->insert_id;
                    
                    $_SESSION['message'] = "AI-generated challenge created successfully!";
                    $_SESSION['message_type'] = 'success';
                    header("Location: challenge.php?id=" . $challenge_id);
                    exit;
                } else {
                    throw new Exception("Error creating challenge: " . $stmt->error);
                }
            } catch (Exception $e) {
                $errors[] = "AI Error: " . $e->getMessage();
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO challenges (title, description, requirements, tips, game_id, difficulty, points, is_featured, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssssssiis", $title, $description, $requirements, $tips, $game_id, $difficulty, $points, $is_featured, $user_id);
            
            if ($stmt->execute()) {
                $challenge_id = $conn->insert_id;
                
                if (isset($_FILES['challenge_image']) && $_FILES['challenge_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/challenges/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = $challenge_id . '_' . basename($_FILES['challenge_image']['name']);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['challenge_image']['tmp_name'], $file_path)) {
                        $stmt = $conn->prepare("UPDATE challenges SET image = ? WHERE id = ?");
                        $stmt->bind_param("si", $file_path, $challenge_id);
                        $stmt->execute();
                    }
                }
                
                $_SESSION['message'] = "Challenge created successfully!";
                $_SESSION['message_type'] = 'success';
                header("Location: challenge.php?id=" . $challenge_id);
                exit;
            } else {
                $errors[] = "Error creating challenge: " . $stmt->error;
            }
        }
    }
}

function getGameName($conn, $game_id) {
    $stmt = $conn->prepare("SELECT name FROM games WHERE id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $game = $result->fetch_assoc();
        return $game['name'];
    }
    
    return "Unknown Game";
}

function getDefaultPoints($difficulty) {
    switch ($difficulty) {
        case 'easy':
            return rand(100, 300);
        case 'medium':
            return rand(300, 600);
        case 'hard':
            return rand(600, 1000);
        case 'expert':
            return rand(1000, 2000);
        default:
            return 100;
    }
}

function callOpenAI($api_key, $system_prompt, $user_prompt) {
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
                $retry_delay *= 2; // Exponential backoff
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
    
    return $response_data['choices'][0]['message']['content'];
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
    <title>Add Challenge - Game Challenge</title>
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
            <h1>Add Challenge</h1>
            <p>Create a new gaming challenge</p>
        </div>
        
        <?php if ($message): ?>
        <div class="<?php echo $messageType === 'error' ? 'error-message' : 'success-message'; ?> mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="error-message mb-4">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Challenge Creation</h2>
                <div class="tab-navigation">
                    <button class="tab-button active" data-tab="manual">Manual Creation</button>
                    <button class="tab-button" data-tab="ai">AI-Assisted Creation</button>
                </div>
            </div>
            <div class="card-body">
                <div class="tab-content active" id="manual-tab">
                    <form action="add_challenge.php" method="post" enctype="multipart/form-data" class="form">
                        <div class="form-group">
                            <label for="title">Challenge Title</label>
                            <input type="text" id="title" name="title" value="<?php echo isset($title) && !$use_ai ? htmlspecialchars($title) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="game_id">Game</label>
                            <select id="game_id" name="game_id" required>
                                <option value="">Select a Game</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo isset($game_id) && $game_id === $game['id'] && !$use_ai ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="difficulty">Difficulty</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="easy" <?php echo isset($difficulty) && $difficulty === 'easy' && !$use_ai ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo isset($difficulty) && $difficulty === 'medium' && !$use_ai ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo isset($difficulty) && $difficulty === 'hard' && !$use_ai ? 'selected' : ''; ?>>Hard</option>
                                <option value="expert" <?php echo isset($difficulty) && $difficulty === 'expert' && !$use_ai ? 'selected' : ''; ?>>Expert</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" min="1" value="<?php echo isset($points) && !$use_ai ? $points : '100'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4" required><?php echo isset($description) && !$use_ai ? htmlspecialchars($description) : ''; ?></textarea>
                            <div class="form-help">Provide a detailed description of the challenge.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="requirements">Requirements</label>
                            <textarea id="requirements" name="requirements" rows="4" required><?php echo isset($requirements) && !$use_ai ? htmlspecialchars($requirements) : ''; ?></textarea>
                            <div class="form-help">Specify what players need to do to complete this challenge.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tips">Tips (Optional)</label>
                            <textarea id="tips" name="tips" rows="3"><?php echo isset($tips) && !$use_ai ? htmlspecialchars($tips) : ''; ?></textarea>
                            <div class="form-help">Provide optional tips to help players complete the challenge.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="challenge_image">Challenge Image (Optional)</label>
                            <input type="file" id="challenge_image" name="challenge_image" accept="image/*">
                            <div class="form-help">Upload an image related to the challenge. Max size: 2MB.</div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="featured" name="is_featured" value="1" <?php echo isset($featured) && $featured && !$use_ai ? 'checked' : ''; ?>>
                            <label for="featured">Feature this challenge on the homepage</label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Create Challenge</button>
                            <a href="manage_challenges.php" class="btn">Cancel</a>
                        </div>
                    </form>
                </div>
                
                <div class="tab-content" id="ai-tab">
                    <form action="add_challenge.php" method="post" class="form">
                        <input type="hidden" name="use_ai" value="yes">
                        
                        <div class="form-group">
                            <label for="ai_game_id">Game</label>
                            <select id="ai_game_id" name="ai_game_id" required>
                                <option value="">Select a Game</option>
                                <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo isset($ai_game_id) && $ai_game_id === $game['id'] && $use_ai ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ai_difficulty">Difficulty</label>
                            <select id="ai_difficulty" name="ai_difficulty" required>
                                <option value="easy" <?php echo isset($ai_difficulty) && $ai_difficulty === 'easy' && $use_ai ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo isset($ai_difficulty) && $ai_difficulty === 'medium' && $use_ai ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo isset($ai_difficulty) && $ai_difficulty === 'hard' && $use_ai ? 'selected' : ''; ?>>Hard</option>
                                <option value="expert" <?php echo isset($ai_difficulty) && $ai_difficulty === 'expert' && $use_ai ? 'selected' : ''; ?>>Expert</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ai_prompt">Instructions for AI</label>
                            <textarea id="ai_prompt" name="ai_prompt" rows="6" required><?php echo isset($ai_prompt) && $use_ai ? htmlspecialchars($ai_prompt) : ''; ?></textarea>
                            <div class="form-help">
                                <p>Provide instructions for the AI to generate a challenge. Be specific about what kind of challenge you want.</p>
                                <p>Example: "Create a stealth challenge for Counter-Strike 2 where players need to complete objectives without being detected."</p>
                            </div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="ai_featured" name="is_featured" value="1" <?php echo isset($featured) && $featured && $use_ai ? 'checked' : ''; ?>>
                            <label for="ai_featured">Feature this challenge on the homepage</label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Generate Challenge with AI</button>
                            <a href="manage_challenges.php" class="btn">Cancel</a>
                        </div>
                    </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
        });
    </script>
</body>
</html>