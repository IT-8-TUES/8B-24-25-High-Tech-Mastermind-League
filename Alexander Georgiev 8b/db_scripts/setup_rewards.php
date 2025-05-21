<?php
require_once 'config.php';

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result->num_rows > 0;
}

$results = [];

if (!tableExists($conn, 'rewards')) {
    $sql = "CREATE TABLE rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        points_cost INT NOT NULL,
        stock INT NOT NULL DEFAULT -1,
        game_id INT,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
    )";

    if ($conn->query($sql)) {
        $results[] = "Created 'rewards' table";
    } else {
        $results[] = "Error creating 'rewards' table: " . $conn->error;
    }
}

if (!tableExists($conn, 'redemptions')) {
    $sql = "CREATE TABLE redemptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reward_id INT NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
        redemption_code VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE
    )";

    if ($conn->query($sql)) {
        $results[] = "Created 'redemptions' table";
    } else {
        $results[] = "Error creating 'redemptions' table: " . $conn->error;
    }
}

$result = $conn->query("SELECT COUNT(*) as count FROM rewards");
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    $games = [];
    $result = $conn->query("SELECT id, name FROM games");
    while ($row = $result->fetch_assoc()) {
        $games[$row['name']] = $row['id'];
    }
    
    $sample_rewards = [
        [
            'name' => 'Steam $10 Gift Card',
            'description' => 'A $10 gift card for Steam. Redeem for games, DLC, or in-game items.',
            'points_cost' => 1000,
            'stock' => 5,
            'game_id' => null,
            'image' => '/images/rewards/steam_card.jpg'
        ],
        [
            'name' => 'Discord Nitro (1 Month)',
            'description' => 'One month of Discord Nitro. Enjoy enhanced Discord features including custom emojis, higher upload limits, and more.',
            'points_cost' => 800,
            'stock' => 10,
            'game_id' => null,
            'image' => '/images/rewards/discord_nitro.jpg'
        ],
        [
            'name' => 'Exclusive Profile Badge',
            'description' => 'An exclusive profile badge that shows your elite status on Game Challenge.',
            'points_cost' => 300,
            'stock' => -1, 
            'game_id' => null,
            'image' => '/images/rewards/badge.jpg'
        ]
    ];
    
    if (isset($games['Counter-Strike 2'])) {
        $sample_rewards[] = [
            'name' => 'CS2 Weapon Skin',
            'description' => 'A random weapon skin for Counter-Strike 2.',
            'points_cost' => 500,
            'stock' => 8,
            'game_id' => $games['Counter-Strike 2'],
            'image' => '/images/rewards/cs2_skin.jpg'
        ];
    }
    
    if (isset($games['Minecraft'])) {
        $sample_rewards[] = [
            'name' => 'Minecraft Cape',
            'description' => 'A custom cape for your Minecraft character.',
            'points_cost' => 600,
            'stock' => 5,
            'game_id' => $games['Minecraft'],
            'image' => '/images/rewards/minecraft_cape.jpg'
        ];
    }
    
    if (isset($games['Fortnite'])) {
        $sample_rewards[] = [
            'name' => 'Fortnite V-Bucks (1000)',
            'description' => '1000 V-Bucks for Fortnite. Use them to purchase skins, emotes, and more.',
            'points_cost' => 1200,
            'stock' => 3,
            'game_id' => $games['Fortnite'],
            'image' => '/images/rewards/vbucks.jpg'
        ];
    }
    
    $inserted_count = 0;
    foreach ($sample_rewards as $reward) {
        if ($reward['game_id'] !== null) {
            $stmt = $conn->prepare("
                INSERT INTO rewards (name, description, points_cost, stock, game_id, image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssiiss", $reward['name'], $reward['description'], $reward['points_cost'], $reward['stock'], $reward['game_id'], $reward['image']);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO rewards (name, description, points_cost, stock, image)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssiis", $reward['name'], $reward['description'], $reward['points_cost'], $reward['stock'], $reward['image']);
        }
        
        if ($stmt->execute()) {
            $inserted_count++;
        }
    }
    
    if ($inserted_count > 0) {
        $results[] = "Inserted $inserted_count sample rewards";
    }
}

echo "<h1>Rewards System Setup Results</h1>";
echo "<ul>";
foreach ($results as $result) {
    echo "<li>$result</li>";
}
echo "</ul>";

if (empty($results)) {
    echo "<p>No changes were needed. Rewards system is already set up.</p>";
}

echo "<p><a href='admin.php' class='btn'>Return to Admin Panel</a></p>";
?>