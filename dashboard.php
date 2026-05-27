<?php
// dashboard.php
require_once 'config/auth.php';
require_login();
global $pdo;

$pageTitle = "Dashboard - Patrick Auto Repair System";

// Fetch live metrics
try {
    $totalCustomers = $pdo->query("SELECT COUNT(*) FROM Customer_T")->fetchColumn();
    $totalVehicles = $pdo->query("SELECT COUNT(*) FROM Vehicle_T")->fetchColumn();
    $totalMechanics = $pdo->query("SELECT COUNT(*) FROM Mechanic_T")->fetchColumn();
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM Order_T WHERE OrderStatus = 'Pending'")->fetchColumn() ?: 0;
    
    // Fetch the 5 most recent payments for the new widget
    $recentPayments = $pdo->query("
        SELECT p.PaymentID, p.AmountPaid, p.PaymentDate, p.PaymentMethod, c.CustomerName
        FROM Payment_T p
        JOIN Order_T o ON p.OrderID = o.OrderID
        JOIN Customer_T c ON o.CustomerID = c.CustomerID
        ORDER BY p.PaymentDate DESC, p.PaymentID DESC
        LIMIT 5
    ")->fetchAll();
    
} catch (PDOException $e) {
    // Graceful fallback if tables are not fully seeded yet
    $totalCustomers = $totalVehicles = $totalMechanics = $pendingOrders = 0;
    $recentPayments = [];
}

// 1. Include the global Header and Top Navbar
include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>
    /* Top Metrics Grid */
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
    .card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid var(--accent); }
    .card h3 { margin: 0 0 0.5rem 0; color: var(--text-light); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .card .value { font-size: 2.5rem; font-weight: bold; color: var(--text-main); }
    
    /* Lower Dashboard Sections */
    .dashboard-sections { display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-top: 2rem; }
    @media (max-width: 850px) { .dashboard-sections { grid-template-columns: 1fr; } }
    
    .section-card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .section-card h3 { margin-top: 0; margin-bottom: 1.2rem; color: var(--text-main); border-bottom: 2px solid #f3f4f6; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
    
    /* Quick Actions */
    .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .action-btn { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; background: #f9fafb; color: var(--text-main); padding: 1rem; border-radius: 6px; text-decoration: none; font-weight: 500; border: 1px solid #e5e7eb; transition: all 0.2s; text-align: center; }
    .action-btn i { font-size: 1.5rem; }
    .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .action-btn.blue:hover { border-color: #3b82f6; color: #1d4ed8; }
    .action-btn.green:hover { border-color: #10b981; color: #047857; }
    .action-btn.orange:hover { border-color: #f59e0b; color: #b45309; }
    .action-btn.purple:hover { border-color: #8b5cf6; color: #6d28d9; }
    .action-btn.blue i { color: #3b82f6; }
    .action-btn.green i { color: #10b981; }
    .action-btn.orange i { color: #f59e0b; }
    .action-btn.purple i { color: #8b5cf6; }

    /* Recent Payments Table */
    .recent-table { width: 100%; border-collapse: collapse; }
    .recent-table th, .recent-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #f3f4f6; font-size: 0.9em; }
    .recent-table th { color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8em; }
    .recent-table tr:last-child td { border-bottom: none; }
    .method-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.85em; font-weight: 600; white-space: nowrap; }
    .method-gcash { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .method-cash { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
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
                <div class="value" style="color: <?php echo $pendingOrders > 0 ? '#f59e0b' : 'var(--text-main)'; ?>;">
                    <?php echo number_format($pendingOrders); ?>
                </div>
            </div>
        </div>

        <div class="dashboard-sections">
            
            <div class="section-card">
                <h3><i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Quick Actions</h3>
                <div class="quick-actions">
                    <a href="orders/index.php" class="action-btn blue">
                        <i class="fa-solid fa-file-invoice"></i>
                        New Order
                    </a>
                    <a href="payments/index.php" class="action-btn green">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        Log Payment
                    </a>
                    <a href="servicerecords/index.php" class="action-btn purple">
                        <i class="fa-solid fa-wrench"></i>
                        New Repair
                    </a>
                    <a href="customers/index.php" class="action-btn orange">
                        <i class="fa-solid fa-user-plus"></i>
                        Add Customer
                    </a>
                </div>
            </div>

            <div class="section-card">
                <h3><i class="fa-solid fa-clock-rotate-left" style="color: var(--accent);"></i> Recent Payments</h3>
                <div style="overflow-x: auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #9ca3af; padding: 2rem;">No recent payments recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentPayments as $rp): ?>
                                    <tr>
                                        <td style="color: #4b5563;"><?php echo htmlspecialchars($rp['PaymentDate']); ?></td>
                                        <td style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($rp['CustomerName']); ?></td>
                                        <td style="font-weight: bold; color: #0369a1;">₱<?php echo number_format($rp['AmountPaid'], 2); ?></td>
                                        <td>
                                            <?php if ($rp['PaymentMethod'] === 'GCash'): ?>
                                                <span class="method-badge method-gcash"><i class="fa-solid fa-mobile-screen"></i> GCash</span>
                                            <?php elseif ($rp['PaymentMethod'] === 'Cash'): ?>
                                                <span class="method-badge method-cash"><i class="fa-solid fa-money-bill-wave"></i> Cash</span>
                                            <?php else: ?>
                                                <span class="method-badge" style="background: #f3f4f6; border: 1px solid #d1d5db; color: #4b5563;"><?php echo htmlspecialchars($rp['PaymentMethod']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($recentPayments)): ?>
                    <div style="text-align: right; margin-top: 1rem;">
                        <a href="payments/index.php" style="color: var(--accent); text-decoration: none; font-size: 0.9em; font-weight: 500;">View All Payments &rarr;</a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>