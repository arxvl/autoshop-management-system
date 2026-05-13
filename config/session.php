<?php
// config/session.php

// Start the session only if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>