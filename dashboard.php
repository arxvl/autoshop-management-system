<?php
// dashboard.php
require_once 'config/auth.php';
require_login();
global $pdo;

$pageTitle = "Dashboard - Patrick Auto Repair System";

// Fetch live metrics from your actual tables
try {
    $totalCustomers = $pdo->query("SELECT COUNT(*) FROM Customer_T")->fetchColumn();
    $totalVehicles = $pdo->query("SELECT COUNT(*) FROM Vehicle_T")->fetchColumn();
    $totalMechanics = $pdo->query("SELECT COUNT(*) FROM Mechanic_T")->fetchColumn();
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM Order_T WHERE OrderStatus = 'Pending'")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Graceful fallback if tables are not fully seeded yet
    $totalCustomers = $totalVehicles = $totalMechanics = $pendingOrders = 0;
}

// 1. Include the global Header and Top Navbar
include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
    .card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid var(--accent); }
    .card h3 { margin: 0 0 0.5rem 0; color: var(--text-light); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .card .value { font-size: 2.5rem; font-weight: bold; color: var(--text-main); }
    .info-box { margin-top: 2rem; background: #e0f2fe; padding: 1rem; border-radius: 6px; border: 1px solid #bae6fd; color: #0369a1; }
</style>

<div class="wrapper">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/alerts.php'; ?>
        
        <h2 style="margin-top: 0;">Dashboard Overview</h2>
        
        <div class="grid">
            <div class="card">
                <h3>Total Customers</h3>
                <div class="value"><?php echo number_format($totalCustomers); ?></div>
            </div>
            <div class="card">
                <h3>Registered Vehicles</h3>
                <div class="value"><?php echo number_format($totalVehicles); ?></div>
            </div>
            <div class="card">
                <h3>Active Mechanics</h3>
                <div class="value"><?php echo number_format($totalMechanics); ?></div>
            </div>
            <div class="card">
                <h3>Pending Orders</h3>
                <div class="value"><?php echo number_format($pendingOrders); ?></div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>