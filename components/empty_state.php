<?php
// components/empty_state.php
$emptyMessage = isset($emptyMessage) ? $emptyMessage : "No records found.";
?>
<style>
    .empty-state { background: #fff; padding: 3rem; text-align: center; border-radius: 8px; border: 1px dashed #d1d5db; color: #6b7280; }
    .empty-state i { font-size: 3rem; color: #9ca3af; margin-bottom: 1rem; }
    .empty-state p { font-size: 1.1rem; margin: 0; }
</style>
<div class="empty-state">
    <i class="fa-solid fa-folder-open"></i>
    <p><?php echo htmlspecialchars($emptyMessage); ?></p>
</div>