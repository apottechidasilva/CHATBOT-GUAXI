<?php
// ============================================================================
// BACKEND SEGURO (Oculto dos utilizadores no Render)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api']) && $_GET['api'] === 'chat') {
    header('Content-Type: application/json');
    $apiKey = getenv('GROQ_API_KEY');
    
    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'A Chave API (GROQ_API_KEY) não foi configurada no Render.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $messages = isset($input['messages']) ? $input['messages'] : [];
    
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    $payload = json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => $messages,
        'temperature' => 0.6
    ]);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    http_response_code($httpCode);
    echo $response;
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoBot - Soluções em Automação Industrial</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2ff;
            --secondary: #7000ff;
            --bg-dark: #020617;
            --glass: rgba(15, 23, 42, 0.9);
            --border: rgba(0, 242, 255, 0.3);
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: #f8fafc; overflow-x: hidden; scroll-behavior: smooth; }

        .grid-background {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(to right, rgba(0, 242, 255, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 242, 255, 0.05) 1px, transparent 1px);
            background-size: 50px 50px; z-index: -1; mask-image: radial-gradient(circle at center, black, transparent 80%);
        }

        .glow {
            position: fixed; width: 600px; height: 600px; background: radial-gradient(circle, rgba(0, 114, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none; z-index: -1; transition: transform 0.1s ease-out;
        }

        .glass-card { background: var(--glass); backdrop-filter: blur(16px); border: 1px solid var(--border); border-radius: 24px; transition: all 0.3s ease; }

        /* --- DESIGN DO CHAT (BASEADO NA PRINT) --- */
        #chatWindow {
            position: fixed; bottom: 100px; right: 25px; width: 380px; height: 550px; 
            max-width: calc(100vw - 50px); max-height: calc(100vh - 150px);
            display: flex; flex-direction: column; z-index: 1001; 
            transform: translateY(30px) scale(0.9); opacity: 0; pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            border: 1.5px solid rgba(0, 242, 255, 0.4);
            overflow: hidden;
        }
        #chatWindow.active { transform: translateY(0) scale(1); opacity: 1; pointer-events: all; }

        .chat-header { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); 
            padding: 18px 24px; 
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .chat-body { 
            flex: 1; overflow-y: auto; padding: 20px; 
            background: radial-gradient(circle at top right, #0f172a, #020617);
            display: flex; flex-direction: column; gap: 16px;
            scrollbar-width: thin; scrollbar-color: var(--primary) transparent;
        }

        .chat-footer { 
            padding: 15px 20px; 
            background: #0f172a; 
            border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
        }

        /* Bolhas de Mensagem Estilo Print */
        .msg { 
            max-width: 85%; padding: 12px 18px; font-size: 14px; line-height: 1.6; 
            position: relative; animation: slideIn 0.3s ease-out;
            font-family: 'Inter', sans-serif;
        }
        
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .msg-bot { 
            align-self: flex-start; 
            background: rgba(30, 41, 59, 0.8); 
            color: #e2e8f0; 
            border-radius: 20px 20px 20px 4px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .msg-user { 
            align-self: flex-end; 
            background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary) 100%); 
            color: white; 
            border-radius: 20px 20px 4px 20px;
            box-shadow: 0 4px 15px rgba(112, 0, 255, 0.3);
        }

        .chat-trigger {
            position: fixed; bottom: 25px; right: 25px; width: 60px; height: 60px; 
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; z-index: 1000; transition: all 0.3s;
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.4);
        }
        .chat-trigger:hover { transform: scale(1.1) rotate(15deg); }

        .status-dot { width: 10px; height: 10px; background: #22c55e; border-radius: 50%; display: inline-block; box-shadow: 0 0 10px #22c55e; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.8; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.8; } }

        .page { display: none; }
        .page.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .nav-link { cursor: pointer; color: #94a3b8; font-weight: 500; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active-nav { color: var(--primary); }

        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 10px 24px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-2px); }

        /* Modals */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .modal-overlay.active { display: flex; }
    </style>
