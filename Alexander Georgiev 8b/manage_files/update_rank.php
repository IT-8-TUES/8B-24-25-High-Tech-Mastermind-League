<?php
/**
 * @param int $user_id The user ID
 * @param int $score The user's current score
 * @param mysqli $conn Database connection
 * @return string The updated rank
 */
function updateUserRank($user_id, $score, $conn) {
    $ranks = [
        ['name' => 'Rookie', 'min' => 0, 'max' => 1000],
        ['name' => 'Bronze', 'min' => 1001, 'max' => 5000],
        ['name' => 'Silver', 'min' => 5001, 'max' => 15000],
        ['name' => 'Gold', 'min' => 15001, 'max' => 30000],
        ['name' => 'Platinum', 'min' => 30001, 'max' => 50000],
        ['name' => 'Diamond', 'min' => 50001, 'max' => PHP_INT_MAX]
    ];
    
    $stmt = $conn->prepare("SELECT player_rank FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $current_rank = $user['player_rank'] ?? 'Rookie';
    
    if ($current_rank === 'Admin') {
        return 'Admin';
    }
    
    $new_rank = 'Rookie'; 
    
    foreach ($ranks as $rank) {
        if ($score >= $rank['min'] && $score <= $rank['max']) {
            $new_rank = $rank['name'];
            break;
        }
    }
    
    if ($new_rank !== $current_rank) {
        $stmt = $conn->prepare("UPDATE users SET player_rank = ? WHERE id = ?");
        $stmt->bind_param("si", $new_rank, $user_id);
        $stmt->execute();

        logRankChange($user_id, $current_rank, $new_rank, $conn);
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['player_rank'] = $new_rank;
        }
    }
    
    return $new_rank;
}

/** 
 * @param int $user_id The user ID
 * @param string $old_rank The previous rank
 * @param string $new_rank The new rank
 * @param mysqli $conn Database connection
 * @return void
 */
function logRankChange($user_id, $old_rank, $new_rank, $conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS rank_changes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            old_rank VARCHAR(50) NOT NULL,
            new_rank VARCHAR(50) NOT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    $stmt = $conn->prepare("INSERT INTO rank_changes (user_id, old_rank, new_rank) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $old_rank, $new_rank);
    $stmt->execute();
}

/**
 * @param int $user_id The user ID
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function updateUserSession($user_id, $conn) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $stmt = $conn->prepare("SELECT username, player_rank, score FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['username'] = $user['username'];
        $_SESSION['player_rank'] = $user['player_rank'];
        $_SESSION['score'] = $user['score'];
        return true;
    }
    
    return false;
}