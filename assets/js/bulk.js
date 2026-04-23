function toggleSelectAll(master) {
    document
        .querySelectorAll('input[name="ids[]"]')
        .forEach(cb => cb.checked = master.checked);
}

function submitBulk(action) {
    const bulkAction = document.getElementById("bulkAction");
    if (!bulkAction) return;

    bulkAction.value = action;

    const form = document.getElementById("bulkForm");
    if (!form) return;

    form.submit();
}
