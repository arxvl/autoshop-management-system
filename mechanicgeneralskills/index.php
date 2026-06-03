<?php
// mechanicgeneralskills/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Mechanic Certifications";

$mechanics = $pdo->query("SELECT MechanicID, MechanicName FROM Mechanic_T")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO MechanicGeneralSkill_T (MechanicID, Skill) VALUES (?, ?)");
            $stmt->execute([$_POST['MechanicID'], $_POST['Skill']]);
            $_SESSION['success_msg'] = "Certification added!";
        } 
        elseif ($action === 'delete') {
            list($mechID, $skill) = explode('|', $_POST['delete_id']);
            $stmt = $pdo->prepare("DELETE FROM MechanicGeneralSkill_T WHERE MechanicID=? AND Skill=?");
            $stmt->execute([$mechID, $skill]);
            $_SESSION['success_msg'] = "Certification removed!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT ms.MechanicID, ms.Skill, m.MechanicName, CONCAT(ms.MechanicID, '|', ms.Skill) as CompositeID 
        FROM MechanicGeneralSkill_T ms 
        JOIN Mechanic_T m ON ms.MechanicID = m.MechanicID";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE m.MechanicName LIKE ? OR ms.Skill LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

$tableHeaders = ['MechanicName' => 'Mechanic', 'Skill' => 'Certification / General Skill'];
$primaryKey = 'CompositeID';
$showAddButton = true;
$deleteActionUrl = "index.php";

//Future updates
include '../includes/header.php'; include '../includes/navbar.php';
?>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        <h2>General Certifications</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Add Certification</h2><button class="btn-close" onclick="closeModal('addModal')">&times;</button></div>
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
                        <div class="form-group"><label>Certification or Skill Description</label><input type="text" name="Skill" required placeholder="e.g., ASE Certified"></div>
                        <div class="modal-footer"><button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save</button></div>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>
<style>
    /* Hide the Edit Pen since we rely on Delete/Add for this specific table */
    .btn-edit { display: none; }
</style>
<?php include '../includes/footer.php'; ?>