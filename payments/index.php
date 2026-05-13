<?php
// payments/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Manage Payments";

// Fetch active orders for the dropdown (showing the Order ID and Customer Name for context)
$orders = $pdo->query("
    SELECT o.OrderID, c.CustomerName, o.OrderTotalAmount 
    FROM Order_T o 
    JOIN Customer_T c ON o.CustomerID = c.CustomerID 
    ORDER BY o.OrderDate DESC
")->fetchAll();

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            // AUTO-GENERATE PRIMARY KEY (e.g., PAY-A1B2C3)
            $newPaymentID = 'PAY-' . strtoupper(substr(uniqid(), -6));
            
            $stmt = $pdo->prepare("INSERT INTO Payment_T (PaymentID, OrderID, PaymentDate, AmountPaid, PaymentMethod, PaymentStatus) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newPaymentID, $_POST['OrderID'], $_POST['PaymentDate'], $_POST['AmountPaid'], $_POST['PaymentMethod'], $_POST['PaymentStatus']]);
            $_SESSION['success_msg'] = "Payment recorded successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE Payment_T SET OrderID=?, PaymentDate=?, AmountPaid=?, PaymentMethod=?, PaymentStatus=? WHERE PaymentID=?");
            $stmt->execute([$_POST['OrderID'], $_POST['PaymentDate'], $_POST['AmountPaid'], $_POST['PaymentMethod'], $_POST['PaymentStatus'], $_POST['PaymentID']]);
            $_SESSION['success_msg'] = "Payment updated successfully!";
        }
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM Payment_T WHERE PaymentID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Payment record deleted!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT p.*, o.OrderTotalAmount, c.CustomerName 
        FROM Payment_T p 
        JOIN Order_T o ON p.OrderID = o.OrderID 
        JOIN Customer_T c ON o.CustomerID = c.CustomerID";
$params = [];
if ($searchQuery) {
    $sql .= " WHERE p.PaymentID LIKE ? OR c.CustomerName LIKE ? OR p.OrderID LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}
$sql .= " ORDER BY p.PaymentDate DESC, p.PaymentID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// Format Currency & Add Text Emojis
foreach ($tableData as &$row) {
    $row['AmountPaid'] = "₱" . number_format($row['AmountPaid'], 2);
    
    // Uses plain text emojis so they aren't blocked by the table security!
    if ($row['PaymentMethod'] === 'GCash') {
        $row['PaymentMethod'] = "📱 GCash";
    } elseif ($row['PaymentMethod'] === 'Cash') {
        $row['PaymentMethod'] = "💵 Cash";
    } elseif ($row['PaymentMethod'] === 'Card') {
        $row['PaymentMethod'] = "💳 Card";
    } elseif ($row['PaymentMethod'] === 'Bank Transfer') {
        $row['PaymentMethod'] = "🏦 Bank Transfer";
    }
}
unset($row); // FIX: Destroys the reference to prevent duplicating the last item!

// Setup UI
$tableHeaders = [
    'PaymentID' => 'Receipt No.', 
    'CustomerName' => 'Customer',
    'OrderID' => 'Order Ref', 
    'PaymentDate' => 'Date', 
    'AmountPaid' => 'Amount Paid', 
    'PaymentMethod' => 'Method',
    'PaymentStatus' => 'Status' // Note: The footer.php script handles the status badge automatically!
];
$primaryKey = 'PaymentID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Payment Collections</h2>
        <?php include '../components/search.php'; include '../components/table.php'; ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Record New Payment</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Target Order</label>
                                <select name="OrderID" required>
                                    <option value="">Select Order to Pay...</option>
                                    <?php foreach($orders as $o): ?>
                                        <option value="<?php echo $o['OrderID']; ?>">
                                            <?php echo $o['OrderID'] . " - " . htmlspecialchars($o['CustomerName']) . " (Total: ₱" . number_format($o['OrderTotalAmount'], 2) . ")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Payment Date</label>
                                <input type="date" name="PaymentDate" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Amount Paid (₱)</label>
                                <input type="number" name="AmountPaid" required step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="PaymentMethod" required>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Card">Credit/Debit Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="PaymentStatus" required>
                                    <option value="Paid">Fully Paid</option>
                                    <option value="Partial">Partial</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Refunded">Refunded</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--success); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h2>Edit Payment Record</h2><button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label>Receipt No.</label>
                                <input type="text" name="PaymentID" id="edit_PaymentID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <label>Target Order</label>
                                <select name="OrderID" id="edit_OrderID" required>
                                    <?php foreach($orders as $o): ?>
                                        <option value="<?php echo $o['OrderID']; ?>">
                                            <?php echo $o['OrderID'] . " - " . htmlspecialchars($o['CustomerName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Payment Date</label>
                                <input type="date" name="PaymentDate" id="edit_PaymentDate" required>
                            </div>
                            <div class="form-group">
                                <label>Amount Paid (₱)</label>
                                <input type="number" name="AmountPaid" id="edit_AmountPaid" required step="0.01" min="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="PaymentMethod" id="edit_PaymentMethod" required>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Card">Credit/Debit Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="PaymentStatus" id="edit_PaymentStatus" required>
                                    <option value="Paid">Fully Paid</option>
                                    <option value="Partial">Partial</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Refunded">Refunded</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    const paymentData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = paymentData.find(item => item.PaymentID === id);
        if (row) {
            document.getElementById('edit_PaymentID').value = row.PaymentID;
            document.getElementById('edit_OrderID').value = row.OrderID;
            document.getElementById('edit_PaymentDate').value = row.PaymentDate;
            document.getElementById('edit_AmountPaid').value = row.AmountPaid.replace('₱', '').replace(/,/g, '');
            
            // Clean the emojis off the text so the dropdown menu can auto-select the right option
            let rawMethod = row.PaymentMethod;
            rawMethod = rawMethod.replace('📱 ', '').replace('💵 ', '').replace('💳 ', '').replace('🏦 ', '');
            
            document.getElementById('edit_PaymentMethod').value = rawMethod;
            document.getElementById('edit_PaymentStatus').value = row.PaymentStatus;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>