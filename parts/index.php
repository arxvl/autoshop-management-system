<?php
// parts/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Master Inventory";

// Handle POST Requests (Delete only for master view, adding is done in specific subtype pages)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM Part_T WHERE PartID=?");
        $stmt->execute([$_POST['delete_id']]);
        $_SESSION['success_msg'] = "Part deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// Fetch all parts
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT * FROM Part_T";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE PartName LIKE ? OR PartID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$tableData = $pdo->prepare($sql);
$tableData->execute($params);
$tableData = $tableData->fetchAll();

// Setup UI Components
$tableHeaders = [
    'PartID' => 'ID', 
    'PartName' => 'Part Description', 
    'QuantityInStock' => 'Stock Qty', 
    'UnitPrice' => 'Unit Price (₱)'
];
$primaryKey = 'PartID';
$searchPlaceholder = "Search all inventory...";
$showAddButton = false; // We hide this here. Users will add via Consumables or Spare Parts pages
$deleteActionUrl = "index.php";

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2>Master Inventory</h2>
            <div>
                <a href="../consumables/index.php" style="background: var(--accent); color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; margin-right: 0.5rem;">+ Add Consumable</a>
                <a href="../spareparts/index.php" style="background: var(--accent); color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px;">+ Add Spare Part</a>
            </div>
        </div>
        
        <?php 
        include '../components/search.php'; 
        include '../components/table.php'; 
        include '../components/delete_confirm.php';
        ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>