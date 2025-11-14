<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Coloring Book Generator</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root { --primary: #6366f1; --primary-dark: #4f46e5; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f7ff 0%, #e0e7ff 100%); min-height: 100vh; padding: 2rem 1rem; }
    .card { border: none; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    .card-header { background: var(--primary); color: white; font-weight: 600; }
    .nav-tabs .nav-link.active { background: var(--primary); color: white; }
    .drop-zone { border: 3px dashed #bbb; border-radius: 1rem; padding: 3rem; text-align: center; transition: all .3s; background: #fafaff; }
    .drop-zone.dragover { border-color: var(--primary); background: #eef1ff; }
    #uploadPreview { max-width: 100%; max-height: 600px; margin: 1rem 0; border-radius: .75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .spinner { width: 1.5rem; height: 1.5rem; border: 3px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9 col-md-10">

        <div class="card">
          <div class="card-header text-center">
            <i class="fas fa-palette me-2"></i> AI Coloring Book Generator
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs mt-4 px-4" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" data-bs-toggle="tab" href="#tab-prompt"><i class="fas fa-magic me-1"></i> Generate from Text</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-bs-toggle="tab" href="#tab-upload"><i class="fas fa-image me-1"></i> Upload & Convert</a>
            </li>
          </ul>

          <div class="tab-content p-4">
            <!-- TAB 1: Original Prompt Generator (unchanged) -->
            <div class="tab-pane fade show active" id="tab-prompt">
              <form id="generateForm">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Prompt</label>
                  <input type="text" class="form-control" id="prompt" required placeholder="dragon in castle, line art">
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Number of pages (1–22)</label>
                  <input type="number" class="form-control" id="pages" min="1" max="22" value="3" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                  <i class="fas fa-magic me-1"></i> Generate Pages
                </button>
              </form>

              <div id="status" class="mt-4 text-center" style="display:none;"></div>
              <div id="downloads" class="row mt-4"></div>
            </div>

            <!-- TAB 2: Upload Image → Convert to Coloring Page -->
            <div class="tab-pane fade" id="tab-upload">
              <div class="drop-zone" id="dropZone">
                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                <p class="lead">Drag & drop an image here or click to select</p>
                <input type="file" id="imageInput" accept="image/*" style="display:none;">
              </div>

              <div id="processing" class="text-center my-4" style="display:none;">
                <div class="spinner"></div>
                <span class="ms-2 h5">Converting to coloring page… (takes ~8–15 sec)</span>
              </div>

              <div id="result" class="text-center" style="display:none;">
                <h4 class="mb-3">Your Coloring Page is Ready!</h4>
                <img id="uploadPreview" alt="Coloring page preview">
                <div class="mt-3">
                  <a id="downloadBtn" class="btn btn-success btn-lg">
                    <i class="fas fa-download me-2"></i> Download PNG
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <footer class="text-center mt-4 text-muted small">
          Built with <i class="fas fa-heart text-danger"></i> • Powered by Hugging Face
        </footer>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ==================== ORIGINAL PROMPT GENERATOR (unchanged) ====================
    const form = document.getElementById('generateForm');
    const status = document.getElementById('status');
    const downloads = document.getElementById('downloads');

    form.addEventListener('submit', e => {
      e.preventDefault();
      const prompt = document.getElementById('prompt').value.trim();
      const pages = parseInt(document.getElementById('pages').value);

      status.style.display = 'block';
      status.innerHTML = '<div class="spinner"></div><span class="ms-2">Preparing pages…</span>';
      downloads.innerHTML = '';

      let generated = 0;
      for (let i = 1; i <= pages; i++) {
        setTimeout(() => {
          const url = `/api/generate.php?prompt=${encodeURIComponent(prompt)}&page=${i}`;

          const col = document.createElement('div');
          col.className = 'col-12 col-sm-6 col-md-4';
          const card = document.createElement('div');
          card.className = 'card border-0 shadow-sm';
          const img = document.createElement('img');
          img.src = url;
          img.className = 'card-img-top';
          img.loading = 'lazy';
          const body = document.createElement('div');
          body.className = 'card-body text-center p-2';
          const btn = document.createElement('a');
          btn.href = url;
          btn.download = `coloring-page-${i}.png`;
          btn.className = 'btn btn-outline-primary btn-sm';
          btn.innerHTML = `<i class="fas fa-download"></i> Page ${i}`;
          body.appendChild(btn);
          card.appendChild(img);
          card.appendChild(body);
          col.appendChild(card);
          downloads.appendChild(col);

          generated++;
          if (generated === pages) {
            status.innerHTML = `<i class="fas fa-check text-success"></i> All ${pages} pages ready!`;
          }
        }, i * 450);
      }
    });

    // ==================== NEW: UPLOAD & CONVERT TAB ====================
    const dropZone = document.getElementById('dropZone');
    const imageInput = document.getElementById('imageInput');
    const processing = document.getElementById('processing');
    const result = document.getElementById('result');
    const uploadPreview = document.getElementById('uploadPreview');
    const downloadBtn = document.getElementById('downloadBtn');

    // Click to open file picker
    dropZone.addEventListener('click', () => imageInput.click());

    // Drag & drop effects
    ['dragover', 'dragenter'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('dragover')));
    ['dragleave', 'dragend', 'drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('dragover')));

    // Handle file selection
    imageInput.addEventListener('change', handleFile);
    dropZone.addEventListener('drop', e => {
      e.preventDefault();
      if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
    });

    function handleFile(file) {
      if (!file || !file.type.startsWith('image/')) return alert('Please select an image file');

      const reader = new FileReader();
      reader.onload = () => {
        processing.style.display = 'block';
        result.style.display = 'none';

        // Send to new PHP endpoint
        const formData = new FormData();
        formData.append('image', file);

        fetch('/api/image-to-coloring.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.blob())
        .then(blob => {
          const url = URL.createObjectURL(blob);
          uploadPreview.src = url;
          downloadBtn.href = url;
          downloadBtn.download = 'coloring-page.png';
          processing.style.display = 'none';
          result.style.display = 'block';
        })
        .catch(err => {
          console.error(err);
          alert('Conversion failed. Try another image.');
          processing.style.display = 'none';
        });
      };
      reader.readAsDataURL(file);
    }
  </script>
</body>
</html>