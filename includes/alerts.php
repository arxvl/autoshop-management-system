<?php
// includes/alerts.php

// Ensure session is started (fallback if auth/session.php was missed)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
    .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; border-left: 4px solid; display: flex; justify-content: space-between; align-items: center; }
    .alert-success { background-color: #d1fae5; color: #065f46; border-left-color: var(--success); }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border-left-color: var(--danger); }
    .alert-close { cursor: pointer; background: none; border: none; font-size: 1.2rem; color: inherit; opacity: 0.7; }
    .alert-close:hover { opacity: 1; }
</style>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success">
        <div><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($_SESSION['success_msg']); ?></div>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <?php unset($_SESSION['success_msg']); // Clear message after displaying ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-danger">
        <div><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($_SESSION['error_msg']); ?></div>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <?php unset($_SESSION['error_msg']); // Clear message after displaying ?>
<?php endif; ?>