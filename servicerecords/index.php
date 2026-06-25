<?php
// servicerecords/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Service Records (Repairs)";

// Fetch Orders for the Dropdown.
$orders = $pdo->query("
    SELECT o.OrderID, c.CustomerName,
           (SELECT GROUP_CONCAT(CONCAT(VehiclePlateNumber, ' [', VehicleModel, ']') SEPARATOR ', ') 
            FROM Vehicle_T WHERE CustomerID = c.CustomerID) as OwnedVehicles
    FROM Order_T o 
    JOIN Customer_T c ON o.CustomerID = c.CustomerID 
    ORDER BY o.OrderDate DESC
")->fetchAll();

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            
            $targetOrderID = $_POST['OrderID'];
            
            // 1. Get CustomerID
            $custStmt = $pdo->prepare("SELECT CustomerID FROM Order_T WHERE OrderID = ?");
            $custStmt->execute([$targetOrderID]);
            $customerID = $custStmt->fetchColumn();
            
            // 2. Get their primary VehicleID (If they are a walk-in without a car, this becomes null)
            $vehStmt = $pdo->prepare("SELECT VehicleID FROM Vehicle_T WHERE CustomerID = ? LIMIT 1");
            $vehStmt->execute([$customerID]);
            $vehicleID = $vehStmt->fetchColumn() ?: null;

            if ($action === 'add') {
                $newRecordID = 'SR-' . strtoupper(substr(uniqid(), -6));
                
                // IMPORTANT: We now include CustomerID and VehicleID in the INSERT statement!
                // If your database does not have a VehicleID column in ServiceRecord_T, just delete "VehicleID, " and "$vehicleID, " below.
                $stmt = $pdo->prepare("INSERT INTO ServiceRecord_T (ServiceRecordID, OrderID, CustomerID, VehicleID, DateReceived, TotalLaborCost, TotalPartsCost, Stat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$newRecordID, $targetOrderID, $customerID, $vehicleID, $_POST['DateReceived'], $_POST['TotalLaborCost'], $_POST['TotalPartsCost'], $_POST['Stat']]);
                $_SESSION['success_msg'] = "Service Record created successfully!";
            } else {
                // IMPORTANT: We now include CustomerID and VehicleID in the UPDATE statement!
                $stmt = $pdo->prepare("UPDATE ServiceRecord_T SET OrderID=?, CustomerID=?, VehicleID=?, DateReceived=?, TotalLaborCost=?, TotalPartsCost=?, Stat=? WHERE ServiceRecordID=?");
                $stmt->execute([$targetOrderID, $customerID, $vehicleID, $_POST['DateReceived'], $_POST['TotalLaborCost'], $_POST['TotalPartsCost'], $_POST['Stat'], $_POST['ServiceRecordID']]);
                $_SESSION['success_msg'] = "Service Record updated successfully!";
            }
        }
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM ServiceRecord_T WHERE ServiceRecordID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Service Record deleted!";
        }

        // AUTO-SYNC: Update the Master Order Grand Total
        $pdo->query("
            UPDATE Order_T o
            SET OrderTotalAmount = (
                COALESCE((SELECT SUM(TotalLaborCost + TotalPartsCost) FROM ServiceRecord_T WHERE OrderID = o.OrderID), 0) +
                COALESCE((SELECT SUM(Subtotal) FROM OrderItem_T WHERE OrderID = o.OrderID), 0)
            )
        ");

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Data for the Table ---
$searchQuery = $_GET['q'] ?? '';

$sql = "SELECT 
            sr.*, 
            o.OrderID,
            c.CustomerName,
            (SELECT GROUP_CONCAT(CONCAT(VehiclePlateNumber, ' [', VehicleModel, ']') SEPARATOR ', ') 
             FROM Vehicle_T WHERE CustomerID = c.CustomerID) as OwnedVehicles
        FROM ServiceRecord_T sr
        JOIN Order_T o ON sr.OrderID = o.OrderID
        JOIN Customer_T c ON o.CustomerID = c.CustomerID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE sr.ServiceRecordID LIKE ? OR o.OrderID LIKE ? OR c.CustomerName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY sr.DateReceived DESC, sr.ServiceRecordID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Format Currency & Create Customer Display Label
foreach ($tableData as &$row) {
    $row['TotalLaborCost'] = "₱" . number_format($row['TotalLaborCost'], 2);
    $row['TotalPartsCost'] = "₱" . number_format($row['TotalPartsCost'], 2);
    
    if (!empty($row['OwnedVehicles'])) {
        $badge = " :: " . $row['OwnedVehicles'];
    } else {
        $badge = " :: Walk-in";
    }
    $row['CustomerDisplay'] = $row['CustomerName'] . $badge;
}
unset($row);

// Setup UI
$tableHeaders = [
    'ServiceRecordID' => 'Record Ref', 
    'OrderID' => 'Order Ref',
    'CustomerDisplay' => 'Customer & Vehicle', 
    'DateReceived' => 'Date Received', 
    'TotalLaborCost' => 'Labor Cost',
    'TotalPartsCost' => 'Parts Cost',
    'Stat' => 'Status'
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Service Records (Vehicle Repairs)</h2>
        </div>

        <div style="background: #f3f4f6; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; color: #4b5563; font-size: 0.9em; border: 1px solid #e5e7eb;">
            <strong><i class="fa-solid fa-circle-info"></i> How it works:</strong> A Service Record tracks the labor and parts for a vehicle repair. It must be linked to an existing <strong>Order</strong>. You do not need to select the customer or vehicle manually; the system pulls that data from the Order automatically!
        </div>
        
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Create Service Record</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label>Target Order (Customer & Vehicle Info)</label>
                            <select name="OrderID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                <option value="">Select Master Order...</option>
                                <?php foreach($orders as $o): 
                                    $vehicleContext = !empty($o['OwnedVehicles']) ? " :: " . $o['OwnedVehicles'] : " :: Walk-in";
                                ?>
                                    <option value="<?php echo $o['OrderID']; ?>">
                                        <?php echo $o['OrderID'] . " - " . htmlspecialchars($o['CustomerName']) . $vehicleContext; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Date Received</label>
                                <input type="date" name="DateReceived" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Status</label>
                                <select name="Stat" required style="width: 100%; box-sizing: border-box;">
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Total Labor Cost (₱)</label>
                                <input type="number" name="TotalLaborCost" required step="0.01" min="0" value="0.00" style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Total Parts Cost (₱)</label>
                                <input type="number" name="TotalPartsCost" required step="0.01" min="0" value="0.00" style="width: 100%; box-sizing: border-box;">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Edit Service Record</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Record Ref</label>
                                <input type="text" name="ServiceRecordID" id="edit_ServiceRecordID" readonly style="background: #f3f4f6; color: #9ca3af; width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex: 2; min-width: 0;">
                                <label>Target Order</label>
                                <select name="OrderID" id="edit_OrderID" required style="width: 100%; box-sizing: border-box;">
                                    <?php foreach($orders as $o): 
                                        $vehicleContext = !empty($o['OwnedVehicles']) ? " ::" . $o['OwnedVehicles'] : " ::Walk-in";
                                    ?>
                                        <option value="<?php echo $o['OrderID']; ?>">
                                            <?php echo $o['OrderID'] . " - " . htmlspecialchars($o['CustomerName']) . $vehicleContext; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Date Received</label>
                                <input type="date" name="DateReceived" id="edit_DateReceived" required style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Status</label>
                                <select name="Stat" id="edit_Stat" required style="width: 100%; box-sizing: border-box;">
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Total Labor Cost (₱)</label>
                                <input type="number" name="TotalLaborCost" id="edit_TotalLaborCost" required step="0.01" min="0" style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Total Parts Cost (₱)</label>
                                <input type="number" name="TotalPartsCost" id="edit_TotalPartsCost" required step="0.01" min="0" style="width: 100%; box-sizing: border-box;">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Update Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const recordData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = recordData.find(item => item.ServiceRecordID === id);
        if (row) {
            document.getElementById('edit_ServiceRecordID').value = row.ServiceRecordID;
            document.getElementById('edit_OrderID').value = row.OrderID;
            document.getElementById('edit_DateReceived').value = row.DateReceived;
            document.getElementById('edit_TotalLaborCost').value = row.TotalLaborCost.replace('₱', '').replace(/,/g, '');
            document.getElementById('edit_TotalPartsCost').value = row.TotalPartsCost.replace('₱', '').replace(/,/g, '');
            document.getElementById('edit_Stat').value = row.Stat;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>