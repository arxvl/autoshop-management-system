<?php
// reports/inventory.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Inventory Valuation Report";

// --- Fetch Aggregate Statistics ---
try {
    // Total value of all parts in stock (Quantity * Price)
    $stmtVal = $pdo->query("SELECT SUM(QuantityInStock * UnitPrice) FROM Part_T");
    $totalInventoryValue = $stmtVal->fetchColumn() ?: 0;

    // Count parts that are low on stock (less than 5 items left)
    $stmtLow = $pdo->query("SELECT COUNT(*) FROM Part_T WHERE QuantityInStock <= 5");
    $lowStockItems = $stmtLow->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $totalInventoryValue = $lowStockItems = 0;
    $_SESSION['error_msg'] = "Analytics Error: " . $e->getMessage();
}

// --- Fetch Table Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT 
            PartID, 
            PartName, 
            QuantityInStock, 
            UnitPrice, 
            (QuantityInStock * UnitPrice) as TotalValue 
        FROM Part_T";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE PartName LIKE ? OR PartID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY QuantityInStock ASC"; // Sort by lowest stock first

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Formatting Data for the Table ---
// We loop through the data to format the numbers with commas and peso signs before rendering the table
foreach ($tableData as &$row) {
    // If stock is low, wrap it in a red warning span
    if ($row['QuantityInStock'] <= 5) {
        $row['QuantityInStock'] = "<span style='color: #dc2626; font-weight: bold;'>⚠️ " . $row['QuantityInStock'] . " (Low Stock)</span>";
    }
    $row['UnitPrice'] = "₱" . number_format($row['UnitPrice'], 2);
    $row['TotalValue'] = "₱" . number_format($row['TotalValue'], 2);
}

// --- Setup UI Components ---
$tableHeaders = [
    'PartID' => 'Item Code', 
    'PartName' => 'Description', 
    'QuantityInStock' => 'Current Stock', 
    'UnitPrice' => 'Unit Price', 
    'TotalValue' => 'Total Asset Value'
];
$primaryKey = 'PartID';
$searchPlaceholder = "Search inventory assets...";
$showAddButton = false;

include '../includes/header.php'; 
include '../includes/navbar.php';
?>

<style>
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid var(--accent); }
    .stat-card h3 { margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-card .val { font-size: 2.5rem; font-weight: bold; color: var(--text-main); }
    
    /* Hide Actions Column for Reports */
    .data-table th:last-child, .data-table td:last-child { display: none; }
</style>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Inventory Asset Valuation</h2>

        <div class="stat-grid">
            <div class="stat-card">
                <h3>Total Asset Value</h3>
                <div class="val">₱<?php echo number_format($totalInventoryValue, 2); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ef4444;">
                <h3>Low Stock Warnings</h3>
                <div class="val" style="color: #dc2626;"><?php echo number_format($lowStockItems); ?></div>
            </div>
        </div>
        
        <?php 
        include '../components/search.php'; 
        include '../components/table.php'; 
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>