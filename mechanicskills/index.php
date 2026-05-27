<?php
// mechanicskills/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Mechanic Service Skills";

$mechanics = $pdo->query("SELECT MechanicID, MechanicName FROM Mechanic_T ORDER BY MechanicName")->fetchAll();
$services = $pdo->query("SELECT ServiceTypeID, ServiceName FROM ServiceType_T ORDER BY ServiceName")->fetchAll();

// --- Handle POST Requests (Advanced Per-Skill Sync) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'sync') {
            $mechID = $_POST['MechanicID'];
            $postedSkills = $_POST['skills'] ?? []; // Array of checked ServiceTypeIDs
            $postedLevels = $_POST['levels'] ?? []; // Array mapping ServiceTypeID to SkillLevel
            
            $pdo->beginTransaction();
            
            // 1. Fetch current skills from database to compare
            $currentStmt = $pdo->prepare("SELECT ServiceTypeID FROM MechanicSkill_T WHERE MechanicID = ?");
            $currentStmt->execute([$mechID]);
            $currentSkills = $currentStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 2. Determine what needs to be Added, Updated, or Removed
            $toAdd = array_diff($postedSkills, $currentSkills);
            $toUpdate = array_intersect($postedSkills, $currentSkills);
            $toRemove = array_diff($currentSkills, $postedSkills);
            
            // 3. Remove unchecked skills safely
            if (!empty($toRemove)) {
                $deleteStmt = $pdo->prepare("DELETE FROM MechanicSkill_T WHERE MechanicID = ? AND ServiceTypeID = ?");
                foreach ($toRemove as $skillID) {
                    $deleteStmt->execute([$mechID, $skillID]);
                }
            }
            
            // 4. Add brand new skills with their specific level
            if (!empty($toAdd)) {
                $insertStmt = $pdo->prepare("INSERT INTO MechanicSkill_T (MechanicID, ServiceTypeID, SkillLevel) VALUES (?, ?, ?)");
                foreach ($toAdd as $skillID) {
                    $level = $postedLevels[$skillID] ?? 'Beginner';
                    $insertStmt->execute([$mechID, $skillID, $level]);
                }
            }
            
            // 5. Update the levels of existing skills just in case they were changed
            if (!empty($toUpdate)) {
                $updateStmt = $pdo->prepare("UPDATE MechanicSkill_T SET SkillLevel = ? WHERE MechanicID = ? AND ServiceTypeID = ?");
                foreach ($toUpdate as $skillID) {
                    $level = $postedLevels[$skillID] ?? 'Beginner';
                    $updateStmt->execute([$level, $mechID, $skillID]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Mechanic skills and proficiencies updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM MechanicSkill_T WHERE MechanicID = ?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "All skills cleared for this mechanic!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined & Grouped Data ---
$searchQuery = $_GET['q'] ?? '';

// We fetch the skills AND their specific levels. 
// RawSkillsData creates a string like "S1:Expert,S2:Beginner" so JavaScript can rebuild the Edit form perfectly!
$sql = "SELECT 
            m.MechanicID, 
            m.MechanicName,
            
            (SELECT GROUP_CONCAT(CONCAT(st.ServiceName, ' (', ms.SkillLevel, ')') SEPARATOR ', ')
             FROM MechanicSkill_T ms
             JOIN ServiceType_T st ON ms.ServiceTypeID = st.ServiceTypeID
             WHERE ms.MechanicID = m.MechanicID) as SkillsList,
             
            (SELECT GROUP_CONCAT(CONCAT(ms.ServiceTypeID, ':', ms.SkillLevel) SEPARATOR ',')
             FROM MechanicSkill_T ms
             WHERE ms.MechanicID = m.MechanicID) as RawSkillsData
             
        FROM Mechanic_T m
        WHERE EXISTS (SELECT 1 FROM MechanicSkill_T ms WHERE ms.MechanicID = m.MechanicID)";

$params = [];
if ($searchQuery) {
    $sql .= " AND m.MechanicName LIKE ?";
    $params = ["%$searchQuery%"];
}
$sql .= " ORDER BY m.MechanicName ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

foreach ($tableData as &$row) {
    if (empty($row['SkillsList'])) {
        $row['SkillsList'] = "<span style='color:#9ca3af; font-style:italic;'>No skills assigned</span>";
    }
}
unset($row);

// Setup UI
$tableHeaders = [
    'MechanicName' => 'Mechanic Name',
    'SkillsList' => 'Assigned Service Skills & Levels'
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Mechanic Service Skills</h2>
        </div>

        <div style="background: #f3f4f6; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; color: #4b5563; font-size: 0.9em; border: 1px solid #e5e7eb;">
            <strong><i class="fa-solid fa-circle-info"></i> How to use:</strong> Check the box next to the skill, then select the mechanic's specific proficiency level for that individual skill.
        </div>
        
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;"> <div class="modal-header">
                    <h2>Assign Skills to Mechanic</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="sync">
                        
                        <div class="form-group">
                            <label>Select Mechanic</label>
                            <select name="MechanicID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                <option value="">Choose Mechanic...</option>
                                <?php foreach($mechanics as $m) echo "<option value='{$m['MechanicID']}'>{$m['MechanicName']}</option>"; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Tick Approved Skills & Set Levels</label>
                            <div style="max-height: 300px; overflow-y: auto; background: #fff; border: 1px solid #d1d5db; padding: 1rem; border-radius: 4px; box-sizing: border-box;">
                                <?php foreach($services as $s): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f3f4f6;">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #374151; font-weight: 500; flex: 1;">
                                            <input type="checkbox" name="skills[]" value="<?php echo $s['ServiceTypeID']; ?>" style="width: 18px; height: 18px; cursor: pointer;">
                                            <?php echo htmlspecialchars($s['ServiceName']); ?>
                                        </label>
                                        <select name="levels[<?php echo $s['ServiceTypeID']; ?>]" style="padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.85em; width: 130px; background: #f9fafb;">
                                            <option value="Beginner">Beginner</option>
                                            <option value="Intermediate">Intermediate</option>
                                            <option value="Expert">Expert</option>
                                            <option value="Master">Master</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Skills</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Edit Mechanic Skills</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="sync">
                        <input type="hidden" name="MechanicID" id="edit_MechanicID">

                        <div class="form-group">
                            <label>Mechanic Name</label>
                            <input type="text" id="edit_MechanicName" readonly style="width: 100%; box-sizing: border-box; background: #f3f4f6; color: #4b5563; font-weight: bold; padding: 0.6rem;">
                        </div>

                        <div class="form-group">
                            <label>Update Skills & Levels</label>
                            <div style="max-height: 300px; overflow-y: auto; background: #fff; border: 1px solid #d1d5db; padding: 1rem; border-radius: 4px; box-sizing: border-box;">
                                <?php foreach($services as $s): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f3f4f6;">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #374151; font-weight: 500; flex: 1;">
                                            <input type="checkbox" name="skills[]" value="<?php echo $s['ServiceTypeID']; ?>" id="edit_skill_<?php echo $s['ServiceTypeID']; ?>" class="edit-skill-checkbox" style="width: 18px; height: 18px; cursor: pointer;">
                                            <?php echo htmlspecialchars($s['ServiceName']); ?>
                                        </label>
                                        <select name="levels[<?php echo $s['ServiceTypeID']; ?>]" id="edit_level_<?php echo $s['ServiceTypeID']; ?>" class="edit-skill-level" style="padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.85em; width: 130px; background: #f9fafb;">
                                            <option value="Beginner">Beginner</option>
                                            <option value="Intermediate">Intermediate</option>
                                            <option value="Expert">Expert</option>
                                            <option value="Master">Master</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Profile</button>
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
        const row = tableData.find(item => item.MechanicID === id);
        if (row) {
            document.getElementById('edit_MechanicID').value = row.MechanicID;
            document.getElementById('edit_MechanicName').value = row.MechanicName;
            
            // RESET FORM: Untick all boxes and reset dropdowns to Beginner
            document.querySelectorAll('.edit-skill-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.edit-skill-level').forEach(sel => sel.value = 'Beginner');
            
            // REPOPULATE FORM: Read the hidden RawSkillsData and apply it to the UI
            if (row.RawSkillsData) {
                const assignedSkills = row.RawSkillsData.split(',');
                assignedSkills.forEach(pair => {
                    const parts = pair.split(':');
                    if (parts.length === 2) {
                        const skillID = parts[0];
                        const skillLevel = parts[1];
                        
                        const checkbox = document.getElementById('edit_skill_' + skillID);
                        const selectBox = document.getElementById('edit_level_' + skillID);
                        
                        if (checkbox) checkbox.checked = true;
                        if (selectBox) selectBox.value = skillLevel;
                    }
                });
            }
            
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>