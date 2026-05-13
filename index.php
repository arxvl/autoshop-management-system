<?php
// index.php
require_once 'config/auth.php';

// Route users based on authentication status
if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
?>