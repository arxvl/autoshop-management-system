<?php
// config/auth.php
require_once 'session.php';
require_once 'database.php';
require_once 'functions.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function login($username, $password, $pdo) {
    try {
        // Fetch the user from the new User_T table
        $stmt = $pdo->prepare("SELECT UserID, Username, PasswordHash, UserRole FROM User_T WHERE Username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Verify the user exists AND the password matches the hash
        if ($user && password_verify($password, $user['PasswordHash'])) {
            // Set secure session variables
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['role'] = $user['UserRole'];
            return true;
        }
        return false;
    } catch (PDOException $e) {
        // Optional: Log the error ($e->getMessage()) to a file here
        return false;
    }
}

function logout() {
    session_unset();
    session_destroy();
    redirect('login.php');
}
?>