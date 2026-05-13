<?php
// reports/services.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Service Performance Report";

// --- Handle Date Filtering ---
$startDate = $_GET['start_date'] ?? date('Y-m-01'); 
$endDate = $_GET['end_date'] ?? date('Y-m-t');      

// --- Fetch Aggregate Statistics ---
try {
    // Total Service Records logged in this period
    $stmtRec = $pdo->prepare("SELECT COUNT(*) FROM ServiceRecord_T WHERE DateReceived BETWEEN ? AND ?");
    $stmtRec->execute([$startDate, $endDate]);
    $totalRecords = $stmtRec->fetchColumn() ?: 0;

    // Total Labor Hours Billed
    $stmtHrs = $pdo->prepare("
        SELECT SUM(rs.HoursWorked) 
        FROM RepairService_T rs 
        JOIN ServiceRecord_T sr ON rs.ServiceRecordID = sr.ServiceRecordID 
        WHERE sr.DateReceived BETWEEN ? AND ?
    ");
    $stmtHrs->execute([$startDate, $endDate]);
    $totalHours = $stmtHrs->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $totalRecords = $totalHours = 0;
    $_SESSION['error_msg'] = "Analytics Error: " . $e->getMessage();
}

// --- Fetch Table Data (Grouped by Service Type) ---
// This query calculates how many times each service was performed and the revenue it generated
$sql = "SELECT 
            st.ServiceName, 
            COUNT(rs.ServiceRecordID) as TimesRequested, 
            SUM(rs.HoursWorked) as TotalHours, 
            SUM(rs.LaborCost) as TotalRevenue 
        FROM RepairService_T rs 
        JOIN ServiceType_T st ON rs.ServiceTypeID = st.ServiceTypeID 
        JOIN ServiceRecord_T sr ON rs.ServiceRecordID = sr.ServiceRecordID 
        WHERE sr.DateReceived BETWEEN ? AND ? 
        GROUP BY st.ServiceName 
        ORDER BY TimesRequested DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$tableData = $stmt->fetchAll();

// Format numbers for display
foreach ($tableData as &$row) {
    $row['TotalRevenue'] = "₱" . number_format($row['TotalRevenue'], 2);
    $row['TotalHours'] = number_format($row['TotalHours'], 2) . " hrs";
}

// --- Setup UI Components ---
$tableHeaders = [
    'ServiceName' => 'Service Type', 
    'TimesRequested' => 'Times Performed', 
    'TotalHours' => 'Total Labor Hours', 
    'TotalRevenue' => 'Labor Revenue Generated'
];
$primaryKey = 'ServiceName';
$showAddButton = false;

include '../includes/header.php'; 
include '../includes/navbar.php';
?>

<style>
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #8b5cf6; } /* Purple accent for services */
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
        
        <h2>Service Performance Analytics</h2>
        
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
            </div>
            <button type="submit" style="background: #8b5cf6; color: white; padding: 0.5rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; height: 35px;">Filter Report</button>
        </form>

        <div class="stat-grid">
            <div class="stat-card">
                <h3>Vehicles Serviced</h3>
                <div class="val"><?php echo number_format($totalRecords); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: var(--accent);">
                <h3>Total Labor Hours</h3>
                <div class="val"><?php echo number_format($totalHours, 1); ?></div>
            </div>
        </div>
        
        <h3>Most Popular Services</h3>
        <?php include '../components/table.php'; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>