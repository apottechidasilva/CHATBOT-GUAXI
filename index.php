<?php
// ============================================================================
// BACKEND SEGURO (Oculto dos utilizadores no Render)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api']) && $_GET['api'] === 'chat') {
    header('Content-Type: application/json');
    
    // O Render vai ler a sua chave a partir das Variáveis de Ambiente (Environment Variables)
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2ff;
            --secondary: #0072ff;
            --chat-primary: #3b82f6; /* Azul moderno para o chat */
            --chat-secondary: #8b5cf6; /* Roxo vibrante para o chat */
            --bg-dark: #020617;
            --glass: rgba(15, 23, 42, 0.8);
            --border: rgba(0, 242, 255, 0.2);
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

        .glass-card { background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 24px; transition: all 0.3s ease; }
        .glass-card:hover { border-color: var(--primary); box-shadow: 0 0 30px rgba(0, 242, 255, 0.1); }

        .page { display: none; animation: fadeInPage 0.5s ease-out forwards; }
        .page.active { display: block; }

        @keyframes fadeInPage { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .nav-link { cursor: pointer; transition: all 0.2s; position: relative; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active-nav { color: var(--primary); font-weight: 600; }
        .nav-link.active-nav::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 100%; height: 2px; background: var(--primary); box-shadow: 0 0 10px var(--primary); }

        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #020617; font-weight: 700; padding: 12px 24px; border-radius: 12px; transition: all 0.3s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 242, 255, 0.3); }

        .form-input { width: 100%; background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(0, 242, 255, 0.2); border-radius: 12px; padding: 12px 16px; color: white; outline: none; transition: border-color 0.3s; }
        .form-input:focus { border-color: var(--primary); }
        
        .login-input { width: 100%; background: white; border: none; border-radius: 8px; padding: 12px 16px; color: #1e293b; outline: none; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .login-input::placeholder { color: #94a3b8; font-weight: 400; }
        
        .toast-enter { opacity: 1 !important; transform: translateY(0) !important; }

        .cat-btn { width: 100%; text-align: left; padding: 10px 16px; border-radius: 8px; color: #94a3b8; transition: all 0.2s; font-weight: 600; }
        .cat-btn:hover { background: rgba(30, 41, 59, 0.8); color: white; }
        .cat-btn.active { background: rgba(0, 242, 255, 0.15); color: var(--primary); }

        /* Modals do Admin */
        .modal-overlay { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; background: rgba(2, 6, 23, 0.85); backdrop-filter: blur(8px); opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        .modal-content { transform: scale(0.95) translateY(20px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); max-height: 90vh; overflow-y: auto; }
        .modal-overlay.active .modal-content { transform: scale(1) translateY(0); }

        .auth-field { transition: all 0.3s ease; overflow: hidden; }
        .auth-field.hidden { opacity: 0; max-height: 0; padding-top: 0; padding-bottom: 0; margin: 0; pointer-events: none; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(59, 143, 212, 0.3); border-radius: 10px; }

        /* ========================================================= */
        /* NOVO DESIGN DO CHAT (Inspirado no Print)                  */
        /* ========================================================= */
        .chat-trigger {
            position: fixed; bottom: 30px; right: 30px; width: 70px; height: 70px; 
            background: linear-gradient(135deg, var(--chat-primary), var(--chat-secondary));
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; box-shadow: 0 10px 30px rgba(139, 92, 246, 0.5);
            z-index: 1000; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 2px solid rgba(255, 255, 255, 0.1); padding: 4px;
        }
        .chat-trigger:hover { transform: scale(1.1) rotate(10deg); box-shadow: 0 15px 35px rgba(139, 92, 246, 0.7); }

        #chatWindow {
            position: fixed; bottom: 110px; right: 30px; width: 380px; height: 620px; 
            max-width: calc(100vw - 40px); max-height: calc(100vh - 140px);
            display: flex; flex-direction: column; z-index: 1001; 
            transform: translateY(30px) scale(0.9); opacity: 0; pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 28px;
            overflow: hidden;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
        }
        #chatWindow.active { transform: translateY(0) scale(1); opacity: 1; pointer-events: all; }

        .chat-header { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); 
            padding: 20px 24px; 
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .chat-body { 
            flex: 1; overflow-y: auto; padding: 20px; 
            background: radial-gradient(circle at top right, #0f172a, #020617);
            display: flex; flex-direction: column; gap: 16px;
            scrollbar-width: thin; scrollbar-color: var(--chat-secondary) transparent;
        }

        .chat-footer { 
            padding: 16px 20px; 
            background: #0f172a; 
            border-top: 1px solid rgba(139, 92, 246, 0.2);
            display: flex; align-items: center; gap: 12px;
        }

        .msg { 
            max-width: 85%; padding: 14px 18px; font-size: 14px; line-height: 1.5; 
            position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .msg-bot { 
            background: #1e293b; 
            color: #f8fafc; 
            border-radius: 16px 16px 16px 4px; 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }

        .msg-user { 
            align-self: flex-end; 
            background: linear-gradient(135deg, var(--chat-secondary) 0%, var(--chat-primary) 100%); 
            color: white; 
            border-radius: 16px 16px 4px 16px; 
        }

        .status-dot { 
            width: 10px; height: 10px; background: #22c55e; border-radius: 50%; 
            display: inline-block; box-shadow: 0 0 12px #22c55e; animation: pulseDot 2s infinite; 
        }
        @keyframes pulseDot { 0% { transform: scale(0.95); opacity: 0.8; } 50% { transform: scale(1.2); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.8; } }

        .typing-indicator { display: none; align-items: center; gap: 5px; padding: 14px 18px; background: #1e293b; border-radius: 16px 16px 16px 4px; border: 1px solid rgba(255,255,255,0.05); }
        .typing-indicator.active { display: flex; }
        .typing-indicator span { width: 6px; height: 6px; background: var(--chat-secondary); border-radius: 50%; animation: bounceDot 1.4s infinite ease-in-out both; }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounceDot { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    </style>
</head>
<body>

    <div class="grid-background"></div>
    <div class="glow" id="mouseGlow"></div>

    <nav class="sticky top-0 z-50 p-6 glass-card border-none rounded-none border-b border-cyan-500/10 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center w-full">
            <div class="flex items-center gap-3 font-bold text-2xl cursor-pointer" onclick="navigate('home')">
                <!-- LOGO GUAXINIM NAVBAR -->
                <div class="w-10 h-10 rounded-full border-2 border-cyan-400 overflow-hidden bg-slate-900 flex items-center justify-center shadow-[0_0_10px_rgba(0,242,255,0.3)]">
                    <img src="avatar.jpg" alt="Avatar" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=Raccoon&backgroundColor=transparent'">
                </div>
                <div><span class="text-cyan-400" id="brandDisplay1">AUTO</span><span id="brandDisplay2">BOT</span></div>
            </div>
            <div class="hidden md:flex gap-8 text-sm items-center">
                <a onclick="navigate('home')" class="nav-link active-nav" id="nav-home">Início</a>
                <a onclick="navigate('catalogo')" class="nav-link" id="nav-catalogo">Catálogo</a>
                <a onclick="navigate('projetos')" class="nav-link" id="nav-projetos">Projetos</a>
                <a onclick="navigate('sobre')" class="nav-link" id="nav-sobre">Sobre Nós</a>
                <a onclick="navigate('admin')" class="nav-link text-amber-400 hover:text-amber-300 hidden" id="nav-admin">Admin</a>
                
                <button onclick="navigate('login')" class="btn-primary text-xs py-2 px-6 ml-4 shadow-lg shadow-cyan-500/20" id="nav-loginBtn">Fazer Login</button>
            </div>
        </div>
    </nav>

    <main id="page-login" class="page max-w-5xl mx-auto px-6 pt-16 pb-20">
        <div class="flex flex-col md:flex-row rounded-[2rem] overflow-hidden shadow-2xl min-h-[600px] border border-white/10">
            <div class="hidden md:flex md:w-1/2 relative bg-slate-900 flex-col justify-center p-12 overflow-hidden">
                <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1581092335397-9583eb92d232?auto=format&fit=crop&w=800')] bg-cover bg-center opacity-40"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-[#020617] to-transparent"></div>
                <div class="relative z-10">
                    <h2 class="text-4xl font-bold text-white mb-4">Junte-se à <span class="text-yellow-400">AutoBot IA</span> ⚡</h2>
                    <p class="text-slate-300 text-lg">Crie a sua conta e inicie a sua jornada na automação industrial inteligente!</p>
                </div>
            </div>

            <div class="w-full md:w-1/2 bg-[#2563eb] p-8 md:p-14 flex flex-col justify-center relative">
                <div id="loginToast" class="absolute top-6 right-6 bg-green-500 text-white text-xs font-bold px-4 py-3 rounded-lg shadow-xl opacity-0 transform -translate-y-4 transition-all duration-500 pointer-events-none z-50">
                    Acesso efetuado com sucesso! A redirecionar...
                </div>

                <div class="max-w-xs mx-auto w-full">
                    <div class="flex justify-center gap-8 mb-10 font-bold text-sm">
                        <button type="button" id="tab-login" class="text-white border-b-2 border-white pb-1" onclick="toggleAuthMode('login')">ENTRAR</button>
                        <button type="button" id="tab-register" class="text-blue-200 pb-1 hover:text-white transition-colors" onclick="toggleAuthMode('register')">CADASTRAR</button>
                    </div>

                    <form id="authForm" onsubmit="processAuth(event)" class="space-y-4">
                        <div id="field-name" class="auth-field hidden max-h-0">
                            <label class="block text-xs font-bold text-white mb-1.5">Nome completo:</label>
                            <input type="text" class="login-input" id="authName" placeholder="Digite o seu nome">
                        </div>
                        <div class="auth-field">
                            <label class="block text-xs font-bold text-white mb-1.5">Email:</label>
                            <input type="email" class="login-input" id="authEmail" required placeholder="seu@email.com">
                        </div>
                        <div class="auth-field">
                            <label class="block text-xs font-bold text-white mb-1.5">Senha:</label>
                            <input type="password" class="login-input" id="authPassword" required placeholder="••••••••">
                        </div>
                        <div id="field-confirm-password" class="auth-field hidden max-h-0">
                            <label class="block text-xs font-bold text-white mb-1.5">Confirmar Senha:</label>
                            <input type="password" class="login-input" id="authConfirmPassword" placeholder="••••••••">
                        </div>
                        <button type="submit" id="authSubmitBtn" class="w-full bg-white text-[#2563eb] font-bold py-3.5 rounded-lg hover:bg-slate-100 transition-all mt-4 shadow-lg">
                            Entrar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <main id="page-home" class="page active max-w-7xl mx-auto px-6 pt-20 pb-20 text-center">
        <div class="mb-8 inline-block px-4 py-1 rounded-full border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 text-sm font-medium">Líderes em Indústria 4.0</div>
        <h1 class="text-6xl md:text-8xl font-bold mb-8 text-white leading-tight">Engenharia de <br> <span class="text-cyan-400">Próxima Geração.</span></h1>
        <p class="text-slate-400 text-xl max-w-3xl mx-auto mb-12 leading-relaxed" id="homeDesc">Transformamos fábricas tradicionais em ecossistemas inteligentes com IA, robótica avançada e automação de alta precisão.</p>
        <div class="flex flex-wrap justify-center gap-6">
            <button onclick="navigate('projetos')" class="btn-primary text-lg px-8 py-4">Ver Nossos Projetos</button>
            <button onclick="handleChatRequest()" class="px-8 py-4 rounded-xl glass-card border border-cyan-500/20 hover:bg-cyan-500/10 transition-all font-semibold text-white shadow-lg shadow-cyan-500/10">Falar com Consultor IA</button>
        </div>
    </main>

    <main id="page-catalogo" class="page max-w-7xl mx-auto px-6 pt-16 pb-20">
        <div class="flex flex-col md:flex-row gap-8">
            <div class="w-full md:w-1/4">
                <div class="glass-card p-6 sticky top-32">
                    <h3 class="text-xl font-bold text-white mb-6">Categorias</h3>
                    <div class="space-y-2">
                        <button onclick="filterCat('all')" id="btn-filter-all" class="cat-btn active">Todos os Itens</button>
                        <button onclick="filterCat('produto')" id="btn-filter-produto" class="cat-btn">📦 Produtos (Hardware)</button>
                        <button onclick="filterCat('servico')" id="btn-filter-servico" class="cat-btn">🔧 Serviços Técnicos</button>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-3/4">
                <h2 class="text-4xl font-bold mb-2 text-white">Nosso <span class="text-cyan-400">Catálogo</span></h2>
                <p class="text-slate-400 mb-8">Navegue pelas nossas soluções de ponta e serviços de automação.</p>
                <div id="catalogGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>
            </div>
        </div>
    </main>

    <main id="page-projetos" class="page max-w-7xl mx-auto px-6 pt-20 pb-20">
        <h2 class="text-4xl font-bold mb-4 text-white">Projetos <span class="text-cyan-400">Realizados</span></h2>
        <p class="text-slate-400 mb-12">Conheça algumas das transformações que implementámos em parceiros industriais. Clique num projeto para ver detalhes.</p>
        <div id="projectGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"></div>
    </main>

    <main id="page-sobre" class="page max-w-7xl mx-auto px-6 pt-16 pb-20">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
            <div>
                <h2 class="text-4xl font-bold mb-6 text-white">Sobre a <span class="text-cyan-400">AutoBot</span></h2>
                <p class="text-slate-400 mb-6 leading-relaxed">Nascemos da necessidade de integrar soluções digitais complexas no chão de fábrica. Combinamos o rigor da engenharia com o software.</p>
                <ul class="space-y-4 text-slate-300 mb-8">
                    <li class="flex items-start gap-3"><span class="text-cyan-400">✔</span><div><strong>Expertise Técnica:</strong> Engenheiros certificados em normas internacionais.</div></li>
                    <li class="flex items-start gap-3"><span class="text-cyan-400">✔</span><div><strong>Foco em ROI:</strong> Implementamos projetos visando rápido retorno financeiro.</div></li>
                </ul>
            </div>
            <div class="glass-card p-4 aspect-video flex items-center justify-center border-dashed border-cyan-500/30 bg-slate-900/50">
                <p class="text-slate-500 italic flex flex-col items-center gap-2"><span>Vídeo Institucional</span></p>
            </div>
        </div>
    </main>

    <main id="page-admin" class="page max-w-7xl mx-auto px-6 pt-16 pb-20">
        <h2 class="text-4xl font-bold mb-4 text-white">Painel do <span class="text-amber-400">Administrador</span></h2>
        <p class="text-slate-400 mb-12">Área restrita para gestão. <b>Nota de Segurança:</b> A Chave de API está segura no ambiente do Render e removida deste painel.</p>

        <!-- Configuração IA -->
        <div class="glass-card p-8 border-t-4 border-t-cyan-400 mb-12">
            <h3 class="text-2xl font-bold text-white mb-6">Configurações Base (Treinar IA)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-cyan-400 uppercase mb-2">Nome da Empresa</label>
                        <input type="text" id="cfgName" class="form-input focus:border-cyan-400" placeholder="Ex: AutoBot Indústria">
                    </div>
                </div>
                <div class="space-y-4 flex flex-col">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-cyan-400 uppercase mb-2">Contexto Base</label>
                        <textarea id="cfgContext" class="form-input h-24 focus:border-cyan-400" placeholder="Ajudar a IA a entender o que fazemos..."></textarea>
                    </div>
                    <button onclick="saveSettings()" class="w-full bg-cyan-500 text-slate-900 font-bold py-3 rounded-xl hover:bg-cyan-400 transition-all mt-auto shadow-lg shadow-cyan-500/20">Salvar Inteligência</button>
                </div>
            </div>
        </div>

        <!-- Tabela Catálogo -->
        <div class="glass-card p-8 border-t-4 border-t-amber-400 mb-12">
            <div class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4">
                <h3 class="text-2xl font-bold text-white">Catálogo de Produtos & Serviços</h3>
                <button onclick="openAdminCatalogModal()" class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold py-2 px-6 rounded-xl transition-colors shadow-lg shadow-amber-500/20">+ Novo Item</button>
            </div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs text-amber-400 uppercase bg-slate-800/50"><tr><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Título</th><th class="px-4 py-3 text-right">Ações</th></tr></thead>
                <tbody id="adminCatalogList" class="divide-y divide-slate-700/50"></tbody>
            </table></div>
        </div>

        <!-- Tabela Projetos -->
        <div class="glass-card p-8 border-t-4 border-t-blue-500">
            <div class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4">
                <h3 class="text-2xl font-bold text-white">Gestão de Projetos</h3>
                <button onclick="openAdminProjectModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-6 rounded-xl transition-colors shadow-lg shadow-blue-500/20">+ Novo Projeto</button>
            </div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs text-blue-400 uppercase bg-slate-800/50"><tr><th class="px-4 py-3">Setor</th><th class="px-4 py-3">Projeto</th><th class="px-4 py-3 text-right">Ações</th></tr></thead>
                <tbody id="adminProjectList" class="divide-y divide-slate-700/50"></tbody>
            </table></div>
        </div>

        <!-- Tabela Funcionários -->
        <div class="glass-card p-8 border-t-4 border-t-green-500">
            <div class="flex justify-between items-center mb-6 border-b border-slate-700 pb-4">
                <h3 class="text-2xl font-bold text-white">Gestão de Funcionários</h3>
                <button onclick="openAdminEmployeeModal()" class="bg-green-600 hover:bg-green-500 text-white font-bold py-2 px-6 rounded-xl transition-colors shadow-lg shadow-green-500/20">+ Novo Funcionário</button>
            </div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs text-green-400 uppercase bg-slate-800/50"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Cargo</th><th class="px-4 py-3">Email</th><th class="px-4 py-3 text-right">Ações</th></tr></thead>
                <tbody id="adminEmployeeList" class="divide-y divide-slate-700/50"></tbody>
            </table></div>
        </div>
    </main>

    <main id="page-employee-dashboard" class="page max-w-6xl mx-auto px-6 pt-16 pb-20">
        <h2 class="text-2xl font-bold text-white mb-6">Mensagens Pendentes</h2>
        <div id="employeeMessages" class="space-y-4"></div>
        <button onclick="employeeLogout()" class="mt-6 bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded">Sair</button>
    </main>
    <div id="adminCatalogModal" class="modal-overlay" onclick="closeAdminCatalogModal(event)">
        <div class="glass-card modal-content w-full max-w-5xl mx-4 flex flex-col md:flex-row overflow-hidden bg-slate-900/95 border-amber-500/40" onclick="event.stopPropagation()">
            <div class="w-full md:w-1/2 h-64 md:h-auto relative bg-slate-800 flex items-center justify-center group border-r border-slate-700/50">
                <img id="itemPreview" src="" class="absolute inset-0 w-full h-full object-cover hidden z-0">
                <div class="absolute inset-0 bg-slate-900/40 group-hover:bg-slate-900/60 transition-all z-10 flex flex-col items-center justify-center backdrop-blur-[2px]">
                    <label for="itemImageFile" class="cursor-pointer bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold py-3 px-6 rounded-xl transition-all shadow-lg transform group-hover:scale-105 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Carregar Imagem
                    </label>
                    <input type="file" id="itemImageFile" accept="image/*" class="hidden">
                    <p class="text-xs text-amber-200 mt-3 font-medium bg-black/50 px-3 py-1 rounded-full">Recomendado: Imagens Horizontais</p>
                </div>
            </div>
            
            <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center relative">
                <button onclick="closeAdminCatalogModal()" class="absolute top-6 right-6 text-slate-400 hover:text-white transition-colors text-2xl font-bold z-20">✕</button>
                <span class="text-amber-400 text-xs font-bold uppercase tracking-widest mb-2 border border-amber-500/30 bg-amber-500/10 w-fit px-3 py-1 rounded-full">Gestão de Catálogo</span>
                <h3 class="text-3xl font-bold text-white mb-6 leading-tight" id="catalogModalTitle">Adicionar Item</h3>
                <input type="hidden" id="editCatalogId" value="">
                <div class="space-y-5 mb-8">
                    <div><label class="block text-xs font-bold text-amber-400 uppercase mb-2">Tipo de Item</label><select id="itemType" class="form-input bg-slate-950 focus:border-amber-400"><option value="produto">📦 Produto (Equipamento)</option><option value="servico">🔧 Serviço Técnico</option></select></div>
                    <div><label class="block text-xs font-bold text-amber-400 uppercase mb-2">Título do Item</label><input type="text" id="itemTitle" class="form-input focus:border-amber-400" placeholder="Ex: CLP Siemens S7-1200"></div>
                    <div><label class="block text-xs font-bold text-amber-400 uppercase mb-2">Descrição Detalhada</label><textarea id="itemDesc" class="form-input h-32 focus:border-amber-400 custom-scrollbar" placeholder="Insira as especificações..."></textarea></div>
                </div>
                <div class="flex gap-3 mt-auto border-t border-white/5 pt-6">
                    <button onclick="closeAdminCatalogModal()" class="flex-1 bg-slate-800 text-white font-bold py-3.5 rounded-xl hover:bg-slate-700 transition-colors border border-slate-700">Cancelar</button>
                    <button onclick="saveCatalogItem()" class="flex-1 bg-amber-500 text-slate-900 font-bold py-3.5 rounded-xl hover:bg-amber-400 transition-colors shadow-lg shadow-amber-500/20">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <!-- NOVO MODAL: Edição de Projetos -->
    <div id="adminProjectModal" class="modal-overlay" onclick="closeAdminProjectModal(event)">
        <div class="glass-card modal-content w-full max-w-5xl mx-4 flex flex-col md:flex-row overflow-hidden bg-slate-900/95 border-blue-500/40" onclick="event.stopPropagation()">
            <div class="w-full md:w-1/2 h-64 md:h-auto relative bg-slate-800 flex items-center justify-center group border-r border-slate-700/50">
                <img id="projPreview" src="" class="absolute inset-0 w-full h-full object-cover hidden z-0">
                <div class="absolute inset-0 bg-slate-900/40 group-hover:bg-slate-900/60 transition-all z-10 flex flex-col items-center justify-center backdrop-blur-[2px]">
                    <label for="projImageFile" class="cursor-pointer bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg transform group-hover:scale-105 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Carregar Imagem
                    </label>
                    <input type="file" id="projImageFile" accept="image/*" class="hidden">
                    <p class="text-xs text-blue-200 mt-3 font-medium bg-black/50 px-3 py-1 rounded-full">Recomendado: Imagens Horizontais</p>
                </div>
            </div>
            
            <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center relative">
                <button onclick="closeAdminProjectModal()" class="absolute top-6 right-6 text-slate-400 hover:text-white transition-colors text-2xl font-bold z-20">✕</button>
                <span class="text-blue-400 text-xs font-bold uppercase tracking-widest mb-2 border border-blue-500/30 bg-blue-500/10 w-fit px-3 py-1 rounded-full">Gestão de Projetos</span>
                <h3 class="text-3xl font-bold text-white mb-6 leading-tight" id="projectModalTitle">Adicionar Projeto</h3>
                <input type="hidden" id="editProjectId" value="">
                <div class="space-y-4 mb-6 overflow-y-auto pr-2 custom-scrollbar" style="max-height: 40vh;">
                    <div><label class="block text-xs font-bold text-blue-400 uppercase mb-1.5">Setor / Categoria</label><input type="text" id="projCat" class="form-input focus:border-blue-400" placeholder="Ex: Farmacêutica"></div>
                    <div><label class="block text-xs font-bold text-blue-400 uppercase mb-1.5">Título do Projeto</label><input type="text" id="projTitle" class="form-input focus:border-blue-400" placeholder="Ex: Automação de Linha 1"></div>
                    <div><label class="block text-xs font-bold text-blue-400 uppercase mb-1.5">Descrição Completa</label><textarea id="projDesc" class="form-input h-24 focus:border-blue-400 custom-scrollbar" placeholder="Detalhes da implementação..."></textarea></div>
                    <div><label class="block text-xs font-bold text-green-400 uppercase mb-1.5">Resultados Obtidos</label><textarea id="projResults" class="form-input h-20 focus:border-green-400 custom-scrollbar" placeholder="Ex: Aumento de 30% na produção..."></textarea></div>
                </div>
                <div class="flex gap-3 mt-auto pt-4 border-t border-white/5">
                    <button onclick="closeAdminProjectModal()" class="flex-1 bg-slate-800 text-white font-bold py-3.5 rounded-xl hover:bg-slate-700 transition-colors border border-slate-700">Cancelar</button>
                    <button onclick="saveProjectItem()" class="flex-1 bg-blue-600 text-white font-bold py-3.5 rounded-xl hover:bg-blue-500 transition-colors shadow-lg shadow-blue-500/20">Salvar Projeto</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualização de Projeto (Público) -->
    <div id="projectModal" class="modal-overlay" onclick="closeProjectModal(event)">
        <div class="glass-card modal-content w-full max-w-5xl mx-4 flex flex-col md:flex-row overflow-hidden bg-slate-900/90 border-cyan-500/30" onclick="event.stopPropagation()">
            <div class="w-full md:w-1/2 h-64 md:h-auto relative bg-slate-800"><img id="modalImg" src="" class="w-full h-full object-cover"><div class="absolute inset-0 bg-gradient-to-r from-transparent to-slate-900/90 hidden md:block"></div><div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 to-transparent md:hidden"></div></div>
            <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center relative"><button onclick="closeProjectModal()" class="absolute top-6 right-6 text-slate-400 hover:text-white transition-colors text-2xl font-bold z-20">✕</button><span id="modalCat" class="text-cyan-400 text-xs font-bold uppercase tracking-widest mb-2 border border-cyan-500/30 bg-cyan-500/10 w-fit px-3 py-1 rounded-full"></span><h3 id="modalTitle" class="text-3xl font-bold text-white mb-6 leading-tight"></h3><div class="space-y-4 mb-8"><p id="modalDesc" class="text-slate-300 text-sm leading-relaxed"></p></div><div class="bg-[#020617] p-5 rounded-xl border border-slate-700 mt-auto"><h4 class="text-white font-bold text-sm mb-3 flex items-center gap-2"><svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Resultados</h4><p id="modalResults" class="text-cyan-100 text-sm leading-relaxed"></p></div></div>
        </div>
    </div>

    <!-- NOVO MODAL: Edição de Funcionários -->
    <div id="adminEmployeeModal" class="modal-overlay" onclick="closeAdminEmployeeModal(event)">
        <div class="glass-card modal-content w-full max-w-4xl mx-4 bg-slate-900/95 border-green-500/40 p-8" onclick="event.stopPropagation()">
            <button onclick="closeAdminEmployeeModal()" class="absolute top-6 right-6 text-slate-400 hover:text-white transition-colors text-2xl font-bold z-20">✕</button>
            <span class="text-green-400 text-xs font-bold uppercase tracking-widest mb-2 border border-green-500/30 bg-green-500/10 w-fit px-3 py-1 rounded-full">Gestão de Funcionários</span>
            <h3 class="text-3xl font-bold text-white mb-6 leading-tight" id="employeeModalTitle">Adicionar Funcionário</h3>
            <input type="hidden" id="editEmployeeId" value="">
            <div class="space-y-5 mb-8">
                <div><label class="block text-xs font-bold text-green-400 uppercase mb-2">Nome Completo</label><input type="text" id="employeeName" class="form-input focus:border-green-400" placeholder="Ex: João Silva"></div>
                <div><label class="block text-xs font-bold text-green-400 uppercase mb-2">Cargo</label><input type="text" id="employeeRole" class="form-input focus:border-green-400" placeholder="Ex: Engenheiro de Automação"></div>
                <div><label class="block text-xs font-bold text-green-400 uppercase mb-2">Email</label><input type="email" id="employeeEmail" class="form-input focus:border-green-400" placeholder="funcionario@autobot.com"></div>
                <div><label class="block text-xs font-bold text-green-400 uppercase mb-2">Senha</label><input type="password" id="employeePassword" class="form-input focus:border-green-400" placeholder="••••••••"></div>
                <div><label class="block text-xs font-bold text-green-400 uppercase mb-2">Especialidade</label><textarea id="employeeSpecialty" class="form-input h-20 focus:border-green-400 custom-scrollbar" placeholder="Áreas de expertise..."></textarea></div>
            </div>
            <div class="flex gap-3 mt-auto border-t border-white/5 pt-6">
                <button onclick="closeAdminEmployeeModal()" class="flex-1 bg-slate-800 text-white font-bold py-3.5 rounded-xl hover:bg-slate-700 transition-colors border border-slate-700">Cancelar</button>
                <button onclick="saveEmployee()" class="flex-1 bg-green-500 text-slate-900 font-bold py-3.5 rounded-xl hover:bg-green-400 transition-colors shadow-lg shadow-green-500/20">Salvar Funcionário</button>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- NOVA INTERFACE DO CHAT (Estilo Print)      -->
    <!-- ========================================== -->
    <div class="chat-trigger" id="openChat" onclick="handleChatRequest()">
        <div class="w-full h-full rounded-full bg-[#0056D2] flex items-center justify-center overflow-hidden border-2 border-white/20 relative">
            <!-- IMAGEM DO GUAXINIM (BOTÃO FLUTUANTE) -->
            <img src="avatar.jpg" alt="Avatar" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=Raccoon&backgroundColor=transparent'">
        </div>
        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 border-2 border-[#020617] rounded-full animate-pulse shadow-[0_0_10px_#22c55e]"></div>
    </div>

    <div id="chatWindow">
        <div class="chat-header">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border border-purple-500/30 bg-slate-800 flex items-center justify-center overflow-hidden shadow-[0_0_10px_rgba(139,92,246,0.3)]">
                    <!-- IMAGEM DO AVATAR (CABEÇALHO) -->
                    <img src="avatar.jpg" alt="Avatar" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=Raccoon&backgroundColor=transparent'">
                </div>
                <div>
                    <h4 class="text-white font-bold text-sm tracking-tight leading-none mb-1 flex items-center gap-2" id="chatBotName">AutoBot AI <div class="status-dot relative top-auto shadow-none"></div></h4>
                    <p class="text-[10px] text-cyan-400 uppercase font-bold leading-none">Assistente Online</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button id="speakToEmployee" class="text-slate-300 hover:text-white text-lg transition-colors" title="Falar com funcionário" onclick="showEmployeeSelection()">👤</button>
                <button id="closeChat" class="text-slate-400 hover:text-white transition-colors text-lg font-bold p-1">✕</button>
            </div>
        </div>
        
        <div class="chat-body" id="chatContainer">
            <div class="flex items-end gap-2 w-full justify-start mb-2 animate-[slideIn_0.3s_ease-out]">
                <div class="w-8 h-8 rounded-full border border-purple-500/30 flex-shrink-0 bg-slate-800 flex items-center justify-center overflow-hidden shadow-[0_0_10px_rgba(139,92,246,0.3)]">
                    <!-- IMAGEM DO AVATAR (PRIMEIRA MENSAGEM) -->
                    <img src="avatar.jpg" alt="Avatar" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=Raccoon&backgroundColor=transparent'">
                </div>
                <div class="msg msg-bot" id="welcomeMsg">Olá! Sou o assistente inteligente da AutoBot. Em que posso ajudar na sua automação hoje?</div>
            </div>
            
            <div class="flex items-end gap-2 w-full justify-start mb-2 hidden" id="typingIndicatorWrapper">
                <div class="w-8 h-8 rounded-full border border-purple-500/30 flex-shrink-0 bg-slate-800 flex items-center justify-center overflow-hidden shadow-[0_0_10px_rgba(139,92,246,0.3)]">
                    <!-- IMAGEM DO AVATAR (A PENSAR) -->
                    <img src="avatar.jpg" alt="Avatar" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=Raccoon&backgroundColor=transparent'">
                </div>
                <div class="typing-indicator active" id="typingIndicator"><span></span><span></span><span></span></div>
            </div>
        </div>

        <div class="chat-footer">
            <input type="text" id="userInput" class="flex-1 bg-slate-900/80 border border-white/10 rounded-full py-3 px-5 text-white text-sm outline-none focus:border-purple-500/50 transition-all placeholder:text-slate-500" placeholder="Escreva a sua dúvida aqui...">
            <button id="sendMessage" class="bg-gradient-to-br from-cyan-400 to-purple-600 hover:from-cyan-300 hover:to-purple-500 w-11 h-11 flex flex-shrink-0 items-center justify-center rounded-full text-white transition-all transform hover:scale-105 shadow-lg shadow-purple-500/30">
                <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
            </button>
        </div>
    </div>

    <script>
        // Efeitos Visuais Background
        const glow = document.getElementById('mouseGlow');
        document.addEventListener('mousemove', (e) => { glow.style.transform = `translate(${e.clientX - 300}px, ${e.clientY - 300}px)`; });

        let isAdmin = false;
        let selectedEmployee = null; // Funcionário selecionado para chat
        let isEmployeeMode = false; // Se está no modo de falar com funcionário

        function navigate(id) {
            if (id === 'admin' && !isAdmin) { alert('Acesso restrito a administradores.'); return navigate('home'); }
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active-nav'));
            const targetPage = document.getElementById(`page-${id}`);
            if (targetPage) targetPage.classList.add('active');
            if (document.getElementById(`nav-${id}`)) document.getElementById(`nav-${id}`).classList.add('active-nav');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            document.getElementById('chatWindow').classList.remove('active');
        }

        // --- SISTEMA DE DADOS: CATÁLOGO, PROJETOS E FUNCIONÁRIOS ---
        let catalogItems = [];
        let projectItems = [];
        let employees = [];
        let clients = [];
        let pendingMessages = []; // Mensagens aguardando resposta de funcionários

        const defaultCatalogItems = [
            { id: 1, type: 'produto', title: 'CLP Siemens S7-1200', desc: 'Controlador Lógico Programável compacto para automação de máquinas e processos industriais. Ideal para aplicações de médio porte com alta confiabilidade.', image: 'https://images.unsplash.com/photo-1581092335397-9583eb92d232?auto=format&fit=crop&w=600' },
            { id: 2, type: 'produto', title: 'Sensor de Proximidade Indutivo', desc: 'Sensor não-contato para detecção de metais em linhas de produção. Resistente a poeira e umidade, com alcance de até 15mm.', image: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=600' },
            { id: 3, type: 'produto', title: 'Atuador Pneumático', desc: 'Cilindro pneumático de dupla ação para controle preciso de movimentos em equipamentos industriais. Pressão máxima de 10 bar.', image: 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=600' },
            { id: 4, type: 'produto', title: 'Robô Colaborativo UR5', desc: 'Robô colaborativo de 6 eixos para tarefas de pick-and-place e montagem. Payload de 5kg, programável via interface intuitiva.', image: 'https://images.unsplash.com/photo-1565514020179-026b92b84bb6?auto=format&fit=crop&w=600' },
            { id: 5, type: 'produto', title: 'Controlador de Motores VFD', desc: 'Inversor de frequência para controle variável de velocidade de motores trifásicos. Eficiência energética e proteção contra sobrecargas.', image: 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=600' },
            { id: 6, type: 'produto', title: 'Sistema de Visão Industrial', desc: 'Câmera inteligente com processamento de imagem para inspeção de qualidade e orientação de peças. Resolução 5MP com iluminação integrada.', image: 'https://images.unsplash.com/photo-1518709268805-4e9042af2176?auto=format&fit=crop&w=600' },
            { id: 7, type: 'servico', title: 'Consultoria em Automação', desc: 'Análise completa de processos industriais e recomendação de soluções de automação personalizadas. Inclui estudo de viabilidade e ROI.', image: 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=600' },
            { id: 8, type: 'servico', title: 'Manutenção Preventiva de Equipamentos', desc: 'Programa de manutenção preventiva para sistemas automatizados, reduzindo downtime e aumentando vida útil dos equipamentos.', image: 'https://images.unsplash.com/photo-1581092334651-ddf26d9a09d0?auto=format&fit=crop&w=600' },
            { id: 9, type: 'servico', title: 'Integração de Sistemas SCADA', desc: 'Implementação e integração de sistemas de supervisão e controle de dados industriais. Compatível com protocolos Modbus e Profinet.', image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=600' },
            { id: 10, type: 'servico', title: 'Treinamento em PLC Programming', desc: 'Cursos práticos de programação de controladores lógicos, incluindo linguagens Ladder, FBD e SCL. Certificação incluída.', image: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600' },
            { id: 11, type: 'servico', title: 'Otimização de Processos Industriais', desc: 'Análise e otimização de linhas de produção para maximizar eficiência e reduzir custos operacionais. Utiliza ferramentas Lean e Six Sigma.', image: 'https://images.unsplash.com/photo-1581092795360-fd1ca04f0952?auto=format&fit=crop&w=600' },
            { id: 12, type: 'servico', title: 'Suporte Técnico 24/7', desc: 'Suporte remoto e presencial 24 horas por dia para resolução de problemas em sistemas automatizados. Contrato SLA garantido.', image: 'https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=600' }
        ];

        const defaultProjects = [
            { id: 1, cat: 'Setor Automóvel', title: 'Linha de Montagem Robotizada', image: 'https://images.unsplash.com/photo-1565514020179-026b92b84bb6?auto=format&fit=crop&w=1200', desc: 'Projeto chave-na-mão para a montagem de chassis automóveis. Integrou 12 células robóticas de alta precisão em rede Profinet.', results: 'Aumento de 35% na eficiência. Redução de falhas em 99%.' },
            { id: 2, cat: 'Energia', title: 'Otimização de Smart Grid', image: 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=1200', desc: 'Modernização de centro de controlo de energia com Sistema SCADA avançado e algoritmos de previsão.', results: 'Poupança de 22% na rede. Resposta a falhas baixou para 3 minutos.' }
        ];

        window.onload = () => {
            const savedCat = localStorage.getItem('autobot_catalog');
            const savedProj = localStorage.getItem('autobot_projects');
            const savedEmp = localStorage.getItem('autobot_employees');
            const savedCli = localStorage.getItem('autobot_clients');
            const savedMsg = localStorage.getItem('autobot_pending_messages');
            catalogItems = savedCat ? JSON.parse(savedCat) : defaultCatalogItems;
            projectItems = savedProj ? JSON.parse(savedProj) : defaultProjects;
            employees = savedEmp ? JSON.parse(savedEmp) : [];
            clients = savedCli ? JSON.parse(savedCli) : [];
            pendingMessages = savedMsg ? JSON.parse(savedMsg) : [];
            
            const savedCfg = localStorage.getItem('autobot_pro_cfg');
            if (savedCfg) appConfig = JSON.parse(savedCfg);
            
            document.getElementById('cfgName').value = appConfig.name;
            document.getElementById('cfgContext').value = appConfig.context;
            updateUI();

            renderCatalog('all');
            renderProjects();
            renderAdminLists();
        };

        // Imagens Base64 Helper
        function setupImageUpload(inputId, previewId, callback) {
            document.getElementById(inputId).addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.getElementById(previewId).src = event.target.result;
                        document.getElementById(previewId).classList.remove('hidden');
                        callback(event.target.result);
                    };
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById(previewId).classList.add('hidden');
                    callback('');
                }
            });
        }

        let catBase64 = ''; setupImageUpload('itemImageFile', 'itemPreview', val => catBase64 = val);
        let projBase64 = ''; setupImageUpload('projImageFile', 'projPreview', val => projBase64 = val);

        // --- MODAIS ADMIN (Edição/Criação com Layout Dividido) ---
        function openAdminCatalogModal(id = null) {
            const modal = document.getElementById('adminCatalogModal');
            if (id) {
                const item = catalogItems.find(i => i.id == id);
                document.getElementById('editCatalogId').value = item.id;
                document.getElementById('itemType').value = item.type;
                document.getElementById('itemTitle').value = item.title;
                document.getElementById('itemDesc').value = item.desc;
                document.getElementById('itemPreview').src = item.image;
                document.getElementById('itemPreview').classList.remove('hidden');
                catBase64 = item.image;
                document.getElementById('catalogModalTitle').innerText = "Editar Item";
            } else {
                document.getElementById('editCatalogId').value = '';
                document.getElementById('itemTitle').value = '';
                document.getElementById('itemDesc').value = '';
                document.getElementById('itemImageFile').value = '';
                document.getElementById('itemPreview').classList.add('hidden');
                catBase64 = '';
                document.getElementById('catalogModalTitle').innerText = "Adicionar Item";
            }
            modal.classList.add('active');
        }

        function closeAdminCatalogModal(e) {
            if (e && e.target !== document.getElementById('adminCatalogModal') && e.target.tagName !== 'BUTTON') return;
            document.getElementById('adminCatalogModal').classList.remove('active');
        }

        function openAdminProjectModal(id = null) {
            const modal = document.getElementById('adminProjectModal');
            if (id) {
                const p = projectItems.find(i => i.id == id);
                document.getElementById('editProjectId').value = p.id;
                document.getElementById('projCat').value = p.cat;
                document.getElementById('projTitle').value = p.title;
                document.getElementById('projDesc').value = p.desc;
                document.getElementById('projResults').value = p.results;
                document.getElementById('projPreview').src = p.image;
                document.getElementById('projPreview').classList.remove('hidden');
                projBase64 = p.image;
                document.getElementById('projectModalTitle').innerText = "Editar Projeto";
            } else {
                document.getElementById('editProjectId').value = '';
                document.getElementById('projCat').value = '';
                document.getElementById('projTitle').value = '';
                document.getElementById('projDesc').value = '';
                document.getElementById('projResults').value = '';
                document.getElementById('projImageFile').value = '';
                document.getElementById('projPreview').classList.add('hidden');
                projBase64 = '';
                document.getElementById('projectModalTitle').innerText = "Adicionar Projeto";
            }
            modal.classList.add('active');
        }

        function closeAdminProjectModal(e) {
            if (e && e.target !== document.getElementById('adminProjectModal') && e.target.tagName !== 'BUTTON') return;
            document.getElementById('adminProjectModal').classList.remove('active');
        }


        // --- GESTÃO DE CATÁLOGO ---
        function renderCatalog(filter) {
            const grid = document.getElementById('catalogGrid'); grid.innerHTML = '';
            const items = filter === 'all' ? catalogItems : catalogItems.filter(i => i.type === filter);
            if (!items.length) return grid.innerHTML = `<p class="text-slate-500 italic">Nenhum item nesta categoria.</p>`;
            items.forEach(item => {
                const badgeColor = item.type === 'produto' ? 'bg-cyan-500/20 text-cyan-400' : 'bg-purple-500/20 text-purple-400';
                const badgeIcon = item.type === 'produto' ? '📦 Produto' : '🔧 Serviço';
                grid.innerHTML += `
                    <div class="glass-card overflow-hidden group flex flex-col h-full">
                        <div class="h-48 relative overflow-hidden bg-slate-800"><img src="${item.image}" class="w-full h-full object-cover opacity-80 group-hover:scale-110 group-hover:opacity-100 transition-all"></div>
                        <div class="p-6 flex flex-col flex-1">
                            <span class="inline-flex w-fit px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider mb-3 ${badgeColor}">${badgeIcon}</span>
                            <h3 class="text-xl font-bold text-white mb-2">${item.title}</h3>
                            <p class="text-slate-400 text-sm mb-6 flex-1 line-clamp-3">${item.desc}</p>
                            <button onclick="handleChatRequest('Quero cotação para: ${item.title}')" class="w-full py-2 border border-cyan-500/30 text-cyan-400 hover:bg-cyan-500 hover:text-slate-900 rounded-lg transition-colors">Cotação</button>
                        </div>
                    </div>`;
            });
        }

        function filterCat(type) {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(`btn-filter-${type}`).classList.add('active'); renderCatalog(type);
        }

        function saveCatalogItem() {
            const id = document.getElementById('editCatalogId').value;
            const type = document.getElementById('itemType').value;
            const title = document.getElementById('itemTitle').value.trim();
            const desc = document.getElementById('itemDesc').value.trim();
            
            if (!title || !desc) return alert("Preencha o título e descrição!");
            
            if (id) {
                const idx = catalogItems.findIndex(i => i.id == id);
                catalogItems[idx] = { ...catalogItems[idx], type, title, desc, image: catBase64 || catalogItems[idx].image };
            } else {
                catalogItems.push({ id: Date.now(), type, title, desc, image: catBase64 || 'https://images.unsplash.com/photo-1581092335397-9583eb92d232?auto=format&fit=crop&w=600' });
            }
            
            try { localStorage.setItem('autobot_catalog', JSON.stringify(catalogItems)); } 
            catch(e) { alert("Imagem muito pesada para guardar localmente!"); if(!id) catalogItems.pop(); return; }
            
            closeAdminCatalogModal(); renderCatalog('all'); renderAdminLists();
        }

        function deleteCatalog(id) {
            if(!confirm("Excluir item do catálogo?")) return;
            catalogItems = catalogItems.filter(i => i.id != id);
            localStorage.setItem('autobot_catalog', JSON.stringify(catalogItems));
            renderCatalog('all'); renderAdminLists();
        }

        // --- GESTÃO DE PROJETOS ---
        function renderProjects() {
            const grid = document.getElementById('projectGrid'); grid.innerHTML = '';
            projectItems.forEach(p => {
                grid.innerHTML += `
                    <div class="glass-card project-card group" onclick="openProjectModal(${p.id})">
                        <div class="h-52 relative overflow-hidden bg-slate-800"><img src="${p.image}" class="w-full h-full object-cover opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-700"></div>
                        <div class="p-6">
                            <span class="text-xs text-cyan-400 uppercase tracking-widest font-bold">${p.cat}</span>
                            <h3 class="text-xl font-bold text-white mt-2 mb-3 group-hover:text-cyan-400">${p.title}</h3>
                            <p class="text-slate-400 text-sm line-clamp-2">${p.desc}</p>
                        </div>
                    </div>`;
            });
        }

        function saveProjectItem() {
            const id = document.getElementById('editProjectId').value;
            const cat = document.getElementById('projCat').value.trim();
            const title = document.getElementById('projTitle').value.trim();
            const desc = document.getElementById('projDesc').value.trim();
            const results = document.getElementById('projResults').value.trim();
            
            if (!title || !cat) return alert("Preencha Categoria e Título!");
            
            if (id) {
                const idx = projectItems.findIndex(p => p.id == id);
                projectItems[idx] = { ...projectItems[idx], cat, title, desc, results, image: projBase64 || projectItems[idx].image };
            } else {
                projectItems.push({ id: Date.now(), cat, title, desc, results, image: projBase64 || 'https://images.unsplash.com/photo-1565514020179-026b92b84bb6?auto=format&fit=crop&w=600' });
            }
            
            try { localStorage.setItem('autobot_projects', JSON.stringify(projectItems)); } 
            catch(e) { alert("Imagem muito pesada para guardar localmente!"); if(!id) projectItems.pop(); return; }
            
            closeAdminProjectModal(); renderProjects(); renderAdminLists();
        }

        function deleteProject(id) {
            if(!confirm("Excluir projeto?")) return;
            projectItems = projectItems.filter(p => p.id != id);
            localStorage.setItem('autobot_projects', JSON.stringify(projectItems));
            renderProjects(); renderAdminLists();
        }

        function openProjectModal(id) {
            const data = projectItems.find(p => p.id == id);
            if (!data) return;
            document.getElementById('modalImg').src = data.image;
            document.getElementById('modalCat').innerText = data.cat;
            document.getElementById('modalTitle').innerText = data.title;
            document.getElementById('modalDesc').innerHTML = data.desc;
            document.getElementById('modalResults').innerHTML = data.results;
            document.getElementById('projectModal').classList.add('active');
        }

        function closeProjectModal(e) { if (!e || e.target.id === 'projectModal') document.getElementById('projectModal').classList.remove('active'); }

        // --- GESTÃO DE FUNCIONÁRIOS ---
        function openAdminEmployeeModal(id = null) {
            const modal = document.getElementById('adminEmployeeModal');
            if (id) {
                const emp = employees.find(e => e.id == id);
                document.getElementById('editEmployeeId').value = emp.id;
                document.getElementById('employeeName').value = emp.name;
                document.getElementById('employeeRole').value = emp.role;
                document.getElementById('employeeEmail').value = emp.email;
                document.getElementById('employeePassword').value = emp.password;
                document.getElementById('employeeSpecialty').value = emp.specialty;
                document.getElementById('employeeModalTitle').innerText = "Editar Funcionário";
            } else {
                document.getElementById('editEmployeeId').value = '';
                document.getElementById('employeeName').value = '';
                document.getElementById('employeeRole').value = '';
                document.getElementById('employeeEmail').value = '';
                document.getElementById('employeePassword').value = '';
                document.getElementById('employeeSpecialty').value = '';
                document.getElementById('employeeModalTitle').innerText = "Adicionar Funcionário";
            }
            modal.classList.add('active');
        }

        function closeAdminEmployeeModal(e) {
            if (e && e.target !== document.getElementById('adminEmployeeModal') && e.target.tagName !== 'BUTTON') return;
            document.getElementById('adminEmployeeModal').classList.remove('active');
        }

        function saveEmployee() {
            const id = document.getElementById('editEmployeeId').value;
            const name = document.getElementById('employeeName').value.trim();
            const role = document.getElementById('employeeRole').value.trim();
            const email = document.getElementById('employeeEmail').value.trim();
            const password = document.getElementById('employeePassword').value.trim();
            const specialty = document.getElementById('employeeSpecialty').value.trim();
            
            if (!name || !role || !email || !password) return alert("Preencha todos os campos obrigatórios!");
            
            // Verificar se o email já existe em funcionários ou clientes
            const existingEmployee = employees.find(e => e.email === email && e.id != id);
            const existingClient = clients.find(c => c.email === email);
            if (existingEmployee || existingClient) return alert("Este email já está cadastrado!");
            
            if (id) {
                const idx = employees.findIndex(e => e.id == id);
                employees[idx] = { ...employees[idx], name, role, email, password, specialty };
            } else {
                employees.push({ id: Date.now(), name, role, email, password, specialty });
            }
            
            localStorage.setItem('autobot_employees', JSON.stringify(employees));
            closeAdminEmployeeModal(); renderAdminLists();
        }

        function deleteEmployee(id) {
            if(!confirm("Excluir funcionário?")) return;
            employees = employees.filter(e => e.id != id);
            localStorage.setItem('autobot_employees', JSON.stringify(employees));
            renderAdminLists();
        }

        // --- RENDERIZAR LISTAS ADMIN ---
        function renderAdminLists() {
            const cl = document.getElementById('adminCatalogList'); cl.innerHTML = '';
            catalogItems.forEach(i => cl.innerHTML += `<tr class="hover:bg-slate-800/30"><td class="px-4 py-3">${i.type}</td><td class="px-4 py-3">${i.title}</td><td class="px-4 py-3 text-right"><button onclick="openAdminCatalogModal(${i.id})" class="text-amber-400 hover:text-amber-300 mr-3">Editar</button><button onclick="deleteCatalog(${i.id})" class="text-red-400 hover:text-red-300">Excluir</button></td></tr>`);
            
            const pl = document.getElementById('adminProjectList'); pl.innerHTML = '';
            projectItems.forEach(p => pl.innerHTML += `<tr class="hover:bg-slate-800/30"><td class="px-4 py-3">${p.cat}</td><td class="px-4 py-3">${p.title}</td><td class="px-4 py-3 text-right"><button onclick="openAdminProjectModal(${p.id})" class="text-blue-400 hover:text-blue-300 mr-3">Editar</button><button onclick="deleteProject(${p.id})" class="text-red-400 hover:text-red-300">Excluir</button></td></tr>`);

            const el = document.getElementById('adminEmployeeList'); el.innerHTML = '';
            employees.forEach(e => el.innerHTML += `<tr class="hover:bg-slate-800/30"><td class="px-4 py-3">${e.name}</td><td class="px-4 py-3">${e.role}</td><td class="px-4 py-3">${e.email}</td><td class="px-4 py-3 text-right"><button onclick="openAdminEmployeeModal(${e.id})" class="text-green-400 hover:text-green-300 mr-3">Editar</button><button onclick="deleteEmployee(${e.id})" class="text-red-400 hover:text-red-300">Excluir</button></td></tr>`);
        }

        // --- AUTENTICAÇÃO ---
        let isLoggedIn = false; let pendingUserMessage = ""; let currentAuthMode = 'login';

        function toggleAuthMode(mode) {
            currentAuthMode = mode;
            const isLogin = mode === 'login';
            document.getElementById('tab-login').className = isLogin ? "text-white border-b-2 border-white pb-1 font-bold transition-all" : "text-blue-200 pb-1 hover:text-white transition-all font-bold";
            document.getElementById('tab-register').className = !isLogin ? "text-white border-b-2 border-white pb-1 font-bold transition-all" : "text-blue-200 pb-1 hover:text-white transition-all font-bold";
            
            const fn = document.getElementById('field-name'); const fc = document.getElementById('field-confirm-password');
            if (isLogin) {
                fn.classList.add('hidden'); fc.classList.add('hidden');
                document.getElementById('authName').removeAttribute('required'); document.getElementById('authConfirmPassword').removeAttribute('required');
                document.getElementById('authSubmitBtn').innerText = "Entrar";
            } else {
                fn.classList.remove('hidden'); fc.classList.remove('hidden'); fn.style.maxHeight = '100px'; fc.style.maxHeight = '100px';
                document.getElementById('authName').setAttribute('required', 'true'); document.getElementById('authConfirmPassword').setAttribute('required', 'true');
                document.getElementById('authSubmitBtn').innerText = "Cadastrar";
            }
        }

        function processAuth(e) {
            e.preventDefault();
            const email = document.getElementById('authEmail').value;
            const password = document.getElementById('authPassword').value;
            const name = document.getElementById('authName').value;

            if (currentAuthMode === 'register') {
                // Validar preenchimento obrigatório
                if (!name || !email || !password) return alert("Preencha todos os campos obrigatórios!");
                
                // Verificar se email já existe
                const existingEmployee = employees.find(e => e.email === email);
                const existingClient = clients.find(c => c.email === email);
                if (existingEmployee || existingClient) return alert("Este email já está cadastrado!");
                
                // Salvar novo cliente
                clients.push({ id: Date.now(), name, email, password });
                localStorage.setItem('autobot_clients', JSON.stringify(clients));
            } else if (currentAuthMode === 'login') {
                // Verificar se é funcionário
                const emp = employees.find(e => e.email === email);
                if (emp) {
                    if (emp.password === password) {
                        currentEmployee = emp;
                        navigate('employee-dashboard');
                        renderEmployeeMessages();
                        return;
                    } else {
                        return alert('Senha de funcionário incorreta!');
                    }
                }

                // Verificar se é admin
                if (email === 'admin@autobot.com') {
                    if (password === 'admin123') { 
                        isAdmin = true; 
                        document.getElementById('nav-admin').classList.remove('hidden'); 
                    } else { 
                        return alert('Senha de administrador incorreta!'); 
                    }
                } else {
                    // Login de cliente - verificar se existe
                    const client = clients.find(c => c.email === email);
                    if (!client) return alert("Email não cadastrado! Faça login pela aba CADASTRAR.");
                    if (client.password !== password) return alert("Senha incorreta!");
                    isAdmin = false;
                    document.getElementById('nav-admin').classList.add('hidden');
                }
            }

            // Para registro ou login bem-sucedido
            if (currentAuthMode === 'register' || currentAuthMode === 'login') {
                const toast = document.getElementById('loginToast');
                toast.innerText = currentAuthMode === 'register' ? "Conta criada com sucesso!" : "Acesso efetuado com sucesso!";
                toast.classList.add('toast-enter');
                
                isLoggedIn = true;
                const loginBtn = document.getElementById('nav-loginBtn');
                loginBtn.innerText = "Sair";
                loginBtn.classList.replace("btn-primary", "border-red-500");
                loginBtn.onclick = logout;

                setTimeout(() => {
                    toast.classList.remove('toast-enter');
                    if (isAdmin) navigate('admin');
                    else {
                        navigate('catalogo'); document.getElementById('chatWindow').classList.add('active');
                        if(pendingUserMessage) { document.getElementById('userInput').value = pendingUserMessage; handleSend(); pendingUserMessage = ""; }
                    }
                }, 1500);
            }
        }

        function logout() {
            if(!confirm("Deseja terminar a sessão?")) return;
            isLoggedIn = false; isAdmin = false; currentEmployee = null;
            const loginBtn = document.getElementById('nav-loginBtn');
            loginBtn.innerText = "Fazer Login";
            loginBtn.classList.replace("border-red-500", "btn-primary");
            loginBtn.onclick = () => navigate('login');
            document.getElementById('nav-admin').classList.add('hidden');
            document.getElementById('chatWindow').classList.remove('active');
            navigate('home');
        }

        function handleChatRequest(initialMsg = "") {
            if(initialMsg) pendingUserMessage = initialMsg;
            if (isLoggedIn) { document.getElementById('chatWindow').classList.add('active'); if (pendingUserMessage) { document.getElementById('userInput').value = pendingUserMessage; handleSend(); pendingUserMessage = ""; } } 
            else navigate('login');
        }

        // --- BACKEND IA COMUNICACÃO (PHP ENDPOINT) ---
        const DEFAULT_CONFIG = { name: "AutoBot", email: "contacto@autobot.com", context: "Especialistas em automação." };
        let appConfig = { ...DEFAULT_CONFIG };
        let chatHistory = [];

        function saveSettings() {
            appConfig.name = document.getElementById('cfgName').value || "AutoBot";
            appConfig.context = document.getElementById('cfgContext').value;
            
            localStorage.setItem('autobot_pro_cfg', JSON.stringify(appConfig));
            updateUI();
            alert("Informações base da IA salvas com sucesso!");
        }

        function showEmployeeSelection() {
            if (employees.length === 0) {
                alert('Nenhum funcionário cadastrado ainda. Entre em contato conosco pelo email ou telefone.');
                return;
            }
            
            // Notificar todos os funcionários automaticamente
            notifyAllEmployees();
        }

        function notifyAllEmployees() {
            // Fechar modal
            document.body.querySelector('.fixed')?.remove();
            
            // Entrar no modo funcionário geral (qualquer funcionário pode responder)
            selectedEmployee = null; // Nenhum funcionário específico
            isEmployeeMode = true;
            document.getElementById('chatBotName').innerText = 'Equipe AutoBot';
            document.querySelector('.status-dot').nextSibling.textContent = 'Suporte Especializado';
            document.getElementById('userInput').placeholder = 'Fale com nossa equipe...';
            
            appendMsg('bot', '✅ Notificação enviada para toda a equipe! O primeiro funcionário disponível entrará em contato.');
        }

        async function handleSend() {
            const input = document.getElementById('userInput'); const val = input.value.trim();
            if(!val) return;
            appendMsg('user', val); input.value = ''; document.getElementById('typingIndicatorWrapper').classList.remove('hidden');
            
            if (isEmployeeMode && selectedEmployee) {
                // Modo funcionário: adicionar à lista de mensagens pendentes
                const message = {
                    id: Date.now(),
                    employeeId: selectedEmployee.id,
                    userMessage: val,
                    timestamp: new Date().toISOString(),
                    status: 'pending'
                };
                pendingMessages.push(message);
                localStorage.setItem('autobot_pending_messages', JSON.stringify(pendingMessages));
                
                setTimeout(() => {
                    document.getElementById('typingIndicatorWrapper').classList.add('hidden');
                    appendMsg('bot', `✅ Sua mensagem foi enviada para ${selectedEmployee.name}. Ele entrará em contato em breve através do email ${selectedEmployee.email} ou telefone.`);
                }, 1500);
                return;
            } else if (isEmployeeMode && !selectedEmployee) {
                // Modo notificação geral: enviar para todos os funcionários
                employees.forEach(emp => {
                    const message = {
                        id: Date.now() + Math.random(), // IDs únicos
                        employeeId: emp.id,
                        userMessage: val,
                        timestamp: new Date().toISOString(),
                        status: 'general',
                        isGeneral: true
                    };
                    pendingMessages.push(message);
                });
                localStorage.setItem('autobot_pending_messages', JSON.stringify(pendingMessages));
                
                setTimeout(() => {
                    document.getElementById('typingIndicatorWrapper').classList.add('hidden');
                    appendMsg('bot', '✅ Sua mensagem foi enviada para toda a equipe! O primeiro funcionário disponível entrará em contato.');
                }, 1500);
                return;
            }
            
            try {
                const catalogContext = catalogItems.map(item => `- ${item.type}: ${item.title} (${item.desc})`).join('\n');
                const systemMsg = { 
                    role: "system", 
                    content: `Você é um consultor de vendas da ${appConfig.name}. Nosso catálogo: \n${catalogContext}\nResponda em Português.` 
                };

                const currentMessages = [systemMsg, ...chatHistory, { role: "user", content: val }];

                // Faz a chamada ao Backend seguro em PHP (Render)
                const response = await fetch('?api=chat', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' }, 
                    body: JSON.stringify({ messages: currentMessages }) 
                });

                if (response.status === 401) throw new Error("A API Key não está configurada no servidor (Render Environment Variables).");
                if (!response.ok) throw new Error(`Erro na API: HTTP ${response.status}`);

                const data = await response.json();
                document.getElementById('typingIndicatorWrapper').classList.add('hidden');
                
                if (data.error) throw new Error(data.error);

                const aiRes = data.choices[0].message.content;
                chatHistory.push({ role: "user", content: val });
                chatHistory.push({ role: "assistant", content: aiRes });
                if(chatHistory.length > 8) chatHistory.shift();
                
                appendMsg('bot', aiRes.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'));
            } catch (e) {
                document.getElementById('typingIndicatorWrapper').classList.add('hidden');
                appendMsg('bot', `❌ Erro de ligação ao servidor: ${e.message}. Nota: O chat necessita do Backend PHP alojado no Render para funcionar com segurança.`);
            }
        }
        
        function appendMsg(role, text) { 
            const c = document.getElementById('chatContainer'); 
            const wrapper = document.createElement('div');
            wrapper.className = `flex items-end gap-2 w-full ${role === 'user' ? 'justify-end' : 'justify-start'} mb-2 animate-[slideIn_0.3s_ease-out]`;
            
            const mascotImg = `<img src="avatar.jpg" alt="Avatar" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/bottts/svg?seed=Raccoon&backgroundColor=transparent'">`;

            if (role === 'bot') {
                wrapper.innerHTML = `
                <div class="w-8 h-8 rounded-full border border-purple-500/30 flex-shrink-0 bg-slate-800 flex items-center justify-center overflow-hidden shadow-[0_0_10px_rgba(139,92,246,0.3)]">
                    ${mascotImg}
                </div>
                <div class="msg msg-bot">${text}</div>`;
            } else {
                wrapper.innerHTML = `<div class="msg msg-user">${text}</div>`;
            }

            c.insertBefore(wrapper, document.getElementById('typingIndicatorWrapper')); 
            c.scrollTop = c.scrollHeight; 
        }

        document.getElementById('closeChat').onclick = () => document.getElementById('chatWindow').classList.remove('active');
        document.getElementById('userInput').onkeydown = (e) => { if(e.key === 'Enter') handleSend(); };

        // --- FUNCIONÁRIOS ---
        let currentEmployee = null;

        function renderEmployeeMessages() {
            const container = document.getElementById('employeeMessages');
            const myMessages = pendingMessages.filter(m => m.employeeId == currentEmployee.id);
            const generalMessages = pendingMessages.filter(m => m.isGeneral && m.status === 'general');
            
            let html = '';
            
            // Mensagens específicas do funcionário
            if (myMessages.length > 0) {
                html += '<h3 class="text-white text-lg font-bold mb-4">Suas Mensagens</h3>';
                html += myMessages.map(m => `
                    <div class="glass-card p-4 mb-4">
                        <p class="text-white"><strong>Mensagem:</strong> ${m.userMessage}</p>
                        <p class="text-slate-400 text-sm">Data: ${new Date(m.timestamp).toLocaleString()}</p>
                        <div class="flex gap-2 mt-2">
                            <button onclick="respondToMessage(${m.id})" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded text-sm">Marcar como Respondida</button>
                            ${getTransferOptions(m)}
                        </div>
                    </div>
                `).join('');
            }
            
            // Mensagens gerais disponíveis
            if (generalMessages.length > 0) {
                html += '<h3 class="text-white text-lg font-bold mb-4 mt-6">Mensagens Gerais (Disponíveis)</h3>';
                html += generalMessages.map(m => `
                    <div class="glass-card p-4 mb-4 border-l-4 border-blue-500">
                        <p class="text-white"><strong>Mensagem Geral:</strong> ${m.userMessage}</p>
                        <p class="text-slate-400 text-sm">Data: ${new Date(m.timestamp).toLocaleString()}</p>
                        <button onclick="assumeGeneralMessage(${m.id})" class="mt-2 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded text-sm">Assumir Esta Mensagem</button>
                    </div>
                `).join('');
            }
            
            if (myMessages.length === 0 && generalMessages.length === 0) {
                html = '<p class="text-slate-400">Nenhuma mensagem pendente.</p>';
            }
            
            container.innerHTML = html;
        }

        function getTransferOptions(message) {
            // Encontrar funcionários com cargo superior (baseado na hierarquia)
            const superiorRoles = {
                'Estagiário': ['Analista', 'Coordenador', 'Gerente', 'Diretor'],
                'Analista': ['Coordenador', 'Gerente', 'Diretor'],
                'Coordenador': ['Gerente', 'Diretor'],
                'Gerente': ['Diretor'],
                'Diretor': []
            };
            
            const superiors = employees.filter(e => 
                e.id !== currentEmployee.id && 
                superiorRoles[currentEmployee.role]?.includes(e.role)
            );
            
            if (superiors.length === 0) return '';
            
            const options = superiors.map(s => `<option value="${s.id}">${s.name} - ${s.role}</option>`).join('');
            return `<select onchange="transferMessage(${message.id}, this.value)" class="bg-slate-600 text-white px-2 py-1 rounded text-sm">
                <option value="">Transferir para...</option>
                ${options}
            </select>`;
        }

        function assumeGeneralMessage(messageId) {
            // Encontrar todas as mensagens gerais com o mesmo userMessage e timestamp
            const message = pendingMessages.find(m => m.id == messageId && m.isGeneral);
            if (!message) return;
            
            // Remover todas as cópias desta mensagem geral
            pendingMessages = pendingMessages.filter(m => 
                !(m.isGeneral && m.userMessage === message.userMessage && m.timestamp === message.timestamp)
            );
            
            // Adicionar como mensagem específica para este funcionário
            const newMessage = {
                id: Date.now(),
                employeeId: currentEmployee.id,
                userMessage: message.userMessage,
                timestamp: message.timestamp,
                status: 'pending'
            };
            pendingMessages.push(newMessage);
            
            localStorage.setItem('autobot_pending_messages', JSON.stringify(pendingMessages));
            renderEmployeeMessages();
            
            alert('Mensagem assumida com sucesso!');
        }

        function transferMessage(messageId, targetEmployeeId) {
            if (!targetEmployeeId) return;
            
            const message = pendingMessages.find(m => m.id == messageId);
            if (!message) return;
            
            message.employeeId = parseInt(targetEmployeeId);
            localStorage.setItem('autobot_pending_messages', JSON.stringify(pendingMessages));
            renderEmployeeMessages();
            
            alert('Mensagem transferida com sucesso!');
        }

        function employeeLogout() {
            currentEmployee = null;
            navigate('home');
        }

        toggleAuthMode('login');
    </script>
</body>
</html>
