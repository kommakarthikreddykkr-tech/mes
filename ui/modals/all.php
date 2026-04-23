<?php
/* ui/modals/all.php */
/* Variables expected:
   - $current
   - $uid
*/
?>


<!-- ================= RENAME MODAL ================= -->
<div class="modal" id="renameModal">
<div class="modal-box">
    <h3>Rename</h3>

    <form method="post" action="actions.php?action=rename">
        <input type="hidden" name="id" id="renameId">
        <input type="hidden" name="parent" value="<?= $current ?>">

        <input type="text"
               name="new_name"
               id="renameInput"
               required
               placeholder="New name">

        <button class="btn primary">Rename</button>
        <button type="button"
                class="btn"
                onclick="closeModal('renameModal')">
            Cancel
        </button>
    </form>
</div>
</div>

<!-- ================= NEW FILE / FOLDER ================= -->
<div class="modal" id="newModal">
<div class="modal-box">
    <h3>Create</h3>

    <button class="btn" onclick="choose('file')">
        📄 New File
    </button>

    <button class="btn" onclick="choose('folder')">
        📁 New Folder
    </button>

    <button class="btn"
            onclick="closeModal('newModal')">
        Cancel
    </button>
</div>
</div>

<!-- ================= SAVE FILE / FOLDER ================= -->

<!-- SAVE MODAL (DEBUG) -->
<div class="modal" id="saveModal">
<div class="modal-box">
    <h3>Create</h3>

    <form method="post" action="actions.php"
          onsubmit="console.log('🟢 FORM SUBMIT TRIGGERED');">

        <input type="hidden" name="parent" value="<?= $current ?>">
        <input type="hidden" id="actionType" name="type">

        <input
            type="text"
            name="name"
            placeholder="Enter name"
            required
            oninput="console.log('✏ Name input:', this.value)"
        >

        <button type="submit" class="btn primary">
            Create
        </button>

        <button type="button" class="btn"
                onclick="closeModal('saveModal')">
            Cancel
        </button>
    </form>
</div>
</div>




<!-- ================= UPLOAD MODAL ================= -->
<div class="modal" id="uploadModal">
<div class="modal-box">

    <h3>Upload</h3>

    <!-- FILE INPUT -->
    <label class="btn" style="display:block;text-align:center;cursor:pointer">
        📄 Select File
        <input type="file"
               id="uploadFile"
               style="display:none">
    </label>

    <br>

    <!-- PROGRESS BAR -->
    <div style="width:100%;background:#e5e7eb;height:10px;border-radius:6px">
        <div id="uploadProgress"
             style="width:0%;height:10px;background:#22c55e;border-radius:6px"></div>
    </div>

    <!-- STATUS -->
    <div id="uploadStatus"
         style="margin-top:8px;font-size:14px;text-align:center;color:#374151">
        Waiting for file…
    </div>

    <br>

    <!-- ACTION BUTTONS -->
    <button class="btn primary" style="width:100%" onclick="startChunkUpload()">
        Upload
    </button>

    <button type="button"
            class="btn"
            style="width:100%;margin-top:6px"
            onclick="closeModal('uploadModal')">
        Cancel
    </button>

</div>
</div>


<!-- ================= MOVE MODAL ================= -->
<div class="modal" id="moveModal">
<div class="modal-box">
    <h3>Move to folder</h3>

    <div class="folder-nav">
        <div class="folder-item active"
             onclick="selectTarget(null,this)">
            / (Root)
        </div>

        <?php
        $folders = fs_list($uid, null);
        while ($f = $folders->fetch_assoc()):
            if ($f['type'] !== 'folder') continue;
        ?>
        <div class="folder-item"
             onclick="selectTarget(<?= $f['id'] ?>,this)">
            <?= e($f['name']) ?>
        </div>
        <?php endwhile; ?>
    </div>

    <button class="btn primary"
            style="width:100%;margin-top:10px"
            onclick="submitMove()">
        Move
    </button>

    <button class="btn"
            style="width:100%;margin-top:6px"
            onclick="closeModal('moveModal')">
        Cancel
    </button>
</div>
</div>

<!-- ================= COPY MODAL ================= -->
<div class="modal" id="copyModal">
<div class="modal-box">
    <h3>Copy to folder</h3>

    <div class="folder-nav">
        <div class="folder-item active"
             onclick="selectTarget(null,this)">
            / (Root)
        </div>

        <?php
        $folders = fs_list($uid, null);
        while ($f = $folders->fetch_assoc()):
            if ($f['type'] !== 'folder') continue;
        ?>
        <div class="folder-item"
             onclick="selectTarget(<?= $f['id'] ?>,this)">
            <?= e($f['name']) ?>
        </div>
        <?php endwhile; ?>
    </div>

    <button class="btn primary"
            style="width:100%;margin-top:10px"
            onclick="submitCopy()">
        Copy
    </button>

    <button class="btn"
            style="width:100%;margin-top:6px"
            onclick="closeModal('copyModal')">
        Cancel
    </button>
</div>
</div>




