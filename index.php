<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Coloring Book Generator</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Google Font – Inter (clean, modern) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary: #6366f1;
      --primary-dark: #4f46e5;
      --gray-100: #f8f9fa;
      --gray-200: #e9ecef;
      --gray-800: #343a40;
    }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f5f7ff 0%, #e0e7ff 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    .card-header {
      background: var(--primary);
      color: white;
      font-weight: 600;
      text-align: center;
      padding: 1.25rem;
    }
    .btn-primary {
      background: var(--primary);
      border: none;
      border-radius: .5rem;
      font-weight: 600;
      transition: background .2s;
    }
    .btn-primary:hover { background: var(--primary-dark); }
    .page-btn {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .5rem 1rem;
      background: var(--gray-100);
      border-radius: .5rem;
      text-decoration: none;
      color: var(--gray-800);
      font-weight: 500;
      transition: background .2s;
    }
    .page-btn:hover { background: var(--gray-200); }
    .spinner {
      width: 1.2rem;
      height: 1.2rem;
      border: 2px solid #fff;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      display: inline-block;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    #downloads .col { margin-bottom: .75rem; }
    footer { margin-top: 2rem; font-size: .85rem; color: #6c757d; }
  </style>
</head>

<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-6 col-md-8">

        <div class="card">
          <div class="card-header">
            <i class="fas fa-palette me-2"></i>
            AI Coloring Book Generator
          </div>

          <div class="card-body p-4">
            <form id="generateForm">
              <div class="mb-3">
                <label class="form-label fw-semibold">Prompt <span class="text-muted">(e.g. “cute cats in jungle”)</span></label>
                <input type="text" class="form-control" id="prompt" required minlength="3" maxlength="150"
                       placeholder="dragon in castle, line art">
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Number of pages (1–22)</label>
                <input type="number" class="form-control" id="pages" min="1" max="22" value="3" required>
              </div>

              <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-magic me-1"></i> Generate Pages
              </button>
            </form>

            <div id="status" class="mt-4 text-center" style="display:none;">
              <div class="spinner"></div>
              <span class="ms-2">Preparing pages…</span>
            </div>

            <div id="downloads" class="row mt-4"></div>

            <!-- <div id="mergeSection" class="text-center mt-3" style="display:none;">
              <button id="mergeBtn" class="btn btn-outline-primary">
                <i class="fas fa-file-pdf me-1"></i> Merge All into One PDF
              </button>
            </div> -->
          </div>
        </div>

        <footer class="text-center">
          Built with <i class="fas fa-heart text-danger"></i> on Vercel •
          <a href="https://huggingface.co" target="_blank" class="text-decoration-none">Powered by Hugging Face</a>
        </footer>
      </div>
    </div>
  </div>

  <!-- Bootstrap + PDF-Lib (client-side merge) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>

  <script>
    const form = document.getElementById('generateForm');
    const status = document.getElementById('status');
    const downloads = document.getElementById('downloads');
    // const mergeSection = document.getElementById('mergeSection');
    // const mergeBtn = document.getElementById('mergeBtn');

    form.addEventListener('submit', e => {
      e.preventDefault();
      const prompt = document.getElementById('prompt').value.trim();
      const pages = parseInt(document.getElementById('pages').value);

      status.style.display = 'block';
      downloads.innerHTML = '';
      // mergeSection.style.display = 'none';

      let generated = 0;
      const urls = [];

      for (let i = 1; i <= pages; i++) {
        setTimeout(() => {
          const url = `/api/generate.php?prompt=${encodeURIComponent(prompt)}&page=${i}`;
          urls.push(url);

          const col = document.createElement('div');
          col.className = 'col-12 col-sm-6 col-md-4';
          const link = document.createElement('a');
          link.href = url;
          link.className = 'page-btn w-100 text-center';
          link.target = '_blank';
          link.download = `page-${i}.png`;
          link.innerHTML = `<i class="fas fa-image"></i> Page ${i}`;
          col.appendChild(link);
          downloads.appendChild(col);

          generated++;
          if (generated === pages) {
            status.innerHTML = `<i class="fas fa-check text-success"></i> All ${pages} pages ready!`;
            // mergeSection.style.display = 'block';
          }
        }, i * 400); // staggered UI update
      }
    });

    // ---------- Merge into single PDF (client-side) ----------
    // mergeBtn.addEventListener('click', async () => {
    //   mergeBtn.disabled = true;
    //   mergeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Merging…';

    //   const pdfDoc = await PDFLib.PDFDocument.create();

    //   const links = downloads.querySelectorAll('a');
    //   for (let a of links) {
    //     try {
    //       const arrayBuffer = await fetch(a.href).then(r => r.arrayBuffer());
    //       const imgDoc = await PDFLib.PDFDocument.load(arrayBuffer);
    //       const copiedPages = await pdfDoc.copyPages(imgDoc, imgDoc.getPageIndices());
    //       copiedPages.forEach(p => pdfDoc.addPage(p));
    //     } catch (err) {
    //       console.error('Failed to load a page', err);
    //     }
    //   }

    //   const pdfBytes = await pdfDoc.save();
    //   const blob = new Blob([pdfBytes], { type: 'application/pdf' });
    //   const url = URL.createObjectURL(blob);
    //   const dl = document.createElement('a');
    //   dl.href = url;
    //   dl.download = 'coloring-book.pdf';
    //   dl.click();

    //   mergeBtn.disabled = false;
    //   mergeBtn.innerHTML = '<i class="fas fa-file-pdf me-1"></i> Merge All into One PDF';
    // });
  </script>
</body>
</html>