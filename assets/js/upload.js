const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

async function startChunkUpload() {

    const fileInput = document.getElementById("uploadFile");
    const file = fileInput.files[0];

    if (!file) {
        alert("Please select a file");
        return;
    }

    const progressBar = document.getElementById("uploadProgress");
    const statusText  = document.getElementById("uploadStatus");

    let offset = 0;
    let index  = 0;

    statusText.innerText = "Starting upload…";

    while (offset < file.size) {

        const chunk = file.slice(offset, offset + CHUNK_SIZE);

        const formData = new FormData();
        formData.append("chunk", chunk);
        formData.append("name", file.name);
        formData.append("size", file.size);
        formData.append("offset", offset);
        formData.append("index", index);
        formData.append("parent", CURRENT_FOLDER_ID ?? "");

        const res = await fetch("upload_chunk.php", {
            method: "POST",
            body: formData
        });

        const json = await res.json();

        if (!json.success) {
            statusText.innerText = "Upload failed: " + json.error;
            return;
        }

        offset += chunk.size;
        index++;

        const percent = Math.floor((offset / file.size) * 100);
        progressBar.style.width = percent + "%";
        statusText.innerText = "Uploading… " + percent + "%";
    }

    statusText.innerText = "Upload complete ✅";

    setTimeout(() => {
        location.reload();
    }, 1200);
}
