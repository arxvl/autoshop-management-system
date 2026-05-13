<?php
// vehicles/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Base Vehicles Masterlist";

// Fetch customers for the dropdown menu
$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer_T ORDER BY CustomerName")->fetchAll();

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            // AUTO-GENERATE PRIMARY KEY
            $newVehicleID = 'V-' . strtoupper(substr(uniqid(), -7));

            $stmt = $pdo->prepare("INSERT INTO Vehicle_T (VehicleID, CustomerID, VehiclePlateNumber, VehicleModel, VehicleYear) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$newVehicleID, $_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear']]);
            $_SESSION['success_msg'] = "Base Vehicle added successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE Vehicle_T SET CustomerID=?, VehiclePlateNumber=?, VehicleModel=?, VehicleYear=? WHERE VehicleID=?");
            $stmt->execute([$_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear'], $_POST['VehicleID']]);
            $_SESSION['success_msg'] = "Vehicle updated successfully!";
        }
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM Vehicle_T WHERE VehicleID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Vehicle deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Data (JOINING CHILD TABLES TO DETERMINE TYPE) ---
$searchQuery = $_GET['q'] ?? '';

$sql = "SELECT 
            v.*, 
            cust.CustomerName,
            CASE 
                WHEN c.VehicleID IS NOT NULL THEN '🚗 Car' 
                WHEN m.VehicleID IS NOT NULL THEN '🏍️ Motorcycle' 
                ELSE '⚙️ Base Vehicle' 
            END AS VehicleType
        FROM Vehicle_T v 
        JOIN Customer_T cust ON v.CustomerID = cust.CustomerID
        LEFT JOIN Car_T c ON v.VehicleID = c.VehicleID
        LEFT JOIN Motorcycle_T m ON v.VehicleID = m.VehicleID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE v.VehiclePlateNumber LIKE ? OR cust.CustomerName LIKE ? OR v.VehicleModel LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY v.VehicleID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Setup Component Variables ---
$tableHeaders = [
    'VehicleID' => 'System ID', 
    'CustomerName' => 'Owner', 
    'VehicleType' => 'Type',
    'VehiclePlateNumber' => 'Plate Number', 
    'VehicleModel' => 'Model', 
    'VehicleYear' => 'Year'
];
$primaryKey = 'VehicleID';
$searchPlaceholder = "Search plate, model, or owner...";
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Master Vehicles Directory</h2>
            <div>
                <a href="../cars/index.php" style="background: var(--accent); color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; margin-right: 0.5rem; font-size: 0.9em;"><i class="fa-solid fa-plus"></i> Add Car</a>
                <a href="../motorcycles/index.php" style="background: var(--accent); color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; font-size: 0.9em;"><i class="fa-solid fa-plus"></i> Add Motorcycle</a>
            </div>
        </div>

        <div style="background: #e0f2fe; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; color: #0369a1; font-size: 0.9em; border: 1px solid #bae6fd;">
            <strong><i class="fa-solid fa-circle-info"></i> Note:</strong> To register a fully detailed Car or Motorcycle with specific engine/transmission info, please use the specific buttons above.
        </div>
        
        <?php 
        include '../components/search.php'; 
        include '../components/table.php'; 
        ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Register Base Vehicle</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
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
                                <input type="text" name="VehiclePlateNumber" required maxlength="20">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Model</label>
                                <input type="text" name="VehicleModel" required maxlength="50">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Year</label>
                                <input type="number" name="VehicleYear" required min="1900" max="2100">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Base Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Vehicle Info</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
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
                            <div class="form-group" style="flex: 1;">
                                <label>Plate Number</label>
                                <input type="text" name="VehiclePlateNumber" id="edit_VehiclePlateNumber" required maxlength="20">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Model</label>
                                <input type="text" name="VehicleModel" id="edit_VehicleModel" required maxlength="50">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Year</label>
                                <input type="number" name="VehicleYear" id="edit_VehicleYear" required min="1900" max="2100">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Info</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const vehicleData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = vehicleData.find(v => v.VehicleID === id);
        if (row) {
            document.getElementById('edit_VehicleID').value = row.VehicleID;
            document.getElementById('edit_CustomerID').value = row.CustomerID;
            document.getElementById('edit_VehiclePlateNumber').value = row.VehiclePlateNumber;
            document.getElementById('edit_VehicleModel').value = row.VehicleModel;
            document.getElementById('edit_VehicleYear').value = row.VehicleYear;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>