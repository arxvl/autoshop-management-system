<?php
// includes/navbar.php
?>
<style>
    .top-navbar { background: var(--primary-bg); color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10; position: relative; }
    .top-navbar .brand { font-size: 1.25rem; font-weight: bold; letter-spacing: 0.5px; }
    .top-navbar .user-actions { display: flex; align-items: center; gap: 1.5rem; }
    .logout-btn { color: #fff; text-decoration: none; background: var(--danger); padding: 0.4rem 1rem; border-radius: 4px; font-size: 0.9em; font-weight: 500; transition: background 0.2s; }
    .logout-btn:hover { background: #dc2626; }
</style>

<div class="top-navbar">
    <div class="brand">
        <i class="fa-solid fa-wrench"></i> Patrick Auto Repair Shop
    </div>
    <div class="user-actions">
        <span><i class="fa-solid fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</span>
        <a href="/autoshop_system/logout.php" class="logout-btn"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>
</div>