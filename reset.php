<?php
require_once 'config/database.php';

// Actual password
$new_password = 'admin123';

// Let PHP generate the correct hash for your specific server
$correct_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update the database
$stmt = $pdo->prepare("UPDATE User_T SET PasswordHash = ? WHERE Username = 'admin'");
$stmt->execute([$correct_hash]);

echo "<h3 style='color:green;'>Success! Password updated to 'admin123'. You can now log in!</h3>";
?>