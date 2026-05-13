<?php
// components/modal_edit.php
?>
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo htmlspecialchars($editModalTitle ?? 'Edit Record'); ?></h2>
            <button class="btn-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body" id="editModalBody">
            <?php if (isset($editFormInclude) && file_exists($editFormInclude)) include $editFormInclude; ?>
        </div>
    </div>
</div>

<script>
    function openEditModal(id) {
        // Here you would typically make an AJAX fetch call to get the record data
        // For now, it just opens the modal and logs the ID
        console.log("Editing record:", id);
        document.getElementById('editModal').classList.add('modal-active');
        
        // If you have a hidden input for ID in your edit form, set it:
        const idInput = document.getElementById('edit_record_id');
        if(idInput) idInput.value = id;
    }
</script>