<?php
// ============================================================
// components/pagination.php
// Expected variables: $totalPages, $currentPage
// Optional variable: $searchQuery
// ============================================================

// Prevent undefined variable errors
$totalPages  = isset($totalPages) ? (int)$totalPages : 1;
$currentPage = isset($currentPage) ? (int)$currentPage : 1;
$searchQuery = isset($searchQuery) ? $searchQuery : '';

// Don't show pagination if only 1 page
if ($totalPages <= 1) {
    return;
}
?>

<style>
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 1.5rem;
        list-style: none;
        padding: 0;
    }

    .pagination li {
        display: inline-block;
    }

    .pagination a {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        text-decoration: none;
        color: #374151;
        background: #fff;
        transition: 0.2s;
    }

    .pagination a:hover {
        background: #f3f4f6;
    }

    .pagination a.active {
        background: var(--accent, #2563eb);
        color: #fff;
        border-color: var(--accent, #2563eb);
    }

    .pagination a.disabled {
        color: #9ca3af;
        pointer-events: none;
        background: #f9fafb;
    }
</style>

<ul class="pagination">

    <!-- Previous Button -->
    <li>
        <a
            href="?page=<?php echo max(1, $currentPage - 1); ?>&q=<?php echo urlencode($searchQuery); ?>"
            class="<?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>"
        >
            &laquo; Prev
        </a>
    </li>

    <!-- Page Numbers -->
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li>
            <a
                href="?page=<?php echo $i; ?>&q=<?php echo urlencode($searchQuery); ?>"
                class="<?php echo ($currentPage == $i) ? 'active' : ''; ?>"
            >
                <?php echo $i; ?>
            </a>
        </li>
    <?php endfor; ?>

    <!-- Next Button -->
    <li>
        <a
            href="?page=<?php echo min($totalPages, $currentPage + 1); ?>&q=<?php echo urlencode($searchQuery); ?>"
            class="<?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>"
        >
            Next &raquo;
        </a>
    </li>

</ul>