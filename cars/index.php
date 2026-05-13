<?php
// cars/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Manage Cars";

$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer_T ORDER BY CustomerName")->fetchAll();

// --- Handle POST Requests (Full CRUD with Transactions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            // AUTO-GENERATE THE PRIMARY KEY (e.g., V-A1B2C3D)
            $newVehicleID = 'V-' . strtoupper(substr(uniqid(), -7));

            $pdo->beginTransaction();
            // 1. Insert Parent Vehicle
            $stmt1 = $pdo->prepare("INSERT INTO Vehicle_T (VehicleID, CustomerID, VehiclePlateNumber, VehicleModel, VehicleYear) VALUES (?, ?, ?, ?, ?)");
            $stmt1->execute([$newVehicleID, $_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear']]);
            // 2. Insert Child Car
            $stmt2 = $pdo->prepare("INSERT INTO Car_T (VehicleID, TransmissionType, FuelType, NumberOfDoors) VALUES (?, ?, ?, ?)");
            $stmt2->execute([$newVehicleID, $_POST['TransmissionType'], $_POST['FuelType'], $_POST['NumberOfDoors']]);
            $pdo->commit();
            $_SESSION['success_msg'] = "Car registered successfully!";
        } 
        elseif ($action === 'edit') {
            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("UPDATE Vehicle_T SET CustomerID=?, VehiclePlateNumber=?, VehicleModel=?, VehicleYear=? WHERE VehicleID=?");
            $stmt1->execute([$_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear'], $_POST['VehicleID']]);
            $stmt2 = $pdo->prepare("UPDATE Car_T SET TransmissionType=?, FuelType=?, NumberOfDoors=? WHERE VehicleID=?");
            $stmt2->execute([$_POST['TransmissionType'], $_POST['FuelType'], $_POST['NumberOfDoors'], $_POST['VehicleID']]);
            $pdo->commit();
            $_SESSION['success_msg'] = "Car updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM Vehicle_T WHERE VehicleID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Car deleted securely!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT c.*, v.VehiclePlateNumber, v.VehicleModel, v.VehicleYear, v.CustomerID, cust.CustomerName 
        FROM Car_T c 
        JOIN Vehicle_T v ON c.VehicleID = v.VehicleID
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
    'VehicleYear' => 'Year',
    'TransmissionType' => 'Trans.', 
    'FuelType' => 'Fuel', 
    'NumberOfDoors' => 'Doors'
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
        <h2>Cars Directory</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Register New Car</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
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
                                <label>Transmission</label>
                                <select name="TransmissionType" required>
                                    <option value="Automatic">Automatic</option><option value="Manual">Manual</option><option value="CVT">CVT</option><option value="Semi-Automatic">Semi-Automatic</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fuel Type</label>
                                <select name="FuelType" required>
                                    <option value="Gasoline">Gasoline</option><option value="Diesel">Diesel</option><option value="Electric">Electric</option><option value="Hybrid">Hybrid</option><option value="LPG">LPG</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 0.5;">
                                <label>Doors</label>
                                <input type="number" name="NumberOfDoors" required min="2" max="6">
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Car</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Edit Car</h2><button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button></div>
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
                                <label>Transmission</label>
                                <select name="TransmissionType" id="edit_TransmissionType" required>
                                    <option value="Automatic">Automatic</option><option value="Manual">Manual</option><option value="CVT">CVT</option><option value="Semi-Automatic">Semi-Automatic</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fuel Type</label>
                                <select name="FuelType" id="edit_FuelType" required>
                                    <option value="Gasoline">Gasoline</option><option value="Diesel">Diesel</option><option value="Electric">Electric</option><option value="Hybrid">Hybrid</option><option value="LPG">LPG</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 0.5;">
                                <label>Doors</label>
                                <input type="number" name="NumberOfDoors" id="edit_NumberOfDoors" required min="2" max="6">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Car</button>
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
            document.getElementById('edit_TransmissionType').value = row.TransmissionType;
            document.getElementById('edit_FuelType').value = row.FuelType;
            document.getElementById('edit_NumberOfDoors').value = row.NumberOfDoors;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>