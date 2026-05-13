<?php
// ============================================================
// components/table.php
// Expected variables:
// $tableHeaders (array)
// $tableData (array)
// $primaryKey (string)
// ============================================================

// Prevent undefined variable errors
$tableHeaders = isset($tableHeaders) && is_array($tableHeaders) ? $tableHeaders : [];
$tableData    = isset($tableData) && is_array($tableData) ? $tableData : [];
$primaryKey   = isset($primaryKey) ? $primaryKey : 'id';
?>

<style>
    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }

    .data-table th,
    .data-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .data-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        font-size: 0.85rem;
    }

    .data-table tr:hover {
        background: #f3f4f6;
    }

    .action-btns {
        display: flex;
        gap: 0.5rem;
    }

    .btn-edit,
    .btn-delete {
        cursor: pointer;
        background: none;
        border: none;
        font-size: 1rem;
    }

    .btn-edit {
        color: var(--accent, #2563eb);
    }

    .btn-delete {
        color: var(--danger, #dc2626);
    }

    .empty-state {
        padding: 2rem;
        text-align: center;
        background: #fff;
        border-radius: 8px;
        color: #6b7280;
    }
</style>

<?php if (empty($tableData)): ?>

    <div class="empty-state">
        No records found.
    </div>

<?php else: ?>

    <table class="data-table">
        <thead>
            <tr>

                <?php foreach ($tableHeaders as $key => $header): ?>
                    <th>
                        <?php echo htmlspecialchars($header); ?>
                    </th>
                <?php endforeach; ?>

                <th>Actions</th>

            </tr>
        </thead>

        <tbody>

            <?php foreach ($tableData as $row): ?>

                <tr>

                    <?php foreach ($tableHeaders as $key => $header): ?>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                isset($row[$key]) ? $row[$key] : ''
                            );
                            ?>
                        </td>

                    <?php endforeach; ?>

                    <td>
                        <div class="action-btns">

                            <button
                                type="button"
                                class="btn-edit"
                                onclick="openEditModal('<?php echo htmlspecialchars($row[$primaryKey] ?? ''); ?>')"
                            >
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <button
                                type="button"
                                class="btn-delete"
                                onclick="confirmDelete('<?php echo htmlspecialchars($row[$primaryKey] ?? ''); ?>')"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>

                        </div>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>
    </table>

<?php endif; ?>