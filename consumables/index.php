<?php
// consumables/index.php
require_once '../config/auth.php';
require_login();
global $pdo;
$pageTitle = "Manage Consumables";

// --- Handle POST Requests (Full CRUD with Transactions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $newPartID = 'P-' . strtoupper(substr(uniqid(), -7));

            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("INSERT INTO part_t (PartID, PartName, QuantityInStock, UnitPrice) VALUES (?, ?, ?, ?)");
            $stmt1->execute([$newPartID, $_POST['PartName'], $_POST['QuantityInStock'], $_POST['UnitPrice']]);
            
            $stmt2 = $pdo->prepare("INSERT INTO consumable_t (PartID, VolumeInLiters, ExpirationDate) VALUES (?, ?, ?)");
            $stmt2->execute([$newPartID, $_POST['VolumeInLiters'], $_POST['ExpirationDate']]);
            $pdo->commit();
            
            $_SESSION['success_msg'] = "Consumable item added successfully!";
        } 
        elseif ($action === 'edit') {
            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("UPDATE part_t SET PartName=?, QuantityInStock=?, UnitPrice=? WHERE PartID=?");
            $stmt1->execute([$_POST['PartName'], $_POST['QuantityInStock'], $_POST['UnitPrice'], $_POST['PartID']]);
            
            $stmt2 = $pdo->prepare("UPDATE consumable_t SET VolumeInLiters=?, ExpirationDate=? WHERE PartID=?");
            $stmt2->execute([$_POST['VolumeInLiters'], $_POST['ExpirationDate'], $_POST['PartID']]);
            $pdo->commit();
            
            $_SESSION['success_msg'] = "Item updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM part_t WHERE PartID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Item deleted successfully!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT c.*, p.PartName, p.QuantityInStock, p.UnitPrice 
        FROM consumable_t c 
        JOIN part_t p ON c.PartID = p.PartID";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE p.PartName LIKE ? OR p.PartID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Add Peso Sign
foreach ($tableData as &$row) {
    $row['UnitPrice'] = "₱" . number_format($row['UnitPrice'], 2);
}
unset($row); // <--- THIS IS THE FIX! Destroys the dangling reference so the table loads perfectly.

// Setup UI
$tableHeaders = [
    'PartID' => 'Item Code', 
    'PartName' => 'Item Name', 
    'VolumeInLiters' => 'Volume (L)',
    'ExpirationDate' => 'Expiry Date',
    'QuantityInStock' => 'Stock Qty', 
    'UnitPrice' => 'Price'
];
$primaryKey = 'PartID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Shop Consumables</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Add Consumable</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Item Name</label>
                                <input type="text" name="PartName" required placeholder="e.g., Synthetic Motor Oil 5W-30">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Volume In Liters</label>
                                <input type="number" name="VolumeInLiters" required step="0.01" min="0" placeholder="e.g., 1.5">
                            </div>
                            <div class="form-group">
                                <label>Expiration Date</label>
                                <input type="date" name="ExpirationDate" required>
                            </div>
                        </div>
                        
                        <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Initial Stock Qty</label>
                                <input type="number" name="QuantityInStock" required min="0">
                            </div>
                            <div class="form-group">
                                <label>Unit Price (₱)</label>
                                <input type="number" name="UnitPrice" required step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Edit Consumable</h2><button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Item Code</label>
                                <input type="text" name="PartID" id="edit_PartID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Item Name</label>
                                <input type="text" name="PartName" id="edit_PartName" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Volume In Liters</label>
                                <input type="number" name="VolumeInLiters" id="edit_VolumeInLiters" required step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label>Expiration Date</label>
                                <input type="date" name="ExpirationDate" id="edit_ExpirationDate" required>
                            </div>
                        </div>
                        
                        <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stock Qty</label>
                                <input type="number" name="QuantityInStock" id="edit_QuantityInStock" required min="0">
                            </div>
                            <div class="form-group">
                                <label>Unit Price (₱)</label>
                                <input type="number" name="UnitPrice" id="edit_UnitPrice" required step="0.01" min="0">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Item</button>
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
        const row = tableData.find(item => item.PartID === id);
        if (row) {
            document.getElementById('edit_PartID').value = row.PartID;
            document.getElementById('edit_PartName').value = row.PartName;
            document.getElementById('edit_VolumeInLiters').value = row.VolumeInLiters;
            document.getElementById('edit_ExpirationDate').value = row.ExpirationDate;
            document.getElementById('edit_QuantityInStock').value = row.QuantityInStock;
            document.getElementById('edit_UnitPrice').value = row.UnitPrice.replace('₱', '').replace(',', '');
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>