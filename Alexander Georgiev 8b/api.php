<?php
// API functions for OpenAI integration

/**
 * Call OpenAI API to get a response
 * 
 * @param string $message The user's message
 * @return string The AI response
 */
function callOpenAI($message) {
    global $api_key;
    
    // If no API key is set, use fallback response
    if (empty($api_key) || $api_key === 'your_api_key') {
        return generateFallbackResponse($message);
    }
    
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
                'content' => 'You are a helpful gaming assistant for the Game Challenge platform. You provide tips, strategies, and advice for various games and gaming challenges. Keep responses concise and focused on gaming.'
            ],
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ];
    
    // Use cURL for better error handling and retry logic
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Implement retry logic
    $max_retries = 3;
    $retry_delay = 2;
    $response = false;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        if ($response !== false && $http_code === 200) {
            break; // Success, exit retry loop
        }
        
        if ($attempt < $max_retries) {
            error_log("API attempt $attempt failed. HTTP code: $http_code, Error: $curl_error. Retrying in $retry_delay seconds.");
            sleep($retry_delay);
            $retry_delay *= 2; // Exponential backoff
        }
    }
    
    curl_close($ch);
    
    if ($response === false) {
        error_log("All API attempts failed. Last error: $curl_error");
        return generateFallbackResponse($message);
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        error_log("Unexpected API response format: " . json_encode($result));
        return generateFallbackResponse($message);
    }
}

/**
 * Call OpenAI API with image analysis
 * 
 * @param string $message The user's message
 * @param string $image_url URL of the image to analyze
 * @return string The AI response
 */
function callOpenAIWithImage($message, $image_url) {
    global $api_key;
    
    // If no API key is set, use fallback response
    if (empty($api_key) || $api_key === 'your_api_key') {
        return generateFallbackResponse($message);
    }
    
    // Process the image
    $image_data = getImageData($image_url);
    
    if (!$image_data) {
        error_log("Failed to process image: $image_url");
        return callOpenAI($message . "\n\n[Note: There was an image attached, but it could not be processed.]");
    }
    
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
                'content' => 'You are a helpful gaming assistant for the Game Challenge platform. You provide tips, strategies, and advice for various games and gaming challenges. Keep responses concise and focused on gaming.'
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $message
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $image_data
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ];
    
    // Use cURL for better error handling and retry logic
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Implement retry logic
    $max_retries = 3;
    $retry_delay = 2;
    $response = false;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        if ($response !== false && $http_code === 200) {
            break; // Success, exit retry loop
        }
        
        if ($attempt < $max_retries) {
            error_log("API attempt $attempt failed. HTTP code: $http_code, Error: $curl_error. Retrying in $retry_delay seconds.");
            sleep($retry_delay);
            $retry_delay *= 2; // Exponential backoff
        }
    }
    
    curl_close($ch);
    
    if ($response === false) {
        error_log("All API attempts failed. Last error: $curl_error");
        return generateFallbackResponse($message);
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        error_log("Unexpected API response format: " . json_encode($result));
        return generateFallbackResponse($message);
    }
}

/**
 * Get image data in a format suitable for the OpenAI API
 * 
 * @param string $image_url URL of the image
 * @return string|null Base64 encoded image data or null on failure
 */
function getImageData($image_url) {
    // Check if the URL is valid
    if (empty($image_url)) {
        error_log("Empty image URL provided");
        return null;
    }
    
    // Get the image content
    $image_content = @file_get_contents($image_url);
    
    if ($image_content === false) {
        error_log("Failed to get image content from URL: " . $image_url);
        return null;
    }
    
    // Get image info
    $image_info = @getimagesizefromstring($image_content);
    
    if ($image_info === false) {
        error_log("Failed to get image info from content");
        return null;
    }
    
    // Check if the image is too large (OpenAI has a 20MB limit)
    if (strlen($image_content) > 20 * 1024 * 1024) {
        error_log("Image is too large: " . strlen($image_content) . " bytes");
        
        // Try to resize the image
        $image = @imagecreatefromstring($image_content);
        
        if ($image === false) {
            error_log("Failed to create image from string");
            return null;
        }
        
        // Get original dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calculate new dimensions (max 2048px on longest side)
        $max_dimension = 2048;
        if ($width > $height) {
            $new_width = $max_dimension;
            $new_height = floor($height * ($max_dimension / $width));
        } else {
            $new_height = $max_dimension;
            $new_width = floor($width * ($max_dimension / $height));
        }
        
        // Create resized image
        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // Output to buffer
        ob_start();
        imagejpeg($resized, null, 80);
        $image_content = ob_get_clean();
        
        // Free memory
        imagedestroy($image);
        imagedestroy($resized);
    }
    
    // Get MIME type
    $mime_type = $image_info['mime'];
    
    // Convert to base64
    $base64_image = base64_encode($image_content);
    
    // Return data URI
    return "data:$mime_type;base64,$base64_image";
}

/**
 * Generate a fallback response when API call fails
 * 
 * @param string $message The user's message
 * @return string A fallback response
 */
function generateFallbackResponse($message) {
    // Default responses if API call fails
    $responses = [
        "Hello" => "Hi there! How can I help you with your gaming challenges today?",
        "Hi" => "Hello! Ready to tackle some gaming challenges?",
        "Help" => "I'm here to help! You can ask me about game challenges, strategies, or tips for completing them.",
        "Challenge" => "Looking for a challenge? Try completing a game level without taking any damage!",
        "Tips" => "Here's a tip: Practice makes perfect. Try breaking down difficult challenges into smaller steps.",
        "Strategy" => "A good strategy is to analyze patterns in enemy behavior and exploit weaknesses.",
        "Game" => "There are many great games on our platform! Which one are you interested in?",
        "Points" => "You earn points by completing challenges. The harder the challenge, the more points you'll earn!",
        "Rank" => "Your rank increases as you earn more points. Keep completing challenges to climb the leaderboard!",
        "Reward" => "You can redeem your points for various rewards in the rewards section.",
    ];
    
    // Check for keywords in the message
    foreach ($responses as $keyword => $response) {
        if (stripos($message, $keyword) !== false) {
            return $response;
        }
    }
    
    // Default responses if no keywords match
    $default_responses = [
        "That's an interesting question about gaming. Let me think about that...",
        "I understand you're asking about gaming. Could you provide more details?",
        "I'm your gaming assistant! I can help with challenges, strategies, and tips.",
        "Thanks for your message! I'm here to help with all your gaming needs.",
        "I'm processing your request. Is there anything specific about gaming you'd like to know?",
    ];
    
    return $default_responses[array_rand($default_responses)];
}
?>