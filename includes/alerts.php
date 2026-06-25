<?php
// includes/alerts.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
    .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 2px; border: 1px solid; border-left: 4px solid; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 0.9em; box-shadow: 2px 2px 0px rgba(0,0,0,0.05); text-transform: uppercase; letter-spacing: 0.05em; }
    .alert-success { background-color: #f0fdf4; color: #14532d; border-color: #bbf7d0; border-left-color: var(--success); }
    .alert-danger { background-color: #fef2f2; color: #7f1d1d; border-color: #fecaca; border-left-color: var(--danger); }
    .alert-close { cursor: pointer; background: none; border: none; box-shadow: none; font-size: 1.2rem; color: inherit; opacity: 0.6; padding: 0; }
    .alert-close:hover { opacity: 1; transform: none; background: none; }
</style>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success">
        <div><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($_SESSION['success_msg']); ?></div>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <?php unset($_SESSION['success_msg']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-danger">
        <div><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($_SESSION['error_msg']); ?></div>
        <button class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <?php unset($_SESSION['error_msg']); ?>
<?php endif; ?>