<?php
// servicerecords/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Service Records";

$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer_T ORDER BY CustomerName")->fetchAll();
$vehicles = $pdo->query("
    SELECT v.VehicleID, v.VehiclePlateNumber, v.VehicleModel, c.CustomerName 
    FROM Vehicle_T v 
    JOIN Customer_T c ON v.CustomerID = c.CustomerID 
    ORDER BY v.VehiclePlateNumber
")->fetchAll();

$orders = $pdo->query("SELECT OrderID FROM Order_T ORDER BY OrderDate DESC")->fetchAll();
$services = $pdo->query("SELECT ServiceTypeID, ServiceName, LaborCost FROM ServiceType_T ORDER BY ServiceName")->fetchAll();
$parts = $pdo->query("SELECT PartID, PartName, UnitPrice, QuantityInStock FROM Part_T WHERE QuantityInStock > 0 ORDER BY PartName")->fetchAll();

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $pdo->beginTransaction();
            
            $newRecordID = 'SR-' . strtoupper(substr(uniqid(), -6));
            $dateCompleted = !empty($_POST['DateCompleted']) ? $_POST['DateCompleted'] : null;
            $laborCost = !empty($_POST['TotalLaborCost']) ? $_POST['TotalLaborCost'] : 0.00;
            $partsCost = !empty($_POST['TotalPartsCost']) ? $_POST['TotalPartsCost'] : 0.00;

            $stmt1 = $pdo->prepare("INSERT INTO ServiceRecord_T (ServiceRecordID, CustomerID, VehicleID, OrderID, DateReceived, DateCompleted, Stat, TotalLaborCost, TotalPartsCost, Notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt1->execute([$newRecordID, $_POST['CustomerID'], $_POST['VehicleID'], $_POST['OrderID'], $_POST['DateReceived'], $dateCompleted, $_POST['Stat'], $laborCost, $partsCost, $_POST['Notes']]);
            
            if (!empty($_POST['ServiceTypeID'])) {
                $stmt2 = $pdo->prepare("INSERT INTO RepairService_T (ServiceRecordID, ServiceTypeID, HoursWorked, LaborCost) VALUES (?, ?, ?, ?)");
                $stmt2->execute([$newRecordID, $_POST['ServiceTypeID'], 1, $laborCost]); 
            }

            if (!empty($_POST['PartID'])) {
                $stmt3 = $pdo->prepare("INSERT INTO PartsUsed_T (ServiceRecordID, PartID, QuantityUsed, Subtotal) VALUES (?, ?, 1, ?)");
                $stmt3->execute([$newRecordID, $_POST['PartID'], $partsCost]);

                $stmt4 = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock - 1 WHERE PartID = ?");
                $stmt4->execute([$_POST['PartID']]);
            }
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Service record created successfully!";
        } 
        elseif ($action === 'edit') {
            $dateCompleted = !empty($_POST['DateCompleted']) ? $_POST['DateCompleted'] : null;

            $stmt = $pdo->prepare("UPDATE ServiceRecord_T SET CustomerID=?, VehicleID=?, OrderID=?, DateReceived=?, DateCompleted=?, Stat=?, Notes=? WHERE ServiceRecordID=?");
            $stmt->execute([$_POST['CustomerID'], $_POST['VehicleID'], $_POST['OrderID'], $_POST['DateReceived'], $dateCompleted, $_POST['Stat'], $_POST['Notes'], $_POST['ServiceRecordID']]);
            
            $_SESSION['success_msg'] = "Record updated successfully!";
        }
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM ServiceRecord_T WHERE ServiceRecordID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Record deleted successfully!";
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
            sr.*, 
            c.CustomerName, 
            CONCAT(v.VehiclePlateNumber, ' (', v.VehicleModel, ')') as VehicleDisplay,
            
            (SELECT GROUP_CONCAT(CONCAT(p.PartName, ' (Qty: ', pu.QuantityUsed, ')') SEPARATOR ', ')
             FROM PartsUsed_T pu JOIN Part_T p ON pu.PartID = p.PartID
             WHERE pu.ServiceRecordID = sr.ServiceRecordID) AS PartsList,
             
            (SELECT GROUP_CONCAT(st.ServiceName SEPARATOR ', ')
             FROM RepairService_T rs JOIN ServiceType_T st ON rs.ServiceTypeID = st.ServiceTypeID
             WHERE rs.ServiceRecordID = sr.ServiceRecordID) AS ServicesList

        FROM ServiceRecord_T sr 
        JOIN Vehicle_T v ON sr.VehicleID = v.VehicleID
        JOIN Customer_T c ON sr.CustomerID = c.CustomerID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE sr.ServiceRecordID LIKE ? OR v.VehiclePlateNumber LIKE ? OR c.CustomerName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY sr.DateReceived DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

foreach ($tableData as &$row) {
    $row['TotalLaborCost'] = "₱" . number_format($row['TotalLaborCost'], 2);
    $row['TotalPartsCost'] = "₱" . number_format($row['TotalPartsCost'], 2);
    // REMOVED: HTML badge injection here. The footer JS handles it!
}
unset($row); // FIX: Prevents the item duplication bug!

$tableHeaders = [
    'ServiceRecordID' => 'Record Ref', 
    'VehicleDisplay' => 'Vehicle',
    'DateReceived' => 'Date Received', 
    'Stat' => 'Status',
    'TotalLaborCost' => 'Labor Cost',
    'TotalPartsCost' => 'Parts Cost'
];
$primaryKey = 'ServiceRecordID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Service Records Masterlist</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Create Service Record</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Target Customer</label>
                                <select name="CustomerID" required>
                                    <option value="">Select Customer...</option>
                                    <?php foreach($customers as $c) echo "<option value='{$c['CustomerID']}'>{$c['CustomerName']}</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Target Vehicle</label>
                                <select name="VehicleID" required>
                                    <option value="">Select Vehicle...</option>
                                    <?php foreach($vehicles as $v) echo "<option value='{$v['VehicleID']}'>{$v['VehiclePlateNumber']} - {$v['VehicleModel']}</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>Linked Order ID</label>
                                <select name="OrderID" required>
                                    <option value="">Select Order...</option>
                                    <?php foreach($orders as $o) echo "<option value='{$o['OrderID']}'>{$o['OrderID']}</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;">

                        <div class="form-row">
                            <div class="form-group">
                                <label style="color: var(--accent);">Initial Service (Optional)</label>
                                <select name="ServiceTypeID" id="serviceSelectAdd" onchange="autoFillCost('serviceSelectAdd', 'add_TotalLaborCost')">
                                    <option value="" data-cost="0">Select Service...</option>
                                    <?php foreach($services as $s) echo "<option value='{$s['ServiceTypeID']}' data-cost='{$s['LaborCost']}'>{$s['ServiceName']}</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="color: var(--accent);">Initial Part (Optional)</label>
                                <select name="PartID" id="partSelectAdd" onchange="autoFillCost('partSelectAdd', 'add_TotalPartsCost')">
                                    <option value="" data-cost="0">Select Part from Stock...</option>
                                    <?php foreach($parts as $p) echo "<option value='{$p['PartID']}' data-cost='{$p['UnitPrice']}'>{$p['PartName']} (In Stock: {$p['QuantityInStock']})</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Total Labor Cost (₱)</label>
                                <input type="number" name="TotalLaborCost" id="add_TotalLaborCost" step="0.01" min="0" value="0.00" readonly style="background: #e0f2fe; border-color: #bae6fd;">
                            </div>
                            <div class="form-group">
                                <label>Total Parts Cost (₱)</label>
                                <input type="number" name="TotalPartsCost" id="add_TotalPartsCost" step="0.01" min="0" value="0.00" readonly style="background: #e0f2fe; border-color: #bae6fd;">
                            </div>
                        </div>

                        <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date Received</label>
                                <input type="date" name="DateReceived" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="Stat" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes / Customer Complaints</label>
                            <textarea name="Notes" rows="2" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit;"></textarea>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Edit Service Record</h2><button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Record Ref</label>
                                <input type="text" name="ServiceRecordID" id="edit_ServiceRecordID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="Stat" id="edit_Stat" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Target Customer</label>
                                <select name="CustomerID" id="edit_CustomerID" required>
                                    <?php foreach($customers as $c) echo "<option value='{$c['CustomerID']}'>{$c['CustomerName']}</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Target Vehicle</label>
                                <select name="VehicleID" id="edit_VehicleID" required>
                                    <?php foreach($vehicles as $v) echo "<option value='{$v['VehicleID']}'>{$v['VehiclePlateNumber']} - {$v['VehicleModel']}</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>Linked Order ID</label>
                                <select name="OrderID" id="edit_OrderID" required>
                                    <?php foreach($orders as $o) echo "<option value='{$o['OrderID']}'>{$o['OrderID']}</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div style="background: #f9fafb; padding: 1rem; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 1rem;">
                            <h4 style="margin-top: 0; margin-bottom: 0.5rem; color: #374151; font-size: 0.9em;">Job Summary</h4>
                            
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label style="font-size: 0.8em; color: #6b7280;">Services Performed:</label>
                                <textarea id="edit_ServicesList" readonly rows="1" style="width: 100%; border: none; background: transparent; resize: none; font-weight: bold; color: #111827;"></textarea>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label style="font-size: 0.8em; color: #6b7280;">Parts Installed:</label>
                                <textarea id="edit_PartsList" readonly rows="2" style="width: 100%; border: none; background: transparent; resize: none; font-weight: bold; color: #111827;"></textarea>
                            </div>

                            <div class="form-row" style="margin-bottom: 0;">
                                <div class="form-group">
                                    <label style="font-size: 0.8em; color: #6b7280;">Total Labor Cost (₱)</label>
                                    <input type="text" id="edit_TotalLaborCost" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold; border-color: #bae6fd;">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.8em; color: #6b7280;">Total Parts Cost (₱)</label>
                                    <input type="text" id="edit_TotalPartsCost" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold; border-color: #bae6fd;">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date Received</label>
                                <input type="date" name="DateReceived" id="edit_DateReceived" required>
                            </div>
                            <div class="form-group">
                                <label>Date Completed</label>
                                <input type="date" name="DateCompleted" id="edit_DateCompleted">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes / Customer Complaints</label>
                            <textarea name="Notes" id="edit_Notes" rows="2" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit;"></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    function autoFillCost(selectId, targetInputId) {
        const select = document.getElementById(selectId);
        const cost = select.options[select.selectedIndex].getAttribute('data-cost');
        if (cost && parseFloat(cost) > 0) {
            document.getElementById(targetInputId).value = cost;
        }
    }

    const srData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = srData.find(item => item.ServiceRecordID === id);
        if (row) {
            document.getElementById('edit_ServiceRecordID').value = row.ServiceRecordID;
            document.getElementById('edit_CustomerID').value = row.CustomerID;
            document.getElementById('edit_VehicleID').value = row.VehicleID;
            document.getElementById('edit_OrderID').value = row.OrderID;
            document.getElementById('edit_DateReceived').value = row.DateReceived;
            document.getElementById('edit_DateCompleted').value = row.DateCompleted;
            
            document.getElementById('edit_ServicesList').value = row.ServicesList || 'No services logged yet.';
            document.getElementById('edit_PartsList').value = row.PartsList || 'No parts logged yet.';
            
            // Simplified status assignment (no more HTML stripping required)
            document.getElementById('edit_Stat').value = row.Stat;
            
            document.getElementById('edit_TotalLaborCost').value = "₱" + (row.TotalLaborCost ? row.TotalLaborCost.replace('₱', '').replace(/,/g, '') : '0.00');
            document.getElementById('edit_TotalPartsCost').value = "₱" + (row.TotalPartsCost ? row.TotalPartsCost.replace('₱', '').replace(/,/g, '') : '0.00');
            
            document.getElementById('edit_Notes').value = row.Notes;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>