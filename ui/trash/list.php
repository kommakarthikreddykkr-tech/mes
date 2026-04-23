<?php
/* ui/trash/list.php */
/* Variables expected:
   - $nodes
*/
?>

<!-- TRASH TOOLBAR -->
<div class="toolbar">

    <!-- SELECT ALL -->
    <label style="display:flex;align-items:center;gap:6px;">
        <input type="checkbox" onclick="toggleSelectAll(this)">
        Select All
    </label>

    <button class="btn" onclick="submitBulk('restore')">
        ♻ Restore
    </button>

    <button class="btn danger" onclick="submitBulk('delete')">
        🗑 Delete Permanently
    </button>

</div>

<form id="bulkForm" method="post" action="actions.php">

<input type="hidden" name="bulk_action" id="bulkAction">
<input type="hidden" name="from_view" value="trash">

<div class="files">

<?php if ($nodes->num_rows === 0): ?>
    <div class="empty">Trash is empty</div>

<?php else: while ($n = $nodes->fetch_assoc()): ?>

<div class="row">

    <!-- CHECKBOX -->
    <input type="checkbox"
           name="ids[]"
           value="<?= $n['id'] ?>">

    <!-- NAME -->
    <div class="name">
        🗑 <?= e($n['name']) ?>
    </div>

    <!-- TYPE -->
    <div><?= ucfirst($n['type']) ?></div>

    <!-- EMPTY ACTION COLUMN -->
    <div></div>

</div>

<?php endwhile; endif; ?>

</div>
</form>
