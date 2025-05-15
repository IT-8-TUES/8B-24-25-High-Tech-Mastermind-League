<?php
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $httponly = true;
    
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

function debug_session() {
    echo "<pre>Session: " . print_r($_SESSION, true) . "</pre>";
    echo "<pre>Session ID: " . session_id() . "</pre>";
    echo "<pre>Session Status: " . session_status() . "</pre>";
    echo "<pre>Session Cookie: " . print_r($_COOKIE, true) . "</pre>";
}


?>