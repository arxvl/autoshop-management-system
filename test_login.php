<?php
// test_login.php
require_once 'config/database.php';

$username = 'admin';
$password = 'admin123';

echo "<h3>Login Diagnostic Test</h3>";

try {
    $stmt = $pdo->prepare("SELECT UserID, Username, PasswordHash FROM User_T WHERE Username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<strong style='color:red;'>FAILED:</strong> Username '$username' was NOT found in the database. Check your spelling or database connection.<br>";
    } else {
        echo "<strong style='color:green;'>SUCCESS:</strong> User found!<br><br>";
        echo "<strong>Hash stored in DB:</strong> " . $user['PasswordHash'] . "<br>";
        echo "<strong>Length of hash:</strong> " . strlen($user['PasswordHash']) . " characters (Should be 60)<br><br>";
        
        if (password_verify($password, $user['PasswordHash'])) {
            echo "<strong style='color:green;'>SUCCESS:</strong> Password matches the hash perfectly!<br>";
            echo "<p><em>Conclusion: If you are seeing this, your database and password are 100% correct. Your issue is that PHP Sessions are not working on your server (check config/session.php).</em></p>";
        } else {
            echo "<strong style='color:red;'>FAILED:</strong> The password does NOT match the hash.<br>";
            echo "<p><em>Conclusion: Your hash was likely cut off when you pasted it into phpMyAdmin, or your PasswordHash column is not set to VARCHAR(255).</em></p>";
        }
    }
} catch (PDOException $e) {
    echo "<strong style='color:red;'>DATABASE ERROR:</strong> " . $e->getMessage() . "<br>";
}
?>