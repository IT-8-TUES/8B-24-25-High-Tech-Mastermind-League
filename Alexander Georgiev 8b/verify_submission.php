<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['player_rank'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id']) && isset($_POST['action'])) {
    $submission_id = intval($_POST['submission_id']);
    $action = $_POST['action'];
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    
    $stmt = $conn->prepare("
        SELECT s.*, 
               c.title as challenge_title, c.description as challenge_description, 
               c.requirements, c.difficulty, c.points,
               u.username, u.player_rank,
               g.name as game_name
        FROM submissions s
        JOIN challenges c ON s.challenge_id = c.id
        JOIN users u ON s.user_id = u.id
        JOIN games g ON c.game_id = g.id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = 'Submission not found.';
        $_SESSION['message_type'] = 'error';
        header("Location: manage_submissions.php");
        exit;
    }
    
    $submission = $result->fetch_assoc();
    
    try {
        if ($action === 'approve') {
            $status = 'approved';
            $points_awarded = $submission['points'];
            $final_feedback = !empty($feedback) ? $feedback : 'Your submission has been approved by an admin.';
            
            $stmt = $conn->prepare("UPDATE submissions SET status = ?, feedback = ?, points_awarded = ? WHERE id = ?");
            $stmt->bind_param("ssii", $status, $final_feedback, $points_awarded, $submission_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE users SET score = score + ? WHERE id = ?");
            $stmt->bind_param("ii", $points_awarded, $submission['user_id']);
            $stmt->execute();
            
            $_SESSION['message'] = 'Submission approved successfully!';
            $_SESSION['message_type'] = 'success';
            
        } elseif ($action === 'reject') {
            $status = 'rejected';
            $final_feedback = !empty($feedback) ? $feedback : 'Your submission has been rejected by an admin.';
            
            $stmt = $conn->prepare("UPDATE submissions SET status = ?, feedback = ?, points_awarded = 0 WHERE id = ?");
            $stmt->bind_param("ssi", $status, $final_feedback, $submission_id);
            $stmt->execute();
            
            $_SESSION['message'] = 'Submission rejected.';
            $_SESSION['message_type'] = 'info';
            
        } elseif ($action === 'auto_verify') {
            if (empty($api_key)) {
                throw new Exception("API key not configured. Please set the API key in config.php.");
            }
            
            $image_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $submission['screenshot_path'];
            
            error_log("Starting AI verification for submission ID: $submission_id - Image URL: $image_url");
            
            $system_prompt = "You are an expert gaming challenge verification assistant specializing in {$submission['game_name']}. ";
            $system_prompt .= "Your task is to verify if a player's submission meets the requirements for a gaming challenge. ";
            $system_prompt .= "You must be EXTREMELY STRICT and SKEPTICAL in your assessment. ";
            $system_prompt .= "IMPORTANT: You should REJECT any submission that doesn't provide CLEAR and CONVINCING evidence of completing the EXACT challenge requirements.";
            
            $user_prompt = "I need to verify if this screenshot shows completion of the following challenge:\n\n";
            $user_prompt .= "GAME: {$submission['game_name']}\n";
            $user_prompt .= "CHALLENGE: {$submission['challenge_title']}\n";
            $user_prompt .= "DESCRIPTION: {$submission['challenge_description']}\n";
            $user_prompt .= "REQUIREMENTS: {$submission['requirements']}\n\n";
            
            $user_prompt .= "VERIFICATION INSTRUCTIONS:\n";
            $user_prompt .= "1. Check if the image is clearly from {$submission['game_name']}.\n";
            $user_prompt .= "2. Verify if it shows clear evidence of completing the EXACT requirements: {$submission['requirements']}\n";
            $user_prompt .= "3. Be extremely strict - if you can't clearly see evidence of completion, reject it.\n\n";
            
            $user_prompt .= "Respond with a JSON object in this exact format:\n";
            $user_prompt .= "{\n";
            $user_prompt .= "  \"verified\": false,\n";
            $user_prompt .= "  \"confidence\": 0-100,\n";
            $user_prompt .= "  \"feedback\": \"Your detailed explanation of why the submission meets or doesn't meet the requirements.\"\n";
            $user_prompt .= "}\n\n";
            
            $user_prompt .= "IMPORTANT: Default to 'verified': false unless you are ABSOLUTELY CERTAIN the submission shows clear evidence of completing the challenge.";
            
            $url = 'https://api.openai.com/v1/chat/completions';
            
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ];
            
            $messages = [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $user_prompt
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $image_url,
                                'detail' => 'high'
                            ]
                        ]
                    ]
                ]
            ];
            
            $data = [
                'model' => 'gpt-4o',
                'messages' => $messages,
                'temperature' => 0.1,
                'max_tokens' => 1000
            ];
            

            error_log("Sending verification request to OpenAI API");
            error_log("Request data: " . json_encode($data));
            
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
            
            error_log("API Response (HTTP $http_code): " . $response);
            
            if ($curl_error || $http_code !== 200) {
                throw new Exception("API Error: " . ($curl_error ?: "HTTP $http_code: $response"));
            }
            
            $response_data = json_decode($response, true);
            
            if (isset($response_data['error'])) {
                throw new Exception("API Error: " . $response_data['error']['message']);
            }
            
            $ai_response = $response_data['choices'][0]['message']['content'];
            error_log("AI Response: " . $ai_response);
            
            preg_match('/{.*}/s', $ai_response, $matches);
            
            if (empty($matches)) {
                throw new Exception("Failed to parse AI response: " . $ai_response);
            }
            
            $verification_json = $matches[0];
            $verification = json_decode($verification_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Failed to parse verification JSON: " . json_last_error_msg());
            }
            
            $status = $verification['verified'] ? 'approved' : 'rejected';
            $ai_feedback = $verification['feedback'];
            $points_awarded = $verification['verified'] ? $submission['points'] : 0;
            $confidence = isset($verification['confidence']) ? $verification['confidence'] : 0;
            
            $final_feedback = "AI Verification (Confidence: {$confidence}%): " . $ai_feedback;
            if (!empty($feedback)) {
                $final_feedback = $feedback . "\n\n" . $final_feedback;
            }
            
            $stmt = $conn->prepare("UPDATE submissions SET status = ?, feedback = ?, points_awarded = ?, ai_verified = 1 WHERE id = ?");
            $stmt->bind_param("ssii", $status, $final_feedback, $points_awarded, $submission_id);
            $stmt->execute();
            
            if ($status === 'approved') {
                $stmt = $conn->prepare("UPDATE users SET score = score + ? WHERE id = ?");
                $stmt->bind_param("ii", $points_awarded, $submission['user_id']);
                $stmt->execute();
                
                $_SESSION['message'] = "AI verification completed: Submission approved! Points awarded: {$points_awarded}";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "AI verification completed: Submission rejected. Reason: " . $ai_feedback;
                $_SESSION['message_type'] = 'info';
            }
            
            error_log("AI Verification result: Status=$status, Confidence=$confidence, Feedback=$ai_feedback");
        }
        
    } catch (Exception $e) {
        error_log("Verification error: " . $e->getMessage());
        $_SESSION['message'] = "Error processing submission: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
    
    header("Location: view_submission.php?id=" . $submission_id);
    exit;
}

header("Location: admin.php");
exit;
?>