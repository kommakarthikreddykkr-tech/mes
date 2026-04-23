function renameItem(id, name) {
    document.getElementById("renameId").value = id;
    document.getElementById("renameInput").value = name;
    openModal("renameModal");
}
