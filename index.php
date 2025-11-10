<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AI Coloring Book Generator</title>
  <style>
    body { font-family: Arial; max-width: 600px; margin: 40px auto; padding: 20px; }
    input, button, select { width: 100%; padding: 12px; margin: 8px 0; font-size: 16px; }
    button { background: #0070f3; color: white; border: none; cursor: pointer; }
    button:hover { background: #005edc; }
    #status { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 8px; display: none; }
  </style>
</head>
<body>
  <h1>AI Coloring Book Generator</h1>
  <p>Enter a theme and generate a printable coloring book (max 22 pages).</p>

  <form id="generateForm">
    <label>Prompt (e.g., "cute animals in jungle"):</label>
    <input type="text" name="prompt" required minlength="3" maxlength="150" />

    <label>Pages (1–22):</label>
    <input type="number" name="pages" min="1" max="22" value="5" required />

    <button type="submit">Generate PDF</button>
  </form>

  <div id="status"></div>

  <script>
    document.getElementById('generateForm').onsubmit = async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      const status = document.getElementById('status');
      status.style.display = 'block';
      status.innerHTML = 'Generating... This may take 1-3 minutes.';

      try {
        const res = await fetch('/api/generate.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          status.innerHTML = `Success! <a href="${data.download_url}" download>Download PDF</a>`;
        } else {
          status.innerHTML = 'Error: ' + (data.error || 'Unknown');
        }
      } catch (err) {
        status.innerHTML = 'Network error. Try again.';
      }
    };
  </script>
</body>
</html>