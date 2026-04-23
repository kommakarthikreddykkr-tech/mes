<?php
/* ui/files/toolbar.php */
/* Variables expected:
   - $current
*/
?>

<div class="toolbar">

    <!-- SELECT ALL -->
    <label style="display:flex;align-items:center;gap:6px;">
        <input type="checkbox" onclick="toggleSelectAll(this)">
        Select
    </label>

    <!-- ACTION BUTTONS -->
    <button class="btn primary" onclick="openModal('newModal')">
        ＋ New
    </button>

    <button class="btn" onclick="openModal('uploadModal')">
        ⬆ Upload
    </button>

    <button class="btn" onclick="openModal('moveModal')">
        📂 Move
    </button>

    <button class="btn" onclick="openModal('copyModal')">
        📄 Copy
    </button>
    <button class="btn" onclick="submitBulk('download')">⬇ Download</button>

    <button class="btn danger" onclick="submitBulk('trash')">
        Delete
    </button>

</div>
