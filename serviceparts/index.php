<?php
// serviceparts/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Log Parts Used";

// 1. Fetch active service records WITH their Customer and Vehicle info!
$records = $pdo->query("
    SELECT 
        sr.ServiceRecordID, 
        c.CustomerName, 
        v.VehiclePlateNumber, 
        v.VehicleModel 
    FROM ServiceRecord_T sr
    LEFT JOIN Order_T o ON sr.OrderID = o.OrderID
    LEFT JOIN Customer_T c ON o.CustomerID = c.CustomerID
    LEFT JOIN Vehicle_T v ON sr.VehicleID = v.VehicleID
    WHERE sr.Stat != 'Completed' 
    ORDER BY sr.DateReceived DESC
")->fetchAll();

$parts = $pdo->query("SELECT PartID, PartName, UnitPrice, QuantityInStock FROM Part_T WHERE QuantityInStock > 0 ORDER BY PartName")->fetchAll();

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $pdo->beginTransaction();
            
            $serviceRecordID = $_POST['ServiceRecordID'];
            $partID = $_POST['PartID'];
            $quantityAdded = (int)$_POST['QuantityUsed'];
            $subtotalAdded = (float)$_POST['Subtotal'];

            // 1. Check if this exact part is ALREADY in this exact Service Record
            $checkStmt = $pdo->prepare("SELECT QuantityUsed, Subtotal FROM PartsUsed_T WHERE ServiceRecordID = ? AND PartID = ?");
            $checkStmt->execute([$serviceRecordID, $partID]);
            $existingPart = $checkStmt->fetch();

            if ($existingPart) {
                // UPDATE: It already exists! Just add the new quantity and subtotal to the old ones to prevent duplicates.
                $newQty = $existingPart['QuantityUsed'] + $quantityAdded;
                $newSubtotal = $existingPart['Subtotal'] + $subtotalAdded;
                
                $updateStmt = $pdo->prepare("UPDATE PartsUsed_T SET QuantityUsed = ?, Subtotal = ? WHERE ServiceRecordID = ? AND PartID = ?");
                $updateStmt->execute([$newQty, $newSubtotal, $serviceRecordID, $partID]);
            } else {
                // INSERT: It's a brand new part for this record.
                $insertStmt = $pdo->prepare("INSERT INTO PartsUsed_T (ServiceRecordID, PartID, QuantityUsed, Subtotal) VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$serviceRecordID, $partID, $quantityAdded, $subtotalAdded]);
            }
            
            // 2. Deduct from Master Inventory
            $stmtInv = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock - ? WHERE PartID = ?");
            $stmtInv->execute([$quantityAdded, $partID]);

            // 3. Sync Total Parts Cost to the Service Record
            $syncStmt = $pdo->prepare("UPDATE ServiceRecord_T SET TotalPartsCost = (SELECT COALESCE(SUM(Subtotal), 0) FROM PartsUsed_T WHERE ServiceRecordID = ?) WHERE ServiceRecordID = ?");
            $syncStmt->execute([$serviceRecordID, $serviceRecordID]);
            
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

// Upgraded query to pull Customer and Vehicle data for the main table!
$sql = "SELECT 
            pu.ServiceRecordID, 
            pu.PartID,
            p.PartName, 
            pu.QuantityUsed, 
            p.UnitPrice, 
            pu.Subtotal,
            c.CustomerName,
            v.VehiclePlateNumber,
            v.VehicleModel,
            CONCAT(pu.ServiceRecordID, '|', pu.PartID, '|', pu.QuantityUsed) as CompositeID 
        FROM PartsUsed_T pu 
        JOIN Part_T p ON pu.PartID = p.PartID
        LEFT JOIN ServiceRecord_T sr ON pu.ServiceRecordID = sr.ServiceRecordID
        LEFT JOIN Order_T o ON sr.OrderID = o.OrderID
        LEFT JOIN Customer_T c ON o.CustomerID = c.CustomerID
        LEFT JOIN Vehicle_T v ON sr.VehicleID = v.VehicleID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE pu.ServiceRecordID LIKE ? OR p.PartName LIKE ? OR c.CustomerName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

foreach ($tableData as &$row) {
    $row['UnitPrice'] = "₱" . number_format($row['UnitPrice'], 2);
    $row['Subtotal'] = "₱" . number_format($row['Subtotal'], 2);
    
    // Add text emojis for the table display context
    $veh = $row['VehiclePlateNumber'] ? " :: {$row['VehiclePlateNumber']} [{$row['VehicleModel']}]" : " :: Walk-in";
    $row['CustomerDisplay'] = $row['CustomerName'] . $veh;
}
unset($row); // 🚨 THE MAGIC BUG FIX IS RIGHT HERE! 🚨

$tableHeaders = [
    'ServiceRecordID' => 'Record Ref', 
    'CustomerDisplay' => 'Customer & Vehicle',
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
            <div class="modal-content" style="max-width: 650px;">
                <div class="modal-header">
                    <h2>Attach Part to Record</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Target Service Record</label>
                                <select name="ServiceRecordID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                    <option value="">Select Target Record...</option>
                                    <?php foreach($records as $r): 
                                        $veh = $r['VehiclePlateNumber'] ? " :: {$r['VehiclePlateNumber']} [{$r['VehicleModel']}]" : " :: Walk-in";
                                    ?>
                                        <option value="<?php echo $r['ServiceRecordID']; ?>">
                                            <?php echo $r['ServiceRecordID'] . " - " . htmlspecialchars($r['CustomerName']) . $veh; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1.5;">
                                <label>Inventory Part</label>
                                <select name="PartID" id="partSelect" required onchange="calculatePartCost()" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                    <option value="" data-price="0">Select Part from Stock...</option>
                                    <?php foreach($parts as $p) echo "<option value='{$p['PartID']}' data-price='{$p['UnitPrice']}'>{$p['PartName']} (In Stock: {$p['QuantityInStock']})</option>"; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Quantity Used</label>
                                <input type="number" name="QuantityUsed" id="qtyInput" required min="1" value="1" oninput="calculatePartCost()" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                            </div>
                            <div class="form-group">
                                <label>Unit Price (₱)</label>
                                <input type="number" id="priceInput" required step="0.01" min="0" readonly style="background: #f3f4f6; width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Total Subtotal (₱)</label>
                            <input type="number" name="Subtotal" id="totalCostInput" required step="0.01" min="0" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold; border-color: #bae6fd; width: 100%; box-sizing: border-box; padding: 0.6rem; border-radius: 4px;">
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
    /* Parts used is a linking table; we disable edit to enforce inventory strictness */
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