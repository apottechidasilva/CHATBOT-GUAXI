<?php
// ============================================================================
// BACKEND SEGURO (Oculto dos utilizadores)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api']) && $_GET['api'] === 'chat') {
    header('Content-Type: application/json');
    
    // A SUA CHAVE FICA AQUI NO SERVIDOR! NINGUÉM CONSEGUE VER.
    // Pode usar a variável de ambiente GROQ_API_KEY ou colar a chave diretamente abaixo.
    $apiKey = getenv('GROQ_API_KEY') ?: 'COLE_A_SUA_CHAVE_GROQ_AQUI';
    
    if (empty($apiKey) || $apiKey === 'COLE_A_SUA_CHAVE_GROQ_AQUI') {
        http_response_code(401);
        echo json_encode(['error' => 'API Key não configurada no servidor.']);
        exit;
    }

    // Receber as mensagens do Frontend
    $input = json_decode(file_get_contents('php://input'), true);
    $messages = isset($input['messages']) ? $input['messages'] : [];
    
    // Preparar o pedido para a API da Groq
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
    
    // Executar e devolver a resposta ao Frontend
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    http_response_code($httpCode);
    echo $response;
    exit;
}
// ============================================================================
// FRONTEND (HTML/CSS/JS)
// ============================================================================
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
            --bg-dark: #020617;
            --glass: rgba(15, 23, 42, 0.8);
            --border: rgba(0, 242, 255, 0.2);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: #f8fafc;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        .grid-background {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(to right, rgba(0, 242, 255, 0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0, 242, 255, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            mask-image: radial-gradient(circle at center, black, transparent 80%);
        }

        .glow {
            position: fixed; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(0, 114, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none; z-index: -1;
            transition: transform 0.1s ease-out;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 24px;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 30px rgba(0, 242, 255, 0.1);
        }

        .page { display: none; animation: fadeInPage 0.5s ease-out forwards; }
        .page.active { display: block; }

        @keyframes fadeInPage {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .nav-link { cursor: pointer; transition: all 0.2s; position: relative; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active-nav { color: var(--primary); font-weight: 600; }
        .nav-link.active-nav::after {
            content: ''; position: absolute; bottom: -4px; left: 0; width: 100%;
            height: 2px; background: var(--primary); box-shadow: 0 0 10px var(--primary);
        }

        .chat-trigger {
            position: fixed; bottom: 30px; right: 30px;
            width: 65px; height: 65px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 10px 25px rgba(0, 114, 255, 0.4);
            z-index: 1000; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .chat-trigger:hover { transform: scale(1.1) rotate(5deg); }

        #chatWindow {
            position: fixed; bottom: 110px; right: 30px;
            width: 420px; height: 600px; max-width: calc(100vw - 60px); max-height: calc(100vh - 160px);
            display: flex; flex-direction: column; z-index: 1001;
            transform: translateY(20px) scale(0.95); opacity: 0; pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        #chatWindow.active { transform: translateY(0) scale(1); opacity: 1; pointer-events: all; }

        .chat-header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            padding: 20px; border-bottom: 1px solid var(--border);
            border-top-left-radius: 24px; border-top-right-radius: 24px;
        }

        .chat-body {
            flex: 1; overflow-y: auto; padding: 20px;
            background: #020617; display: flex; flex-direction: column; gap: 12px;
            scrollbar-width: thin; scrollbar-color: var(--border) transparent;
        }

        .chat-footer {
            padding: 15px; background: #0f172a;
            border-top: 1px solid var(--border);
            border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;
        }

        .msg {
            max-width: 85%; padding: 12px 16px; border-radius: 18px;
            font-size: 14px; line-height: 1.5; animation: fadeIn 0.3s ease;
            word-wrap: break-word;
        }

        .msg-bot {
            align-self: flex-start; background: #1e293b; color: #e2e8f0;
            border-bottom-left-radius: 4px; border-left: 3px solid var(--primary);
        }

        .msg-user {
            align-self: flex-end; background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white; border-bottom-right-radius: 4px;
        }

        .typing-indicator {
            display: none; align-items: center; gap: 4px; padding: 12px 16px;
            background: #1e293b; border-radius: 16px; align-self: flex-start;
        }
        .typing-indicator.active { display: flex; }
        .typing-indicator span {
            width: 6px; height: 6px; background: var(--primary); border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #020617; font-weight: 700; padding: 12px 24px; border-radius: 12px;
            transition: all 0.3s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 242, 255, 0.3); }

        .project-card { overflow: hidden; position: relative; cursor: pointer; }
        
        .form-input {
            width: 100%; background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(0, 242, 255, 0.2); border-radius: 12px;
            padding: 12px 16px; color: white; outline: none; transition: border-color 0.3s;
        }
        .form-input:focus { border-color: var(--primary); }

        .login-input {
            width: 100%; background: white; border: none; border-radius: 8px;
            padding: 12px 16px; color: #1e293b; outline: none; font-weight: 500;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .login-input::placeholder { color: #94a3b8; font-weight: 400; }
        
        .toast-enter { opacity: 1 !important; transform: translateY(0) !important; }

        /* Categorias Sidebar */
        .cat-btn {
            width: 100%; text-align: left; padding: 10px 16px; border-radius: 8px;
            color: #94a3b8; transition: all 0.2s; font-weight: 600;
        }
        .cat-btn:hover { background: rgba(30, 41, 59, 0.8); color: white; }
        .cat-btn.active { background: rgba(0, 242, 255, 0.15); color: var(--primary); }

        /* Modal de Projetos */
        #projectModal {
            position: fixed; inset: 0; z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            background: rgba(2, 6, 23, 0.85); backdrop-filter: blur(8px);
            opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
        }
        #projectModal.active { opacity: 1; pointer-events: auto; }
        .modal-content {
            transform: scale(0.95) translateY(20px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-height: 90vh; overflow-y: auto;
        }
        #projectModal.active .modal-content { transform: scale(1) translateY(0); }
    </style>
</head>
<body>

    <div class="grid-background"></div>
    <div class="glow" id="mouseGlow"></div>

    <!-- Navegação -->
    <nav class="sticky top-0 z-50 p-6 glass-card border-none rounded-none border-b border-cyan-500/10 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center w-full">
            <div class="flex items-center gap-2 font-bold text-2xl cursor-pointer" onclick="navigate('home')">
                <span class="text-cyan-400" id="brandDisplay1">AUTO</span><span id="brandDisplay2">BOT</span>
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

    <!-- PÁGINA: LOGIN / CADASTRO -->
    <main id="page-login" class="page max-w-5xl mx-auto px-6 pt-16 pb-20">
        <div class="flex flex-col md:flex-row rounded-[2rem] overflow-hidden shadow-2xl min-h-[600px] border border-white/10">
            <!-- Lado Esquerdo -->
            <div class="hidden md:flex md:w-1/2 relative bg-slate-900 flex-col justify-center p-12 overflow-hidden">
                <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1581092335397-9583eb92d232?auto=format&fit=crop&w=800')] bg-cover bg-center opacity-40"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-[#020617] to-transparent"></div>
                <div class="relative z-10">
                    <h2 class="text-4xl font-bold text-white mb-4">Junte-se à <span class="text-yellow-400">AutoBot IA</span> ⚡</h2>
                    <p class="text-slate-300 text-lg">Crie a sua conta e inicie a sua jornada na automação industrial inteligente!</p>
                </div>
            </div>

            <!-- Lado Direito -->
            <div class="w-full md:w-1/2 bg-[#2563eb] p-8 md:p-14 flex flex-col justify-center relative">
                <div id="loginToast" class="absolute top-6 right-6 bg-green-500 text-white text-xs font-bold px-4 py-3 rounded-lg shadow-xl opacity-0 transform -translate-y-4 transition-all duration-500 pointer-events-none z-50">
                    Acesso efetuado com sucesso! A redirecionar...
                </div>

                <div class="max-w-xs mx-auto w-full">
                    <div class="flex justify-center gap-8 mb-10 font-bold text-sm">
                        <button id="tab-login" class="text-white border-b-2 border-white pb-1" onclick="toggleAuthMode('login')">ENTRAR</button>
                        <button id="tab-register" class="text-blue-200 pb-1 hover:text-white transition-colors" onclick="toggleAuthMode('register')">CADASTRAR</button>
                    </div>

                    <form id="authForm" onsubmit="processAuth(event)" class="space-y-5">
                        <div id="field-name" class="hidden">
                            <label class="block text-xs font-bold text-white mb-1.5">Nome completo:</label>
                            <input type="text" class="login-input" id="authName" placeholder="Digite o seu nome">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-white mb-1.5">Email:</label>
                            <input type="email" class="login-input" id="authEmail" required placeholder="seu@email.com">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-white mb-1.5">Senha:</label>
                            <input type="password" class="login-input" id="authPassword" required placeholder="••••••••">
                        </div>
                        <div id="field-confirm-password" class="hidden">
                            <label class="block text-xs font-bold text-white mb-1.5">Confirmar Senha:</label>
                            <input type="password" class="login-input" id="authConfirmPassword" placeholder="••••••••">
                        </div>
                        <button type="submit" id="authSubmitBtn" class="w-full bg-white text-[#2563eb] font-bold py-3.5 rounded-lg hover:bg-slate-100 transition-all mt-6 shadow-lg">
                            Entrar
                        </button>
                        <p class="text-center text-blue-200 text-xs mt-4 leading-relaxed">
                            Para testar o painel admin, aceda usando:<br>
                            Email: <strong class="text-white">admin@autobot.com</strong><br>
                            Senha: <strong class="text-white">admin123</strong>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- PÁGINA: HOME -->
    <main id="page-home" class="page active max-w-7xl mx-auto px-6 pt-20 pb-20 text-center">
        <div class="mb-8 inline-block px-4 py-1 rounded-full border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 text-sm font-medium">
            Líderes em Indústria 4.0
        </div>
        <h1 class="text-6xl md:text-8xl font-bold mb-8 text-white leading-tight">
            Engenharia de <br> <span class="text-cyan-400">Próxima Geração.</span>
        </h1>
        <p class="text-slate-400 text-xl max-w-3xl mx-auto mb-12 leading-relaxed" id="homeDesc">
            Transformamos fábricas tradicionais em ecossistemas inteligentes com IA, robótica avançada e automação de alta precisão.
        </p>
        <div class="flex flex-wrap justify-center gap-6">
            <button onclick="navigate('projetos')" class="btn-primary text-lg px-8 py-4">Ver Nossos Projetos</button>
            <button onclick="handleChatRequest()" class="px-8 py-4 rounded-xl glass-card border border-cyan-500/20 hover:bg-cyan-500/10 transition-all font-semibold text-white shadow-lg shadow-cyan-500/10">Falar com Consultor IA</button>
        </div>
    </main>

    <!-- PÁGINA: CATÁLOGO (Produtos e Serviços) -->
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
                
                <div id="catalogGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Gerado via JavaScript -->
                </div>
            </div>
        </div>
    </main>

    <!-- PÁGINA: PROJETOS -->
    <main id="page-projetos" class="page max-w-7xl mx-auto px-6 pt-20 pb-20">
        <h2 class="text-4xl font-bold mb-4 text-white">Projetos <span class="text-cyan-400">Realizados</span></h2>
        <p class="text-slate-400 mb-12">Conheça algumas das transformações que implementámos em parceiros industriais. Clique num projeto para ver detalhes.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="glass-card project-card group" onclick="openProjectModal('auto')">
                <div class="h-52 w-full relative overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1565514020179-026b92b84bb6?auto=format&fit=crop&w=800" alt="Linha de Montagem" class="w-full h-full object-cover opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#020617] to-transparent"></div>
                </div>
                <div class="p-6 relative">
                    <span class="text-xs text-cyan-400 uppercase tracking-widest font-bold">Setor Automóvel</span>
                    <h3 class="text-xl font-bold text-white mt-2 mb-3 group-hover:text-cyan-400 transition-colors">Linha de Montagem Robotizada</h3>
                    <p class="text-slate-400 text-sm mb-4">Implementação de células robóticas sincronizadas para montagem de chassis.</p>
                </div>
            </div>

            <div class="glass-card project-card group" onclick="openProjectModal('energia')">
                <div class="h-52 w-full relative overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=800" alt="Painel SCADA Energia" class="w-full h-full object-cover opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#020617] to-transparent"></div>
                </div>
                <div class="p-6 relative">
                    <span class="text-xs text-cyan-400 uppercase tracking-widest font-bold">Energia</span>
                    <h3 class="text-xl font-bold text-white mt-2 mb-3 group-hover:text-cyan-400 transition-colors">Otimização de Smart Grid</h3>
                    <p class="text-slate-400 text-sm mb-4">Sistema SCADA avançado para monitorização em tempo real de redes de distribuição.</p>
                </div>
            </div>

            <div class="glass-card project-card group" onclick="openProjectModal('farma')">
                <div class="h-52 w-full relative overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=800" alt="Automação Farmacêutica" class="w-full h-full object-cover opacity-70 group-hover:scale-110 group-hover:opacity-100 transition-all duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#020617] to-transparent"></div>
                </div>
                <div class="p-6 relative">
                    <span class="text-xs text-cyan-400 uppercase tracking-widest font-bold">Farmacêutica</span>
                    <h3 class="text-xl font-bold text-white mt-2 mb-3 group-hover:text-cyan-400 transition-colors">Serialização de Embalagens</h3>
                    <p class="text-slate-400 text-sm mb-4">Sistema de visão computacional para garantia de qualidade e rastreabilidade total.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- PÁGINA: SOBRE NÓS -->
    <main id="page-sobre" class="page max-w-7xl mx-auto px-6 pt-16 pb-20">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
            <div>
                <h2 class="text-4xl font-bold mb-6 text-white">Sobre a <span class="text-cyan-400">AutoBot</span></h2>
                <p class="text-slate-400 mb-6 leading-relaxed">
                    Nascemos da necessidade de integrar soluções digitais complexas no chão de fábrica. Combinamos o rigor da engenharia mecânica clássica com a agilidade do desenvolvimento de software moderno para o setor industrial.
                </p>
                <ul class="space-y-4 text-slate-300 mb-8">
                    <li class="flex items-start gap-3"><span class="text-cyan-400">✔</span><div><strong>Expertise Técnica:</strong> Engenheiros certificados em normas internacionais.</div></li>
                    <li class="flex items-start gap-3"><span class="text-cyan-400">✔</span><div><strong>Foco em ROI:</strong> Implementamos projetos visando rápido retorno financeiro.</div></li>
                    <li class="flex items-start gap-3"><span class="text-cyan-400">✔</span><div><strong>Suporte 24/7:</strong> Assistência remota e presencial para continuidade ininterrupta.</div></li>
                </ul>
            </div>
            
            <div class="glass-card p-4 aspect-video flex items-center justify-center border-dashed border-cyan-500/30 bg-slate-900/50">
                <p class="text-slate-500 italic flex flex-col items-center gap-2">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Vídeo Institucional - AutoBot Pro</span>
                </p>
            </div>
        </div>
    </main>

    <!-- PÁGINA: ADMINISTRAÇÃO (TREINO DA IA & CATÁLOGO) -->
    <main id="page-admin" class="page max-w-7xl mx-auto px-6 pt-16 pb-20">
        <h2 class="text-4xl font-bold mb-4 text-white">Painel do <span class="text-amber-400">Administrador</span></h2>
        <p class="text-slate-400 mb-12">Área restrita para gestão do catálogo e treino da Inteligência Artificial. A sua Chave API está oculta por razões de segurança.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Configuração IA -->
            <div class="glass-card p-8 border-t-4 border-t-amber-400">
                <h3 class="text-2xl font-bold text-white mb-6">Identidade da Empresa</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-1">Nome da Empresa</label>
                        <input type="text" id="cfgName" class="form-input focus:border-amber-400" placeholder="Ex: AutoBot Indústria">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-1">Email de Contacto</label>
                        <input type="email" id="cfgEmail" class="form-input focus:border-amber-400" placeholder="Ex: geral@autobot.com">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-1">Contexto e Serviços (Treino da IA)</label>
                        <textarea id="cfgContext" class="form-input h-24 focus:border-amber-400" placeholder="O que faz a empresa? Forneça contexto à IA..."></textarea>
                    </div>
                    <button onclick="saveSettings()" class="w-full bg-amber-500 text-slate-900 font-bold py-3 rounded-xl hover:bg-amber-400 shadow-lg mt-2 transition-colors">Guardar Configurações</button>
                </div>
            </div>

            <!-- Informação do Sistema -->
            <div class="glass-card p-8 border-t-4 border-t-slate-700 flex flex-col justify-center bg-slate-900/50">
                <h3 class="text-xl font-bold text-white mb-4">Segurança Backend</h3>
                <ul class="text-sm text-slate-400 space-y-4 leading-relaxed">
                    <li><strong class="text-amber-400 block mb-1">API Key Oculta:</strong> A ligação à API da Groq agora é feita exclusivamente através do Servidor (PHP). A sua chave de API não é mais inserida no ecrã.</li>
                    <li><strong class="text-amber-400 block mb-1">Catálogo Inteligente:</strong> Ao adicionar um produto abaixo, a IA é atualizada imediatamente com essa informação para recomendação no chat.</li>
                    <li><strong class="text-amber-400 block mb-1">Privacidade Garantida:</strong> Os utilizadores não têm acesso às credenciais da Groq através do código-fonte ou da consola de rede.</li>
                </ul>
            </div>
        </div>

        <!-- Adicionar Produtos/Serviços -->
        <div class="glass-card p-10 border border-slate-700 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <h3 class="text-2xl font-bold text-white mb-2 relative z-10">Gerir Catálogo</h3>
            <p class="text-slate-400 text-sm mb-8 relative z-10">Adicione novos Produtos (Equipamentos) ou Serviços Técnicos ao catálogo do site.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-2">Tipo de Item</label>
                        <select id="itemType" class="form-input bg-slate-900 focus:border-amber-400">
                            <option value="produto">📦 Produto (Equipamento/Material)</option>
                            <option value="servico">🔧 Serviço (Instalação/Programação)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-2">Título do Item</label>
                        <input type="text" id="itemTitle" class="form-input focus:border-amber-400" placeholder="Ex: CLP Siemens S7-1200">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-2">Imagem do Item (Upload)</label>
                        <input type="file" id="itemImageFile" accept="image/*" class="form-input focus:border-amber-400 p-2 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500/10 file:text-amber-400 hover:file:bg-amber-500/20 cursor-pointer bg-slate-900">
                        <div id="imagePreviewContainer" class="mt-3 hidden">
                            <img id="imagePreview" src="" alt="Preview" class="h-24 w-auto rounded-lg border border-slate-700 object-cover shadow-lg">
                        </div>
                    </div>
                </div>
                <div class="space-y-4 flex flex-col">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-amber-400 uppercase mb-2">Descrição Completa</label>
                        <textarea id="itemDesc" class="form-input h-full min-h-[120px] focus:border-amber-400" placeholder="Descreva as especificações, vantagens e aplicações para aparecer no catálogo..."></textarea>
                    </div>
                    <button onclick="addCatalogItem()" class="w-full bg-amber-500 text-slate-900 font-bold py-3.5 rounded-xl hover:bg-amber-400 transition-all shadow-lg mt-auto">
                        + Adicionar ao Catálogo
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Chat Bot Trigger -->
    <div class="chat-trigger" id="openChat" onclick="handleChatRequest()">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    </div>

    <!-- Janela de Chat -->
    <div id="chatWindow" class="glass-card">
        <div class="chat-header flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-white font-bold" id="chatBotName">Assistente AutoBot</span>
            </div>
            <button id="closeChat" class="text-slate-400 hover:text-white transition-colors text-xl font-bold">✕</button>
        </div>
        <div class="chat-body" id="chatContainer">
            <div class="msg msg-bot" id="welcomeMsg">Olá! Sou o assistente da AutoBot. Como posso ajudar?</div>
            <div class="typing-indicator" id="typingIndicator"><span></span><span></span><span></span></div>
        </div>
        <div class="chat-footer flex gap-2">
            <input type="text" id="userInput" class="w-full bg-slate-950 border border-slate-800 rounded-full py-3 px-5 text-white text-sm outline-none focus:border-cyan-500 transition-all" placeholder="Escreva a sua dúvida aqui...">
            <button id="sendMessage" class="bg-cyan-400 hover:bg-cyan-300 w-12 h-12 flex flex-shrink-0 items-center justify-center rounded-full text-slate-900 transition-all transform hover:scale-110">➤</button>
        </div>
    </div>

    <!-- Modal de Detalhes do Projeto -->
    <div id="projectModal" onclick="closeProjectModal(event)">
        <div class="glass-card modal-content w-full max-w-5xl mx-4 flex flex-col md:flex-row overflow-hidden bg-slate-900/90 border-cyan-500/30" onclick="event.stopPropagation()">
            <div class="w-full md:w-1/2 h-64 md:h-auto relative bg-slate-800">
                <img id="modalImg" src="" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-r from-transparent to-slate-900/90 hidden md:block"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 to-transparent md:hidden"></div>
            </div>
            <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center relative">
                <button onclick="closeProjectModal()" class="absolute top-6 right-6 text-slate-400 hover:text-white transition-colors text-2xl font-bold">✕</button>
                
                <span id="modalCat" class="text-cyan-400 text-xs font-bold uppercase tracking-widest mb-2 border border-cyan-500/30 bg-cyan-500/10 w-fit px-3 py-1 rounded-full"></span>
                <h3 id="modalTitle" class="text-3xl font-bold text-white mb-6 leading-tight"></h3>
                
                <div class="space-y-4 mb-8">
                    <p id="modalDesc" class="text-slate-300 text-sm leading-relaxed"></p>
                </div>
                
                <div class="bg-[#020617] p-5 rounded-xl border border-slate-700 mt-auto">
                    <h4 class="text-white font-bold text-sm mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Resultados do Projeto
                    </h4>
                    <p id="modalResults" class="text-cyan-100 text-sm leading-relaxed"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- EFEITOS E NAVEGAÇÃO ---
        const glow = document.getElementById('mouseGlow');
        document.addEventListener('mousemove', (e) => {
            glow.style.transform = `translate(${e.clientX - 300}px, ${e.clientY - 300}px)`;
        });

        // Controlo de permissões Admin
        let isAdmin = false;

        function navigate(id) {
            if (id === 'admin' && !isAdmin) {
                alert('Acesso restrito. Apenas administradores podem aceder a esta página.');
                navigate('home');
                return;
            }

            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active-nav'));
            
            const targetPage = document.getElementById(`page-${id}`);
            const targetNavLink = document.getElementById(`nav-${id}`);
            
            if (targetPage) targetPage.classList.add('active');
            if (targetNavLink) targetNavLink.classList.add('active-nav');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
            document.getElementById('chatWindow').classList.remove('active');
        }

        // --- SISTEMA DE MODAL DE PROJETOS ---
        const projectDetailsData = {
            'auto': {
                cat: 'Setor Automóvel',
                title: 'Linha de Montagem Robotizada',
                img: 'https://images.unsplash.com/photo-1565514020179-026b92b84bb6?auto=format&fit=crop&w=1200',
                desc: 'Desenvolvemos uma solução completa chave-na-mão para a montagem de chassis automóveis. O projeto integrou 12 células robóticas de alta precisão comunicando através de rede Profinet com os controladores principais.<br><br>Implementamos também barreiras de segurança fotoelétricas e sistemas de visão computacional para verificação de cordões de soldadura em tempo real.',
                results: 'Aumento de 35% na eficiência da linha de produção. Redução do tempo de ciclo por chassi em 45 segundos e eliminação quase total (99%) das falhas de soldadura.'
            },
            'energia': {
                cat: 'Energia',
                title: 'Otimização de Smart Grid',
                img: 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=1200',
                desc: 'Modernização de um centro de controlo de distribuição de energia. Substituímos equipamentos obsoletos por um Sistema SCADA moderno, altamente redundante e protegido contra ciberataques.<br><br>Os operadores têm agora acesso a dashboards interativos que preveem picos de consumo através de Inteligência Artificial baseada no histórico meteorológico.',
                results: 'Poupança de 22% nos custos operacionais mensais da rede. Redução do tempo de resposta a falhas de fornecimento de 45 minutos para apenas 3 minutos.'
            },
            'farma': {
                cat: 'Farmacêutica',
                title: 'Serialização de Embalagens',
                img: 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1200',
                desc: 'Em conformidade com as rigorosas normas da indústria farmacêutica, criámos um sistema de rastreabilidade de ponta a ponta. Cada embalagem passa por uma câmara de inspeção de alta velocidade que faz a leitura e verificação de códigos Datamatrix 2D.<br><br>A informação é sincronizada em tempo real com o ERP central (SAP) para assegurar a autenticidade dos lotes.',
                results: 'Garantia de 100% de precisão (Erro 0%) no rastreio de medicamentos. Capacidade da linha aumentada para inspecionar até 400 embalagens por minuto sem interrupções.'
            }
        };

        function openProjectModal(projectId) {
            const data = projectDetailsData[projectId];
            if (!data) return;

            document.getElementById('modalImg').src = data.img;
            document.getElementById('modalCat').innerText = data.cat;
            document.getElementById('modalTitle').innerText = data.title;
            document.getElementById('modalDesc').innerHTML = data.desc;
            document.getElementById('modalResults').innerHTML = data.results;

            document.getElementById('projectModal').classList.add('active');
        }

        function closeProjectModal(e) {
            if (e && e.target !== document.getElementById('projectModal') && e.target.tagName !== 'BUTTON') {
                return; 
            }
            document.getElementById('projectModal').classList.remove('active');
        }

        // --- SISTEMA DE CATÁLOGO (Produtos e Serviços) ---
        const defaultCatalog = [
            { id: 1, type: 'produto', title: 'CLP Modular Avançado', image: 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=600', desc: 'Controlador Lógico Programável para missões críticas industriais. Expansível até 256 I/Os com comunicação Profinet integrada.' },
            { id: 2, type: 'produto', title: 'Inversor de Frequência 10CV', image: 'https://images.unsplash.com/photo-1581092335397-9583eb92d232?auto=format&fit=crop&w=600', desc: 'Controlo de motores trifásicos com máxima eficiência energética. Ideal para bombas, ventiladores e esteiras.' },
            { id: 3, type: 'servico', title: 'Desenvolvimento de Software (SCADA)', image: 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=600', desc: 'Criação de telas de supervisão e integração de dados de chão de fábrica com ERPs empresariais.' },
            { id: 4, type: 'servico', title: 'Montagem de Quadros Elétricos', image: 'https://images.unsplash.com/photo-1565514020179-026b92b84bb6?auto=format&fit=crop&w=600', desc: 'Montagem comissionada e certificada de painéis de controlo e distribuição (NR10/NR12).' }
        ];

        let catalogItems = [];

        function loadCatalog() {
            const saved = localStorage.getItem('autobot_catalog');
            catalogItems = saved ? JSON.parse(saved) : defaultCatalog;
            renderCatalog('all');
        }

        function renderCatalog(filter) {
            const grid = document.getElementById('catalogGrid');
            grid.innerHTML = '';

            const filteredItems = filter === 'all' ? catalogItems : catalogItems.filter(item => item.type === filter);

            if (filteredItems.length === 0) {
                grid.innerHTML = `<p class="text-slate-500 italic col-span-2">Nenhum item encontrado nesta categoria.</p>`;
                return;
            }

            filteredItems.forEach(item => {
                const badgeColor = item.type === 'produto' ? 'bg-cyan-500/20 text-cyan-400' : 'bg-purple-500/20 text-purple-400';
                const badgeIcon = item.type === 'produto' ? '📦 Produto' : '🔧 Serviço';

                grid.innerHTML += `
                    <div class="glass-card overflow-hidden group flex flex-col h-full">
                        <div class="h-48 w-full relative overflow-hidden bg-slate-800">
                            <img src="${item.image}" alt="${item.title}" class="w-full h-full object-cover opacity-80 group-hover:scale-110 group-hover:opacity-100 transition-all duration-500" onerror="this.src='https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=600'">
                        </div>
                        <div class="p-6 flex flex-col flex-1">
                            <div class="inline-flex w-fit px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider mb-3 ${badgeColor}">
                                ${badgeIcon}
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">${item.title}</h3>
                            <p class="text-slate-400 text-sm leading-relaxed mb-6 flex-1">${item.desc}</p>
                            <button onclick="handleChatRequest('Gostaria de saber o preço de: ${item.title}')" class="w-full mt-auto py-2 rounded-lg border border-cyan-500/30 text-cyan-400 font-semibold hover:bg-cyan-500 hover:text-slate-900 transition-colors text-sm">
                                Pedir Cotação
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        function filterCat(type) {
            document.querySelectorAll('.cat-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`btn-filter-${type}`).classList.add('active');
            renderCatalog(type);
        }

        let currentItemImageBase64 = '';

        document.getElementById('itemImageFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    currentItemImageBase64 = event.target.result;
                    document.getElementById('imagePreview').src = currentItemImageBase64;
                    document.getElementById('imagePreviewContainer').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                currentItemImageBase64 = '';
                document.getElementById('imagePreviewContainer').classList.add('hidden');
            }
        });

        function addCatalogItem() {
            const type = document.getElementById('itemType').value;
            const title = document.getElementById('itemTitle').value.trim();
            const image = currentItemImageBase64 || 'https://images.unsplash.com/photo-1581092335397-9583eb92d232?auto=format&fit=crop&w=600';
            const desc = document.getElementById('itemDesc').value.trim();

            if (!title || !desc) {
                alert("Por favor, preencha o título e a descrição do item.");
                return;
            }

            const newItem = { id: Date.now(), type, title, image, desc };
            catalogItems.push(newItem);
            
            try {
                localStorage.setItem('autobot_catalog', JSON.stringify(catalogItems));
            } catch (e) {
                alert("Aviso: A imagem selecionada é muito pesada para o armazenamento local do navegador. Tente utilizar uma imagem menor ou comprimida (inferior a 1MB).");
                catalogItems.pop(); 
                return;
            }
            
            document.getElementById('itemTitle').value = '';
            document.getElementById('itemImageFile').value = '';
            document.getElementById('itemDesc').value = '';
            currentItemImageBase64 = '';
            document.getElementById('imagePreviewContainer').classList.add('hidden');

            alert("Item adicionado com sucesso ao Catálogo!");
            navigate('catalogo');
            renderCatalog('all');
        }

        // --- AUTENTICAÇÃO ---
        let isLoggedIn = false;
        let pendingUserMessage = "";
        let currentAuthMode = 'login'; 

        function toggleAuthMode(mode) {
            currentAuthMode = mode;
            const tabLogin = document.getElementById('tab-login');
            const tabRegister = document.getElementById('tab-register');
            const fieldName = document.getElementById('field-name');
            const fieldConfirm = document.getElementById('field-confirm-password');
            const btnSubmit = document.getElementById('authSubmitBtn');
            const nameInput = document.getElementById('authName');
            const confirmInput = document.getElementById('authConfirmPassword');

            if (mode === 'login') {
                tabLogin.className = "text-white border-b-2 border-white pb-1 font-bold";
                tabRegister.className = "text-blue-200 pb-1 hover:text-white transition-colors font-bold";
                fieldName.classList.add('hidden'); 
                fieldConfirm.classList.add('hidden');
                nameInput.removeAttribute('required');
                confirmInput.removeAttribute('required');
                btnSubmit.innerText = "Entrar";
            } else {
                tabRegister.className = "text-white border-b-2 border-white pb-1 font-bold";
                tabLogin.className = "text-blue-200 pb-1 hover:text-white transition-colors font-bold";
                fieldName.classList.remove('hidden'); 
                fieldConfirm.classList.remove('hidden');
                nameInput.setAttribute('required', 'true');
                confirmInput.setAttribute('required', 'true');
                btnSubmit.innerText = "Cadastrar";
            }
        }

        function processAuth(e) {
            e.preventDefault();
            
            const email = document.getElementById('authEmail').value;
            const password = document.getElementById('authPassword').value;

            if (email === 'admin@autobot.com' && currentAuthMode === 'login') {
                if (password === 'admin123') {
                    isAdmin = true;
                    document.getElementById('nav-admin').classList.remove('hidden');
                } else {
                    alert('Senha de administrador incorreta!');
                    return; 
                }
            } else {
                isAdmin = false;
                document.getElementById('nav-admin').classList.add('hidden');
            }

            const toast = document.getElementById('loginToast');
            toast.innerText = currentAuthMode === 'register' ? "Conta criada com sucesso!" : "Acesso efetuado com sucesso!";
            toast.classList.add('toast-enter');
            isLoggedIn = true;
            document.getElementById('nav-loginBtn').innerText = "A Minha Conta";
            document.getElementById('nav-loginBtn').classList.replace("btn-primary", "border-cyan-500");

            setTimeout(() => {
                toast.classList.remove('toast-enter');
                if (isAdmin) {
                    navigate('admin');
                } else {
                    navigate('catalogo'); 
                    document.getElementById('chatWindow').classList.add('active');
                    if(pendingUserMessage) {
                        document.getElementById('userInput').value = pendingUserMessage;
                        handleSend(); 
                        pendingUserMessage = "";
                    }
                }
            }, 2000);
        }

        function handleChatRequest(initialMsg = "") {
            if(initialMsg) pendingUserMessage = initialMsg;
            if (isLoggedIn) {
                document.getElementById('chatWindow').classList.add('active');
                if (pendingUserMessage) {
                    document.getElementById('userInput').value = pendingUserMessage;
                    handleSend();
                    pendingUserMessage = "";
                }
            } else {
                navigate('login');
            }
        }

        // --- DEFINIÇÕES E LÓGICA DO BACKEND SEGURO ---
        const DEFAULT_CONFIG = { name: "AutoBot", email: "contacto@autobot.com", context: "Especialistas em automação." };
        let appConfig = { ...DEFAULT_CONFIG };
        let chatHistory = [];
        const chatContainer = document.getElementById('chatContainer');
        const userInput = document.getElementById('userInput');
        const indicator = document.getElementById('typingIndicator');

        window.onload = () => {
            const saved = localStorage.getItem('autobot_pro_cfg');
            if (saved) appConfig = JSON.parse(saved);
            
            document.getElementById('cfgName').value = appConfig.name;
            document.getElementById('cfgEmail').value = appConfig.email;
            document.getElementById('cfgContext').value = appConfig.context;
            
            updateUI();
            loadCatalog();
        };

        function saveSettings() {
            appConfig.name = document.getElementById('cfgName').value || "AutoBot";
            appConfig.email = document.getElementById('cfgEmail').value;
            appConfig.context = document.getElementById('cfgContext').value;
            
            localStorage.setItem('autobot_pro_cfg', JSON.stringify(appConfig));
            updateUI();
            alert("Configurações salvas e IA treinada com sucesso!");
        }

        function updateUI() {
            document.getElementById('chatBotName').innerText = appConfig.name;
            document.getElementById('brandDisplay1').innerText = appConfig.name.split(' ')[0].toUpperCase();
            document.getElementById('brandDisplay2').innerText = appConfig.name.split(' ')[1] ? appConfig.name.split(' ')[1].toUpperCase() : "BOT";
        }

        // Integração Segura com Backend PHP
        async function askGroq(text) {
            try {
                const catalogContext = catalogItems.map(item => `- ${item.type}: ${item.title} (${item.desc})`).join('\n');
                
                const messagesPayload = [
                    { 
                        role: "system", 
                        content: `Você é um consultor de vendas da ${appConfig.name}. 
                        Nosso catálogo de produtos e serviços atual: \n${catalogContext}\n
                        Responda em Português, sugerindo os nossos produtos ou serviços quando adequado.` 
                    },
                    ...chatHistory,
                    { role: "user", content: text }
                ];

                const response = await fetch('?api=chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ messages: messagesPayload })
                });

                if (response.status === 401) return "❌ Erro: Chave da API Groq não foi configurada no servidor (Backend).";
                if (!response.ok) throw new Error(`Status: ${response.status}`);
                
                const data = await response.json();
                
                if (data.error) return `❌ Erro da API: ${data.error}`;
                
                const aiResponse = data.choices[0].message.content;
                
                chatHistory.push({ role: "user", content: text });
                chatHistory.push({ role: "assistant", content: aiResponse });
                if(chatHistory.length > 6) chatHistory.shift(); 

                return aiResponse.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            } catch (e) { 
                return `❌ Ocorreu um erro ao comunicar com o servidor: ${e.message}. Verifique se o código PHP está a correr corretamente.`; 
            }
        }

        function append(role, text) {
            const d = document.createElement('div');
            d.className = `msg msg-${role}`;
            d.innerHTML = text;
            chatContainer.insertBefore(d, indicator);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        async function handleSend() {
            const val = userInput.value.trim();
            if(!val) return;
            
            append('user', val);
            userInput.value = '';
            indicator.classList.add('active');
            
            const res = await askGroq(val);
            
            indicator.classList.remove('active');
            append('bot', res);
        }

        document.getElementById('closeChat').onclick = () => document.getElementById('chatWindow').classList.remove('active');
        document.getElementById('sendMessage').onclick = handleSend;
        userInput.onkeydown = (e) => { if(e.key === 'Enter') handleSend(); };
    </script>
</body>
</html>