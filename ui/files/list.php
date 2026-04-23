<?php
/* ui/files/list.php */
/* Variables expected:
   - $nodes
   - $current
*/
?>

<form id="bulkForm" method="post" action="actions.php">

<input type="hidden" name="parent" value="<?= $current ?>">
<input type="hidden" name="bulk_action" id="bulkAction">
<input type="hidden" name="target" id="targetInput">

<div class="files">

<?php if ($nodes->num_rows === 0): ?>
    <div class="empty">No files or folders</div>

<?php else: while ($n = $nodes->fetch_assoc()): ?>

<div class="row"
     data-id="<?= $n['id'] ?>"
     data-name="<?= e($n['name']) ?>"
     data-type="<?= $n['type'] ?>"
     onclick="handleDoubleClick(this)">

    <!-- CHECKBOX -->
    <input type="checkbox"
           name="ids[]"
           value="<?= $n['id'] ?>"
           onclick="event.stopPropagation()">

    <!-- NAME -->
    <div class="name">
        <?= $n['type'] === 'folder' ? '📁' : '📄' ?>

        <?php if ($n['type'] === 'folder'): ?>
            <a href="?f=<?= $n['id'] ?>"
               onclick="event.stopPropagation()">
                <?= e($n['name']) ?>
            </a>
        <?php else: ?>
            <?= e($n['name']) ?>
        <?php endif; ?>
    </div>

    <!-- TYPE -->
    <div><?= ucfirst($n['type']) ?></div>

    <!-- ACTION -->
    <div>
        <a href="#"
           onclick="renameItem(<?= $n['id'] ?>,'<?= e($n['name']) ?>');return false;">
            Rename
        </a>
    </div>

</div>

<?php endwhile; endif; ?>

</div>
</form>
