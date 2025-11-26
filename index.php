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

  <style>
    :root { --primary: #10a37f; --bg: #0d1117; --text: #c9d1d9; --border: #30363d; --user-bg: #238636; }
    body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; height: 100vh; margin: 0; display: flex; flex-direction: column; overflow: hidden; }
    #chat-container { flex: 1; overflow-y: auto; padding: 2rem 1rem; max-width: 900px; margin: 0 auto; width: 100%; }
    .message { margin: 1rem 0; display: flex; gap: 14px; animation: fadeIn 0.4s ease-out; }
    .avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; background: var(--primary); color: white; }
    .user .avatar { background: var(--user-bg); }
    .bubble { max-width: 85%; padding: 1rem 1.4rem; border-radius: 20px; line-height: 1.6; word-wrap: break-word; }
    .user .bubble { background: var(--user-bg); color: white; border-bottom-right-radius: 6px; }
    .bot .bubble { background: #21262d; border: 1px solid var(--border); border-bottom-left-radius: 6px; }
    .image-result { max-width: 100%; width: 460px; border-radius: 16px; margin: 16px 0; box-shadow: 0 12px 40px rgba(0,0,0,0.6); border: 2px solid #333; }
    #input-area { padding: 1rem; background: var(--bg); border-top: 1px solid var(--border); }
    #prompt-input { background: #0d1117; border: 1px solid var(--border); color: white; border-radius: 24px; padding: 14px 20px; font-size: 1rem; width: 100%; max-width: 900px; margin: 0 auto; display: block; }
    #prompt-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,163,127,0.2); }
    .send-btn { position: fixed; bottom: 24px; right: 24px; width: 56px; height: 56px; border-radius: 50%; background: var(--primary); color: white; border: none; font-size: 1.4rem; box-shadow: 0 8px 30px rgba(0,0,0,0.5); cursor: pointer; }
    .send-btn:hover { transform: scale(1.1); }
    .typing-dots span { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #888; margin: 0 3px; animation: bounce 1.4s infinite; }
    .typing-dots span:nth-child(1) { animation-delay: 0s; }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce { 0%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-10px); } }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    .welcome h1 { font-size: 2.8rem; background: linear-gradient(90deg, #10a37f, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .example-btn { background: #21262d; border: 1px solid var(--border); color: #c9d1d9; padding: 12px 20px; border-radius: 12px; cursor: pointer; }
    .example-btn:hover { background: var(--primary); color: white; }
    .download-controls { background: #1a1f25; padding: 15px; border-radius: 12px; margin: 20px 0; text-align: center; }
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
        <div class="example-btn" onclick="sendExample('A 5-page adventure of a brave turtle')">5-page turtle story</div>
        <div class="example-btn" onclick="sendExample('Full 32-page storybook about a lost puppy')">32-page puppy book</div>
      </div>
    </div>
  </div>

  <div id="input-area">
    <input type="text" id="prompt-input" placeholder="Describe your coloring page or full storybook..." autocomplete="off" />
  </div>

  <button id="send-btn" class="send-btn"><i class="fas fa-paper-plane"></i></button>

  <script>
    const chat = document.getElementById('chat-container');
    const input = document.getElementById('prompt-input');
    const sendBtn = document.getElementById('send-btn');
    let storyScenes = [];
    let totalPages = 0;
    let generatedImages = [];

    function addMessage(sender, content) {
      const msg = document.createElement('div');
      msg.className = `message ${sender}`;
      msg.innerHTML = `<div class="avatar">${sender === 'user' ? 'You' : '<i class="fas fa-palette"></i>'}</div><div class="bubble">${content}</div>`;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    function addImage(pageNum, url, caption = '') {
      generatedImages.push({ page: pageNum, url, blob: null, selected: true });

      const msg = document.createElement('div');
      msg.className = 'message bot';
      msg.innerHTML = `
        <div class="avatar"><i class="fas fa-palette"></i></div>
        <div class="bubble">
          <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin-bottom:10px;">
            <input type="checkbox" checked onchange="toggleSelect(${pageNum}, this.checked)"> Page ${pageNum}: ${caption}
          </label>
          <img src="${url}" class="image-result" alt="Page ${pageNum}">
        </div>
      `;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    function toggleSelect(page, checked) {
      const img = generatedImages.find(i => i.page === page);
      if (img) img.selected = checked;
    }

    async function startColoringBook(prompt) {
      addMessage('user', prompt);
      chat.querySelector('.welcome')?.remove();

      const typingMsg = document.createElement('div');
      typingMsg.className = 'message bot';
      typingMsg.innerHTML = `<div class="avatar"><i class="fas fa-palette"></i></div><div class="bubble">Planning your storybook<span class="typing-dots"><span></span><span></span><span></span></span></div>`;
      chat.appendChild(typingMsg);
      chat.scrollTop = chat.scrollHeight;

      const res = await fetch('/api/plan-story.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'prompt=' + encodeURIComponent(prompt)
      });
      typingMsg.remove();

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
          
        </div>
      `);
    }

    window.generateAll = async () => {
      const loadingMsg = document.createElement('div');
      loadingMsg.className = 'message bot';
      loadingMsg.innerHTML = `<div class="avatar"><i class="fas fa-palette"></i></div><div class="bubble">Generating all ${totalPages} pages... Please wait ${totalPages > 15 ? '3–5' : '1–2'} minutes<span class="typing-dots"><span></span><span></span><span></span></span></div>`;
      chat.appendChild(loadingMsg);
      chat.scrollTop = chat.scrollHeight;

      for (let i = 0; i < totalPages; i++) {
        const page = i + 1;
        loadingMsg.innerHTML = `<div class="avatar"><i class="fas fa-palette"></i></div><div class="bubble">Generating page ${page}/${totalPages}: ${storyScenes[i]}...<span class="typing-dots"><span></span><span></span><span></span></span></div>`;

        try {
          const r = await fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[i])}&page=${page}&story=${encodeURIComponent(input.value)}&t=${Date.now()}`);
          const blob = await r.blob();
          const url = URL.createObjectURL(blob);
          const img = generatedImages.find(x => x.page === page);
          if (img) img.blob = blob;
          else generatedImages.push({ page, url, blob, selected: true });
          addImage(page, url, storyScenes[i]);
        } catch (e) {
          addMessage('bot', `Page ${page} failed to generate`);
        }
      }
      loadingMsg.remove();

      addMessage('bot', `
        <div class="download-controls">
          <strong>All ${totalPages} pages generated!</strong><br><br>
          <button class="btn btn-success me-2" onclick="downloadSelected('zip')"><i class="fas fa-file-zip"></i> Download Selected as ZIP</button>
        
          <button class="btn btn-outline-light btn-sm" onclick="selectAll(true)">Select All</button>
          <button class="btn btn-outline-light btn-sm" onclick="selectAll(false)">Deselect All</button>
        </div>
      `);
    };

    

    window.selectAll = (state) => {
      document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = state);
      generatedImages.forEach(img => img.selected = state);
    };

    window.downloadSelected = async (type) => {
      const selected = generatedImages.filter(i => i.selected && i.blob);
      if (selected.length === 0) return alert("Please select at least one page!");

      if (type === 'zip') {
        const zip = new JSZip();
        selected.forEach(img => zip.file(`page-${String(img.page).padStart(2,'0')}.png`, img.blob));
        const blob = await zip.generateAsync({ type: 'blob' });
        saveAs(blob, `ColorBot-Selected-${selected.length}-Pages.zip`);
        addMessage('bot', 'Selected pages downloaded as ZIP!');
      } 
    };

    function sendPrompt(text) {
      if (!text.trim()) return;
      input.value = '';
      const isStory = /page|book|story|adventure|full|complete|32/i.test(text) || text.length > 30;
      if (isStory) startColoringBook(text);
      else {
        addMessage('user', text);
        addMessage('bot', 'Generating your coloring page<span class="typing-dots"><span></span><span></span><span></span></span>');
        fetch(`/api/generate.php?prompt=${encodeURIComponent(text)}&page=1&t=${Date.now()}`)
          .then(r => r.blob())
          .then(b => {
            const url = URL.createObjectURL(b);
            generatedImages = [{page:1, url, blob: b, selected: true}];
            chat.innerHTML = ''; addMessage('user', text);
            addImage(1, url, 'Here’s your coloring page!');
          });
      }
    }

    function sendExample(t) { input.value = t; sendPrompt(t); }

    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendPrompt(input.value); });
    sendBtn.addEventListener('click', () => sendPrompt(input.value));
    input.focus();
  </script>
</body>
</html>