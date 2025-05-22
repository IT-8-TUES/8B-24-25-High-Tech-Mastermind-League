<?php
require_once 'config.php';

$stmt = $conn->prepare("SELECT id, username FROM users WHERE player_rank = 'Admin'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "Admin user already exists: " . $admin['username'] . " (ID: " . $admin['id'] . ")<br>";
} else {
    $username = "admin";
    $email = "admin@example.com";
    $password = "admin123"; 
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $player_rank = "Admin";
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, player_rank, score) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("ssss", $username, $email, $password_hash, $player_rank);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Username: $username<br>";
        echo "Password: $password<br>";
        echo "Please change this password after logging in.<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . "<br>";
    }
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'player_rank'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN player_rank VARCHAR(50) DEFAULT 'Rookie'");
    echo "Added player_rank column to users table<br>";
}

echo "Setup complete!";
?>