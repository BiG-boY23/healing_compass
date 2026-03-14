document.addEventListener('DOMContentLoaded', () => {
    const openCamera = document.getElementById('open-camera');
    const cameraContainer = document.getElementById('camera-container');
    const uploadContainer = document.getElementById('upload-container');
    const video = document.getElementById('video');
    const captureBtn = document.getElementById('capture-btn');
    const canvas = document.getElementById('canvas');
    const fileInput = document.getElementById('file-input');
    const resultsContainer = document.getElementById('results-container');
    const loadingSpinner = document.getElementById('loading-spinner');

    if (openCamera) {
        openCamera.addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                cameraContainer.style.display = 'block';
                uploadContainer.style.display = 'none';
            } catch (err) {
                alert('Could not access camera: ' + err.message);
            }
        });
    }

    if (captureBtn) {
        captureBtn.addEventListener('click', () => {
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = canvas.toDataURL('image/jpeg');
            processImage(imageData);

            // Stop camera
            const stream = video.srcObject;
            const tracks = stream.getTracks();
            tracks.forEach(track => track.stop());
            cameraContainer.style.display = 'none';
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => processImage(event.target.result);
                reader.readAsDataURL(file);
            }
        });
    }

    async function processImage(imageData) {
        uploadContainer.style.display = 'none';
        loadingSpinner.style.display = 'block';
        resultsContainer.style.display = 'none';

        try {
            const response = await fetch('api/recognize.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: imageData })
            });
            const data = await response.json();

            loadingSpinner.style.display = 'none';
            resultsContainer.style.display = 'block';

            const wrapper = document.getElementById('result-img-wrapper');
            if (wrapper) wrapper.style.backgroundImage = `url(${imageData})`;

            const nameEl = document.getElementById('result-name');
            if (nameEl) nameEl.textContent = data.name || "Unknown Plant";

            const sciEl = document.getElementById('result-scientific');
            if (sciEl) sciEl.textContent = data.scientific_name || "";

            const usesEl = document.getElementById('result-uses');
            if (usesEl) usesEl.textContent = data.uses || "Information pending.";

            const prepEl = document.getElementById('result-prep');
            if (prepEl) prepEl.textContent = data.preparation || "Consult a specialist.";

        } catch (err) {
            alert('Error processing image: ' + err.message);
            loadingSpinner.style.display = 'none';
            uploadContainer.style.display = 'block';
        }
    }
});