</head>
<body>

    <div class="grid-background"></div>
    <div class="glow" id="mouseGlow"></div>

    <nav class="sticky top-0 z-50 p-6 glass-card border-none rounded-none border-b border-white/5">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="text-2xl font-bold flex items-center gap-2 cursor-pointer" onclick="navigate('home')">
                <span class="text-cyan-400">AUTO</span>BOT
            </div>
            <div class="hidden md:flex gap-8 items-center">
                <a onclick="navigate('home')" class="nav-link active-nav" id="nav-home">Início</a>
                <a onclick="navigate('catalogo')" class="nav-link" id="nav-catalogo">Catálogo</a>
                <a onclick="navigate('projetos')" class="nav-link" id="nav-projetos">Projetos</a>
                <a onclick="navigate('sobre')" class="nav-link" id="nav-sobre">Sobre</a>
                <a onclick="navigate('admin')" class="nav-link text-yellow-500 hidden" id="nav-admin">Admin</a>
                <button onclick="navigate('login')" id="nav-loginBtn" class="btn-primary">Aceder</button>
            </div>
        </div>
    </nav>

    <!-- PÁGINA INICIAL -->
    <main id="page-home" class="page active max-w-7xl mx-auto px-6 pt-32 text-center">
        <h1 class="text-6xl md:text-8xl font-bold text-white mb-6">Automação com<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-purple-500">Inteligência Artificial</span></h1>
        <p class="text-slate-400 text-xl max-w-2xl mx-auto mb-10">Líderes em implementação de Robótica e IA para a indústria Portuguesa.</p>
        <div class="flex justify-center gap-4">
            <button onclick="navigate('catalogo')" class="btn-primary py-4 px-10">Explorar Soluções</button>
        </div>
    </main>

    <!-- LOGIN -->
    <main id="page-login" class="page max-w-md mx-auto px-6 pt-20">
        <div class="glass-card p-10">
            <h2 class="text-3xl font-bold text-white mb-8 text-center">Área do Cliente</h2>
            <form onsubmit="processAuth(event)" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-cyan-400 uppercase mb-2">Email</label>
                    <input type="email" id="authEmail" class="w-full bg-slate-900 border border-white/10 p-3 rounded-lg text-white" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-cyan-400 uppercase mb-2">Senha</label>
                    <input type="password" id="authPassword" class="w-full bg-slate-900 border border-white/10 p-3 rounded-lg text-white" required>
                </div>
                <button type="submit" class="w-full btn-primary py-4">Entrar Agora</button>
            </form>
        </div>
    </main>

    <!-- CATÁLOGO -->
    <main id="page-catalogo" class="page max-w-7xl mx-auto px-6 pt-20">
        <h2 class="text-4xl font-bold text-white mb-10">Catálogo de <span class="text-cyan-400">Soluções</span></h2>
        <div id="catalogGrid" class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-20">
            <!-- Items serão carregados via JS -->
        </div>
    </main>

    <!-- ADMIN -->
    <main id="page-admin" class="page max-w-7xl mx-auto px-6 pt-20">
        <h2 class="text-4xl font-bold text-white mb-8">Painel Admin</h2>
        <div class="glass-card p-8 mb-10">
            <h3 class="text-xl font-bold text-cyan-400 mb-6">Treinar IA (Contexto)</h3>
            <textarea id="cfgContext" class="w-full h-32 bg-slate-900 border border-white/10 p-4 rounded-xl text-white mb-4"></textarea>
            <button onclick="saveSettings()" class="btn-primary">Salvar Contexto</button>
        </div>
    </main>

    <!-- --- CHAT WINDOW (BASEADO NA PRINT) --- -->
    <div id="chatWindow" class="glass-card">
        <div class="chat-header">
            <div class="flex items-center gap-3">
                <div class="status-dot"></div>
                <div>
                    <h4 class="text-white font-bold text-sm tracking-tight" id="botName">AutoBot AI</h4>
                    <p class="text-[10px] text-cyan-400 uppercase font-bold">Assistente Online</p>
                </div>
            </div>
            <button onclick="toggleChat()" class="text-slate-400 hover:text-white transition-all text-xl">✕</button>
        </div>
        
        <div class="chat-body" id="chatContainer">
            <div class="msg msg-bot">Olá! Sou o assistente inteligente da AutoBot. Em que posso ajudar na sua automação hoje?</div>
            <div id="typingIndicator" class="hidden text-xs text-slate-500 italic ml-2">A pensar...</div>
        </div>

        <div class="chat-footer">
            <input type="text" id="userInput" placeholder="Escreva a sua dúvida..." class="flex-1 bg-slate-900/50 border border-white/10 rounded-full px-5 py-2.5 text-sm text-white outline-none focus:border-cyan-400/50">
            <button onclick="handleSend()" class="w-10 h-10 bg-cyan-500 rounded-full flex items-center justify-center text-slate-900 hover:scale-110 transition-all">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
            </button>
        </div>
    </div>

    <div class="chat-trigger" onclick="toggleChat()">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    </div>

    <script>
        // Efeito do Mouse
        const glow = document.getElementById('mouseGlow');
        document.addEventListener('mousemove', (e) => {
            glow.style.transform = `translate(${e.clientX - 300}px, ${e.clientY - 300}px)`;
        });

        let isAdmin = false;
        let isLoggedIn = false;
        let chatHistory = [];

        function navigate(id) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active-nav'));
            
            const target = document.getElementById(`page-${id}`);
            if (target) target.classList.add('active');
            
            const navLink = document.getElementById(`nav-${id}`);
            if (navLink) navLink.classList.add('active-nav');
            
            window.scrollTo(0,0);
        }

        function toggleChat() {
            document.getElementById('chatWindow').classList.toggle('active');
        }

        // --- AUTH ---
        function processAuth(e) {
            e.preventDefault();
            const email = document.getElementById('authEmail').value;
            const pass = document.getElementById('authPassword').value;

            if (email === 'admin@autobot.com' && pass === 'admin123') {
                isAdmin = true;
                document.getElementById('nav-admin').classList.remove('hidden');
                navigate('admin');
            } else {
                isLoggedIn = true;
                navigate('catalogo');
            }
            document.getElementById('nav-loginBtn').innerText = "Sair";
        }

        // --- AI CHAT LOGIC ---
        async function handleSend() {
            const input = document.getElementById('userInput');
            const msg = input.value.trim();
            if (!msg) return;

            appendMsg('user', msg);
            input.value = '';
            
            const indicator = document.getElementById('typingIndicator');
            indicator.classList.remove('hidden');

            try {
                const sysPrompt = localStorage.getItem('autobot_pro_cfg') || "És um assistente técnico da AutoBot Industrial.";
                const response = await fetch('?api=chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        messages: [
                            { role: "system", content: sysPrompt },
                            ...chatHistory,
                            { role: "user", content: msg }
                        ] 
                    })
                });

                const data = await response.json();
                indicator.classList.add('hidden');
                
                if (data.choices && data.choices[0]) {
                    const botRes = data.choices[0].message.content;
                    appendMsg('bot', botRes);
                    chatHistory.push({ role: "user", content: msg }, { role: "assistant", content: botRes });
                }
            } catch (err) {
                indicator.classList.add('hidden');
                appendMsg('bot', "Erro na ligação ao servidor. Verifique a API Key no Render.");
            }
        }

        function appendMsg(role, text) {
            const container = document.getElementById('chatContainer');
            const div = document.createElement('div');
            div.className = `msg msg-${role}`;
            div.innerText = text;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        function saveSettings() {
            const ctx = document.getElementById('cfgContext').value;
            localStorage.setItem('autobot_pro_cfg', ctx);
            alert("Contexto guardado!");
        }

        // Inicialização
        document.getElementById('userInput').onkeypress = (e) => { if(e.key === 'Enter') handleSend(); };
    </script>
</body>
</html>
