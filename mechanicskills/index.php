<?php
// mechanicskills/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Mechanic Service Skills";

$mechanics = $pdo->query("SELECT MechanicID, MechanicName FROM Mechanic_T")->fetchAll();
$services = $pdo->query("SELECT ServiceTypeID, ServiceName FROM ServiceType_T")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO MechanicSkill_T (MechanicID, ServiceTypeID, SkillLevel) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['MechanicID'], $_POST['ServiceTypeID'], $_POST['SkillLevel']]);
            $_SESSION['success_msg'] = "Skill logged successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE MechanicSkill_T SET SkillLevel=? WHERE MechanicID=? AND ServiceTypeID=?");
            $stmt->execute([$_POST['SkillLevel'], $_POST['MechanicID'], $_POST['ServiceTypeID']]);
            $_SESSION['success_msg'] = "Skill level updated!";
        }
        elseif ($action === 'delete') {
            list($mechID, $servID) = explode('|', $_POST['delete_id']);
            $stmt = $pdo->prepare("DELETE FROM MechanicSkill_T WHERE MechanicID=? AND ServiceTypeID=?");
            $stmt->execute([$mechID, $servID]);
            $_SESSION['success_msg'] = "Skill removed!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// Fetch Joined Data
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT ms.MechanicID, ms.ServiceTypeID, m.MechanicName, s.ServiceName, ms.SkillLevel, CONCAT(ms.MechanicID, '|', ms.ServiceTypeID) as CompositeID 
        FROM MechanicSkill_T ms 
        JOIN Mechanic_T m ON ms.MechanicID = m.MechanicID
        JOIN ServiceType_T s ON ms.ServiceTypeID = s.ServiceTypeID";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE m.MechanicName LIKE ? OR s.ServiceName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

$tableHeaders = ['MechanicName' => 'Mechanic', 'ServiceName' => 'Service Type', 'SkillLevel' => 'Proficiency Level'];
$primaryKey = 'CompositeID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        <h2>Mechanic Service Proficiency</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Log New Skill</h2><button class="btn-close" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Mechanic</label>
                            <select name="MechanicID" required style="width: 100%; padding: 0.5rem;">
                                <option value="">Select Mechanic...</option>
                                <?php foreach($mechanics as $m) echo "<option value='{$m['MechanicID']}'>{$m['MechanicName']}</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Type</label>
                            <select name="ServiceTypeID" required style="width: 100%; padding: 0.5rem;">
                                <option value="">Select Service...</option>
                                <?php foreach($services as $s) echo "<option value='{$s['ServiceTypeID']}'>{$s['ServiceName']}</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Skill Level</label>
                            <select name="SkillLevel" required style="width: 100%; padding: 0.5rem;">
                                <option value="Beginner">Beginner</option><option value="Intermediate">Intermediate</option><option value="Advanced">Advanced</option><option value="Expert">Expert</option>
                            </select>
                        </div>
                        <div class="modal-footer"><button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save Skill</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Update Skill Level</h2><button class="btn-close" onclick="closeModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="MechanicID" id="edit_MechanicID">
                        <input type="hidden" name="ServiceTypeID" id="edit_ServiceTypeID">
                        
                        <div class="form-group"><label>Mechanic</label><input type="text" id="edit_MechanicName" readonly style="background: #f3f4f6;"></div>
                        <div class="form-group"><label>Service</label><input type="text" id="edit_ServiceName" readonly style="background: #f3f4f6;"></div>
                        
                        <div class="form-group">
                            <label>Update Skill Level</label>
                            <select name="SkillLevel" id="edit_SkillLevel" required style="width: 100%; padding: 0.5rem;">
                                <option value="Beginner">Beginner</option><option value="Intermediate">Intermediate</option><option value="Advanced">Advanced</option><option value="Expert">Expert</option>
                            </select>
                        </div>
                        <div class="modal-footer"><button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Update</button></div>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const tableData = <?php echo json_encode($tableData); ?>;
    function openEditModal(compositeId) {
        const row = tableData.find(item => item.CompositeID === compositeId);
        if (row) {
            document.getElementById('edit_MechanicID').value = row.MechanicID;
            document.getElementById('edit_ServiceTypeID').value = row.ServiceTypeID;
            document.getElementById('edit_MechanicName').value = row.MechanicName;
            document.getElementById('edit_ServiceName').value = row.ServiceName;
            document.getElementById('edit_SkillLevel').value = row.SkillLevel;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>