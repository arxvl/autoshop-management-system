<?php
// vehicles/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Master Vehicles Directory";

// Fetch customers for the dropdown menu (used in Edit)
$customers = $pdo->query("SELECT CustomerID, CustomerName FROM Customer_T ORDER BY CustomerName")->fetchAll();

// --- Handle POST Requests (Edit & Delete Only - No Adding Base Vehicles!) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'edit') {
            // Allows basic editing of the master plate/model from the directory
            $stmt = $pdo->prepare("UPDATE Vehicle_T SET CustomerID=?, VehiclePlateNumber=?, VehicleModel=?, VehicleYear=? WHERE VehicleID=?");
            $stmt->execute([$_POST['CustomerID'], $_POST['VehiclePlateNumber'], $_POST['VehicleModel'], $_POST['VehicleYear'], $_POST['VehicleID']]);
            $_SESSION['success_msg'] = "Vehicle base info updated successfully!";
        }
        elseif ($action === 'delete') {
            // Deleting the parent Vehicle_T safely cascades and deletes the child Car/Motorcycle too
            $stmt = $pdo->prepare("DELETE FROM Vehicle_T WHERE VehicleID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Vehicle deleted securely!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch & Filter Data (Strictly Cars & Motorcycles) ---
$searchQuery = $_GET['q'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Start the query. Notice the WHERE clause strictly requires them to be a Car or Motorcycle!
$sql = "SELECT 
            v.*, 
            cust.CustomerName,
            CASE 
                WHEN c.VehicleID IS NOT NULL THEN 'Car' 
                WHEN m.VehicleID IS NOT NULL THEN 'Motorcycle' 
            END AS VehicleType
        FROM Vehicle_T v 
        JOIN Customer_T cust ON v.CustomerID = cust.CustomerID
        LEFT JOIN Car_T c ON v.VehicleID = c.VehicleID
        LEFT JOIN Motorcycle_T m ON v.VehicleID = m.VehicleID
        WHERE (c.VehicleID IS NOT NULL OR m.VehicleID IS NOT NULL)"; 

$params = [];

// 1. Apply Text Search
if ($searchQuery) {
    $sql .= " AND (v.VehiclePlateNumber LIKE ? OR cust.CustomerName LIKE ? OR v.VehicleModel LIKE ?)";
    array_push($params, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%");
}

// 2. Apply Vehicle Type Dropdown Filter
if ($typeFilter === 'Car') {
    $sql .= " AND c.VehicleID IS NOT NULL"; 
} elseif ($typeFilter === 'Motorcycle') {
    $sql .= " AND m.VehicleID IS NOT NULL"; 
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
$showAddButton = false; // Turned off so we can use our custom inline buttons below!
$deleteActionUrl = "index.php";

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.85em;
        font-weight: 600;
        white-space: nowrap;
    }
    .type-car { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; } /* Blue */
    .type-moto { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; } /* Orange */
</style>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <div style="margin-bottom: 1rem;">
            <h2 style="margin: 0;">Master Vehicles Directory</h2>
        </div>

        <div style="background: #f3f4f6; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; color: #4b5563; font-size: 0.9em; border: 1px solid #e5e7eb;">
            <strong><i class="fa-solid fa-circle-info"></i> Notice:</strong> This is a read-only master directory of all registered vehicles. To add a new vehicle or edit specific engine details, please use the Add buttons below to navigate to the specific pages.
        </div>
        
        <div style="background: #f9fafb; padding: 1rem; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
            <span style="font-weight: 500; color: #4b5563;"><i class="fa-solid fa-filter"></i> Filter by Type:</span>
            
            <form method="GET" action="index.php" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <?php if(!empty($searchQuery)): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <?php endif; ?>
                
                <select name="type" onchange="this.form.submit()" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; background: white; cursor: pointer; font-family: inherit;">
                    <option value="">All Vehicles</option>
                    <option value="Car" <?php echo $typeFilter === 'Car' ? 'selected' : ''; ?>>Cars Only</option>
                    <option value="Motorcycle" <?php echo $typeFilter === 'Motorcycle' ? 'selected' : ''; ?>>Motorcycles Only</option>
                </select>

                <?php if (!empty($typeFilter)): ?>
                    <a href="index.php<?php echo !empty($searchQuery) ? '?q=' . urlencode($searchQuery) : ''; ?>" style="color: #ef4444; text-decoration: none; font-size: 0.9em; margin-left: 0.5rem;">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <form method="GET" action="index.php" style="display: flex; gap: 0.5rem; align-items: center; margin: 0;">
                <?php if(!empty($typeFilter)): ?>
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($typeFilter); ?>">
                <?php endif; ?>
                
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search plate, model, or owner..." style="padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 4px; width: 300px; font-family: inherit;">
                
                <button type="submit" style="background: var(--accent); color: white; padding: 0.6rem 1rem; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                
                <?php if (!empty($searchQuery)): ?>
                    <a href="index.php<?php echo !empty($typeFilter) ? '?type=' . urlencode($typeFilter) : ''; ?>" style="background: #f3f4f6; color: #4b5563; padding: 0.6rem 1rem; text-decoration: none; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9em;">
                        Clear Search
                    </a>
                <?php endif; ?>
            </form>

            <div style="display: flex; gap: 0.5rem;">
                <a href="../cars/index.php" style="background: var(--accent); color: white; padding: 0.6rem 1rem; text-decoration: none; border-radius: 4px; font-size: 0.9em; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"><i class="fa-solid fa-car"></i> Add Car</a>
                <a href="../motorcycles/index.php" style="background: var(--accent); color: white; padding: 0.6rem 1rem; text-decoration: none; border-radius: 4px; font-size: 0.9em; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"><i class="fa-solid fa-motorcycle"></i> Add Motorcycle</a>
            </div>
        </div>

        <?php include '../components/table.php'; ?>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Vehicle Base Info</h2>
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
                                <select name="CustomerID" id="edit_CustomerID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
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
    // JS MAGIC: Instantly scans the table and paints the Vehicle Types with color-coded badges!
    document.addEventListener("DOMContentLoaded", function() {
        const cells = document.querySelectorAll("td");
        
        cells.forEach(cell => {
            const text = cell.textContent.trim();
            if (text === 'Car') {
                cell.innerHTML = `<span class="type-badge type-car"><i class="fa-solid fa-car"></i> Car</span>`;
            } else if (text === 'Motorcycle') {
                cell.innerHTML = `<span class="type-badge type-moto"><i class="fa-solid fa-motorcycle"></i> Motorcycle</span>`;
            }
        });
    });

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