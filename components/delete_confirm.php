<?php
// components/delete_confirm.php
// Expected variables: $deleteActionUrl
?>
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem;"></i>
        <h2 style="margin-top: 0;">Are you sure?</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">This action cannot be undone. This will permanently delete the record.</p>
        
        <form method="POST" action="<?php echo htmlspecialchars($deleteActionUrl ?? ''); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="delete_id" id="delete_id" value="">
            <div style="display: flex; justify-content: center; gap: 1rem;">
                <button type="button" onclick="closeModal('deleteModal')" style="padding: 0.6rem 1rem; border: 1px solid #d1d5db; background: #fff; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 0.6rem 1rem; background: var(--danger); color: #fff; border: none; border-radius: 4px; cursor: pointer;">Yes, Delete it</button>
            </div>
        </form>
    </div>
</div>

<script>
    function confirmDelete(id) {
        document.getElementById('delete_id').value = id;
        openModal('deleteModal');
    }
</script>