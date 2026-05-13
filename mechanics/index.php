<?php
// mechanics/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Manage Mechanics";

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            // AUTO-GENERATE THE PRIMARY KEY (e.g., M-A1B2C3D)
            $newMechanicID = 'M-' . strtoupper(substr(uniqid(), -7));

            $stmt = $pdo->prepare("INSERT INTO Mechanic_T (MechanicID, MechanicName, CPNumber, HireDate) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newMechanicID, $_POST['MechanicName'], $_POST['CPNumber'], $_POST['HireDate']]);
            $_SESSION['success_msg'] = "Mechanic added successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE Mechanic_T SET MechanicName=?, CPNumber=?, HireDate=? WHERE MechanicID=?");
            $stmt->execute([$_POST['MechanicName'], $_POST['CPNumber'], $_POST['HireDate'], $_POST['MechanicID']]);
            $_SESSION['success_msg'] = "Mechanic updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM Mechanic_T WHERE MechanicID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Mechanic deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT * FROM Mechanic_T";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE MechanicName LIKE ? OR MechanicID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY MechanicName ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Setup UI Components ---
$tableHeaders = [
    'MechanicID' => 'System ID', 
    'MechanicName' => 'Full Name', 
    'CPNumber' => 'Contact No.', 
    'HireDate' => 'Date Hired'
];
$primaryKey = 'MechanicID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Mechanic Personnel Directory</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Mechanic</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Full Name</label>
                                <input type="text" name="MechanicName" required placeholder="e.g., John Doe">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Contact Number</label>
                                <input type="text" name="CPNumber" required placeholder="09123456789">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Hire Date</label>
                                <input type="date" name="HireDate" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Mechanic Info</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>System ID</label>
                                <input type="text" name="MechanicID" id="edit_MechanicID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Full Name</label>
                                <input type="text" name="MechanicName" id="edit_MechanicName" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="CPNumber" id="edit_CPNumber" required>
                            </div>
                            <div class="form-group">
                                <label>Hire Date</label>
                                <input type="date" name="HireDate" id="edit_HireDate" required>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const mechanicData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = mechanicData.find(m => m.MechanicID === id);
        if (row) {
            document.getElementById('edit_MechanicID').value = row.MechanicID;
            document.getElementById('edit_MechanicName').value = row.MechanicName;
            document.getElementById('edit_CPNumber').value = row.CPNumber;
            document.getElementById('edit_HireDate').value = row.HireDate;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>