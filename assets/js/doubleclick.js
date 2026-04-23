function handleDoubleClick(row) {
    const id = row.dataset.id;
    const name = row.dataset.name;

    if (!id || !name) return;

    // These helpers are defined in PHP, we mirror logic by extension
    const ext = name.split('.').pop().toLowerCase();

    const editable = ['txt','md','html','css','js','php','json'];
    const viewable = ['jpg','jpeg','png','gif','webp','pdf','mp4','mp3'];

    if (editable.includes(ext)) {
        window.location.href = `edit.php?id=${id}`;
    } else if (viewable.includes(ext)) {
        window.location.href = `view.php?id=${id}`;
    }
}
