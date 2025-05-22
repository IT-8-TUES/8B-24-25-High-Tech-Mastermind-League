<?php
require_once 'config.php';

$result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'ai_prompt'");

if ($result->num_rows === 0) {
    if ($conn->query("ALTER TABLE challenges ADD COLUMN ai_prompt TEXT")) {
        echo "Successfully added 'ai_prompt' column to challenges table.<br>";
    } else {
        echo "Error adding 'ai_prompt' column: " . $conn->error . "<br>";
    }
} else {
    echo "The 'ai_prompt' column already exists in the challenges table.<br>";
}

echo "Done!";
?>