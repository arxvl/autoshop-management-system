<?php
// mechanicassignments/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Job Assignments";

// Fetch data for the dropdown menus
$mechanics = $pdo->query("SELECT MechanicID, MechanicName FROM Mechanic_T ORDER BY MechanicName")->fetchAll();
// Fetch active service records to assign mechanics to
$records = $pdo->query("SELECT ServiceRecordID FROM ServiceRecord_T WHERE Stat != 'Completed' ORDER BY DateReceived DESC")->fetchAll();

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO MechanicAssignment_T (MechanicID, ServiceRecordID, DateAssigned, HoursWorked) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['MechanicID'], $_POST['ServiceRecordID'], $_POST['DateAssigned'], $_POST['HoursWorked']]);
            $_SESSION['success_msg'] = "Mechanic assigned to job successfully!";
        } 
        elseif ($action === 'edit') {
            // We can only update the hours and date, the IDs are the primary key
            $stmt = $pdo->prepare("UPDATE MechanicAssignment_T SET DateAssigned=?, HoursWorked=? WHERE MechanicID=? AND ServiceRecordID=?");
            $stmt->execute([$_POST['DateAssigned'], $_POST['HoursWorked'], $_POST['MechanicID'], $_POST['ServiceRecordID']]);
            $_SESSION['success_msg'] = "Assignment updated successfully!";
        }
        elseif ($action === 'delete') {
            // Split our custom composite ID back into two separate IDs
            list($mechID, $recID) = explode('|', $_POST['delete_id']);
            $stmt = $pdo->prepare("DELETE FROM MechanicAssignment_T WHERE MechanicID=? AND ServiceRecordID=?");
            $stmt->execute([$mechID, $recID]);
            $_SESSION['success_msg'] = "Assignment removed!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';

// We create a custom 'CompositeID' column so the frontend knows exactly which row to edit/delete
$sql = "SELECT 
            m.MechanicName, 
            a.MechanicID,
            a.ServiceRecordID, 
            a.DateAssigned, 
            a.HoursWorked, 
            CONCAT(a.MechanicID, '|', a.ServiceRecordID) as CompositeID 
        FROM MechanicAssignment_T a 
        JOIN Mechanic_T m ON a.MechanicID = m.MechanicID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE m.MechanicName LIKE ? OR a.ServiceRecordID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY a.DateAssigned DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Setup UI Components ---
$tableHeaders = [
    'MechanicName' => 'Mechanic', 
    'ServiceRecordID' => 'Record Ref', 
    'DateAssigned' => 'Date Assigned', 
    'HoursWorked' => 'Hours Logged'
];
$primaryKey = 'CompositeID'; // Use our custom concatenated ID
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; 
include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Active Job Assignments</h2>
        
        <?php 
        include '../components/search.php'; 
        include '../components/table.php'; 
        ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Assign Mechanic to Job</h2>
                    <button class="btn-close" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Mechanic</label>
                            <select name="MechanicID" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                <option value="">Select Mechanic...</option>
                                <?php foreach($mechanics as $m) echo "<option value='{$m['MechanicID']}'>{$m['MechanicName']}</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Record</label>
                            <select name="ServiceRecordID" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                <option value="">Select Active Record...</option>
                                <?php foreach($records as $r) echo "<option value='{$r['ServiceRecordID']}'>{$r['ServiceRecordID']}</option>"; ?>
                            </select>
                        </div>
                        <div style="display:flex; gap:1rem;">
                            <div class="form-group" style="flex:1;">
                                <label>Date Assigned</label>
                                <input type="date" name="DateAssigned" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Hours Worked</label>
                                <input type="number" name="HoursWorked" required step="0.25" min="0" placeholder="e.g., 2.5">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Assignment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Job Assignment</h2>
                    <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <input type="hidden" name="MechanicID" id="edit_MechanicID">
                        <input type="hidden" name="ServiceRecordID" id="edit_ServiceRecordID">
                        
                        <div class="form-group">
                            <label>Mechanic</label>
                            <input type="text" id="edit_MechanicName" readonly style="background: #f3f4f6;">
                        </div>
                        <div class="form-group">
                            <label>Service Record Ref</label>
                            <input type="text" id="edit_RecordRef" readonly style="background: #f3f4f6;">
                        </div>
                        
                        <div style="display:flex; gap:1rem;">
                            <div class="form-group" style="flex:1;">
                                <label>Date Assigned</label>
                                <input type="date" name="DateAssigned" id="edit_DateAssigned" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Hours Worked</label>
                                <input type="number" name="HoursWorked" id="edit_HoursWorked" required step="0.25" min="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Log</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    // Injects the table data into the Edit form when the Pen icon is clicked
    const assignmentData = <?php echo json_encode($tableData); ?>;
    
    function openEditModal(compositeId) {
        // Find the specific row using our custom CompositeID
        const row = assignmentData.find(a => a.CompositeID === compositeId);
        if (row) {
            document.getElementById('edit_MechanicID').value = row.MechanicID;
            document.getElementById('edit_ServiceRecordID').value = row.ServiceRecordID;
            document.getElementById('edit_MechanicName').value = row.MechanicName;
            document.getElementById('edit_RecordRef').value = row.ServiceRecordID;
            document.getElementById('edit_DateAssigned').value = row.DateAssigned;
            document.getElementById('edit_HoursWorked').value = row.HoursWorked;
            
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>