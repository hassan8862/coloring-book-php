<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ColorBot - AI Coloring Book Chat</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary: #6366f1; --bg: #f8f9ff; --sidebar: #1e1e2e; --chat-bg: #ffffff; }
    body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; height: 100vh; overflow: hidden; }
    #sidebar { width: 280px; background: var(--sidebar); color: #cdd6f2; position: fixed; left: 0; top: 0; bottom: 0; z-index: 100; padding: 1rem; overflow-y: auto; }
    #main { margin-left: 280px; height: 100vh; display: flex; flex-direction: column; }
    #chat { flex: 1; overflow-y: auto; padding: 2rem 1.5rem; background: #f0f2ff; }
    #inputArea { padding: 1rem; background: white; border-top: 1px solid #eee; }
    .message { max-width: 85%; margin-bottom: 1.5rem; padding: 1rem 1.2rem; border-radius: 1.2rem; line-height: 1.5; }
    .user { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 0.3rem; }
    .bot { align-self: flex-start; background: white; border: 1px solid #e2e8f5; border-bottom-left-radius: 0.3rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .image-result { max-width: 420px; border-radius: 1rem; margin: 1rem 0; box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    .typing { font-style: italic; color: #888; }
    .sidebar-btn { background: rgba(255,255,255,0.1); border: none; width: 100%; text-align: left; padding: 0.8rem 1rem; border-radius: 0.6rem; margin-bottom: 0.5rem; color: #cdd6f2; }
    .sidebar-btn:hover { background: rgba(255,255,255,0.2); }
    .sidebar-btn.active { background: var(--primary); font-weight: 600; }
    #uploadBtn { position: absolute; right: 10px; top: 10px; background: rgba(0,0,0,0.5); color: white; border-radius: 50%; width: 40px; height: 40px; }
  </style>
</head>
<body class="d-flex">

  <!-- Sidebar -->
  <div id="sidebar">
    <h4 class="text-center mb-4 text-white"><i class="fas fa-palette"></i> ColorBot</h4>
    <button class="sidebar-btn active" id="newChat"><i class="fas fa-plus me-2"></i>New Coloring Chat</button>
    <hr style="border-color: #444;">
    <div id="historyList"></div>
    <div class="mt-auto">
      <div class="sidebar-btn"><i class="fas fa-user me-2"></i>Guest User</div>
      <div class="sidebar-btn"><i class="fas fa-cog me-2"></i>Settings</div>
    </div>
  </div>

  <!-- Main Chat Area -->
  <div id="main">
    <div id="chat" class="d-flex flex-column"></div>

    <div id="inputArea">
      <div class="position-relative">
        <input type="text" id="promptInput" class="form-control form-control-lg" placeholder="Describe a coloring page... (e.g. unicorn in forest, line art)" autocomplete="off">
        <button id="uploadBtn" title="Upload image to convert"><i class="fas fa-image"></i></button>
        <input type="file" id="imageInput" accept="image/*" hidden>
      </div>
      <small class="text-muted">Press Enter to send • Powered by FLUX & ControlNet</small>
    </div>
  </div>

  <script>
    const chat = document.getElementById('chat');
    const input = document.getElementById('promptInput');
    const imageInput = document.getElementById('imageInput');
    const uploadBtn = document.getElementById('uploadBtn');

    let currentChatId = Date.now();
    let chatHistory = JSON.parse(localStorage.getItem('coloringChats') || '[]');

    function saveChat() {
      const existing = chatHistory.find(c => c.id === currentChatId);
      if (existing) {
        existing.messages = Array.from(chat.children).map(el => ({
          type: el.classList.contains('user') ? 'user' : 'bot',
          text: el.querySelector('div')?.innerText || '',
          image: el.querySelector('img')?.src || null
        }));
        existing.title = existing.messages[0]?.text.slice(0, 30) + '...' || 'Coloring Chat';
      } else {
        chatHistory.push({
          id: currentChatId,
          title: Array.from(chat.children)[0]?.querySelector('div')?.innerText.slice(0, 30) + '...' || 'New Chat',
          messages: []
        });
      }
      localStorage.setItem('coloringChats', JSON.stringify(chatHistory));
      renderHistory();
    }

    function renderHistory() {
      const list = document.getElementById('historyList');
      list.innerHTML = chatHistory.slice(-10).reverse().map(c => `
        <button class="sidebar-btn" onclick="loadChat(${c.id})">
          <i class="fas fa-book me-2"></i>${c.title}
        </button>
      `).join('');
    }

    function loadChat(id) {
      const conv = chatHistory.find(c => c.id === id);
      if (!conv) return;
      currentChatId = id;
      chat.innerHTML = '';
      conv.messages.forEach(m => addMessage(m.type, m.text, m.image));
      document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
      event.target.classList.add('active');
    }

    function addMessage(type, text = '', imageUrl = null) {
      const msg = document.createElement('div');
      msg.className = `message ${type}`;
      msg.style.alignSelf = type === 'user' ? 'flex-end' : 'flex-start';

      if (text) {
        const txt = document.createElement('div');
        txt.innerHTML = text.replace(/\n/g, '<br>');
        msg.appendChild(txt);
      }

      if (imageUrl) {
        const img = document.createElement('img');
        img.src = imageUrl;
        img.className = 'image-result img-fluid';
        msg.appendChild(img);

        const dl = document.createElement('a');
        dl.href = imageUrl;
        dl.download = 'coloring-page.png';
        dl.className = 'btn btn-sm btn-success mt-2';
        dl.innerHTML = '<i class="fas fa-download"></i> Download';
        msg.appendChild(dl);
      }

      chat.appendChild(msg);
      chat.scrollTop = chat.scrollHeight;
    }

    function sendPrompt(prompt) {
      if (!prompt.trim()) return;
      addMessage('user', prompt);
      input.value = '';

      const typing = document.createElement('div');
      typing.className = 'message bot typing';
      typing.innerHTML = '<div>Generating coloring page... <i class="fas fa-spinner fa-spin"></i></div>';
      chat.appendChild(typing);
      chat.scrollTop = chat.scrollHeight;

      fetch(`/api/generate.php?prompt=${encodeURIComponent(prompt)}&page=1&t=${Date.now()}`)
        .then(r => r.blob())
        .then(blob => {
          const url = URL.createObjectURL(blob);
          chat.removeChild(typing);
          addMessage('bot', 'Here’s your coloring page!', url);
          saveChat();
        })
        .catch(() => {
          chat.removeChild(typing);
          addMessage('bot', 'Sorry, the AI is still waking up. Try again in 10 seconds! 😊');
        });
    }

    function handleImage(file) {
      addMessage('user', `Uploaded: ${file.name}`);
      const typing = document.createElement('div');
      typing.className = 'message bot typing';
      typing.innerHTML = '<div>Converting to coloring page... (10–15s) <i class="fas fa-spinner fa-spin"></i></div>';
      chat.appendChild(typing);

      const fd = new FormData();
      fd.append('image', file);

      fetch('/api/image-to-coloring.php', { method: 'POST', body: fd })
        .then(r => r.blob())
        .then(blob => {
          const url = URL.createObjectURL(blob);
          chat.removeChild(typing);
          addMessage('bot', 'Converted! Ready to color 🎨', url);
          saveChat();
        })
        .catch(() => {
          chat.removeChild(typing);
          addMessage('bot', 'Model is loading... please try again in 10–20 seconds!');
        });
    }

    // Events
    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendPrompt(input.value); });
    document.getElementById('newChat').addEventListener('click', () => {
      currentChatId = Date.now();
      chat.innerHTML = '<div class="text-center text-muted mt-5"><h4>👋 Ask me to draw anything as a coloring page!</h4><p>Try: "cute baby dragon", "spooky Halloween pumpkin", or upload a photo!</p></div>';
      document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
      document.getElementById('newChat').classList.add('active');
    });

    uploadBtn.addEventListener('click', () => imageInput.click());
    imageInput.addEventListener('change', () => {
      if (imageInput.files[0]) handleImage(imageInput.files[0]);
    });

    // Init
    renderHistory();
    document.getElementById('newChat').click();
  </script>
</body>
</html>