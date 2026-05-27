<?php
// repairservices/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Log Repair Services";

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

$services = $pdo->query("SELECT ServiceTypeID, ServiceName, LaborCost FROM ServiceType_T ORDER BY ServiceName")->fetchAll();

// --- Handle POST Requests (With Auto-Calculating Sync) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $pdo->beginTransaction();
            
            // 1. Link the service
            $stmt = $pdo->prepare("INSERT INTO RepairService_T (ServiceRecordID, ServiceTypeID, HoursWorked, LaborCost) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['ServiceRecordID'], $_POST['ServiceTypeID'], $_POST['HoursWorked'], $_POST['LaborCost']]);
            
            // 2. AUTO-CALCULATE AND SYNC THE TOTAL LABOR COST ON THE MASTER RECORD
            $syncStmt = $pdo->prepare("UPDATE ServiceRecord_T SET TotalLaborCost = (SELECT COALESCE(SUM(LaborCost), 0) FROM RepairService_T WHERE ServiceRecordID = ?) WHERE ServiceRecordID = ?");
            $syncStmt->execute([$_POST['ServiceRecordID'], $_POST['ServiceRecordID']]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Service linked & Master Record Total updated!";
        } 
        elseif ($action === 'edit') {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE RepairService_T SET HoursWorked=?, LaborCost=? WHERE ServiceRecordID=? AND ServiceTypeID=?");
            $stmt->execute([$_POST['HoursWorked'], $_POST['LaborCost'], $_POST['ServiceRecordID'], $_POST['ServiceTypeID']]);
            
            // AUTO-CALCULATE AND SYNC
            $syncStmt = $pdo->prepare("UPDATE ServiceRecord_T SET TotalLaborCost = (SELECT COALESCE(SUM(LaborCost), 0) FROM RepairService_T WHERE ServiceRecordID = ?) WHERE ServiceRecordID = ?");
            $syncStmt->execute([$_POST['ServiceRecordID'], $_POST['ServiceRecordID']]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Service updated & Master Record Total refreshed!";
        }
        elseif ($action === 'delete') {
            $pdo->beginTransaction();
            list($recID, $srvID) = explode('|', $_POST['delete_id']);
            
            $stmt = $pdo->prepare("DELETE FROM RepairService_T WHERE ServiceRecordID=? AND ServiceTypeID=?");
            $stmt->execute([$recID, $srvID]);
            
            // AUTO-CALCULATE AND SYNC
            $syncStmt = $pdo->prepare("UPDATE ServiceRecord_T SET TotalLaborCost = (SELECT COALESCE(SUM(LaborCost), 0) FROM RepairService_T WHERE ServiceRecordID = ?) WHERE ServiceRecordID = ?");
            $syncStmt->execute([$recID, $recID]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Service removed & Master Record Total adjusted!";
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
            rs.ServiceRecordID, 
            rs.ServiceTypeID,
            st.ServiceName, 
            rs.HoursWorked, 
            rs.LaborCost, 
            c.CustomerName,
            v.VehiclePlateNumber,
            v.VehicleModel,
            CONCAT(rs.ServiceRecordID, '|', rs.ServiceTypeID) as CompositeID 
        FROM RepairService_T rs 
        JOIN ServiceType_T st ON rs.ServiceTypeID = st.ServiceTypeID
        LEFT JOIN ServiceRecord_T sr ON rs.ServiceRecordID = sr.ServiceRecordID
        LEFT JOIN Order_T o ON sr.OrderID = o.OrderID
        LEFT JOIN Customer_T c ON o.CustomerID = c.CustomerID
        LEFT JOIN Vehicle_T v ON sr.VehicleID = v.VehicleID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE rs.ServiceRecordID LIKE ? OR st.ServiceName LIKE ? OR c.CustomerName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Format Currency & Create Context String
foreach ($tableData as &$row) {
    $row['LaborCost'] = "₱" . number_format($row['LaborCost'], 2);
    
    // Add text emojis for the table display
    $veh = $row['VehiclePlateNumber'] ? " 🚗 {$row['VehiclePlateNumber']} [{$row['VehicleModel']}]" : " 🚶 Walk-in";
    $row['CustomerDisplay'] = $row['CustomerName'] . $veh;
}
unset($row);

// Setup UI
$tableHeaders = [
    'ServiceRecordID' => 'Record Ref', 
    'CustomerDisplay' => 'Customer & Vehicle',
    'ServiceName' => 'Service Performed', 
    'HoursWorked' => 'Hours Logged', 
    'LaborCost' => 'Labor Cost'
];
$primaryKey = 'CompositeID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; 
include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Link Services to Records</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 650px;">
                <div class="modal-header">
                    <h2>Attach Service to Record</h2>
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
                                        $veh = $r['VehiclePlateNumber'] ? " 🚗 {$r['VehiclePlateNumber']} [{$r['VehicleModel']}]" : " 🚶 Walk-in";
                                    ?>
                                        <option value="<?php echo $r['ServiceRecordID']; ?>">
                                            <?php echo $r['ServiceRecordID'] . " - " . htmlspecialchars($r['CustomerName']) . $veh; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1.5;">
                                <label>Service Type</label>
                                <select name="ServiceTypeID" id="serviceSelect" required onchange="updateCost()" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                    <option value="" data-cost="0">Select Service...</option>
                                    <?php foreach($services as $s) echo "<option value='{$s['ServiceTypeID']}' data-cost='{$s['LaborCost']}'>{$s['ServiceName']}</option>"; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Hours Worked</label>
                                <input type="number" name="HoursWorked" required step="0.25" min="0" value="1.00" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                            </div>
                            <div class="form-group">
                                <label>Labor Cost (₱)</label>
                                <input type="number" name="LaborCost" id="laborCostInput" required step="0.01" min="0" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save Link</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Edit Service Link</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="ServiceRecordID" id="edit_ServiceRecordID">
                        <input type="hidden" name="ServiceTypeID" id="edit_ServiceTypeID">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Service Record Context</label>
                                <input type="text" id="display_RecordID" readonly style="background: #f3f4f6; width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px; color: #4b5563; font-weight: 500;">
                            </div>
                            <div class="form-group" style="flex: 1.5;">
                                <label>Service Type</label>
                                <input type="text" id="display_ServiceName" readonly style="background: #f3f4f6; width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px; color: #4b5563;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Hours Worked</label>
                                <input type="number" name="HoursWorked" id="edit_HoursWorked" required step="0.25" min="0" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                            </div>
                            <div class="form-group">
                                <label>Labor Cost (₱)</label>
                                <input type="number" name="LaborCost" id="edit_LaborCost" required step="0.01" min="0" style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Update Link</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    function updateCost() {
        const select = document.getElementById('serviceSelect');
        const cost = select.options[select.selectedIndex].getAttribute('data-cost');
        document.getElementById('laborCostInput').value = cost;
    }

    const rsData = <?php echo json_encode($tableData); ?>;
    function openEditModal(compositeId) {
        const row = rsData.find(r => r.CompositeID === compositeId);
        if (row) {
            document.getElementById('edit_ServiceRecordID').value = row.ServiceRecordID;
            document.getElementById('edit_ServiceTypeID').value = row.ServiceTypeID;
            
            // Populates the read-only input with the full string (e.g., SR-123456 - John Doe 🚗 ABC-123)
            document.getElementById('display_RecordID').value = row.ServiceRecordID + " - " + row.CustomerDisplay;
            document.getElementById('display_ServiceName').value = row.ServiceName;
            
            document.getElementById('edit_HoursWorked').value = row.HoursWorked;
            document.getElementById('edit_LaborCost').value = row.LaborCost.replace('₱', '').replace(/,/g, '');
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>