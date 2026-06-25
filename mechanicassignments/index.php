<?php
// mechanicassignments/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Job Assignments";

// Fetch mechanics AND their skills for the dropdown menu
$mechanics = $pdo->query("
    SELECT m.MechanicID, m.MechanicName,
        (SELECT GROUP_CONCAT(st.ServiceName SEPARATOR ', ')
         FROM MechanicSkill_T ms
         JOIN ServiceType_T st ON ms.ServiceTypeID = st.ServiceTypeID
         WHERE ms.MechanicID = m.MechanicID) as SkillsList
    FROM Mechanic_T m 
    ORDER BY m.MechanicName
")->fetchAll();

// Fetch active service records WITH their Customer and Vehicle info
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
            $stmt = $pdo->prepare("UPDATE MechanicAssignment_T SET DateAssigned=?, HoursWorked=? WHERE MechanicID=? AND ServiceRecordID=?");
            $stmt->execute([$_POST['DateAssigned'], $_POST['HoursWorked'], $_POST['MechanicID'], $_POST['ServiceRecordID']]);
            $_SESSION['success_msg'] = "Assignment updated successfully!";
        }
        elseif ($action === 'delete') {
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

// --- Fetch & Filter Data ---
$searchQuery = $_GET['q'] ?? '';
$filterDate = $_GET['filter_date'] ?? ''; // NEW: Date Filter Variable

// Base Query with 1=1 trick for easy filtering
$sql = "SELECT 
            m.MechanicName, 
            a.MechanicID,
            a.ServiceRecordID, 
            a.DateAssigned, 
            a.HoursWorked, 
            c.CustomerName,
            v.VehiclePlateNumber,
            v.VehicleModel,
            CONCAT(a.MechanicID, '|', a.ServiceRecordID) as CompositeID,
            
            (SELECT GROUP_CONCAT(st.ServiceName SEPARATOR ', ')
             FROM MechanicSkill_T ms
             JOIN ServiceType_T st ON ms.ServiceTypeID = st.ServiceTypeID
             WHERE ms.MechanicID = a.MechanicID) as MechanicSkills
             
        FROM MechanicAssignment_T a 
        JOIN Mechanic_T m ON a.MechanicID = m.MechanicID
        LEFT JOIN ServiceRecord_T sr ON a.ServiceRecordID = sr.ServiceRecordID
        LEFT JOIN Order_T o ON sr.OrderID = o.OrderID
        LEFT JOIN Customer_T c ON o.CustomerID = c.CustomerID
        LEFT JOIN Vehicle_T v ON sr.VehicleID = v.VehicleID
        WHERE 1=1";

$params = [];

// 1. Apply Text Search
if ($searchQuery) {
    $sql .= " AND (m.MechanicName LIKE ? OR a.ServiceRecordID LIKE ? OR c.CustomerName LIKE ?)";
    array_push($params, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%");
}

// 2. Apply Date Filter
if ($filterDate) {
    $sql .= " AND a.DateAssigned = ?";
    array_push($params, $filterDate);
}

$sql .= " ORDER BY a.DateAssigned DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Clean up empty skill lists and create Customer/Vehicle context
foreach ($tableData as &$row) {
    if (empty($row['MechanicSkills'])) {
        $row['MechanicSkills'] = "No specific skills logged";
    }
    
    // Add text emojis for the table display context
    $veh = $row['VehiclePlateNumber'] ? " :: {$row['VehiclePlateNumber']} [{$row['VehicleModel']}]" : " :: Walk-in";
    $row['CustomerDisplay'] = $row['CustomerName'] . $veh;
}
unset($row); 

// --- Setup UI Components ---
$tableHeaders = [
    'MechanicName' => 'Mechanic', 
    'MechanicSkills' => 'Mechanic Skills', 
    'ServiceRecordID' => 'Record Ref', 
    'CustomerDisplay' => 'Customer & Vehicle', 
    'DateAssigned' => 'Date Assigned', 
    'HoursWorked' => 'Hours Logged'
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
        
        <h2>Active Job Assignments</h2>

        <div style="background: #f9fafb; padding: 1rem; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
            <span style="font-weight: 500; color: #4b5563;"><i class="fa-regular fa-calendar-days"></i> Filter by Work Date:</span>
            
            <form method="GET" action="index.php" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <?php if(!empty($searchQuery)): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <?php endif; ?>
                
                <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filterDate); ?>" onchange="this.form.submit()" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; background: white; cursor: pointer; font-family: inherit;">

                <?php if (!empty($filterDate)): ?>
                    <a href="index.php<?php echo !empty($searchQuery) ? '?q=' . urlencode($searchQuery) : ''; ?>" style="color: #ef4444; text-decoration: none; font-size: 0.9em; margin-left: 0.5rem;">Clear Date</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php 
        include '../components/search.php'; 
        include '../components/table.php'; 
        ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Assign Mechanic to Job</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Mechanic</label>
                            <select name="MechanicID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                <option value="">Select Mechanic...</option>
                                <?php foreach($mechanics as $m): 
                                    $skillDisplay = !empty($m['SkillsList']) ? " ({$m['SkillsList']})" : "";
                                ?>
                                    <option value="<?php echo $m['MechanicID']; ?>">
                                        <?php echo htmlspecialchars($m['MechanicName']) . htmlspecialchars($skillDisplay); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Record</label>
                            <select name="ServiceRecordID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px;">
                                <option value="">Select Active Record...</option>
                                <?php foreach($records as $r): 
                                    $veh = $r['VehiclePlateNumber'] ? " :: {$r['VehiclePlateNumber']} [{$r['VehicleModel']}]" : " :: Walk-in";
                                ?>
                                    <option value="<?php echo $r['ServiceRecordID']; ?>">
                                        <?php echo $r['ServiceRecordID'] . " - " . htmlspecialchars($r['CustomerName']) . $veh; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:flex; gap:1rem;">
                            <div class="form-group" style="flex:1; min-width:0;">
                                <label>Date Assigned</label>
                                <input type="date" name="DateAssigned" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex:1; min-width:0;">
                                <label>Hours Worked</label>
                                <input type="number" name="HoursWorked" required step="0.25" min="0" placeholder="e.g., 2.5" style="width: 100%; box-sizing: border-box;">
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
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <input type="hidden" name="MechanicID" id="edit_MechanicID">
                        <input type="hidden" name="ServiceRecordID" id="edit_ServiceRecordID">
                        
                        <div class="form-group">
                            <label>Mechanic</label>
                            <input type="text" id="edit_MechanicName" readonly style="background: #f3f4f6; width: 100%; box-sizing: border-box;">
                        </div>
                        <div class="form-group">
                            <label>Service Record Context</label>
                            <input type="text" id="edit_RecordRef" readonly style="background: #f3f4f6; width: 100%; box-sizing: border-box; color: #4b5563; font-weight: 500;">
                        </div>
                        
                        <div style="display:flex; gap:1rem;">
                            <div class="form-group" style="flex:1; min-width:0;">
                                <label>Date Assigned</label>
                                <input type="date" name="DateAssigned" id="edit_DateAssigned" required style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex:1; min-width:0;">
                                <label>Hours Worked</label>
                                <input type="number" name="HoursWorked" id="edit_HoursWorked" required step="0.25" min="0" style="width: 100%; box-sizing: border-box;">
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
    const assignmentData = <?php echo json_encode($tableData); ?>;
    
    function openEditModal(compositeId) {
        const row = assignmentData.find(a => a.CompositeID === compositeId);
        if (row) {
            document.getElementById('edit_MechanicID').value = row.MechanicID;
            document.getElementById('edit_ServiceRecordID').value = row.ServiceRecordID;
            document.getElementById('edit_MechanicName').value = row.MechanicName;
            
            document.getElementById('edit_RecordRef').value = row.ServiceRecordID + " - " + row.CustomerDisplay;
            
            document.getElementById('edit_DateAssigned').value = row.DateAssigned;
            document.getElementById('edit_HoursWorked').value = row.HoursWorked;
            
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>