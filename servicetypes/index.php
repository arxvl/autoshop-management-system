<?php
// servicetypes/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Service Types & Pricing";

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            // AUTO-GENERATE PRIMARY KEY (e.g., S-A1B2C3D)
            $newServiceTypeID = 'S-' . strtoupper(substr(uniqid(), -7));

            $stmt = $pdo->prepare("INSERT INTO ServiceType_T (ServiceTypeID, ServiceName, Descript, LaborCost) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newServiceTypeID, $_POST['ServiceName'], $_POST['Descript'], $_POST['LaborCost']]);
            $_SESSION['success_msg'] = "Service Type added successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE ServiceType_T SET ServiceName=?, Descript=?, LaborCost=? WHERE ServiceTypeID=?");
            $stmt->execute([$_POST['ServiceName'], $_POST['Descript'], $_POST['LaborCost'], $_POST['ServiceTypeID']]);
            $_SESSION['success_msg'] = "Service Type updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM ServiceType_T WHERE ServiceTypeID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Service Type deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT * FROM ServiceType_T";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE ServiceName LIKE ? OR ServiceTypeID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY ServiceName ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Setup UI
$tableHeaders = [
    'ServiceTypeID' => 'System ID', 
    'ServiceName' => 'Service Name', 
    'Descript' => 'Description', 
    'LaborCost' => 'Labor Cost (₱)'
];
$primaryKey = 'ServiceTypeID';
$searchPlaceholder = "Search services...";
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Service Types & Pricing</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Service Type</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Service Name</label>
                                <input type="text" name="ServiceName" required maxlength="100" placeholder="e.g., Synthetic Oil Change">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Standard Labor Cost (₱)</label>
                                <input type="number" name="LaborCost" required min="0" step="1" placeholder="e.g., 1500">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="Descript" maxlength="255" placeholder="Brief description of the service...">
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Service Type</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>System ID</label>
                                <input type="text" name="ServiceTypeID" id="edit_ServiceTypeID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Service Name</label>
                                <input type="text" name="ServiceName" id="edit_ServiceName" required maxlength="100">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Description</label>
                                <input type="text" name="Descript" id="edit_Descript" maxlength="255">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Standard Labor Cost (₱)</label>
                                <input type="number" name="LaborCost" id="edit_LaborCost" required min="0" step="1">
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const serviceData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = serviceData.find(s => s.ServiceTypeID === id);
        if (row) {
            document.getElementById('edit_ServiceTypeID').value = row.ServiceTypeID;
            document.getElementById('edit_ServiceName').value = row.ServiceName;
            document.getElementById('edit_Descript').value = row.Descript;
            document.getElementById('edit_LaborCost').value = row.LaborCost;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>