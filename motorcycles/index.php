<?php
// motorcycles/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Manage Motorcycles";

$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer_T ORDER BY CustomerName")->fetchAll();

// --- Handle POST Requests (Full CRUD with Transactions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            // AUTO-GENERATE THE PRIMARY KEY
            $newVehicleID = 'V-' . strtoupper(substr(uniqid(), -7));

            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("INSERT INTO Vehicle_T (VehicleID, CustomerID, VehiclePlateNumber, VehicleModel, VehicleYear) VALUES (?, ?, ?, ?, ?)");
            $stmt1->execute([$newVehicleID, $_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear']]);
            $stmt2 = $pdo->prepare("INSERT INTO Motorcycle_T (VehicleID, EngineDisplacement, CycleType) VALUES (?, ?, ?)");
            $stmt2->execute([$newVehicleID, $_POST['EngineDisplacement'], $_POST['CycleType']]);
            $pdo->commit();
            $_SESSION['success_msg'] = "Motorcycle registered successfully!";
        } 
        elseif ($action === 'edit') {
            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("UPDATE Vehicle_T SET CustomerID=?, VehiclePlateNumber=?, VehicleModel=?, VehicleYear=? WHERE VehicleID=?");
            $stmt1->execute([$_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear'], $_POST['VehicleID']]);
            $stmt2 = $pdo->prepare("UPDATE Motorcycle_T SET EngineDisplacement=?, CycleType=? WHERE VehicleID=?");
            $stmt2->execute([$_POST['EngineDisplacement'], $_POST['CycleType'], $_POST['VehicleID']]);
            $pdo->commit();
            $_SESSION['success_msg'] = "Motorcycle updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM Vehicle_T WHERE VehicleID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Motorcycle deleted securely!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT m.*, v.VehiclePlateNumber, v.VehicleModel, v.VehicleYear, v.CustomerID, cust.CustomerName 
        FROM Motorcycle_T m 
        JOIN Vehicle_T v ON m.VehicleID = v.VehicleID
        JOIN Customer_T cust ON v.CustomerID = cust.CustomerID";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE v.VehiclePlateNumber LIKE ? OR v.VehicleModel LIKE ? OR cust.CustomerName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Setup UI Components ---
$tableHeaders = [
    'VehicleID' => 'System ID', 
    'CustomerName' => 'Owner', 
    'VehiclePlateNumber' => 'Plate', 
    'VehicleModel' => 'Model', 
    'EngineDisplacement' => 'Engine (cc)', 
    'CycleType' => 'Type'
];
$primaryKey = 'VehicleID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        <h2>Motorcycles Directory</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Register New Motorcycle</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Owner (Customer)</label>
                                <select name="CustomerID" required>
                                    <option value="">Select an Owner...</option>
                                    <?php foreach($customers as $c) echo "<option value='{$c['CustomerID']}'>{$c['CustomerName']}</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Plate Number</label>
                                <input type="text" name="VehiclePlateNumber" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Model</label>
                                <input type="text" name="VehicleModel" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Year</label>
                                <input type="number" name="VehicleYear" required min="1900" max="2100">
                            </div>
                        </div>
                        
                        <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Engine Displacement</label>
                                <input type="text" name="EngineDisplacement" required placeholder="e.g., 150cc">
                            </div>
                            <div class="form-group">
                                <label>Cycle Type</label>
                                <select name="CycleType" required>
                                    <option value="Sport">Sport</option><option value="Cruiser">Cruiser</option><option value="Scooter">Scooter</option>
                                    <option value="Off-Road">Off-Road</option><option value="Touring">Touring</option><option value="Standard">Standard</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Motorcycle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Edit Motorcycle</h2><button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>System ID</label>
                                <input type="text" name="VehicleID" id="edit_VehicleID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Owner (Customer)</label>
                                <select name="CustomerID" id="edit_CustomerID" required>
                                    <?php foreach($customers as $c) echo "<option value='{$c['CustomerID']}'>{$c['CustomerName']}</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Plate Number</label>
                                <input type="text" name="VehiclePlateNumber" id="edit_VehiclePlateNumber" required>
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Model</label>
                                <input type="text" name="VehicleModel" id="edit_VehicleModel" required>
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <input type="number" name="VehicleYear" id="edit_VehicleYear" required min="1900" max="2100">
                            </div>
                        </div>
                        
                        <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Engine Displacement</label>
                                <input type="text" name="EngineDisplacement" id="edit_EngineDisplacement" required>
                            </div>
                            <div class="form-group">
                                <label>Cycle Type</label>
                                <select name="CycleType" id="edit_CycleType" required>
                                    <option value="Sport">Sport</option><option value="Cruiser">Cruiser</option><option value="Scooter">Scooter</option>
                                    <option value="Off-Road">Off-Road</option><option value="Touring">Touring</option><option value="Standard">Standard</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Motorcycle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const tableData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = tableData.find(item => item.VehicleID === id);
        if (row) {
            document.getElementById('edit_VehicleID').value = row.VehicleID;
            document.getElementById('edit_CustomerID').value = row.CustomerID;
            document.getElementById('edit_VehiclePlateNumber').value = row.VehiclePlateNumber;
            document.getElementById('edit_VehicleModel').value = row.VehicleModel;
            document.getElementById('edit_VehicleYear').value = row.VehicleYear;
            document.getElementById('edit_EngineDisplacement').value = row.EngineDisplacement;
            document.getElementById('edit_CycleType').value = row.CycleType;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>