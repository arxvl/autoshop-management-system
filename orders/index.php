<?php
// orders/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Manage Orders";

// We now use GROUP_CONCAT to fetch the actual Plate Number and Model of their vehicles!
$customers = $pdo->query("
    SELECT c.CustomerID, c.CustomerName, 
           (SELECT GROUP_CONCAT(CONCAT(VehiclePlateNumber, ' [', VehicleModel, ']') SEPARATOR ', ') 
            FROM Vehicle_T WHERE CustomerID = c.CustomerID) as OwnedVehicles
    FROM Customer_T c 
    ORDER BY c.CustomerName
")->fetchAll();

// --- BULLETPROOF SYNC: Automatically recalculate all Order Totals ---
try {
    $pdo->query("
        UPDATE Order_T o
        SET OrderTotalAmount = (
            COALESCE((SELECT SUM(TotalLaborCost + TotalPartsCost) FROM ServiceRecord_T WHERE OrderID = o.OrderID), 0) +
            COALESCE((SELECT SUM(Subtotal) FROM OrderItem_T WHERE OrderID = o.OrderID), 0)
        )
    ");
} catch (PDOException $e) {
    // Fails silently if OrderItem_T doesn't exist yet, which is fine for the first run!
}

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $newOrderID = 'ORD-' . strtoupper(substr(uniqid(), -6));
            $stmt = $pdo->prepare("INSERT INTO Order_T (OrderID, CustomerID, OrderDate, OrderTotalAmount, OrderStatus) VALUES (?, ?, ?, 0.00, ?)");
            $stmt->execute([$newOrderID, $_POST['CustomerID'], $_POST['OrderDate'], $_POST['OrderStatus']]);
            $_SESSION['success_msg'] = "Order created successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE Order_T SET CustomerID=?, OrderDate=?, OrderStatus=? WHERE OrderID=?");
            $stmt->execute([$_POST['CustomerID'], $_POST['OrderDate'], $_POST['OrderStatus'], $_POST['OrderID']]);
            $_SESSION['success_msg'] = "Order updated successfully!";
        }
        elseif ($action === 'delete') {
            $pdo->beginTransaction();
            $orderIDToDelete = $_POST['delete_id'];
            
            // INVENTORY SALVAGE: Return all OTC parts to stock
            $stmtOTC = $pdo->prepare("SELECT PartID, Quantity FROM OrderItem_T WHERE OrderID = ?");
            $stmtOTC->execute([$orderIDToDelete]);
            foreach ($stmtOTC->fetchAll() as $item) {
                $restore = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock + ? WHERE PartID = ?");
                $restore->execute([$item['Quantity'], $item['PartID']]);
            }

            // INVENTORY SALVAGE: Return all Service Bay parts to stock
            $stmtSR = $pdo->prepare("
                SELECT pu.PartID, pu.QuantityUsed 
                FROM PartsUsed_T pu 
                JOIN ServiceRecord_T sr ON pu.ServiceRecordID = sr.ServiceRecordID 
                WHERE sr.OrderID = ?
            ");
            $stmtSR->execute([$orderIDToDelete]);
            foreach ($stmtSR->fetchAll() as $item) {
                $restore = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock + ? WHERE PartID = ?");
                $restore->execute([$item['QuantityUsed'], $item['PartID']]);
            }
            
            // Delete the master Order
            $stmt = $pdo->prepare("DELETE FROM Order_T WHERE OrderID=?");
            $stmt->execute([$orderIDToDelete]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Order deleted and all attached parts returned to stock!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';

// Added OwnedVehicles subquery to fetch the specific vehicles for the main table!
$sql = "SELECT 
            o.*, 
            c.CustomerName,
            (SELECT GROUP_CONCAT(CONCAT(VehiclePlateNumber, ' [', VehicleModel, ']') SEPARATOR ', ') 
             FROM Vehicle_T WHERE CustomerID = c.CustomerID) as OwnedVehicles,
            (SELECT COALESCE(SUM(TotalLaborCost + TotalPartsCost), 0) FROM ServiceRecord_T WHERE OrderID = o.OrderID) as ServiceTotal,
            (SELECT COALESCE(SUM(Subtotal), 0) FROM OrderItem_T WHERE OrderID = o.OrderID) as RetailTotal,
            (SELECT GROUP_CONCAT(ServiceRecordID SEPARATOR ', ') FROM ServiceRecord_T WHERE OrderID = o.OrderID) as LinkedServices,
            (SELECT GROUP_CONCAT(CONCAT(p.PartName, ' (x', oi.Quantity, ')') SEPARATOR ', ') FROM OrderItem_T oi JOIN Part_T p ON oi.PartID = p.PartID WHERE oi.OrderID = o.OrderID) as LinkedRetailParts
        FROM Order_T o 
        JOIN Customer_T c ON o.CustomerID = c.CustomerID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE o.OrderID LIKE ? OR c.CustomerName LIKE ? OR o.OrderStatus LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY o.OrderDate DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

foreach ($tableData as &$row) {
    $row['OrderTotalAmount'] = "₱" . number_format($row['OrderTotalAmount'], 2);
    
    // Shows the specific vehicle(s), or Walk-in if none exist
    if (!empty($row['OwnedVehicles'])) {
        $badge = "  ::" . $row['OwnedVehicles'];
    } else {
        $badge = "  ::Walk-in";
    }
    
    $row['CustomerDisplay'] = $row['CustomerName'] . $badge;
}
unset($row);

// Setup UI
$tableHeaders = [
    'OrderID' => 'Order Ref', 
    'CustomerDisplay' => 'Customer', 
    'LinkedServices' => 'Services (Repairs)',
    'LinkedRetailParts' => 'Retail Items (OTC)',
    'OrderTotalAmount' => 'Grand Total', 
    'OrderStatus' => 'Status'
];
$primaryKey = 'OrderID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Order Management</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Create New Order</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Customer</label>
                                <select name="CustomerID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <option value="">Select Customer...</option>
                                    <?php foreach($customers as $c): 
                                        $tag = !empty($c['OwnedVehicles']) ? "  ::" . $c['OwnedVehicles'] : "  ::Walk-in";
                                    ?>
                                        <option value="<?php echo $c['CustomerID']; ?>">
                                            <?php echo htmlspecialchars($c['CustomerName']) . " - " . $tag; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Order Date</label>
                                <input type="date" name="OrderDate" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Grand Total (₱)</label>
                                <input type="text" value="0.00" readonly style="width: 100%; box-sizing: border-box; background: #f3f4f6; color: #9ca3af;">
                                <small style="color: #6b7280; font-size: 0.75em; display: block; margin-top: 4px;">Auto-calculated based on Services and Retail Items.</small>
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Status</label>
                                <select name="OrderStatus" required style="width: 100%; box-sizing: border-box;">
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Edit Order</h2><button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Order Ref</label>
                                <input type="text" name="OrderID" id="edit_OrderID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Customer</label>
                                <select name="CustomerID" id="edit_CustomerID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <?php foreach($customers as $c): 
                                        $tag = !empty($c['OwnedVehicles']) ? "  ::" . $c['OwnedVehicles'] : "  ::Walk-in";
                                    ?>
                                        <option value="<?php echo $c['CustomerID']; ?>">
                                            <?php echo htmlspecialchars($c['CustomerName']) . " - " . $tag; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Order Date</label>
                                <input type="date" name="OrderDate" id="edit_OrderDate" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="OrderStatus" id="edit_OrderStatus" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div style="background: #f9fafb; padding: 1rem; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 1rem;">
                            <h4 style="margin-top: 0; margin-bottom: 0.5rem; color: #374151; font-size: 0.9em;">Order Financial Summary</h4>
                            
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label style="font-size: 0.8em; color: #6b7280;">Attached Service Records (Vehicles):</label>
                                <input type="text" id="edit_LinkedServices" readonly style="width: 100%; box-sizing: border-box; border: none; background: transparent; font-weight: bold; color: #111827;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label style="font-size: 0.8em; color: #6b7280;">Over-the-Counter Retail Items:</label>
                                <textarea id="edit_LinkedRetailParts" readonly rows="2" style="width: 100%; box-sizing: border-box; border: none; background: transparent; resize: none; font-weight: bold; color: #111827;"></textarea>
                            </div>

                            <div class="form-row" style="margin-bottom: 0; display: flex; gap: 10px;">
                                <div class="form-group" style="flex: 1; min-width: 0;">
                                    <label style="font-size: 0.8em; color: #6b7280;">Service Subtotal</label>
                                    <input type="text" id="edit_ServiceTotal" readonly style="width: 100%; box-sizing: border-box; background: #f3f4f6; color: #6b7280; font-weight: bold;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 0;">
                                    <label style="font-size: 0.8em; color: #6b7280;">Retail Subtotal</label>
                                    <input type="text" id="edit_RetailTotal" readonly style="width: 100%; box-sizing: border-box; background: #f3f4f6; color: #6b7280; font-weight: bold;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 0;">
                                    <label style="font-size: 0.8em; color: #0369a1;">Grand Total (₱)</label>
                                    <input type="text" id="edit_OrderTotalAmount" readonly style="width: 100%; box-sizing: border-box; background: #e0f2fe; color: #0369a1; font-weight: bold; border-color: #bae6fd;">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const orderData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = orderData.find(item => item.OrderID === id);
        if (row) {
            document.getElementById('edit_OrderID').value = row.OrderID;
            document.getElementById('edit_CustomerID').value = row.CustomerID;
            document.getElementById('edit_OrderDate').value = row.OrderDate;
            document.getElementById('edit_OrderStatus').value = row.OrderStatus;

            document.getElementById('edit_LinkedServices').value = row.LinkedServices || 'No Service Records attached.';
            document.getElementById('edit_LinkedRetailParts').value = row.LinkedRetailParts || 'No Retail Items attached.';
            document.getElementById('edit_ServiceTotal').value = "₱" + parseFloat(row.ServiceTotal).toFixed(2);
            document.getElementById('edit_RetailTotal').value = "₱" + parseFloat(row.RetailTotal).toFixed(2);
            document.getElementById('edit_OrderTotalAmount').value = "₱" + (row.OrderTotalAmount ? row.OrderTotalAmount.replace('₱', '').replace(/,/g, '') : '0.00');
            
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>