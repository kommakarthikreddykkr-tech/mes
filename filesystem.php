<?php
require_once "core.php";

/* =====================================================
   FILE SYSTEM CORE
   ===================================================== */

/* ---------- LIST FILES / FOLDERS ---------- */
function fs_list($uid, $parent = null) {
    global $conn;

    if ($parent === null) {
        $stmt = $conn->prepare(
            "SELECT * FROM fs_nodes
             WHERE uid=? AND parent_id IS NULL AND is_deleted=0
             ORDER BY type DESC, name ASC"
        );
        $stmt->bind_param("i", $uid);
    } else {
        $stmt = $conn->prepare(
            "SELECT * FROM fs_nodes
             WHERE uid=? AND parent_id=? AND is_deleted=0
             ORDER BY type DESC, name ASC"
        );
        $stmt->bind_param("ii", $uid, $parent);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    return $res;
}

/* ---------- LIST TRASH ---------- */
function fs_trash_list($uid) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT * FROM fs_nodes
         WHERE uid=? AND is_deleted=1
         ORDER BY updated_at DESC"
    );
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    return $res;
}

/* ---------- GET PARENT ---------- */
function fs_parent($uid, $id) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT parent_id FROM fs_nodes WHERE id=? AND uid=?"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['parent_id'] ?? null;
}

/* ---------- CHECK NAME EXISTS ---------- */
function fs_exists($uid, $parent, $name) {
    global $conn;

    if ($parent === null) {
        $stmt = $conn->prepare(
            "SELECT id FROM fs_nodes
             WHERE uid=? AND parent_id IS NULL AND name=? AND is_deleted=0"
        );
        $stmt->bind_param("is", $uid, $name);
    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM fs_nodes
             WHERE uid=? AND parent_id=? AND name=? AND is_deleted=0"
        );
        $stmt->bind_param("iis", $uid, $parent, $name);
    }

    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

/* ---------- CREATE FOLDER ---------- */
function fs_create_folder($uid, $parent, $name) {
    global $conn;

    if ($parent === 0 || $parent === '') {
        $parent = null;
    }

    // Check existing
    if ($parent === null) {
        $stmt = $conn->prepare(
            "SELECT id FROM fs_nodes
             WHERE uid=? AND parent_id IS NULL AND name=? AND type='folder' AND is_deleted=0"
        );
        $stmt->bind_param("is", $uid, $name);
    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM fs_nodes
             WHERE uid=? AND parent_id=? AND name=? AND type='folder' AND is_deleted=0"
        );
        $stmt->bind_param("iis", $uid, $parent, $name);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    // Create
    $stmt = $conn->prepare(
        "INSERT INTO fs_nodes (uid, parent_id, name, type)
         VALUES (?, ?, ?, 'folder')"
    );
    $stmt->bind_param("iis", $uid, $parent, $name);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    logUserAction("Created folder: $name");

    return (int)$id;
}

/* ---------- RENAME ---------- */
function fs_rename($uid, $id, $newName) {
    global $conn;

    $newName = trim($newName);
    if ($newName === "") {
        return "Invalid name";
    }

    // Get parent
    $stmt = $conn->prepare(
        "SELECT parent_id FROM fs_nodes WHERE id=? AND uid=?"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return "Item not found";
    }

    if (fs_exists($uid, $row['parent_id'], $newName)) {
        return "Name already exists";
    }

    $stmt = $conn->prepare(
        "UPDATE fs_nodes SET name=? WHERE id=? AND uid=?"
    );
    $stmt->bind_param("sii", $newName, $id, $uid);
    $stmt->execute();
    $stmt->close();

    logUserAction("Renamed item to: $newName");
    return true;
}

/* ---------- MOVE ---------- */
function fs_move($uid, $id, $target) {
    global $conn;

    if ($target === '' || $target === 0) {
        $target = null;
    }

    $stmt = $conn->prepare(
        "UPDATE fs_nodes SET parent_id=? WHERE id=? AND uid=?"
    );
    $stmt->bind_param("iii", $target, $id, $uid);
    $stmt->execute();
    $stmt->close();

    logUserAction("Moved item ID $id");
    return true;
}

/* ---------- COPY ---------- */
function fs_copy($uid, $id, $target) {
    global $conn;

    if ($target === '' || $target === 0) {
        $target = null;
    }

    // Get source
    $stmt = $conn->prepare(
        "SELECT * FROM fs_nodes WHERE id=? AND uid=?"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $node = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$node) return false;

    // Duplicate node
    $stmt = $conn->prepare(
        "INSERT INTO fs_nodes
         (uid, parent_id, name, type, storage_id, size)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iissii",
        $uid,
        $target,
        $node['name'],
        $node['type'],
        $node['storage_id'],
        $node['size']
    );
    $stmt->execute();
    $stmt->close();

    logUserAction("Copied item ID $id");
    return true;
}

/* ---------- TRASH ---------- */
function fs_trash($uid, $id) {
    global $conn;

    $stmt = $conn->prepare(
        "UPDATE fs_nodes SET is_deleted=1 WHERE id=? AND uid=?"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();

    logUserAction("Moved item ID $id to trash");
    return true;
}

/* ---------- RESTORE ---------- */
function fs_restore($uid, $id) {
    global $conn;

    $stmt = $conn->prepare(
        "UPDATE fs_nodes SET is_deleted=0 WHERE id=? AND uid=?"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();

    logUserAction("Restored item ID $id");
    return true;
}

/* ---------- DELETE PERMANENT ---------- */
function fs_delete_permanent($uid, $id) {
    global $conn;

    $stmt = $conn->prepare(
        "DELETE FROM fs_nodes WHERE id=? AND uid=?"
    );
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();

    logUserAction("Permanently deleted item ID $id");
    return true;
}

/* ---------- USER LOGS ---------- */
function fs_logs($uid) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT action, ip, created_at
         FROM user_action_logs
         WHERE uid=?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    return $res;
}
