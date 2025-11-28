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
    :root {
      --primary: #10a37f;
      --bg: #0d1117;
      --text: #c9d1d9;
      --border: #30363d;
      --user-bg: #238636;
    }

    * { box-sizing: border-box; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      height: 100dvh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    #chat-container {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem 1rem;
      max-width: 960px;
      margin: 0 auto;
      width: 100%;
      padding-bottom: 90px; /* Space for fixed input */
      -webkit-overflow-scrolling: touch;
    }

    .message {
      margin: 1rem 0;
      display: flex;
      gap: 12px;
      animation: fadeIn 0.4s ease-out;
      max-width: 100%;
    }

    .avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
      background: var(--primary);
      color: white;
    }

    .user .avatar { background: var(--user-bg); }

    .bubble {
      max-width: calc(100% - 60px);
      padding: 0.9rem 1.2rem;
      border-radius: 18px;
      line-height: 1.55;
      word-wrap: break-word;
      font-size: 0.95rem;
    }

    .user .bubble {
      background: var(--user-bg);
      color: white;
      border-bottom-right-radius: 6px;
      margin-left: auto;
    }

    .bot .bubble {
      background: #21262d;
      border: 1px solid var(--border);
      border-bottom-left-radius: 6px;
    }

    .image-result {
      max-width: 100%;
      width: 100%;
      max-height: 70vh;
      border-radius: 14px;
      margin: 14px 0;
      box-shadow: 0 10px 30px rgba(0,0,0,0.6);
      border: 2px solid #333;
      display: block;
    }

    /* Input Area - Fixed Bottom */
    #input-area {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 12px;
      background: var(--bg);
      border-top: 1px solid var(--border);
      z-index: 10;
      padding-bottom: env(safe-area-inset-bottom, 12px);
      background: rgba(13, 17, 23, 0.95);
      backdrop-filter: blur(10px);
    }

    #prompt-input {
      background: #161b22;
      border: 1px solid var(--border);
      color: white;
      border-radius: 24px;
      padding: 14px 20px;
      font-size: 1rem;
      width: 100%;
      max-width: 100%;
    }

    #prompt-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(16,163,127,0.3);
    }

    /* Floating Send Button */
    .send-btn {
      position: fixed;
      bottom: 10px;
      right: 20px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      border: none;
      font-size: 1.4rem;
      box-shadow: 0 8px 30px rgba(0,0,0,0.6);
      cursor: pointer;
      z-index: 11;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .send-btn:hover,
    .send-btn:active {
      transform: scale(1.08);
      background: #0d8b6f;
    }

    /* Typing Dots */
    .typing-dots span {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #888;
      margin: 0 3px;
      animation: bounce 1.4s infinite;
    }

    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes bounce {
      0%, 80%, 100% { transform: translateY(0); }
      40% { transform: translateY(-10px); }
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: none; }
    }

    /* Welcome Screen */
    .welcome {
      text-align: center;
      padding: 2rem 1rem;
      max-width: 800px;
      margin: 0 auto;
    }

    .welcome h1 {
      font-size: clamp(2.5rem, 8vw, 3.5rem);
      background: linear-gradient(90deg, #10a37f, #34d399);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 0.5rem;
    }

    .welcome .lead {
      font-size: 1.1rem;
      opacity: 0.9;
    }

    .examples {
      gap: 12px;
      margin-top: 2rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .example-btn {
      background: #21262d;
      border: 1px solid var(--border);
      color: #c9d1d9;
      padding: 12px 18px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 0.95rem;
      white-space: nowrap;
      transition: all 0.2s;
    }

    .example-btn:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-2px);
    }

    /* Download Controls */
    .download-controls {
      background: #1a1f25;
      padding: 1rem;
      border-radius: 12px;
      margin: 20px 0;
      text-align: center;
      font-size: 0.95rem;
    }

    .download-controls button {
      margin: 6px;
    }

    /* Checkbox labels */
    label input[type="checkbox"] {
      transform: scale(1.1);
      margin-right: 8px;
    }

    /* Responsive Adjustments */
    @media (max-width: 480px) {
      #chat-container { padding: 1rem 0.8rem; }
      .bubble { padding: 0.8rem 1rem; font-size: 0.93rem; }
      .message { gap: 10px; }
      .avatar { width: 36px; height: 36px; font-size: 1.1rem; }
      .send-btn { width: 52px; height: 52px; bottom: 5px; right: 16px; }
      .example-btn { padding: 10px 16px; font-size: 0.9rem; }
    }

    @media (min-width: 768px) {
      .image-result { width: 480px; }
    }
  </style>
