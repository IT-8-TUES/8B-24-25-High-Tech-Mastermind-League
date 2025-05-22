<?php
require_once 'config.php';

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result->num_rows > 0;
}

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result->num_rows > 0;
}

$results = [];

if (!columnExists($conn, 'users', 'bio')) {
    if ($conn->query("ALTER TABLE users ADD COLUMN bio TEXT")) {
        $results[] = "Added 'bio' column to users table";
    } else {
        $results[] = "Error adding 'bio' column: " . $conn->error;
    }
}

if (!tableExists($conn, 'chats')) {
    $sql = "CREATE TABLE chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        $results[] = "Created 'chats' table";
    } else {
        $results[] = "Error creating 'chats' table: " . $conn->error;
    }
}

if (!tableExists($conn, 'chat_messages')) {
    $sql = "CREATE TABLE chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        role ENUM('user', 'assistant') NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        $results[] = "Created 'chat_messages' table";
    } else {
        $results[] = "Error creating 'chat_messages' table: " . $conn->error;
    }
}

if (!columnExists($conn, 'users', 'player_rank')) {
    if ($conn->query("ALTER TABLE users ADD COLUMN player_rank VARCHAR(50) DEFAULT 'Rookie'")) {
        $results[] = "Added 'player_rank' column to users table";
    } else {
        $results[] = "Error adding 'player_rank' column: " . $conn->error;
    }
}

echo "<h1>Database Schema Update Results</h1>";
echo "<ul>";
foreach ($results as $result) {
    echo "<li>$result</li>";
}
echo "</ul>";

if (empty($results)) {
    echo "<p>No changes were needed. Database schema is up to date.</p>";
}
?>