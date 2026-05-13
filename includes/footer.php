<?php
// includes/footer.php
?>
    </div> 
</div> 

<script>
    // --- Global Modal Functions ---
    // This ensures that Add, Edit, and Delete modals work on EVERY page
    function openModal(id) { 
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('modal-active'); 
        } else {
            console.error("Modal ID '" + id + "' not found.");
        }
    }

    function closeModal(id) { 
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('modal-active'); 
        }
    }

    // --- Global Page Initialization ---
    document.addEventListener('DOMContentLoaded', () => {
        
        // 1. Global Alert Auto-Hide
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300); // Wait for fade transition
            }, 4000);
        });

        // 2. Global Status Badge Renderer
        // Converts plain text statuses in any table into colorful CSS badges
        const badgeStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled', 'Paid', 'Partial', 'Refunded'];
        const cells = document.querySelectorAll("td");
        
        cells.forEach(cell => {
            const text = cell.textContent.trim();
            if (badgeStatuses.includes(text)) {
                const className = 'status-' + text.toLowerCase().replace(' ', '');
                cell.innerHTML = `<span class="badge ${className}">${text}</span>`;
            }
        });
        
    });
</script>

</body>
</html>