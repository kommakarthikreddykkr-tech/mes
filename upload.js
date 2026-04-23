<script>
const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

async function uploadFile(file, parentId) {

    const progressBar = document.getElementById("progress");
    const statusText  = document.getElementById("status");

    let offset = 0;
    let chunkIndex = 0;

    statusText.innerText = "Starting upload...";

    while (offset < file.size) {

        const chunk = file.slice(offset, offset + CHUNK_SIZE);

        const formData = new FormData();
        formData.append("chunk", chunk);
        formData.append("name", file.name);
        formData.append("size", file.size);
        formData.append("offset", offset);
        formData.append("index", chunkIndex);
        formData.append("parent", parentId ?? "");

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
        chunkIndex++;

        const percent = Math.floor((offset / file.size) * 100);
        progressBar.style.width = percent + "%";
        statusText.innerText = `Uploading... ${percent}%`;
    }

    statusText.innerText = "Upload complete ✅";
}
</script>
