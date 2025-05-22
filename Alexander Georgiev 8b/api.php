<?php
function callOpenAI($message) {
    global $api_key;
    
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
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $max_retries = 3;
    $retry_delay = 2;
    $response = false;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        if ($response !== false && $http_code === 200) {
            break; 
        }
        
        if ($attempt < $max_retries) {
            error_log("API attempt $attempt failed. HTTP code: $http_code, Error: $curl_error. Retrying in $retry_delay seconds.");
            sleep($retry_delay);
            $retry_delay *= 2; 
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


function callOpenAIWithImage($message, $image_url) {
    global $api_key;
    
    if (empty($api_key) || $api_key === 'your_api_key') {
        return generateFallbackResponse($message);
    }
    
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
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $max_retries = 3;
    $retry_delay = 2;
    $response = false;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        if ($response !== false && $http_code === 200) {
            break;
        }
        
        if ($attempt < $max_retries) {
            error_log("API attempt $attempt failed. HTTP code: $http_code, Error: $curl_error. Retrying in $retry_delay seconds.");
            sleep($retry_delay);
            $retry_delay *= 2; 
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


function getImageData($image_url) {
    if (empty($image_url)) {
        error_log("Empty image URL provided");
        return null;
    }
    
    $image_content = @file_get_contents($image_url);
    
    if ($image_content === false) {
        error_log("Failed to get image content from URL: " . $image_url);
        return null;
    }
    
    $image_info = @getimagesizefromstring($image_content);
    
    if ($image_info === false) {
        error_log("Failed to get image info from content");
        return null;
    }
    
    if (strlen($image_content) > 20 * 1024 * 1024) {
        error_log("Image is too large: " . strlen($image_content) . " bytes");
        
        $image = @imagecreatefromstring($image_content);
        
        if ($image === false) {
            error_log("Failed to create image from string");
            return null;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        $max_dimension = 2048;
        if ($width > $height) {
            $new_width = $max_dimension;
            $new_height = floor($height * ($max_dimension / $width));
        } else {
            $new_height = $max_dimension;
            $new_width = floor($width * ($max_dimension / $height));
        }
        
        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        ob_start();
        imagejpeg($resized, null, 80);
        $image_content = ob_get_clean();
        
        imagedestroy($image);
        imagedestroy($resized);
    }
    
    $mime_type = $image_info['mime'];
    
    $base64_image = base64_encode($image_content);
    
    return "data:$mime_type;base64,$base64_image";
}


function generateFallbackResponse($message) {
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
    
    foreach ($responses as $keyword => $response) {
        if (stripos($message, $keyword) !== false) {
            return $response;
        }
    }
    
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