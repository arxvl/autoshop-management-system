<?php
// reports/sales.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Sales & Revenue Dashboard";

// --- Handle Date Filtering ---
$startDate = $_GET['start_date'] ?? date('Y-m-01'); 
$endDate = $_GET['end_date'] ?? date('Y-m-t');      

// --- Fetch Aggregate Statistics (STRICTLY FROM PAYMENTS) ---
try {
    // Sums actual cash collected in the Payment_T table
    $stmtRev = $pdo->prepare("SELECT SUM(AmountPaid) FROM Payment_T WHERE PaymentStatus IN ('Paid', 'Partial') AND PaymentDate BETWEEN ? AND ?");
    $stmtRev->execute([$startDate, $endDate]);
    $totalRevenue = $stmtRev->fetchColumn() ?: 0;

    // Counts unique orders that actually received a payment
    $stmtOrd = $pdo->prepare("SELECT COUNT(DISTINCT OrderID) FROM Payment_T WHERE PaymentStatus IN ('Paid', 'Partial') AND PaymentDate BETWEEN ? AND ?");
    $stmtOrd->execute([$startDate, $endDate]);
    $totalOrders = $stmtOrd->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $totalRevenue = $totalOrders = 0;
    $_SESSION['error_msg'] = "Analytics Error: " . $e->getMessage();
}

// --- Fetch Table Data (SHOWS PAID ORDERS ONLY) ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT 
            o.OrderID, 
            c.CustomerName, 
            o.OrderDate, 
            
            -- Fetching the nested items so the dashboard is highly detailed
            (SELECT GROUP_CONCAT(ServiceRecordID SEPARATOR ', ') FROM ServiceRecord_T WHERE OrderID = o.OrderID) as LinkedServices,
            (SELECT GROUP_CONCAT(CONCAT(p.PartName, ' (x', oi.Quantity, ')') SEPARATOR ', ') FROM OrderItem_T oi JOIN Part_T p ON oi.PartID = p.PartID WHERE oi.OrderID = o.OrderID) as LinkedRetailParts,
            
            o.OrderTotalAmount, 
            
            -- Fetches the exact amount of cash collected for this order within the date range
            (SELECT SUM(AmountPaid) FROM Payment_T WHERE OrderID = o.OrderID AND PaymentStatus IN ('Paid', 'Partial') AND PaymentDate BETWEEN ? AND ?) as RevenueCollected,
            
            o.OrderStatus 
        FROM Order_T o 
        JOIN Customer_T c ON o.CustomerID = c.CustomerID 
        
        -- ONLY fetch orders that have a valid payment attached within the date range
        WHERE EXISTS (
            SELECT 1 FROM Payment_T p 
            WHERE p.OrderID = o.OrderID 
            AND p.PaymentStatus IN ('Paid', 'Partial') 
            AND p.PaymentDate BETWEEN ? AND ?
        )";

$params = [$startDate, $endDate, $startDate, $endDate];
if ($searchQuery) {
    $sql .= " AND (o.OrderID LIKE ? OR c.CustomerName LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
$sql .= " ORDER BY o.OrderDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Format Currency & Handle Empty States (NO HTML INJECTION)
foreach ($tableData as &$row) {
    $row['OrderTotalAmount'] = "₱" . number_format($row['OrderTotalAmount'], 2);
    $row['RevenueCollected'] = "₱" . number_format($row['RevenueCollected'], 2);
    
    // Using plain text instead of HTML spans so the table doesn't print code
    if (empty($row['LinkedServices'])) $row['LinkedServices'] = "None";
    if (empty($row['LinkedRetailParts'])) $row['LinkedRetailParts'] = "None";
    
    // Status is left as raw text. The global footer.js script will turn it into a badge!
}
unset($row); // Prevent duplicate row bug

// --- Setup UI Components ---
$tableHeaders = [
    'OrderID' => 'Order Ref', 
    'CustomerName' => 'Customer', 
    'LinkedServices' => 'Services Rendered',
    'LinkedRetailParts' => 'Retail Items Sold',
    'OrderTotalAmount' => 'Bill Total', 
    'RevenueCollected' => 'Amount Paid', 
    'OrderStatus' => 'Order Status'
];
$primaryKey = 'OrderID';
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
    
    .data-table th:last-child, .data-table td:last-child { display: none; } /* Hide Actions column on reports */
</style>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Sales & Revenue Dashboard</h2>
        
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Start Date (Payment Received)</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
            </div>
            <div class="filter-group">
                <label>End Date (Payment Received)</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
            </div>
            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; height: 35px;">Filter Report</button>
        </form>

        <div class="stat-grid">
            <div class="stat-card">
                <h3>Cash Collected</h3>
                <div class="val">₱<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: var(--accent);">
                <h3>Orders Paid</h3>
                <div class="val"><?php echo number_format($totalOrders); ?></div>
            </div>
        </div>
        
        <h3>Paid Orders Log</h3>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>