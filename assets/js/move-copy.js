let selectedTarget = null;

function selectTarget(id, el) {
    selectedTarget = id;

    document
        .querySelectorAll('.folder-item')
        .forEach(i => i.classList.remove('active'));

    el.classList.add('active');
}

function submitMove() {
    submitMoveCopy('move');
}

function submitCopy() {
    submitMoveCopy('copy');
}

function submitMoveCopy(action) {
    if (!document.querySelector('input[name="ids[]"]:checked')) {
        alert("Select at least one item");
        return;
    }

    const form = document.getElementById("bulkForm");
    const bulkAction = document.getElementById("bulkAction");
    const targetInput = document.getElementById("targetInput");

    if (!form || !bulkAction || !targetInput) return;

    bulkAction.value = action;
    targetInput.value = selectedTarget ?? "";

    form.submit();
}
