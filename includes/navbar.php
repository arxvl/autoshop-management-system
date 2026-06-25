<?php
// includes/navbar.php
?>
<style>
    .top-navbar { background: var(--bg-sidebar); color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.3); z-index: 10; position: relative; border-bottom: 3px solid var(--accent); }
    .top-navbar .brand { font-size: 1.25rem; font-weight: 900; letter-spacing: 0.1em; text-transform: uppercase; display: flex; align-items: center; gap: 0.5rem; }
    .top-navbar .user-actions { display: flex; align-items: center; gap: 1.5rem; font-weight: 500; }
    .logout-btn { color: #fff; text-decoration: none; background: #000; padding: 0.5rem 1rem; border-radius: 2px; font-size: 0.85em; font-weight: 700; text-transform: uppercase; transition: background 0.2s; border: 1px solid #374151; }
    .logout-btn:hover { background: var(--danger); border-color: var(--accent-hover); }
</style>

<div class="top-navbar">
    <div class="brand">
        <i class="fa-solid fa-wrench" style="color: var(--accent);"></i> Patrick Auto Repair Shop Management System
    </div>
    <div class="user-actions">
        <span><i class="fa-solid fa-user-gear" style="color: #9ca3af;"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Mechanic'); ?></span>
        <a href="/autoshop_system/logout.php" class="logout-btn"><i class="fa-solid fa-power-off"></i> Logout</a>
    </div>
</div>