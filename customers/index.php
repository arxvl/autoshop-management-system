<?php
// customers/index.php
require_once '../config/auth.php';
require_login();
global $pdo;

$pageTitle = "Manage Customers";

// --- Handle POST Requests (Full CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            // AUTO-GENERATE THE PRIMARY KEY
            // Creates a unique 8-character ID like "C-A1B2C3D"
            $newCustomerID = 'C-' . strtoupper(substr(uniqid(), -7));

            $stmt = $pdo->prepare("INSERT INTO Customer_T (CustomerID, CustomerName, CustomerCPNumber, CustomerAddress) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newCustomerID, $_POST['CustomerName'], $_POST['CustomerCPNumber'], $_POST['CustomerAddress']]);
            $_SESSION['success_msg'] = "Customer added successfully!";
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE Customer_T SET CustomerName=?, CustomerCPNumber=?, CustomerAddress=? WHERE CustomerID=?");
            $stmt->execute([$_POST['CustomerName'], $_POST['CustomerCPNumber'], $_POST['CustomerAddress'], $_POST['CustomerID']]);
            $_SESSION['success_msg'] = "Customer updated successfully!";
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM Customer_T WHERE CustomerID=?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success_msg'] = "Customer deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    redirect('index.php');
}

// --- Fetch Data ---
$searchQuery = $_GET['q'] ?? '';
$sql = "SELECT * FROM Customer_T";
$params = [];

if ($searchQuery) {
    $sql .= " WHERE CustomerName LIKE ? OR CustomerID LIKE ? OR CustomerCPNumber LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}

$sql .= " ORDER BY CustomerName ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableData = $stmt->fetchAll();

// --- Setup Component Variables ---
$tableHeaders = [
    'CustomerID' => 'System ID',
    'CustomerName' => 'Name',
    'CustomerCPNumber' => 'Contact Number',
    'CustomerAddress' => 'Address'
];
$primaryKey = 'CustomerID';
$searchPlaceholder = "Search by name, ID, or phone...";
$showAddButton = true;
$deleteActionUrl = "index.php";

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include '../includes/alerts.php'; ?>
        
        <h2>Customer Directory</h2>

        <?php 
        include '../components/search.php'; 
        include '../components/table.php'; 
        ?>
        
        <div id="addModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Customer</h2>
                    <button class="btn-close" type="button" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Customer Full Name</label>
                                <input type="text" name="CustomerName" required maxlength="100" placeholder="e.g., Jane Doe">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="CustomerCPNumber" required maxlength="15">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="CustomerAddress" required maxlength="200">
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('addModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Save Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Customer</h2>
                    <button class="btn-close" type="button" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="edit">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>System ID</label>
                                <input type="text" name="CustomerID" id="edit_CustomerID" readonly style="background: #f3f4f6; color: #9ca3af;">
                            </div>
                            <div class="form-group">
                                <label>Customer Name</label>
                                <input type="text" name="CustomerName" id="edit_CustomerName" required maxlength="100">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="CustomerCPNumber" id="edit_CustomerCPNumber" required maxlength="15">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="CustomerAddress" id="edit_CustomerAddress" required maxlength="200">
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal('editModal')" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" style="background: var(--accent); color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Update Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../components/delete_confirm.php'; ?>
    </div>
</div>

<script>
    // Injects the table data into the Edit form when the Pen icon is clicked
    const customerData = <?php echo json_encode($tableData); ?>;
    
    function openEditModal(id) {
        const row = customerData.find(c => c.CustomerID === id);
        if (row) {
            document.getElementById('edit_CustomerID').value = row.CustomerID;
            document.getElementById('edit_CustomerName').value = row.CustomerName;
            document.getElementById('edit_CustomerCPNumber').value = row.CustomerCPNumber;
            document.getElementById('edit_CustomerAddress').value = row.CustomerAddress;
            document.getElementById('editModal').classList.add('modal-active');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>