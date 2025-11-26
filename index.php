<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ColorBot • AI Coloring Book Creator</title>
  <meta name="description" content="Generate beautiful printable coloring books with AI — just type your idea!">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #10a37f;
      --bg: #0d1117;
      --chat-bg: #161b22;
      --input-bg: #0d1117;
      --text: #c9d1d9;
      --border: #30363d;
      --user-bg: #238636;
    }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: -apple-system, BlinkMacFont, 'Segoe UI', Roboto, sans-serif;
      height: 100vh;
      margin: 0;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    #chat-container {
      flex: 1;
      overflow-y: auto;
      padding: 2rem 1rem;
      max-width: 900px;
      margin: 0 auto;
      width: 100%;
    }
    .message {
      margin: 1rem 0;
      display: flex;
      gap: 14px;
      animation: fadeIn 0.4s ease-out;
    }
    .avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      flex-shrink: 0;
      background: var(--primary);
      color: white;
    }
    .user .avatar { background: var(--user-bg); }
    .bubble {
      max-width: 85%;
      padding: 1rem 1.4rem;
      border-radius: 20px;
      line-height: 1.6;
      word-wrap: break-word;
    }
    .user .bubble {
      background: var(--user-bg);
      color: white;
      border-bottom-right-radius: 6px;
      align-self: flex-end;
    }
    .bot .bubble {
      background: #21262d;
      border: 1px solid var(--border);
      border-bottom-left-radius: 6px;
    }
    .image-result {
      max-width: 100%;
      width: 460px;
      border-radius: 16px;
      margin: 16px 0;
      box-shadow: 0 12px 40px rgba(0,0,0,0.6);
      border: 2px solid #333;
      transition: transform 0.2s;
    }
    .image-result:hover { transform: scale(1.02); }
    .typing {
      color: #8b949e;
      font-style: italic;
    }
    #input-area {
      padding: 1rem;
      background: var(--bg);
      border-top: 1px solid var(--border);
    }
    #prompt-input {
      background: var(--input-bg);
      border: 1px solid var(--border);
      color: white;
      border-radius: 24px;
      padding: 14px 20px;
      font-size: 1rem;
      width: 100%;
      max-width: 900px;
      margin: 0 auto;
      display: block;
    }
    #prompt-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.2);
    }
    .send-btn {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      border: none;
      font-size: 1.4rem;
      box-shadow: 0 8px 30px rgba(0,0,0,0.5);
      cursor: pointer;
      transition: all 0.2s;
    }
    .send-btn:hover { transform: scale(1.1); background: #0d8f6f; }
    .send-btn:disabled { background: #666; cursor: not-allowed; transform: none; }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: none; }
    }
    .welcome {
      text-align: center;
      margin-top: 10vh;
      color: #8b949e;
    }
    .welcome h1 {
      font-size: 2.8rem;
      background: linear-gradient(90deg, #10a37f, #34d399);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 1rem;
    }
    .examples {
      margin-top: 2rem;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      justify-content: center;
    }
    .example-btn {
      background: #21262d;
      border: 1px solid var(--border);
      color: #c9d1d9;
      padding: 12px 20px;
      border-radius: 12px;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    .example-btn:hover {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
  </style>
</head>
<body>

  <div id="chat-container">
    <div class="welcome">
      <h1>ColorBot</h1>
      <p>Generate beautiful printable coloring books — just describe your idea!</p>
      <div class="examples">
        <div class="example-btn" onclick="sendExample('A cute baby dragon flying over a castle')">Baby dragon adventure</div>
        <div class="example-btn" onclick="sendExample('A magical forest with fairies and mushrooms')">Magical forest</div>
        <div class="example-btn" onclick="sendExample('A brave knight fighting a friendly dragon')">Knight & dragon</div>
        <div class="example-btn" onclick="sendExample('Create a full 32-page storybook about a lost puppy finding home')">32-page puppy story</div>
      </div>
    </div>
  </div>

  <div id="input-area">
    <input type="text" id="prompt-input" placeholder="Describe your coloring page or storybook..." autocomplete="off" />
  </div>

  <button id="send-btn" class="send-btn" title="Send">
    <i class="fas fa-paper-plane"></i>
  </button>

  <script>
    const chat = document.getElementById('chat-container');
    const input = document.getElementById('prompt-input');
    const sendBtn = document.getElementById('send-btn');
    let storyScenes = [];
    let totalPages = 0;

    function addMessage(sender, content) {
      const msg = document.createElement('div');
      msg.className = `message ${sender}`;
      msg.innerHTML = `
        <div class="avatar">${sender === 'user' ? 'You' : '<i class="fas fa-palette"></i>'}</div>
        <div class="bubble">${content}</div>
      `;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
      if (sender === 'user') saveChat();
    }

    function addImage(pageNum, url, caption = '') {
      const msg = document.createElement('div');
      msg.className = 'message bot';
      msg.innerHTML = `
        <div class="avatar"><i class="fas fa-palette"></i></div>
        <div class="bubble">
          ${caption ? `<strong>${caption}</strong><br>` : ''}
          <img src="${url}" class="image-result" alt="Coloring page">
          <div class="mt-3">
            <a href="${url}" download="coloring-page-${String(pageNum).padStart(2,'0')}.png" class="btn btn-sm btn-outline-light">
              <i class="fas fa-download"></i> Download Page ${pageNum}
            </a>
          </div>
        </div>
      `;
      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    async function startColoringBook(prompt) {
      addMessage('user', prompt);
      addMessage('bot', 'Planning your coloring book...');

      const res = await fetch('/api/plan-story.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'prompt=' + encodeURIComponent(prompt)
      });
      const data = await res.json();

      totalPages = data.total_pages || 8;
      storyScenes = data.scenes || [];

      const sceneList = storyScenes.map((s,i) => `${i+1}. ${s}`).join('<br>');
      addMessage('bot', `
        <strong>Your ${totalPages}-page coloring book is ready to generate!</strong><br><br>
        <div style="background:#0d1117; padding:15px; border-radius:12px; font-size:0.95em; max-height:300px; overflow-y:auto;">
          ${sceneList}
        </div>
        <div class="mt-3">
          <button class="btn btn-success btn-sm me-2" onclick="generateAll()">Generate All ${totalPages} Pages</button>
          <button class="btn btn-outline-light btn-sm" onclick="generateOneByOne()">One at a Time</button>
        </div>
      `);
    }

    window.generateAll = async () => {
      addMessage('bot', `Generating all ${totalPages} pages now...`);
      for (let i = 0; i < totalPages; i++) {
        const page = i + 1;
        const status = document.createElement('div');
        status.className = 'message bot';
        status.innerHTML = `<div class="avatar"><i class="fas fa-spinner fa-spin"></i></div><div class="bubble typing">Page ${page}/${totalPages}: ${storyScenes[i]}...</div>`;
        chat.appendChild(status);
        chat.scrollTop = chat.scrollHeight;

        try {
          const r = await fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[i])}&page=${page}&story=${encodeURIComponent(input.value)}&t=${Date.now()}`);
          const blob = await r.blob();
          const url = URL.createObjectURL(blob);
          chat.removeChild(status);
          addImage(page, url, `Page ${page}: ${storyScenes[i]}`);
        } catch {
          chat.removeChild(status);
          addMessage('bot', `Page ${page} failed — please try again.`);
        }
      }
      addMessage('bot', `Your complete ${totalPages}-page coloring book is ready!`);
    };

    window.generateOneByOne = () => {
      let page = 1;
      const next = async () => {
        if (page > totalPages) {
          addMessage('bot', 'All pages completed! Happy coloring!');
          return;
        }
        addMessage('bot', `Page ${page}/${totalPages}: ${storyScenes[page-1]}`);
        const r = await fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[page-1])}&page=${page}&story=${encodeURIComponent(input.value)}`);
        const blob = await r.blob();
        const url = URL.createObjectURL(blob);
        addImage(page, url, `Page ${page}: ${storyScenes[page-1]}`);
        page++;
        setTimeout(next, 1000);
      };
      next();
    };

    function sendPrompt(text) {
      if (!text.trim()) return;
      input.value = '';

      const isStory = text.toLowerCase().includes('page') || 
                     text.toLowerCase().includes('book') || 
                     text.toLowerCase().includes('story') || 
                     text.length > 30;

      if (isStory) {
        startColoringBook(text);
      } else {
        addMessage('user', text);
        addMessage('bot', 'Generating your coloring page...');
        fetch(`/api/generate.php?prompt=${encodeURIComponent(text)}&page=1&t=${Date.now()}`)
          .then(r => r.blob())
          .then(b => {
            const url = URL.createObjectURL(b);
            chat.querySelector('.welcome')?.remove();
            addImage(1, url, 'Here’s your coloring page!');
          });
      }
    }

    function sendExample(text) {
      input.value = text;
      sendPrompt(text);
    }

    // Enter & Button
    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendPrompt(input.value); });
    sendBtn.addEventListener('click', () => sendPrompt(input.value));
    input.focus();
  </script>
</body>
</html>