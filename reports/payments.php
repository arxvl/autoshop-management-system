<?php
// reports/payments.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Payment Collections Report";

// --- Handle Date Filtering ---
$startDate = $_GET['start_date'] ?? date('Y-m-01'); 
$endDate = $_GET['end_date'] ?? date('Y-m-t');      

// --- Fetch Aggregate Statistics ---
try {
    // Total Cash Collected
    $stmtCash = $pdo->prepare("SELECT SUM(AmountPaid) FROM Payment_T WHERE PaymentMethod = 'Cash' AND PaymentStatus IN ('Paid', 'Partial') AND PaymentDate BETWEEN ? AND ?");
    $stmtCash->execute([$startDate, $endDate]);
    $totalCash = $stmtCash->fetchColumn() ?: 0;

    // Total GCash Collected
    $stmtGCash = $pdo->prepare("SELECT SUM(AmountPaid) FROM Payment_T WHERE PaymentMethod = 'GCash' AND PaymentStatus IN ('Paid', 'Partial') AND PaymentDate BETWEEN ? AND ?");
    $stmtGCash->execute([$startDate, $endDate]);
    $totalGCash = $stmtGCash->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $totalCash = $totalGCash = 0;
    $_SESSION['error_msg'] = "Analytics Error: " . $e->getMessage();
}

// --- Fetch Table Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT PaymentID, OrderID, PaymentDate, AmountPaid, PaymentMethod, PaymentStatus 
        FROM Payment_T 
        WHERE PaymentDate BETWEEN ? AND ?";

$params = [$startDate, $endDate];
if ($searchQuery) {
    $sql .= " AND (PaymentID LIKE ? OR OrderID LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
$sql .= " ORDER BY PaymentDate DESC, PaymentID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Format numbers and add styling
foreach ($tableData as &$row) {
    $row['AmountPaid'] = "₱" . number_format($row['AmountPaid'], 2);
    // Add visual tags for Payment Method
    if ($row['PaymentMethod'] === 'GCash') {
        $row['PaymentMethod'] = "<span style='background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;'>📱 GCash</span>";
    } else {
        $row['PaymentMethod'] = "<span style='background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;'>💵 Cash</span>";
    }
}

// --- Setup UI Components ---
$tableHeaders = [
    'PaymentID' => 'Receipt No.', 
    'OrderID' => 'Order Ref', 
    'PaymentDate' => 'Date', 
    'AmountPaid' => 'Amount Collected', 
    'PaymentMethod' => 'Method',
    'PaymentStatus' => 'Status'
];
$primaryKey = 'PaymentID';
$showAddButton = false;

include '../includes/header.php'; 
include '../includes/navbar.php';
?>

<style>
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid var(--success); }
    .stat-card h3 { margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-card .val { font-size: 2.5rem; font-weight: bold; color: var(--text-main); }
    
    .filter-form { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: flex-end; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .filter-group { display: flex; flex-direction: column; }
    .filter-group label { font-size: 0.85rem; color: #4b5563; margin-bottom: 0.25rem; font-weight: 500; }
    .filter-group input { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; }
    
    .data-table th:last-child, .data-table td:last-child { display: none; } /* Hide Actions */
</style>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Payment Collections Dashboard</h2>
        
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
            </div>
            <button type="submit" style="background: var(--success); color: white; padding: 0.5rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; height: 35px;">Filter Report</button>
        </form>

        <div class="stat-grid">
            <div class="stat-card">
                <h3>Cash in Register</h3>
                <div class="val">₱<?php echo number_format($totalCash, 2); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #4338ca;">
                <h3>GCash Transfers</h3>
                <div class="val">₱<?php echo number_format($totalGCash, 2); ?></div>
            </div>
        </div>
        
        <h3>Transaction History</h3>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>