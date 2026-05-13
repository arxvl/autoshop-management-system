<?php
// serviceparts/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Log Parts Used";

$records = $pdo->query("SELECT ServiceRecordID FROM ServiceRecord_T WHERE Stat != 'Completed' ORDER BY DateReceived DESC")->fetchAll();
$parts = $pdo->query("SELECT PartID, PartName, UnitPrice, QuantityInStock FROM Part_T WHERE QuantityInStock > 0 ORDER BY PartName")->fetchAll();

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $pdo->beginTransaction();
            
            // CORRECTED: Uses PartsUsed_T, QuantityUsed, and Subtotal
            $stmt1 = $pdo->prepare("INSERT INTO PartsUsed_T (ServiceRecordID, PartID, QuantityUsed, Subtotal) VALUES (?, ?, ?, ?)");
            $stmt1->execute([$_POST['ServiceRecordID'], $_POST['PartID'], $_POST['QuantityUsed'], $_POST['Subtotal']]);
            
            $stmt2 = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock - ? WHERE PartID = ?");
            $stmt2->execute([$_POST['QuantityUsed'], $_POST['PartID']]);

            $syncStmt = $pdo->prepare("UPDATE ServiceRecord_T SET TotalPartsCost = (SELECT COALESCE(SUM(Subtotal), 0) FROM PartsUsed_T WHERE ServiceRecordID = ?) WHERE ServiceRecordID = ?");
            $syncStmt->execute([$_POST['ServiceRecordID'], $_POST['ServiceRecordID']]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Part logged, inventory deducted, and total updated!";
        } 
        elseif ($action === 'delete') {
            $pdo->beginTransaction();
            list($recID, $partID, $qty) = explode('|', $_POST['delete_id']);
            
            $stmt1 = $pdo->prepare("DELETE FROM PartsUsed_T WHERE ServiceRecordID=? AND PartID=?");
            $stmt1->execute([$recID, $partID]);
            
            $stmt2 = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock + ? WHERE PartID = ?");
            $stmt2->execute([$qty, $partID]);

            $syncStmt = $pdo->prepare("UPDATE ServiceRecord_T SET TotalPartsCost = (SELECT COALESCE(SUM(Subtotal), 0) FROM PartsUsed_T WHERE ServiceRecordID = ?) WHERE ServiceRecordID = ?");
            $syncStmt->execute([$recID, $recID]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Part removed and restored to inventory!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT 
            pu.ServiceRecordID, 
            pu.PartID,
            p.PartName, 
            pu.QuantityUsed, 
            p.UnitPrice, 
            pu.Subtotal,
            CONCAT(pu.ServiceRecordID, '|', pu.PartID, '|', pu.QuantityUsed) as CompositeID 
        FROM PartsUsed_T pu 
        JOIN Part_T p ON pu.PartID = p.PartID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE pu.ServiceRecordID LIKE ? OR p.PartName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

foreach ($tableData as &$row) {
    $row['UnitPrice'] = "₱" . number_format($row['UnitPrice'], 2);
    $row['Subtotal'] = "₱" . number_format($row['Subtotal'], 2);
}

$tableHeaders = [
    'ServiceRecordID' => 'Record Ref', 
    'PartName' => 'Part Used', 
    'QuantityUsed' => 'Qty Used', 
    'UnitPrice' => 'Unit Price', 
    'Subtotal' => 'Subtotal'
];
$primaryKey = 'CompositeID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Log Parts Used on Jobs</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Attach Part to Record</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>Service Record ID</label>
                                <select name="ServiceRecordID" required>
                                    <option value="">Select Active Record...</option>
                                    <?php foreach($records as $r) echo "<option value='{$r['ServiceRecordID']}'>{$r['ServiceRecordID']}</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Inventory Part</label>
                                <select name="PartID" id="partSelect" required onchange="calculatePartCost()">
                                    <option value="" data-price="0">Select Part from Stock...</option>
                                    <?php foreach($parts as $p) echo "<option value='{$p['PartID']}' data-price='{$p['UnitPrice']}'>{$p['PartName']} (In Stock: {$p['QuantityInStock']})</option>"; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Quantity Used</label>
                                <input type="number" name="QuantityUsed" id="qtyInput" required min="1" value="1" oninput="calculatePartCost()">
                            </div>
                            <div class="form-group">
                                <label>Unit Price (₱)</label>
                                <input type="number" id="priceInput" required step="0.01" min="0" readonly style="background: #f3f4f6;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Total Subtotal (₱)</label>
                            <input type="number" name="Subtotal" id="totalCostInput" required step="0.01" min="0" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold; border-color: #bae6fd;">
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save Part Link</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<style>
    .btn-edit { display: none !important; }
</style>

<script>
    function calculatePartCost() {
        const select = document.getElementById('partSelect');
        const price = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
        const qty = parseFloat(document.getElementById('qtyInput').value) || 0;
        
        document.getElementById('priceInput').value = price.toFixed(2);
        document.getElementById('totalCostInput').value = (price * qty).toFixed(2);
    }
</script>

<?php include '../includes/footer.php'; ?>