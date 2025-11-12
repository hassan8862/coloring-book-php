<?php
// index.php – the only PHP you need on the front-end
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AI Coloring Book Generator</title>

  <!-- ==== Preload critical CSS (fast FCP) ==== -->
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        as="style" onload="this.onload=null;this.rel='stylesheet'">
  <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        as="style" onload="this.onload=null;this.rel='stylesheet'">
  <link rel="preload"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        as="style" onload="this.onload=null;this.rel='stylesheet'">

  <noscript>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  </noscript>

  <link rel="icon"
        href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>Palette</text></svg>">

  <style>
    :root { --primary:#6366f1; --primary-dark:#4f46e5; }
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f5f7ff 0%,#e0e7ff 100%);
         min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
    .card{border:none;border-radius:1rem;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden;}
    .card-header{background:var(--primary);color:#fff;font-weight:600;text-align:center;padding:1.25rem;}
    .btn-primary{background:var(--primary);border:none;border-radius:.5rem;font-weight:600;}
    .btn-primary:hover{background:var(--primary-dark);}
    .page-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;
               background:#f1f3f5;border-radius:.5rem;text-decoration:none;color:#343a40;font-weight:500;}
    .page-btn:hover{background:#e2e6ea;}
    .spinner{width:1.2rem;height:1.2rem;border:2px solid #fff;border-top-color:transparent;
             border-radius:50%;animation:spin .8s linear infinite;display:inline-block;}
    @keyframes spin{to{transform:rotate(360deg);}}
    #downloads .col{margin-bottom:.75rem;}
  </style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">

      <div class="card">
        <div class="card-header">
          <i class="fas fa-palette me-2"></i> AI Coloring Book Generator
        </div>

        <div class="card-body p-4">
          <form id="generateForm">
            <div class="mb-3">
              <label class="form-label fw-semibold">Prompt <span class="text-muted">(e.g. “cute cats in jungle”)</span></label>
              <input type="text" class="form-control" id="prompt" required minlength="3" maxlength="150"
                     placeholder="dragon in castle, line art">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Pages (1–22)</label>
              <input type="number" class="form-control" id="pages" min="1" max="22" value="3" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="fas fa-magic me-1"></i> Generate Pages
            </button>
          </form>

          <div id="status" class="mt-4 text-center" style="display:none;">
            <div class="spinner"></div> <span class="ms-2">Preparing pages…</span>
          </div>

          <div id="downloads" class="row mt-4"></div>

          <div id="mergeSection" class="text-center mt-3" style="display:none;">
            <button id="mergeBtn" class="btn btn-outline-primary" onclick="loadPDFLib()">
              <i class="fas fa-file-pdf me-1"></i> Merge into PDF
            </button>
          </div>
        </div>
      </div>

      <!-- ==== PageSpeed Insights live badge ==== -->
      <div class="text-center mt-4">
        <a href="https://pagespeed.web.dev/report?url=https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>"
           target="_blank">
          <img src="https://img.shields.io/endpoint?url=https://pagespeed.web.dev/api/psi/<?php echo urlencode('https://'.$_SERVER['HTTP_HOST']); ?>?strategy=mobile&category=performance&category=accessibility&category=best-practices&category=seo&pretty=true"
               alt="PageSpeed Insights">
        </a>
      </div>

      <footer class="text-center mt-3 text-muted small">
        Powered by <a href="https://huggingface.co" target="_blank">Hugging Face</a> • Hosted on Vercel
      </footer>
    </div>
  </div>
</div>

<!-- Bootstrap (deferred) -->
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // ---- Lazy-load PDF-Lib only when needed ----
  let pdfLibLoaded = false;
  function loadPDFLib() {
    if (pdfLibLoaded) return;
    pdfLibLoaded = true;
    const s = document.createElement('script');
    s.src = 'https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js';
    document.head.appendChild(s);
  }

  // ---- Form handling ----
  document.getElementById('generateForm').addEventListener('submit', e => {
    e.preventDefault();
    const prompt = document.getElementById('prompt').value.trim();
    const pages  = parseInt(document.getElementById('pages').value);
    const status = document.getElementById('status');
    const downloads = document.getElementById('downloads');
    const mergeSection = document.getElementById('mergeSection');

    status.style.display = 'block';
    downloads.innerHTML = '';
    mergeSection.style.display = 'none';

    let generated = 0;
    for (let i = 1; i <= pages; i++) {
      setTimeout(() => {
        const url = `/api/generate.php?prompt=${encodeURIComponent(prompt)}&page=${i}`;

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
          mergeSection.style.display = 'block';
        }
      }, i * 300);   // staggered UI
    }
  });

  // ---- Merge into PDF (client-side) ----
  document.getElementById('mergeBtn').addEventListener('click', async () => {
    const btn = document.getElementById('mergeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Merging…';
    loadPDFLib();

    // Wait for PDFLib to load
    while (!window.PDFLib) await new Promise(r => setTimeout(r, 50));

    const { PDFDocument } = window.PDFLib;
    const pdfDoc = await PDFDocument.create();
    const links = document.querySelectorAll('#downloads a');

    for (let a of links) {
      try {
        const arrayBuffer = await fetch(a.href).then(r => r.arrayBuffer());
        const imgDoc = await PDFDocument.load(arrayBuffer);
        const copied = await pdfDoc.copyPages(imgDoc, imgDoc.getPageIndices());
        copied.forEach(p => pdfDoc.addPage(p));
      } catch (e) { console.error('Failed page', e); }
    }

    const pdfBytes = await pdfDoc.save();
    const blob = new Blob([pdfBytes], {type:'application/pdf'});
    const url = URL.createObjectURL(blob);
    const dl = document.createElement('a');
    dl.href = url;
    dl.download = 'coloring-book.pdf';
    dl.click();

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-pdf me-1"></i> Merge into PDF';
  });
</script>
</body>
</html>