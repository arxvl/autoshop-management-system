<?php
// payments/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Manage Payments";

// 1. ALL ORDERS (Used for the Edit Modal so old receipts don't lose their linked order)
$allOrders = $pdo->query("
    SELECT o.OrderID, c.CustomerName, o.OrderTotalAmount 
    FROM Order_T o 
    JOIN Customer_T c ON o.CustomerID = c.CustomerID 
    ORDER BY o.OrderDate DESC
")->fetchAll();

// 2. UNPAID ORDERS ONLY (Used for the Add Modal to prevent double-charging)
$unpaidOrders = $pdo->query("
    SELECT o.OrderID, c.CustomerName, o.OrderTotalAmount 
    FROM Order_T o 
    JOIN Customer_T c ON o.CustomerID = c.CustomerID 
    WHERE o.OrderID NOT IN (SELECT OrderID FROM Payment_T WHERE PaymentStatus = 'Paid')
    ORDER BY o.OrderDate DESC
")->fetchAll();

// --- Handle POST Requests (Parent/Child Transactions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $newPaymentID = 'PAY-' . strtoupper(substr(uniqid(), -6));
            
            $pdo->beginTransaction();
            
            // 1. Insert into Parent Table
            $stmt = $pdo->prepare("INSERT INTO Payment_T (PaymentID, OrderID, PaymentDate, AmountPaid, PaymentMethod, PaymentStatus) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newPaymentID, $_POST['OrderID'], $_POST['PaymentDate'], $_POST['AmountPaid'], $_POST['PaymentMethod'], $_POST['PaymentStatus']]);
            
            // 2. Insert into Specific Child Table
            if ($_POST['PaymentMethod'] === 'Cash') {
                $stmtCash = $pdo->prepare("INSERT INTO Cash_T (PaymentID, AmountOffer, ChangeAmount) VALUES (?, ?, ?)");
                $stmtCash->execute([$newPaymentID, $_POST['AmountOffer'], $_POST['ChangeAmount']]);
            } elseif ($_POST['PaymentMethod'] === 'GCash') {
                $stmtGCash = $pdo->prepare("INSERT INTO GCash_T (PaymentID, RefNumber, GCashName, GCashNumber) VALUES (?, ?, ?, ?)");
                $stmtGCash->execute([$newPaymentID, $_POST['RefNumber'], $_POST['GCashName'], $_POST['GCashNumber']]);
            }
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Payment recorded successfully!";
        } 
        elseif ($action === 'edit') {
            $paymentID = $_POST['PaymentID'];
            
            $pdo->beginTransaction();
            
            // 1. Update Parent Table
            $stmt = $pdo->prepare("UPDATE Payment_T SET OrderID=?, PaymentDate=?, AmountPaid=?, PaymentMethod=?, PaymentStatus=? WHERE PaymentID=?");
            $stmt->execute([$_POST['OrderID'], $_POST['PaymentDate'], $_POST['AmountPaid'], $_POST['PaymentMethod'], $_POST['PaymentStatus'], $paymentID]);
            
            // 2. Clear old child records
            $pdo->prepare("DELETE FROM Cash_T WHERE PaymentID=?")->execute([$paymentID]);
            $pdo->prepare("DELETE FROM GCash_T WHERE PaymentID=?")->execute([$paymentID]);
            
            // 3. Insert fresh child data
            if ($_POST['PaymentMethod'] === 'Cash') {
                $stmtCash = $pdo->prepare("INSERT INTO Cash_T (PaymentID, AmountOffer, ChangeAmount) VALUES (?, ?, ?)");
                $stmtCash->execute([$paymentID, $_POST['AmountOffer'], $_POST['ChangeAmount']]);
            } elseif ($_POST['PaymentMethod'] === 'GCash') {
                $stmtGCash = $pdo->prepare("INSERT INTO GCash_T (PaymentID, RefNumber, GCashName, GCashNumber) VALUES (?, ?, ?, ?)");
                $stmtGCash->execute([$paymentID, $_POST['RefNumber'], $_POST['GCashName'], $_POST['GCashNumber']]);
            }
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Payment updated successfully!";
        }
        elseif ($action === 'delete') {
            $pdo->beginTransaction();
            $paymentID = $_POST['delete_id'];
            
            $pdo->prepare("DELETE FROM Cash_T WHERE PaymentID=?")->execute([$paymentID]);
            $pdo->prepare("DELETE FROM GCash_T WHERE PaymentID=?")->execute([$paymentID]);
            $pdo->prepare("DELETE FROM Payment_T WHERE PaymentID=?")->execute([$paymentID]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Payment record deleted!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch & Filter Data ---
$searchQuery = $_GET['q'] ?? '';
$methodFilter = $_GET['method'] ?? '';

$sql = "SELECT p.*, o.OrderTotalAmount, c.CustomerName,
               cash.AmountOffer, cash.ChangeAmount,
               g.RefNumber, g.GCashName, g.GCashNumber
        FROM Payment_T p 
        JOIN Order_T o ON p.OrderID = o.OrderID 
        JOIN Customer_T c ON o.CustomerID = c.CustomerID
        LEFT JOIN Cash_T cash ON p.PaymentID = cash.PaymentID
        LEFT JOIN GCash_T g ON p.PaymentID = g.PaymentID
        WHERE 1=1"; 

$params = [];

if ($searchQuery) {
    $sql .= " AND (p.PaymentID LIKE ? OR c.CustomerName LIKE ? OR p.OrderID LIKE ? OR g.RefNumber LIKE ?)";
    array_push($params, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%");
}

if ($methodFilter) {
    $sql .= " AND p.PaymentMethod = ?";
    array_push($params, $methodFilter);
}

$sql .= " ORDER BY p.PaymentDate DESC, p.PaymentID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Format Data (Bypassing Table HTML Blockers) ---
foreach ($tableData as &$row) {
    $row['AmountPaid'] = "₱" . number_format($row['AmountPaid'], 2);
    
    // FIX: Instead of raw HTML, we send a secret string to JS to render later!
    if ($row['PaymentMethod'] === 'Cash' && isset($row['AmountOffer'])) {
        $row['SpecificDetails'] = "JS_CASH|" . number_format($row['AmountOffer'], 2) . "|" . number_format($row['ChangeAmount'], 2);
    } elseif ($row['PaymentMethod'] === 'GCash' && isset($row['RefNumber'])) {
        $row['SpecificDetails'] = "JS_GCASH|" . htmlspecialchars($row['RefNumber']) . "|" . htmlspecialchars($row['GCashName']) . "|" . htmlspecialchars($row['GCashNumber']);
    } else {
        $row['SpecificDetails'] = "-";
    }
}
unset($row);

// Setup UI
$tableHeaders = [
    'PaymentID' => 'Receipt No.', 
    'CustomerName' => 'Customer',
    'OrderID' => 'Order Ref', 
    'PaymentDate' => 'Date', 
    'AmountPaid' => 'Amount Paid', 
    'PaymentMethod' => 'Method',
    'SpecificDetails' => 'Payment Details',
    'PaymentStatus' => 'Status'
];
$primaryKey = 'PaymentID';
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php'; include '../includes/navbar.php';
?>

<style>
    .method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.85em;
        font-weight: 600;
        white-space: nowrap;
    }
    .method-gcash { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .method-cash { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
</style>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <div style="margin-bottom: 1rem;">
            <h2 style="margin: 0;">Payment Collections</h2>
        </div>
        
        <div style="background: #f9fafb; padding: 1rem; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
            <span style="font-weight: 500; color: #4b5563;"><i class="fa-solid fa-filter"></i> Filter by Method:</span>
            
            <form method="GET" action="index.php" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <?php if(!empty($searchQuery)): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <?php endif; ?>
                
                <select name="method" onchange="this.form.submit()" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; background: white; cursor: pointer; font-family: inherit;">
                    <option value="">All Payment Methods</option>
                    <option value="Cash" <?php echo $methodFilter === 'Cash' ? 'selected' : ''; ?>>💵 Cash</option>
                    <option value="GCash" <?php echo $methodFilter === 'GCash' ? 'selected' : ''; ?>>📱 GCash</option>
                </select>

                <?php if (!empty($methodFilter)): ?>
                    <a href="index.php<?php echo !empty($searchQuery) ? '?q=' . urlencode($searchQuery) : ''; ?>" style="color: #ef4444; text-decoration: none; font-size: 0.9em; margin-left: 0.5rem;">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <?php include '../components/search.php'; ?>
        <?php include '../components/table.php'; ?>

        <div id="addModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header"><h2>Record New Payment</h2><button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Target Order</label>
                                <select name="OrderID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <option value="">Select Order to Pay...</option>
                                    <?php if(empty($unpaidOrders)): ?>
                                        <option value="" disabled>No pending orders available.</option>
                                    <?php else: ?>
                                        <?php foreach($unpaidOrders as $o): ?>
                                            <option value="<?php echo $o['OrderID']; ?>">
                                                <?php echo $o['OrderID'] . " - " . htmlspecialchars($o['CustomerName']) . " (Total: ₱" . number_format($o['OrderTotalAmount'], 2) . ")"; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Payment Date</label>
                                <input type="date" name="PaymentDate" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Amount to Pay (₱)</label>
                                <input type="number" name="AmountPaid" id="add_AmountPaid" required step="0.01" min="0" oninput="calculateAddChange()">
                            </div>
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="PaymentMethod" id="add_PaymentMethod" required onchange="toggleAddFields()" style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="PaymentStatus" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <option value="Paid">Fully Paid</option>
                                    <option value="Partial">Partial</option>
                                </select>
                            </div>
                        </div>

                        <div id="add_cashFields" style="background: #f9fafb; padding: 1rem; border-radius: 4px; border: 1px solid #d1d5db; margin-bottom: 1rem;">
                            <h4 style="margin-top: 0; color: #374151;">Cash Details</h4>
                            <div class="form-row" style="margin-bottom: 0;">
                                <div class="form-group">
                                    <label>Amount Tendered (₱)</label>
                                    <input type="number" name="AmountOffer" id="add_AmountOffer" step="0.01" min="0" oninput="calculateAddChange()">
                                </div>
                                <div class="form-group">
                                    <label>Change (₱)</label>
                                    <input type="number" name="ChangeAmount" id="add_ChangeAmount" step="0.01" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold;">
                                </div>
                            </div>
                        </div>

                        <div id="add_gcashFields" style="background: #f9fafb; padding: 1rem; border-radius: 4px; border: 1px solid #d1d5db; margin-bottom: 1rem; display: none;">
                            <h4 style="margin-top: 0; color: #374151;">GCash Details</h4>
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" name="RefNumber" id="add_RefNumber" maxlength="50">
                            </div>
                            <div class="form-row" style="margin-bottom: 0;">
                                <div class="form-group">
                                    <label>GCash Account Name</label>
                                    <input type="text" name="GCashName" id="add_GCashName" maxlength="100">
                                </div>
                                <div class="form-group">
                                    <label>GCash Number</label>
                                    <input type="text" name="GCashNumber" id="add_GCashNumber" maxlength="15" placeholder="e.g., 09123456789">
                                </div>
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
            <div class="modal-content" style="max-width: 600px;">
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
                                <select name="OrderID" id="edit_OrderID" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <?php foreach($allOrders as $o): ?>
                                        <option value="<?php echo $o['OrderID']; ?>">
                                            <?php echo $o['OrderID'] . " - " . htmlspecialchars($o['CustomerName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Amount Paid (₱)</label>
                                <input type="number" name="AmountPaid" id="edit_AmountPaid" required step="0.01" min="0" oninput="calculateEditChange()">
                            </div>
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="PaymentMethod" id="edit_PaymentMethod" required onchange="toggleEditFields()" style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="PaymentStatus" id="edit_PaymentStatus" required style="width: 100%; box-sizing: border-box; padding: 0.6rem;">
                                    <option value="Paid">Fully Paid</option>
                                    <option value="Partial">Partial</option>
                                    <option value="Refunded">Refunded</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Payment Date</label>
                            <input type="date" name="PaymentDate" id="edit_PaymentDate" required>
                        </div>

                        <div id="edit_cashFields" style="background: #f9fafb; padding: 1rem; border-radius: 4px; border: 1px solid #d1d5db; margin-bottom: 1rem;">
                            <h4 style="margin-top: 0; color: #374151;">Cash Details</h4>
                            <div class="form-row" style="margin-bottom: 0;">
                                <div class="form-group">
                                    <label>Amount Tendered (₱)</label>
                                    <input type="number" name="AmountOffer" id="edit_AmountOffer" step="0.01" min="0" oninput="calculateEditChange()">
                                </div>
                                <div class="form-group">
                                    <label>Change (₱)</label>
                                    <input type="number" name="ChangeAmount" id="edit_ChangeAmount" step="0.01" readonly style="background: #e0f2fe; color: #0369a1; font-weight: bold;">
                                </div>
                            </div>
                        </div>

                        <div id="edit_gcashFields" style="background: #f9fafb; padding: 1rem; border-radius: 4px; border: 1px solid #d1d5db; margin-bottom: 1rem; display: none;">
                            <h4 style="margin-top: 0; color: #374151;">GCash Details</h4>
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" name="RefNumber" id="edit_RefNumber" maxlength="50">
                            </div>
                            <div class="form-row" style="margin-bottom: 0;">
                                <div class="form-group">
                                    <label>GCash Account Name</label>
                                    <input type="text" name="GCashName" id="edit_GCashName" maxlength="100">
                                </div>
                                <div class="form-group">
                                    <label>GCash Number</label>
                                    <input type="text" name="GCashNumber" id="edit_GCashNumber" maxlength="15">
                                </div>
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
    // JS MAGIC: This scans the table cells and safely replaces our secret text strings with actual HTML!
    document.addEventListener("DOMContentLoaded", function() {
        const cells = document.querySelectorAll("td");
        
        cells.forEach(cell => {
            const text = cell.textContent.trim();
            
            // Format Payment Methods
            if (text === 'GCash') {
                cell.innerHTML = `<span class="method-badge method-gcash"><i class="fa-solid fa-mobile-screen"></i> GCash</span>`;
            } else if (text === 'Cash') {
                cell.innerHTML = `<span class="method-badge method-cash"><i class="fa-solid fa-money-bill-wave"></i> Cash</span>`;
            }
            
            // Format Payment Details
            if (text.startsWith('JS_CASH|')) {
                const parts = text.split('|');
                cell.innerHTML = `Tendered: ₱${parts[1]}<br><small style='color: #6b7280;'>Change: ₱${parts[2]}</small>`;
            } else if (text.startsWith('JS_GCASH|')) {
                const parts = text.split('|');
                cell.innerHTML = `Ref: ${parts[1]}<br><small style='color: #6b7280;'>${parts[2]} (${parts[3]})</small>`;
            }
        });
        
        toggleAddFields();
    });

    // --- FORM DYNAMICS (ADD MODAL) ---
    function toggleAddFields() {
        const method = document.getElementById('add_PaymentMethod').value;
        const cashFields = document.getElementById('add_cashFields');
        const gcashFields = document.getElementById('add_gcashFields');
        
        if (method === 'Cash') {
            cashFields.style.display = 'block';
            gcashFields.style.display = 'none';
            document.getElementById('add_AmountOffer').required = true;
            document.getElementById('add_RefNumber').required = false;
            document.getElementById('add_GCashName').required = false;
            document.getElementById('add_GCashNumber').required = false;
        } else {
            cashFields.style.display = 'none';
            gcashFields.style.display = 'block';
            document.getElementById('add_AmountOffer').required = false;
            document.getElementById('add_RefNumber').required = true;
            document.getElementById('add_GCashName').required = true;
            document.getElementById('add_GCashNumber').required = true;
        }
    }

    function calculateAddChange() {
        const amountPaid = parseFloat(document.getElementById('add_AmountPaid').value) || 0;
        const amountOffer = parseFloat(document.getElementById('add_AmountOffer').value) || 0;
        const change = amountOffer - amountPaid;
        document.getElementById('add_ChangeAmount').value = change > 0 ? change.toFixed(2) : '0.00';
    }

    // --- FORM DYNAMICS (EDIT MODAL) ---
    function toggleEditFields() {
        const method = document.getElementById('edit_PaymentMethod').value;
        const cashFields = document.getElementById('edit_cashFields');
        const gcashFields = document.getElementById('edit_gcashFields');
        
        if (method === 'Cash') {
            cashFields.style.display = 'block';
            gcashFields.style.display = 'none';
            document.getElementById('edit_AmountOffer').required = true;
            document.getElementById('edit_RefNumber').required = false;
            document.getElementById('edit_GCashName').required = false;
            document.getElementById('edit_GCashNumber').required = false;
        } else {
            cashFields.style.display = 'none';
            gcashFields.style.display = 'block';
            document.getElementById('edit_AmountOffer').required = false;
            document.getElementById('edit_RefNumber').required = true;
            document.getElementById('edit_GCashName').required = true;
            document.getElementById('edit_GCashNumber').required = true;
        }
    }

    function calculateEditChange() {
        const amountPaid = parseFloat(document.getElementById('edit_AmountPaid').value) || 0;
        const amountOffer = parseFloat(document.getElementById('edit_AmountOffer').value) || 0;
        const change = amountOffer - amountPaid;
        document.getElementById('edit_ChangeAmount').value = change > 0 ? change.toFixed(2) : '0.00';
    }

    // --- POPULATE EDIT MODAL ---
    const paymentData = <?php echo json_encode($tableData); ?>;
    function openEditModal(id) {
        const row = paymentData.find(item => item.PaymentID === id);
        if (row) {
            document.getElementById('edit_PaymentID').value = row.PaymentID;
            document.getElementById('edit_OrderID').value = row.OrderID;
            document.getElementById('edit_PaymentDate').value = row.PaymentDate;
            document.getElementById('edit_AmountPaid').value = row.AmountPaid.replace('₱', '').replace(/,/g, '');
            document.getElementById('edit_PaymentStatus').value = row.PaymentStatus;
            
            let rawMethod = row.PaymentMethod;
            document.getElementById('edit_PaymentMethod').value = rawMethod;
            
            // Populate Child Attributes
            if (rawMethod === 'Cash') {
                document.getElementById('edit_AmountOffer').value = row.AmountOffer || '';
                document.getElementById('edit_ChangeAmount').value = row.ChangeAmount || '0.00';
                
                // Clear GCash fields so they don't submit stale data if switched
                document.getElementById('edit_RefNumber').value = '';
                document.getElementById('edit_GCashName').value = '';
                document.getElementById('edit_GCashNumber').value = '';
            } else if (rawMethod === 'GCash') {
                document.getElementById('edit_RefNumber').value = row.RefNumber || '';
                document.getElementById('edit_GCashName').value = row.GCashName || '';
                document.getElementById('edit_GCashNumber').value = row.GCashNumber || '';
                
                // Clear Cash fields
                document.getElementById('edit_AmountOffer').value = '';
                document.getElementById('edit_ChangeAmount').value = '';
            }
            
            toggleEditFields();
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>
<?php include '../includes/footer.php'; ?>