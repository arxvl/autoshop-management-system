<?php
// config/functions.php

// Sanitize user inputs to prevent XSS
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Redirect utility
function redirect($url) {
    header("Location: $url");
    exit();
}
?>