<?php
// includes/sidebar.php
// Get the current script name to highlight the active menu item
$current_page = basename($_SERVER['SCRIPT_NAME']);
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
?>
<style>
    .sidebar { width: 250px; background: #fff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; }
    .sidebar-menu { list-style: none; padding: 1rem 0; margin: 0; flex: 1; overflow-y: auto; }
    .sidebar-menu li { margin-bottom: 0.25rem; }
    .sidebar-menu a { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: var(--text-light); text-decoration: none; font-weight: 500; transition: all 0.2s; }
    .sidebar-menu a i { width: 25px; font-size: 1.1em; }
    .sidebar-menu a:hover { background: #f9fafb; color: var(--accent); }
    .sidebar-menu a.active { background: #eff6ff; color: var(--accent); border-right: 3px solid var(--accent); }
    .menu-header { padding: 1rem 1.5rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; color: #9ca3af; font-weight: bold; letter-spacing: 0.05em; }
    
    /* Indentation for sub-items */
    .sub-item a { padding-left: 3rem; font-size: 0.9em; }
    .sub-item a i { width: 20px; font-size: 0.9em; }
</style>

<div class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="/autoshop_system/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>

        <div class="menu-header">CRM</div>
        <li><a href="/autoshop_system/customers/index.php" class="<?php echo ($current_dir == 'customers') ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i> Customers</a></li>
        
        <div class="menu-header">Vehicles</div>
        <li><a href="/autoshop_system/vehicles/index.php" class="<?php echo ($current_dir == 'vehicles') ? 'active' : ''; ?>"><i class="fa-solid fa-car-side"></i> Master List</a></li>
        <li class="sub-item"><a href="/autoshop_system/cars/index.php" class="<?php echo ($current_dir == 'cars') ? 'active' : ''; ?>"><i class="fa-solid fa-car"></i> Cars</a></li>
        <li class="sub-item"><a href="/autoshop_system/motorcycles/index.php" class="<?php echo ($current_dir == 'motorcycles') ? 'active' : ''; ?>"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a></li>

        <div class="menu-header">Operations & Staff</div>
        <li><a href="/autoshop_system/orders/index.php" class="<?php echo ($current_dir == 'orders') ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice"></i> Orders</a></li>
        <li class="sub-item"><a href="/autoshop_system/orderitems/index.php" class="<?php echo ($current_dir == 'orderitems') ? 'active' : ''; ?>"><i class="fa-solid fa-box-open"></i> Retail Sales (OTC)</a></li>

        <li><a href="/autoshop_system/servicerecords/index.php" class="<?php echo ($current_dir == 'servicerecords') ? 'active' : ''; ?>"><i class="fa-solid fa-clipboard-list"></i> Service Records</a></li>
        <li class="sub-item"><a href="/autoshop_system/repairservices/index.php" class="<?php echo ($current_dir == 'repairservices') ? 'active' : ''; ?>"><i class="fa-solid fa-screwdriver-wrench"></i> Log Services</a></li>
        <li class="sub-item"><a href="/autoshop_system/serviceparts/index.php" class="<?php echo ($current_dir == 'serviceparts') ? 'active' : ''; ?>"><i class="fa-solid fa-toolbox"></i> Log Parts Used</a></li>
        
        <li><a href="/autoshop_system/mechanics/index.php" class="<?php echo ($current_dir == 'mechanics') ? 'active' : ''; ?>"><i class="fa-solid fa-user-gear"></i> Mechanics</a></li>
        <li class="sub-item"><a href="/autoshop_system/mechanicassignments/index.php" class="<?php echo ($current_dir == 'mechanicassignments') ? 'active' : ''; ?>"><i class="fa-solid fa-wrench"></i> Job Assignments</a></li>
        <li class="sub-item"><a href="/autoshop_system/mechanicskills/index.php" class="<?php echo ($current_dir == 'mechanicskills') ? 'active' : ''; ?>"><i class="fa-solid fa-star"></i> Service Skills</a></li>
        <li class="sub-item"><a href="/autoshop_system/mechanicgeneralskills/index.php" class="<?php echo ($current_dir == 'mechanicgeneralskills') ? 'active' : ''; ?>"><i class="fa-solid fa-certificate"></i> Certifications</a></li>

        <div class="menu-header">Inventory</div>
        <li><a href="/autoshop_system/parts/index.php" class="<?php echo ($current_dir == 'parts') ? 'active' : ''; ?>"><i class="fa-solid fa-boxes-stacked"></i> Master Inventory</a></li>
        <li class="sub-item"><a href="/autoshop_system/consumables/index.php" class="<?php echo ($current_dir == 'consumables') ? 'active' : ''; ?>"><i class="fa-solid fa-oil-can"></i> Consumables</a></li>
        <li class="sub-item"><a href="/autoshop_system/spareparts/index.php" class="<?php echo ($current_dir == 'spareparts') ? 'active' : ''; ?>"><i class="fa-solid fa-gear"></i> Spare Parts</a></li>
        
        <div class="menu-header">Finance</div>
        <li><a href="/autoshop_system/servicetypes/index.php" class="<?php echo ($current_dir == 'servicetypes') ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> Service Types & Pricing</a></li>
        <li><a href="/autoshop_system/payments/index.php" class="<?php echo ($current_dir == 'payments') ? 'active' : ''; ?>"><i class="fa-solid fa-money-bill-wave"></i> Payments</a></li>
        
        <div class="menu-header">Analytics</div>
        <li><a href="/autoshop_system/reports/sales.php" class="<?php echo ($current_dir == 'reports') ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
    </ul>
</div>