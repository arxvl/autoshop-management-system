<?php
// components/search.php
$searchQuery = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';
$placeholder = isset($searchPlaceholder) ? $searchPlaceholder : "Search...";
?>
<style>
    .search-container { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    .search-form { display: flex; gap: 0.5rem; }
    .search-input { padding: 0.6rem 1rem; border: 1px solid #d1d5db; border-radius: 4px; width: 300px; outline: none; }
    .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
    .btn-search { background: var(--accent); color: white; border: none; padding: 0.6rem 1rem; border-radius: 4px; cursor: pointer; }
    .btn-search:hover { background: #2563eb; }
</style>

<div class="search-container">
    <form method="GET" class="search-form">
        <input type="text" name="q" class="search-input" value="<?php echo $searchQuery; ?>" placeholder="<?php echo $placeholder; ?>">
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($searchQuery): ?>
            <a href="index.php" style="padding: 0.6rem; color: #6b7280; text-decoration: none;">Clear</a>
        <?php endif; ?>
    </form>
    
    <?php if (isset($showAddButton) && $showAddButton): ?>
        <button onclick="openModal('addModal')" style="background: var(--success); color: white; border: none; padding: 0.6rem 1rem; border-radius: 4px; cursor: pointer;">
            <i class="fa-solid fa-plus"></i> Add New
        </button>
    <?php endif; ?>
</div>