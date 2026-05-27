<?php
// orderitems/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Over-the-Counter Retail Sales";

// Fetch active orders and available parts
$orders = $pdo->query("
    SELECT o.OrderID, c.CustomerName 
    FROM Order_T o 
    JOIN Customer_T c ON o.CustomerID = c.CustomerID 
    WHERE o.OrderStatus != 'Completed' 
    ORDER BY o.OrderDate DESC
")->fetchAll();

$parts = $pdo->query("SELECT PartID, PartName, UnitPrice, QuantityInStock FROM Part_T WHERE QuantityInStock > 0 ORDER BY PartName")->fetchAll();

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $pdo->beginTransaction();
            
            // 1. Link the retail part to the Order directly
            $stmt1 = $pdo->prepare("INSERT INTO OrderItem_T (OrderID, PartID, Quantity, Subtotal) VALUES (?, ?, ?, ?)");
            $stmt1->execute([$_POST['OrderID'], $_POST['PartID'], $_POST['Quantity'], $_POST['Subtotal']]);
            
            // 2. Deduct from Inventory
            $stmt2 = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock - ? WHERE PartID = ?");
            $stmt2->execute([$_POST['Quantity'], $_POST['PartID']]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Retail item added to Order and inventory deducted!";
        } 
        elseif ($action === 'delete') {
            $pdo->beginTransaction();
            list($orderID, $partID, $qty) = explode('|', $_POST['delete_id']);
            
            // 1. Remove the link
            $stmt1 = $pdo->prepare("DELETE FROM OrderItem_T WHERE OrderID=? AND PartID=?");
            $stmt1->execute([$orderID, $partID]);
            
            // 2. Restore to Inventory
            $stmt2 = $pdo->prepare("UPDATE Part_T SET QuantityInStock = QuantityInStock + ? WHERE PartID = ?");
            $stmt2->execute([$qty, $partID]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Retail item removed from Order and returned to stock!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Joined Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT 
            oi.OrderID, 
            oi.PartID,
            p.PartName, 
            oi.Quantity, 
            p.UnitPrice, 
            oi.Subtotal,
            c.CustomerName,
            CONCAT(oi.OrderID, '|', oi.PartID, '|', oi.Quantity) as CompositeID 
        FROM OrderItem_T oi 
        JOIN Part_T p ON oi.PartID = p.PartID
        JOIN Order_T o ON oi.OrderID = o.OrderID
        JOIN Customer_T c ON o.CustomerID = c.CustomerID";

$params = [];
if ($searchQuery) {
    $sql .= " WHERE oi.OrderID LIKE ? OR p.PartName LIKE ? OR c.CustomerName LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

foreach ($tableData as &$row) {
    $row['UnitPrice'] = "₱" . number_format($row['UnitPrice'], 2);
    $row['Subtotal'] = "₱" . number_format($row['Subtotal'], 2);
}

$tableHeaders = [
    'OrderID' => 'Order Ref', 
    'CustomerName' => 'Customer',
    'PartName' => 'Retail Item', 
    'Quantity' => 'Qty Sold', 
    'UnitPrice' => 'Unit Price', 
    'Subtotal' => 'Subtotal'
];
$primaryKey = 'CompositeID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Over-the-Counter Sales (Order Items)</h2>
        </div>

        <div style="background: #e0f2fe; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; color: #0369a1; font-size: 0.9em; border: 1px solid #bae6fd;">
            <strong><i class="fa-solid fa-circle-info"></i> How to use this page:</strong> This page is for selling parts directly to a customer <em>without</em> doing a repair. First, create an Order on the <strong>Orders</strong> page, then come here to attach the items they are buying.
        </div>
        
        <?php include '../components/search.php'; include '../components/table.php'; ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Sell Retail Item</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Target Order</label>
                                <select name="OrderID" required style="width: 100%; box-sizing: border-box;">
                                    <option value="">Select Active Order...</option>
                                    <?php foreach($orders as $o) echo "<option value='{$o['OrderID']}'>{$o['OrderID']} - {$o['CustomerName']}</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1.5; min-width: 0;">
                                <label>Inventory Part to Sell</label>
                                <select name="PartID" id="partSelect" required onchange="calculateRetailCost()" style="width: 100%; box-sizing: border-box;">
                                    <option value="" data-price="0">Select Part...</option>
                                    <?php foreach($parts as $p) echo "<option value='{$p['PartID']}' data-price='{$p['UnitPrice']}'>{$p['PartName']} (In Stock: {$p['QuantityInStock']})</option>"; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row" style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Quantity to Sell</label>
                                <input type="number" name="Quantity" id="qtyInput" required min="1" value="1" oninput="calculateRetailCost()" style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 0;">
                                <label>Unit Price (₱)</label>
                                <input type="number" id="priceInput" required step="0.01" min="0" readonly style="background: #f3f4f6; width: 100%; box-sizing: border-box;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Total Subtotal (₱)</label>
                            <input type="number" name="Subtotal" id="totalCostInput" required step="0.01" min="0" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold; border-color: #bae6fd; width: 100%; box-sizing: border-box;">
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px;">Save Retail Sale</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<style>
    .btn-edit { display: none !important; }
</style>

<script>
    function calculateRetailCost() {
        const select = document.getElementById('partSelect');
        const price = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
        const qty = parseFloat(document.getElementById('qtyInput').value) || 0;
        
        document.getElementById('priceInput').value = price.toFixed(2);
        document.getElementById('totalCostInput').value = (price * qty).toFixed(2);
    }
</script>

<?php include '../includes/footer.php'; ?>