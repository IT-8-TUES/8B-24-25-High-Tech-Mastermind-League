<?php
require_once 'config.php';

$tables = [
    "CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        requirements TEXT NOT NULL,
        difficulty ENUM('Easy', 'Medium', 'Hard') NOT NULL DEFAULT 'Medium',
        points INT NOT NULL DEFAULT 100,
        is_featured BOOLEAN DEFAULT FALSE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        screenshot_path VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        feedback TEXT,
        points_awarded INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        image VARCHAR(255),
        points_cost INT NOT NULL,
        game_id INT,
        stock INT DEFAULT -1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS redemptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reward_id INT NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        redemption_code VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT 'New Chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        role ENUM('user', 'assistant') NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        stored_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(100),
        file_size INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE");
}

$games = [
    ['Counter-Strike 2', 'The iconic team-based action gameplay that revolutionized the FPS genre.', 'images/games/cs2.jpg'],
    ['Brawl Stars', 'Fast-paced 3v3 multiplayer and battle royale made for mobile!', 'images/games/brawl-stars.jpg'],
    ['Minecraft', 'A game about placing blocks and going on adventures.', 'images/games/minecraft.jpg'],
    ['The Forest', 'As the lone survivor of a passenger jet crash, you find yourself in a mysterious forest.', 'images/games/the-forest.jpg'],
    ['Sons of the Forest', 'Sent to find a missing billionaire on a remote island, you find yourself in a cannibal-infested hellscape.', 'images/games/sons-of-forest.jpg'],
    ['Fortnite', 'Battle royale game with building mechanics where 100 players fight to be the last person standing.', 'images/games/fortnite.jpg']
];

$result = $conn->query("SELECT COUNT(*) as count FROM games");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $stmt = $conn->prepare("INSERT INTO games (name, description, image) VALUES (?, ?, ?)");
    
    foreach ($games as $game) {
        $stmt->bind_param("sss", $game[0], $game[1], $game[2]);
        $stmt->execute();
    }
    
    echo "Sample games added successfully.<br>";
}

if (!file_exists('uploads')) {
    mkdir('uploads', 0755, true);
}
if (!file_exists('images/games')) {
    mkdir('images/games', 0755, true);
}

echo "Database setup completed successfully!";
?>