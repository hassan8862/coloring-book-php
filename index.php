<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ColorBot • AI Coloring Book Creator</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    :root { --primary: #10a37f; --bg: #0d1117; --chat-bg: #161b22; --text: #c9d1d9; --border: #30363d; --user-bg: #238636; }
    body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; height: 100vh; margin: 0; display: flex; flex-direction: column; overflow: hidden; }
    #chat-container { flex: 1; overflow-y: auto; padding: 2rem 1rem; max-width: 900px; margin: 0 auto; width: 100%; }
    .message { margin: 1rem 0; display: flex; gap: 14px; animation: fadeIn 0.4s ease-out; }
    .avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; background: var(--primary); color: white; }
    .user .avatar { background: var(--user-bg); }
    .bubble { max-width: 85%; padding: 1rem 1.4rem; border-radius: 20px; line-height: 1.6; word-wrap: break-word; }
    .user .bubble { background: var(--user-bg); color: white; border-bottom-right-radius: 6px; }
    .bot .bubble { background: #21262d; border: 1px solid var(--border); border-bottom-left-radius: 6px; }
    .image-result { max-width: 100%; width: 460px; border-radius: 16px; margin: 16px 0; box-shadow: 0 12px 40px rgba(0,0,0,0.6); border: 2px solid #333; }
    .image-result:hover { transform: scale(1.02); transition: 0.2s; }
    #input-area { padding: 1rem; background: var(--bg); border-top: 1px solid var(--border); }
    #prompt-input { background: #0d1117; border: 1px solid var(--border); color: white; border-radius: 24px; padding: 14px 20px; font-size: 1rem; width: 100%; max-width: 900px; margin: 0 auto; display: block; }
    #prompt-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.2); }
    .send-btn { position: fixed; bottom: 24px; right: 24px; width: 56px; height: 56px; border-radius: 50%; background: var(--primary); color: white; border: none; font-size: 1.4rem; box-shadow: 0 8px 30px rgba(0,0,0,0.5); cursor: pointer; }
    .send-btn:hover { transform: scale(1.1); background: #0d8f6f; }
    .send-btn:disabled { background: #666; cursor: not-allowed; transform: none; }
    .loader { display: inline-block; width: 28px; height: 28px; border: 3px solid #333; border-radius: 50%; border-top-color: var(--primary); animation: spin 1s ease-in-out infinite; margin-right: 10px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    .welcome h1 { font-size: 2.8rem; background: linear-gradient(90deg, #10a37f, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .example-btn { background: #21262d; border: 1px solid var(--border); color: #c9d1d9; padding: 12px 20px; border-radius: 12px; cursor: pointer; }
    .example-btn:hover { background: var(--primary); color: white; }
  </style>
</head>
<body>

  <div id="chat-container">
    <div class="welcome text-center mt-5">
      <h1>ColorBot</h1>
      <p class="lead">Generate beautiful printable coloring books — just type your idea!</p>
      <div class="examples d-flex flex-wrap gap-3 justify-content-center mt-4">
        <div class="example-btn" onclick="sendExample('A cute baby dragon')">Baby dragon</div>
        <div class="example-btn" onclick="sendExample('Magical unicorn forest')">Unicorn forest</div>
        <div class="example-btn" onclick="sendExample('A 32-page adventure of a brave little turtle')">32-page turtle story</div>
      </div>
    </div>
  </div>

  <div id="input-area">
    <input type="text" id="prompt-input" placeholder="Describe your coloring page or full storybook..." autocomplete="off" />
  </div>

  <button id="send-btn" class="send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>

  <script>
    const chat = document.getElementById('chat-container');
    const input = document.getElementById('prompt-input');
    const sendBtn = document.getElementById('send-btn');
    let storyScenes = [];
    let totalPages = 0;
    let generatedImages = []; // Store {page, url, blob}

    function addMessage(sender, content) {
      const msg = document.createElement('div');
      msg.className = `message ${sender}`;
      msg.innerHTML = `<div class="avatar">${sender === 'user' ? 'You' : '<i class="fas fa-palette"></i>'}</div><div class="bubble">${content}</div>`;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    function addImage(pageNum, url, caption = '') {
      generatedImages.push({ page: pageNum, url, blob: null });

      const msg = document.createElement('div');
      msg.className = 'message bot';
      msg.innerHTML = `
        <div class="avatar"><i class="fas fa-palette"></i></div>
        <div class="bubble">
          ${caption ? `<strong>${caption}</strong><br>` : ''}
          <img src="${url}" class="image-result" alt="Page ${pageNum}">
          <div class="mt-3">
            <a href="${url}" download="page-${String(pageNum).padStart(2,'0')}.png" class="btn btn-sm btn-outline-light me-2">
              <i class="fas fa-download"></i> Page ${pageNum}
            </a>
          </div>
        </div>
      `;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    async function startColoringBook(prompt) {
      addMessage('user', prompt);
      const loader = `<div class="message bot"><div class="avatar"><div class="loader"></div></div><div class="bubble">Planning your storybook... This takes 5–10 seconds</div></div>`;
      chat.insertAdjacentHTML('beforeend', loader);
      chat.scrollTop = chat.scrollHeight;

      const res = await fetch('/api/plan-story.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'prompt=' + encodeURIComponent(prompt)
      });
      chat.lastElementChild.remove(); // remove loader

      const data = await res.json();
      totalPages = data.total_pages || 8;
      storyScenes = data.scenes || [];
      generatedImages = [];

      const sceneList = storyScenes.map((s,i) => `${i+1}. ${s}`).join('<br>');
      addMessage('bot', `
        <strong>Your ${totalPages}-page coloring book is ready!</strong><br><br>
        <div style="background:#0d1117;padding:15px;border-radius:12px;font-size:0.95em;max-height:300px;overflow-y:auto;">
          ${sceneList}
        </div>
        <div class="mt-3">
          <button class="btn btn-success btn-sm me-2" onclick="generateAll()">Generate All ${totalPages} Pages</button>
          <button class="btn btn-outline-light btn-sm" onclick="generateOneByOne()">One at a Time</button>
        </div>
      `);
    }

    window.generateAll = async () => {
      addMessage('bot', `<div class="loader"></div> Generating all ${totalPages} pages... Please wait ${totalPages > 15 ? '3–5' : '1–2'} minutes`);
      
      for (let i = 0; i < totalPages; i++) {
        const page = i + 1;
        const statusMsg = document.createElement('div');
        statusMsg.className = 'message bot';
        statusMsg.innerHTML = `<div class="avatar"><div class="loader"></div></div><div class="bubble">Page ${page}/${totalPages}: ${storyScenes[i]}...</div>`;
        chat.appendChild(statusMsg);
        chat.scrollTop = chat.scrollHeight;

        try {
          const r = await fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[i])}&page=${page}&story=${encodeURIComponent(input.value)}&t=${Date.now()}`);
          const blob = await r.blob();
          const url = URL.createObjectURL(blob);
          generatedImages.find(img => img.page === page).blob = blob;
          chat.removeChild(statusMsg);
          addImage(page, url, `Page ${page}: ${storyScenes[i]}`);
        } catch (e) {
          chat.removeChild(statusMsg);
          addMessage('bot', `Page ${page} failed`);
        }
      }

      // Final buttons
      addMessage('bot', `
        <strong>All ${totalPages} pages generated!</strong><br><br>
        <button class="btn btn-success me-2" onclick="downloadAsZip()"><i class="fas fa-file-zip"></i> Download All as ZIP</button>
        <button class="btn btn-info" onclick="downloadAsPDF()"><i class="fas fa-file-pdf"></i> Download as PDF</button>
      `);
    };

    window.generateOneByOne = () => {
      let page = 1;
      const next = async () => {
        if (page > totalPages) {
          addMessage('bot', 'All done! Use the buttons above to download ZIP/PDF');
          return;
        }
        addMessage('bot', `Page ${page}/${totalPages}: ${storyScenes[page-1]}`);
        const r = await fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[page-1])}&page=${page}&story=${encodeURIComponent(input.value)}`);
        const blob = await r.blob();
        const url = URL.createObjectURL(blob);
        generatedImages.find(img => img.page === page).blob = blob;
        addImage(page, url, `Page ${page}: ${storyScenes[page-1]}`);
        page++;
        setTimeout(next, 1200);
      };
      next();
    };

    window.downloadAsZip = async () => {
      const zip = new JSZip();
      generatedImages.forEach(img => {
        if (img.blob) zip.file(`page-${String(img.page).padStart(2,'0')}.png`, img.blob);
      });
      const content = await zip.generateAsync({ type: 'blob' });
      saveAs(content, `ColorBot-${totalPages}-Pages.zip`);
      addMessage('bot', 'ZIP downloaded!');
    };

    window.downloadAsPDF = async () => {
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF();
      let y = 10;

      for (let i = 0; i < generatedImages.length; i++) {
        if (i > 0) pdf.addPage();
        const img = generatedImages[i];
        if (!img.blob) continue;

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const image = new Image();
        image.src = URL.createObjectURL(img.blob);
        await new Promise(r => image.onload = r);

        const ratio = Math.min(180 / image.width, 260 / image.height);
        const w = image.width * ratio;
        const h = image.height * ratio;
        canvas.width = image.width;
        canvas.height = image.height;
        ctx.drawImage(image, 0, 0);
        const data = canvas.toDataURL('image/png');

        pdf.setFontSize(12);
        pdf.text(`Page ${img.page}: ${storyScenes[i]}`, 10, y);
        y += 10;
        pdf.addImage(data, 'PNG', 15, y, w, h);
        y += h + 10;
      }

      pdf.save(`ColorBot-${totalPages}-Pages.pdf`);
      addMessage('bot', 'PDF downloaded!');
    };

    function sendPrompt(text) {
      if (!text.trim()) return;
      input.value = '';
      chat.querySelector('.welcome')?.remove();

      const isStory = /page|book|story|adventure|journey|full|complete/i.test(text) || text.length > 35;
      if (isStory) {
        startColoringBook(text);
      } else {
        addMessage('user', text);
        addMessage('bot', '<div class="loader"></div> Generating your coloring page...');
        fetch(`/api/generate.php?prompt=${encodeURIComponent(text)}&page=1&t=${Date.now()}`)
          .then(r => r.blob())
          .then(b => {
            const url = URL.createObjectURL(b);
            generatedImages = [{page:1, url, blob: b}];
            chat.innerHTML = '';
            addMessage('user', text);
            addImage(1, url, 'Here’s your coloring page!');
          });
      }
    }

    function sendExample(text) { input.value = text; sendPrompt(text); }

    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendPrompt(input.value); });
    sendBtn.addEventListener('click', () => sendPrompt(input.value));
    input.focus();
  </script>
</body>
</html>