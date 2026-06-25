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
    /* Top Metrics Grid - Industrial Style */
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
    .card { 
        background: #fff; 
        padding: 1.5rem; 
        border-radius: 2px; /* Sharper edges */
        box-shadow: 2px 2px 0px rgba(0,0,0,0.05); /* Blocky shadow */
        border: 1px solid #d1d5db;
        border-top: 4px solid var(--accent); /* Red top accent */
    }
    .card h3 { margin: 0 0 0.5rem 0; color: var(--text-light); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; }
    .card .value { font-size: 2.5rem; font-weight: 900; color: var(--text-main); font-family: monospace; }
    
    /* Lower Dashboard Sections */
    .dashboard-sections { display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-top: 2rem; }
    @media (max-width: 850px) { .dashboard-sections { grid-template-columns: 1fr; } }
    
    .section-card { 
        background: #fff; 
        padding: 1.5rem; 
        border-radius: 2px; 
        border: 1px solid #d1d5db;
        box-shadow: 2px 2px 0px rgba(0,0,0,0.05);
    }
    .section-card h3 { 
        margin-top: 0; 
        margin-bottom: 1.5rem; 
        color: var(--text-main); 
        border-bottom: 2px solid #e5e7eb; 
        padding-bottom: 0.75rem; 
        display: flex; 
        align-items: center; 
        gap: 0.5rem; 
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 1rem;
    }
    
    /* Quick Actions - Grayscale & Red */
    .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .action-btn { 
        display: flex; flex-direction: column; align-items: center; gap: 0.75rem; 
        background: #f3f4f6; color: var(--text-main); padding: 1.25rem 1rem; 
        border-radius: 2px; text-decoration: none; font-weight: 600; 
        border: 1px solid #d1d5db; transition: all 0.2s; text-align: center; 
        text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;
    }
    .action-btn i { font-size: 1.75rem; color: #4b5563; transition: color 0.2s; }
    
    /* Industrial Hover Effects */
    .action-btn:hover { background: #fff; border-color: var(--accent); box-shadow: 2px 2px 0px rgba(220, 38, 38, 0.2); transform: translateY(-2px); }
    .action-btn:hover i { color: var(--accent); }

    /* Recent Payments Table */
    .recent-table { width: 100%; border-collapse: collapse; }
    .recent-table th, .recent-table td { padding: 0.85rem; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 0.9em; }
    .recent-table th { color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75em; font-weight: 700; background: #f9fafb; }
    .recent-table tr:hover td { background: #f9fafb; }
    .recent-table tr:last-child td { border-bottom: none; }
    
    /* Industrial Badges */
    .method-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.6rem; border-radius: 2px; font-size: 0.8em; font-weight: 700; text-transform: uppercase; white-space: nowrap; border: 1px solid; }
    .method-gcash { background: #f3f4f6; color: #1f2937; border-color: #4b5563; } /* Steel Badge */
    .method-cash { background: #fff; color: var(--accent); border-color: var(--accent); } /* Red Badge */
</style>

<div class="wrapper">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/alerts.php'; ?>
        
        <h2 style="margin-top: 0; color: #111827; text-transform: uppercase; font-weight: 900; letter-spacing: 0.05em;">Shop Operations</h2>
        
        <!-- Top Metrics Widget -->
        <div class="grid">
            <div class="card">
                <h3><i class="fa-solid fa-users" style="color: #6b7280; margin-right: 5px;"></i> Customers</h3>
                <div class="value"><?php echo number_format($totalCustomers); ?></div>
            </div>
            <div class="card">
                <h3><i class="fa-solid fa-car" style="color: #6b7280; margin-right: 5px;"></i> Vehicles</h3>
                <div class="value"><?php echo number_format($totalVehicles); ?></div>
            </div>
            <div class="card">
                <h3><i class="fa-solid fa-wrench" style="color: #6b7280; margin-right: 5px;"></i> Mechanics</h3>
                <div class="value"><?php echo number_format($totalMechanics); ?></div>
            </div>
            <div class="card" style="border-top-color: <?php echo $pendingOrders > 0 ? 'var(--accent)' : '#9ca3af'; ?>;">
                <h3><i class="fa-solid fa-clipboard-list" style="color: #6b7280; margin-right: 5px;"></i> Pending Orders</h3>
                <div class="value" style="color: <?php echo $pendingOrders > 0 ? 'var(--accent)' : 'var(--text-main)'; ?>;">
                    <?php echo number_format($pendingOrders); ?>
                </div>
            </div>
        </div>

        <!-- Lower Dashboard Sections -->
        <div class="dashboard-sections">
            
            <!-- Quick Actions -->
            <div class="section-card">
                <h3><i class="fa-solid fa-bolt" style="color: var(--accent);"></i> Quick Actions</h3>
                <div class="quick-actions">
                    <a href="orders/index.php" class="action-btn">
                        <i class="fa-solid fa-file-invoice"></i>
                        New Order
                    </a>
                    <a href="payments/index.php" class="action-btn">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        Log Payment
                    </a>
                    <a href="servicerecords/index.php" class="action-btn">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        New Repair
                    </a>
                    <a href="customers/index.php" class="action-btn">
                        <i class="fa-solid fa-user-plus"></i>
                        Add Customer
                    </a>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="section-card">
                <h3><i class="fa-solid fa-receipt" style="color: #6b7280;"></i> Recent Transactions</h3>
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
                                    <td colspan="4" style="text-align: center; color: #9ca3af; padding: 2rem; font-style: italic;">No recent payments recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentPayments as $rp): ?>
                                    <tr>
                                        <td style="color: #6b7280; font-family: monospace;"><?php echo htmlspecialchars($rp['PaymentDate']); ?></td>
                                        <td style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($rp['CustomerName']); ?></td>
                                        <td style="font-weight: 800; color: #111827;">₱<?php echo number_format($rp['AmountPaid'], 2); ?></td>
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
                        <a href="payments/index.php" style="color: var(--accent); text-decoration: none; font-size: 0.85em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">View All Payments &rarr;</a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>