<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'api.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_chat'])) {
    $chat_title = trim($_POST['chat_title']);
    
    if (empty($chat_title)) {
        $chat_title = "New Chat " . date("Y-m-d H:i");
    }
    
    $stmt = $conn->prepare("INSERT INTO chats (user_id, title) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $chat_title);
    
    if ($stmt->execute()) {
        $chat_id = $conn->insert_id;
        header("Location: ai-chat.php?chat_id=" . $chat_id);
        exit;
    } else {
        $error = "Error creating chat: " . $stmt->error;
    }
}

$current_chat = null;
$messages = [];

if (isset($_GET['chat_id'])) {
    $chat_id = intval($_GET['chat_id']);
    
    $stmt = $conn->prepare("SELECT * FROM chats WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $chat_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $current_chat = $result->fetch_assoc();
        
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE chat_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        header("Location: ai-chat.php");
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM chats WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$chats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $chat_id = intval($_POST['chat_id']);
    $message_text = trim($_POST['message']);
    
    if (empty($message_text)) {
        $error = "Message cannot be empty.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $chat_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Invalid chat.";
        } else {
            $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, role, content) VALUES (?, 'user', ?)");
            $stmt->bind_param("is", $chat_id, $message_text);
            
            if (!$stmt->execute()) {
                $error = "Error sending message: " . $stmt->error;
            } else {
                $stmt = $conn->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $chat_id);
                $stmt->execute();
                
                $ai_response = callOpenAI($message_text);
                
                $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, role, content) VALUES (?, 'assistant', ?)");
                $stmt->bind_param("is", $chat_id, $ai_response);
                
                if (!$stmt->execute()) {
                    $error = "Error receiving AI response: " . $stmt->error;
                }
                
                header("Location: ai-chat.php?chat_id=" . $chat_id);
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_chat'])) {
    $chat_id = intval($_POST['chat_id']);
    
    $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $chat_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM chat_messages WHERE chat_id = ?");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM chats WHERE id = ?");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
    }
    
    header("Location: ai-chat.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_chat'])) {
    $chat_id = intval($_POST['chat_id']);
    $new_title = trim($_POST['new_title']);
    
    if (empty($new_title)) {
        $error = "Chat title cannot be empty.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $chat_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE chats SET title = ? WHERE id = ?");
            $stmt->bind_param("si", $new_title, $chat_id);
            $stmt->execute();
        }
        
        header("Location: ai-chat.php?chat_id=" . $chat_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat - Game Challenge</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="ai-style.css">
	<script src="chat_messages.js"></script>
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
                <li><a href="ai-chat.php" class="active">AI Chat</a></li>
                <?php if (isset($_SESSION['player_rank']) && $_SESSION['player_rank'] === 'Admin'): ?>
                <li><a href="admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="user-actions">
            <a href="profile.php" class="profile-link">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <?php if (isset($_SESSION['player_rank']) && $_SESSION['player_rank'] === 'Admin'): ?>
                    <span class="badge admin">Admin</span>
                    <?php endif; ?>
                </div>
            </a>
            <a href="logout.php" class="btn btn-sm">Logout</a>
        </div>
    </header>
    
    <main>
        <div class="page-header">
            <h1>AI Gaming Assistant</h1>
            <p>Chat with our AI assistant for help with challenges, gaming tips, and strategies</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-message mb-4">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="chat-container">
            <div class="chat-sidebar">
                <div class="new-chat-btn">
                    <button class="btn btn-primary btn-block" onclick="openNewChatModal()">
                        <i class="fas fa-plus"></i> New Chat
                    </button>
                </div>
                
                <div class="chat-list">
                    <?php if (count($chats) > 0): ?>
                        <?php foreach ($chats as $chat): ?>
                            <?php
                            // Get last message preview
                            $stmt = $conn->prepare("SELECT content FROM chat_messages WHERE chat_id = ? ORDER BY created_at DESC LIMIT 1");
                            $stmt->bind_param("i", $chat['id']);
                            $stmt->execute();
                            $last_message = $stmt->get_result()->fetch_assoc();
                            $preview = $last_message ? substr($last_message['content'], 0, 50) : "No messages yet";
                            ?>
                            <div class="chat-item <?php echo ($current_chat && $current_chat['id'] == $chat['id']) ? 'active' : ''; ?>" onclick="window.location.href='ai-chat.php?chat_id=<?php echo $chat['id']; ?>'">
                                <div class="chat-item-header">
                                    <div class="chat-item-title"><?php echo htmlspecialchars($chat['title']); ?></div>
                                    <div class="chat-item-date"><?php echo date('M j', strtotime($chat['updated_at'])); ?></div>
                                </div>
                                <div class="chat-item-preview"><?php echo htmlspecialchars($preview); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-chats">
                            <p class="text-center p-4">No chats yet. Start a new conversation!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="chat-main">
                <?php if ($current_chat): ?>
                    <div class="chat-header">
                        <h2 class="chat-title"><?php echo htmlspecialchars($current_chat['title']); ?></h2>
                        <div class="chat-actions">
                            <button class="btn btn-sm" onclick="openRenameChatModal(<?php echo $current_chat['id']; ?>, '<?php echo htmlspecialchars($current_chat['title']); ?>')">
                                <i class="fas fa-edit"></i> Rename
                            </button>
                            <form action="ai-chat.php" method="post" onsubmit="return confirm('Are you sure you want to delete this chat?');" style="display: inline;">
                                <input type="hidden" name="delete_chat" value="1">
                                <input type="hidden" name="chat_id" value="<?php echo $current_chat['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <?php foreach ($messages as $message): ?>
                            <div class="message message-<?php echo $message['role']; ?>">
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                    <div class="message-time">
                                        <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="chat-input">
                        <form action="ai-chat.php" method="post" class="chat-form">
                            <input type="hidden" name="send_message" value="1">
                            <input type="hidden" name="chat_id" value="<?php echo $current_chat['id']; ?>">
                            <input type="text" name="message" placeholder="Type your message..." required autofocus>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-chat">
                        <i class="fas fa-robot"></i>
                        <h2>Welcome to AI Gaming Assistant</h2>
                        <p>Start a new chat to get help with gaming challenges, tips, and strategies.</p>
                        <button class="btn btn-primary" onclick="openNewChatModal()">
                            <i class="fas fa-plus"></i> Start New Chat
                        </button>
                    </div>
                <?php endif; ?>
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
    
    <!-- New Chat Modal -->
    <div id="newChatModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('newChatModal')">&times;</span>
            <h2>Start New Chat</h2>
            <form action="ai-chat.php" method="post">
                <input type="hidden" name="create_chat" value="1">
                
                <div class="form-group">
                    <label for="chat_title">Chat Title (optional)</label>
                    <input type="text" id="chat_title" name="chat_title" placeholder="e.g., CS2 Strategy Help">
                    <div class="form-hint">If left blank, a default title will be generated.</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Chat</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Rename Chat Modal -->
    <div id="renameChatModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('renameChatModal')">&times;</span>
            <h2>Rename Chat</h2>
            <form action="ai-chat.php" method="post">
                <input type="hidden" name="rename_chat" value="1">
                <input type="hidden" name="chat_id" id="rename_chat_id">
                
                <div class="form-group">
                    <label for="new_title">New Title</label>
                    <input type="text" id="new_title" name="new_title" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>