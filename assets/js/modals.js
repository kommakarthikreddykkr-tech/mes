function openModal(id){
    console.log("📂 openModal:", id);
    const m = document.getElementById(id);
    if (!m) {
        console.error("❌ Modal not found:", id);
        return;
    }
    m.style.display = "flex";
}

function closeModal(id){
    console.log("📁 closeModal:", id);
    const m = document.getElementById(id);
    if (m) m.style.display = "none";
}

function choose(type){
    console.log("🟡 choose() called with:", type);

    const input = document.getElementById("actionType");
    if (!input) {
        console.error("❌ actionType input NOT FOUND");
        return;
    }

    input.value = type;
    console.log("✅ actionType set to:", input.value);

    closeModal("newModal");
    openModal("saveModal");
}
