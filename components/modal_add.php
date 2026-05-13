<?php
// components/modal_add.php
// Expected variables: $modalTitle, $formInclude
?>
<style>
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
    .modal-content { background: #fff; padding: 2rem; border-radius: 8px; width: 100%; max-width: 500px; position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
    .modal-header h2 { margin: 0; font-size: 1.25rem; }
    .btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9em; }
    .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
    .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem; }
    .modal-active { display: flex; }
</style>

<div id="addModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo htmlspecialchars($modalTitle ?? 'Add Record'); ?></h2>
            <button class="btn-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (isset($formInclude) && file_exists($formInclude)) include $formInclude; ?>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('modal-active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('modal-active'); }
</script>