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
    let storyScenes = [];
    let generatingAll = false;

    async function startStorybook(prompt) {
        addMessage('user', prompt);
        addMessage('bot', 'Amazing idea! I\'m planning your 32-page coloring storybook now... ✍️');

        const response = await fetch('/api/plan-story.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'prompt=' + encodeURIComponent(prompt)
        });
        const data = await response.json();
        storyScenes = data.scenes || [];

        const storyDiv = document.createElement('div');
        storyDiv.innerHTML = `<strong>Your 32-Page Story:</strong><br><ol style="font-size:0.9em;">${storyScenes.map(s => `<li>${s}</li>`).join('')}</ol>`;
        addMessage('bot', storyDiv.innerHTML);

        addMessage('bot', 'Ready! I\'ll now generate all 32 pages one by one.<br>⏳ This will take ~2–4 minutes total.<br><button class="btn btn-success btn-sm me-2" onclick="generateAllPages()">Generate All 32 Pages Now</button> <button class="btn btn-outline-secondary btn-sm" onclick="generatePageByPage()">Or one at a time</button>');
    }

    window.generateAllPages = async function() {
        if (generatingAll) return;
        generatingAll = true;
        addMessage('bot', '<div class="typing">Starting full 32-page generation... This will take 2–4 minutes ⏳</div>');

        for (let i = 0; i < 32; i++) {
            const pageNum = i + 1;
            const status = document.createElement('div');
            status.id = 'status-' + pageNum;
            status.className = 'message bot typing';
            status.innerHTML = `<div>Generating page ${pageNum}/32: ${storyScenes[i]}...</div>`;
            chat.appendChild(status);
            chat.scrollTop = chat.scrollHeight;

            try {
                const resp = await fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[i])}&page=${pageNum}&story=${encodeURIComponent(input.value)}&t=${Date.now()}`);
                const blob = await resp.blob();
                const url = URL.createObjectURL(blob);

                chat.removeChild(status);
                addMessage('bot', `<strong>Page ${pageNum}/32:</strong> ${storyScenes[i]}`, url);
            } catch (e) {
                chat.removeChild(status);
                addMessage('bot', `Page ${pageNum} failed, retrying later...`);
            }
        }
        addMessage('bot', '🎉 Your complete 32-page coloring storybook is ready!<br><a href="javascript:downloadAll()" class="btn btn-success">📦 Download All as ZIP (soon)</a>');
        generatingAll = false;
        saveChat();
    };

    window.generatePageByPage = function() {
        let page = 1;
        const next = () => {
            if (page > 32) {
                addMessage('bot', '🎉 All 32 pages completed!');
                return;
            }
            const typing = document.createElement('div');
            typing.className = 'message bot typing';
            typing.innerHTML = `<div>Generating page ${page}/32: ${storyScenes[page-1]}... <button onclick="this.parentElement.parentElement.remove();next()" class="btn btn-sm btn-outline-primary ms-3">Skip</button></div>`;
            chat.appendChild(typing);

            fetch(`/api/generate.php?prompt=${encodeURIComponent(storyScenes[page-1])}&page=${page}&story=${encodeURIComponent(input.value)}`)
                .then(r => r.blob())
                .then(blob => {
                    const url = URL.createObjectURL(blob);
                    chat.removeChild(typing);
                    addMessage('bot', `<strong>Page ${page}/32:</strong> ${storyScenes[page-1]}`, url);
                    page++;
                    setTimeout(next, 1000); // auto continue
                });
        };
        next();
    };

    // Modified send function
    function sendPrompt(prompt) {
        if (!prompt.trim()) return;
        input.value = '';
        if (prompt.toLowerCase().includes('32') || prompt.toLowerCase().includes('full book') || prompt.length > 30) {
            startStorybook(prompt);
        } else {
            // Old single-page behavior
            addMessage('user', prompt);
            const typing = document.createElement('div');
            typing.className = 'message bot typing';
            typing.innerHTML = '<div>Generating your coloring page... <i class="fas fa-spinner fa-spin"></i></div>';
            chat.appendChild(typing);

            fetch(`/api/generate.php?prompt=${encodeURIComponent(prompt)}&page=1&t=${Date.now()}`)
                .then(r => r.blob())
                .then(blob => {
                    const url = URL.createObjectURL(blob);
                    chat.removeChild(typing);
                    addMessage('bot', 'Here’s your coloring page!', url);
                    saveChat();
                });
        }
    }

    // Rest of your existing event listeners...
    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendPrompt(input.value); });
    document.getElementById('newChat').addEventListener('click', () => {
        currentChatId = Date.now();
        chat.innerHTML = '<div class="text-center text-muted mt-5"><h4>Describe your 32-page coloring storybook!</h4><p>Examples:<br>• A brave knight rescuing a princess<br>• Baby dinosaur\'s first day at school<br>• Mermaid adventure under the sea</p></div>';
        storyScenes = [];
    });

    renderHistory();
    document.getElementById('newChat').click();
</script>
</body>
</html>