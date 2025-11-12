<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AI Coloring Book Generator</title>
  <style>
    body { font-family: Arial; max-width: 600px; margin: 40px auto; padding: 20px; }
    input, button { width: 100%; padding: 12px; margin: 8px 0; font-size: 16px; }
    button { background: #0070f3; color: white; border: none; cursor: pointer; border-radius: 6px; }
    button:hover { background: #005edc; }
    #status { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 8px; display: none; }
    .page-btn { display: inline-block; margin: 5px; padding: 10px; background: #eee; border-radius: 4px; text-decoration: none; color: #333; }
    .page-btn:hover { background: #ddd; }
  </style>
</head>
<body>
  <h1>AI Coloring Book Generator</h1>
  <p>Generate one page at a time (max 22). Each is a printable PNG.</p>

  <form id="generateForm">
    <label>Prompt (e.g., "cute animals in jungle"):</label>
    <input type="text" id="prompt" required minlength="3" maxlength="150" />

    <label>Pages (1–22):</label>
    <input type="number" id="pages" min="1" max="22" value="3" required />

    <button type="submit">Generate Pages</button>
  </form>

  <div id="status"></div>
  <div id="downloads"></div>
<button onclick="mergePDFs()">Merge into PDF</button>

<script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
<script>
async function mergePDFs() {
  const pdfDoc = await PDFLib.PDFDocument.create();
  const links = document.querySelectorAll('#downloads a');
  
  for (let link of links) {
    const arrayBuffer = await fetch(link.href).then(res => res.arrayBuffer());
    const imgDoc = await PDFLib.PDFDocument.load(arrayBuffer);
    const pages = await pdfDoc.copyPages(imgDoc, imgDoc.getPageIndices());
    pages.forEach(p => pdfDoc.addPage(p));
  }

  const pdfBytes = await pdfDoc.save();
  const blob = new Blob([pdfBytes], { type: 'application/pdf' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'coloring-book.pdf';
  a.click();
}
</script>
  <script>
    document.getElementById('generateForm').onsubmit = (e) => {
      e.preventDefault();
      const prompt = document.getElementById('prompt').value.trim();
      const pages = parseInt(document.getElementById('pages').value);
      const status = document.getElementById('status');
      const downloads = document.getElementById('downloads');
      
      status.style.display = 'block';
      status.innerHTML = 'Generating pages...';
      downloads.innerHTML = '';

      let completed = 0;
      for (let i = 1; i <= pages; i++) {
        setTimeout(() => {
          const url = `/api/generate.php?prompt=${encodeURIComponent(prompt)}&page=${i}`;
          const link = document.createElement('a');
          link.href = url;
          link.className = 'page-btn';
          link.textContent = `Download Page ${i}`;
          link.download = `page-${i}.png`;
          downloads.appendChild(link);
          
          completed++;
          if (completed === pages) {
            status.innerHTML = `Done! ${pages} pages ready.`;
          }
        }, i * 500); // Stagger clicks
      }
    };
  </script>
</body>
</html>