</head>
<body>

  <div id="chat-container">
    <div class="welcome">
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

  <button id="send-btn" class="send-btn" aria-label="Send">
    <i class="fas fa-paper-plane"></i>
  </button>

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
      msg.innerHTML = `<div class="avatar">${sender === 'user' ? 'You' : '<i class="fas fa-palette"></i>'}</div>
                       <div class="bubble">${content}</div>`;
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
          <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin-bottom:12px;font-size:0.95rem;">
            <input type="checkbox" checked onchange="toggleSelect(${pageNum}, this.checked)"> Page ${pageNum}: ${caption}
          </label>
          <img src="${url}" class="image-result" alt="Coloring page ${pageNum}" loading="lazy">
        </div>
      `;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    function toggleSelect(page, checked) {
      const img = generatedImages.find(i => i.page === page);
      if (img) img.selected = checked;
    }

    window.selectAll = (state) => {
      document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = state);
      generatedImages.forEach(img => img.selected = state);
    };

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

      const sceneList = storyScenes.map((s,i) => `${i+1}. ${s}`).join('<br>');
      addMessage('bot', `
        <strong>Your ${totalPages}-page coloring book is ready!</strong><br><br>
        <div style="background:#161b22;padding:15px;border-radius:12px;font-size:0.94em;max-height:300px;overflow-y:auto;margin:10px 0;">
          ${sceneList}
        </div>
        <div class="mt-3 text-center">
          <button class="btn btn-success btn-lg px-4" onclick="generateAll()">Generate All ${totalPages} Pages</button>
        </div>
      `);
    }

    window.generateAll = async () => {
      const loadingMsg = document.createElement('div');
      loadingMsg.className = 'message bot';
      loadingMsg.innerHTML = `<div class="avatar"><i class="fas fa-palette"></i></div>
        <div class="bubble">Generating all ${totalPages} pages... Please wait ${totalPages > 15 ? '3–5' : '1–2'} minutes<span class="typing-dots"><span></span><span></span><span></span></span></div>`;
      chat.appendChild(loadingMsg);
      chat.scrollTop = chat.scrollHeight;

      for (let i = 0; i < totalPages; i++) {
        const page = i + 1;
        loadingMsg.innerHTML = `<div class="avatar"><i class="fas fa-palette"></i></div>
          <div class="bubble">Generating page ${page}/${totalPages}: ${storyScenes[i]}...<span class="typing-dots"><span></span><span></span><span></span></span></div>`;

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
          <button class="btn btn-success btn-lg me-3" onclick="downloadSelected('zip')">
            <i class="fas fa-file-zip"></i> Download Selected as ZIP
          </button><br><br>
          <button class="btn btn-outline-light btn-sm me-2" onclick="selectAll(true)">Select All</button>
          <button class="btn btn-outline-light btn-sm" onclick="selectAll(false)">Deselect All</button>
        </div>
      `);
    };

    window.downloadSelected = async (type) => {
      const selected = generatedImages.filter(i => i.selected && i.blob);
      if (selected.length === 0) return alert("Please select at least one page!");

      if (type === 'zip') {
        const zip = new JSZip();
        selected.forEach(img => zip.file(`page-${String(img.page).padStart(2,'0')}.png`, img.blob));
        const blob = await zip.generateAsync({ type: 'blob' });
        saveAs(blob, `ColorBot-Book-${selected.length}-Pages.zip`);
        addMessage('bot', `Downloaded ${selected.length} page(s) as ZIP!`);
      }
    };

    function sendPrompt(text) {
      if (!text.trim()) return;
      input.value = '';
      const isStory = /page|book|story|adventure|full|complete|32|10|20|story ?book/i.test(text) || text.length > 40;
      if (isStory) {
        startColoringBook(text);
      } else {
        addMessage('user', text);
        chat.querySelector('.welcome')?.remove();
        addMessage('bot', 'Generating your coloring page<span class="typing-dots"><span></span><span></span><span></span></span>');

        fetch(`/api/generate.php?prompt=${encodeURIComponent(text)}&page=1&t=${Date.now()}`)
          .then(r => r.blob())
          .then(b => {
            const url = URL.createObjectURL(b);
            generatedImages = [{page:1, url, blob: b, selected: true}];
            chat.innerHTML = '';
            addMessage('user', text);
            addImage(1, url, text);
            addMessage('bot', `<div class="download-controls">
              <button class="btn btn-success" onclick="downloadSelected('zip')">
                <i class="fas fa-download"></i> Download Page
              </button>
            </div>`);
          });
      }
    }

    function sendExample(t) {
      input.value = t;
      sendPrompt(t);
    }

    input.addEventListener('keypress', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendPrompt(input.value);
      }
    });

    sendBtn.addEventListener('click', () => sendPrompt(input.value));
    input.focus();

    // Auto-focus input on mobile when tapping chat
    chat.addEventListener('click', () => input.focus());
  </script>
</body>
</html>