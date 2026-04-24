<?php
// ============================================================================
// BACKEND (Processamento e Persistência de Dados em JSON)
// ============================================================================
session_start();

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Função auxiliar para ler e escrever JSONs
function getJsonFile($filename) {
    $file = __DIR__ . '/data/' . $filename . '.json';
    if (!file_exists($file)) file_put_contents($file, json_encode([]));
    return json_decode(file_get_contents($file), true) ?: [];
}
function saveJsonFile($filename, $data) {
    $file = __DIR__ . '/data/' . $filename . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Inicialização de ficheiros se não existirem
getJsonFile('clients');
getJsonFile('employees');
getJsonFile('tickets');

// API: Autenticação (Login / Registo)
if (isset($_GET['api']) && $_GET['api'] === 'auth') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'register_client') {
        $clients = getJsonFile('clients');
        $employees = getJsonFile('employees');
        
        // Verifica email duplicado em clientes e funcionários
        foreach ($clients as $c) { if ($c['email'] === $input['email']) { echo json_encode(['error' => 'Email já existe.']); exit; } }
        foreach ($employees as $e) { if ($e['email'] === $input['email']) { echo json_encode(['error' => 'Email já em uso por um funcionário.']); exit; } }
        
        $newClient = [
            'id' => time(),
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_DEFAULT),
            'createdAt' => date('Y-m-d H:i:s')
        ];
        $clients[] = $newClient;
        saveJsonFile('clients', $clients);
        echo json_encode(['success' => true, 'user' => ['id' => $newClient['id'], 'name' => $newClient['name'], 'role' => 'client']]);
        exit;
    }
    
    if ($action === 'login') {
        $email = $input['email'];
        $password = $input['password'];
        
        // Hardcoded Admin
        if ($email === 'admin@autobot.com' && $password === 'admin123') {
            echo json_encode(['success' => true, 'user' => ['id' => 0, 'name' => 'Administrador', 'role' => 'admin']]);
            exit;
        }
        
        // Verifica Funcionários
        $employees = getJsonFile('employees');
        foreach ($employees as $e) {
            if ($e['email'] === $email && password_verify($password, $e['password'])) {
                echo json_encode(['success' => true, 'user' => ['id' => $e['id'], 'name' => $e['name'], 'role' => 'employee', 'job_role' => $e['job_role']]]);
                exit;
            }
        }
        
        // Verifica Clientes
        $clients = getJsonFile('clients');
        foreach ($clients as $c) {
            if ($c['email'] === $email && password_verify($password, $c['password'])) {
                echo json_encode(['success' => true, 'user' => ['id' => $c['id'], 'name' => $c['name'], 'role' => 'client']]);
                exit;
            }
        }
        
        echo json_encode(['error' => 'Credenciais inválidas.']);
        exit;
    }
}

// API: Gestão de Funcionários (Admin)
if (isset($_GET['api']) && $_GET['api'] === 'employees') {
    header('Content-Type: application/json');
    $employees = getJsonFile('employees');
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode($employees); exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'create') {
            $newEmp = [
                'id' => time(),
                'name' => $input['name'],
                'email' => $input['email'],
                'job_role' => $input['job_role'], // Ex: Suporte, Especialista
                'password' => password_hash($input['password'], PASSWORD_DEFAULT)
            ];
            $employees[] = $newEmp;
            saveJsonFile('employees', $employees);
            echo json_encode(['success' => true]); exit;
        }
        
        if ($action === 'delete') {
            $employees = array_filter($employees, function($e) use ($input) { return $e['id'] != $input['id']; });
            saveJsonFile('employees', array_values($employees));
            echo json_encode(['success' => true]); exit;
        }
    }
}

// API: Tickets / Chat de Atendimento
if (isset($_GET['api']) && $_GET['api'] === 'tickets') {
    header('Content-Type: application/json');
    $tickets = getJsonFile('tickets');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode($tickets); exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $input['action'] ?? '';
        
        // Criar novo atendimento
        if ($action === 'create') {
            $newTicket = [
                'id' => time(),
                'clientId' => $input['clientId'],
                'clientName' => $input['clientName'],
                'status' => 'aberto', // aberto, em_atendimento, resolvido
                'employeeId' => null,
                'createdAt' => date('Y-m-d H:i:s'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Atendimento iniciado. Aguarde um funcionário.', 'time' => date('H:i')]
                ]
            ];
            $tickets[] = $newTicket;
            saveJsonFile('tickets', $tickets);
            echo json_encode(['success' => true, 'ticket' => $newTicket]); exit;
        }
        
        // Adicionar Mensagem
        if ($action === 'message') {
            foreach ($tickets as &$t) {
                if ($t['id'] == $input['ticketId']) {
                    $t['messages'][] = ['role' => $input['role'], 'content' => $input['content'], 'time' => date('H:i')];
                    break;
                }
            }
            saveJsonFile('tickets', $tickets);
            echo json_encode(['success' => true]); exit;
        }
        
        // Assumir ou Transferir Atendimento
        if ($action === 'assign') {
            foreach ($tickets as &$t) {
                if ($t['id'] == $input['ticketId']) {
                    $t['employeeId'] = $input['employeeId'];
                    $t['status'] = 'em_atendimento';
                    $t['messages'][] = ['role' => 'system', 'content' => 'Um funcionário assumiu o chat.', 'time' => date('H:i')];
                    break;
                }
            }
            saveJsonFile('tickets', $tickets);
            echo json_encode(['success' => true]); exit;
        }
    }
}

// API: Métricas para o Dashboard (Requisição do Front-end)
if (isset($_GET['api']) && $_GET['api'] === 'metrics') {
    header('Content-Type: application/json');
    $clients = getJsonFile('clients');
    $tickets = getJsonFile('tickets');
    
    $abertos = 0; $resolvidos = 0;
    foreach ($tickets as $t) {
        if ($t['status'] === 'aberto' || $t['status'] === 'em_atendimento') $abertos++;
        if ($t['status'] === 'resolvido') $resolvidos++;
    }
    
    echo json_encode([
        'total_clients' => count($clients),
        'tickets_abertos' => $abertos,
        'tickets_resolvidos' => $resolvidos
    ]);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2ff;
            --secondary: #0072ff;
            --bg-dark: #020617;
            --glass: rgba(15, 23, 42, 0.8);
            --border: rgba(0, 242, 255, 0.2);
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: #f8fafc; overflow-x: hidden; }

        .grid-background {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(to right, rgba(0, 242, 255, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 242, 255, 0.05) 1px, transparent 1px);
            background-size: 50px 50px; z-index: -1; mask-image: radial-gradient(circle at center, black, transparent 80%);
        }

        .glass-card { background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 24px; transition: all 0.3s ease; }
        .page { display: none; animation: fadeInPage 0.5s ease-out forwards; }
        .page.active { display: block; }
        @keyframes fadeInPage { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .nav-link { cursor: pointer; transition: all 0.2s; position: relative; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active-nav { color: var(--primary); font-weight: 600; }

        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #020617; font-weight: 700; padding: 12px 24px; border-radius: 12px; transition: all 0.3s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 242, 255, 0.3); }

        .form-input { width: 100%; background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(0, 242, 255, 0.2); border-radius: 12px; padding: 12px 16px; color: white; outline: none; transition: border-color 0.3s; }
        .form-input:focus { border-color: var(--primary); }

        /* Estilos do Chat de Atendimento */
        .chat-bubble { padding: 10px 14px; border-radius: 16px; max-width: 80%; font-size: 14px; margin-bottom: 8px; }
        .chat-client { background: linear-gradient(135deg, #8b5cf6, #3b82f6); margin-left: auto; border-bottom-right-radius: 4px; }
        .chat-employee { background: #1e293b; border-bottom-left-radius: 4px; border: 1px solid rgba(255,255,255,0.05); }
        .chat-system { background: transparent; color: #64748b; font-size: 12px; text-align: center; width: 100%; font-style: italic; }
    </style>
</head>
<body>
    <div class="grid-background"></div>

    <nav class="sticky top-0 z-50 p-6 glass-card border-none rounded-none border-b border-cyan-500/10 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center w-full">
            <div class="flex items-center gap-3 font-bold text-2xl cursor-pointer" onclick="navigate('home')">
                <div class="w-10 h-10 rounded-full border-2 border-cyan-400 overflow-hidden bg-slate-900 flex items-center justify-center shadow-[0_0_10px_rgba(0,242,255,0.3)]">
                    <img src="guaxinim.jpg" alt="Logo" class="w-full h-full object-cover">
                </div>
                <div><span class="text-cyan-400">AUTO</span>BOT</div>
            </div>
            <div class="hidden md:flex gap-8 text-sm items-center" id="navMenu">
                <a onclick="navigate('home')" class="nav-link active-nav">Início</a>
                
                <a onclick="navigate('clientArea')" class="nav-link hidden text-cyan-400" id="navClient">Meu Atendimento</a>
                <a onclick="navigate('employeeArea')" class="nav-link hidden text-purple-400" id="navEmployee">Painel Funcionário</a>
                <a onclick="navigate('adminArea')" class="nav-link hidden text-amber-400" id="navAdmin">Painel Admin</a>
                
                <button onclick="navigate('login')" class="btn-primary text-xs py-2 px-6 ml-4" id="navLoginBtn">Fazer Login</button>
            </div>
        </div>
    </nav>

    <main id="page-home" class="page active max-w-7xl mx-auto px-6 pt-20 pb-20 text-center">
        <h1 class="text-6xl font-bold mb-8 text-white">Engenharia de <span class="text-cyan-400">Próxima Geração.</span></h1>
        <p class="text-slate-400 text-xl max-w-3xl mx-auto mb-12">Soluções inteligentes em automação e robótica.</p>
    </main>

    <main id="page-login" class="page max-w-xl mx-auto px-6 pt-16">
        <div class="glass-card p-10">
            <h2 class="text-3xl font-bold text-white mb-6 text-center" id="loginTitle">Autenticação</h2>
            <div class="flex gap-4 mb-8 justify-center">
                <button onclick="setAuthMode('login')" class="text-cyan-400 font-bold border-b-2 border-cyan-400 pb-1" id="tabLogin">Entrar</button>
                <button onclick="setAuthMode('register')" class="text-slate-400 font-bold pb-1" id="tabRegister">Registar (Cliente)</button>
            </div>
            <form onsubmit="handleAuth(event)" class="space-y-4">
                <input type="text" id="authName" class="form-input hidden" placeholder="O seu Nome">
                <input type="email" id="authEmail" class="form-input" required placeholder="Email">
                <input type="password" id="authPassword" class="form-input" required placeholder="Senha">
                <button type="submit" class="w-full btn-primary mt-4 py-3" id="authBtn">Entrar no Sistema</button>
            </form>
        </div>
    </main>

    <main id="page-clientArea" class="page max-w-5xl mx-auto px-6 pt-16">
        <h2 class="text-3xl font-bold text-white mb-2">Central de <span class="text-cyan-400">Atendimento</span></h2>
        <p class="text-slate-400 mb-8">Fale diretamente com os nossos especialistas.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass-card p-6 col-span-1">
                <button onclick="startNewTicket()" class="w-full btn-primary mb-6 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Abrir Novo Chamado
                </button>
                <h3 class="text-white font-bold mb-4">Meus Chamados</h3>
                <div id="clientTicketList" class="space-y-2">
                    </div>
            </div>
            <div class="glass-card flex flex-col h-[500px] col-span-2 relative">
                <div id="activeChatOverlay" class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm z-10 flex flex-col items-center justify-center rounded-24">
                    <span class="text-slate-400">Selecione ou abra um chamado para conversar.</span>
                </div>
                <div class="p-4 border-b border-white/10 bg-slate-800/50 rounded-t-[24px]">
                    <h3 class="text-white font-bold" id="chatHeaderTitle">Atendimento #---</h3>
                </div>
                <div class="flex-1 p-4 overflow-y-auto flex flex-col" id="chatMessages">
                    </div>
                <div class="p-4 border-t border-white/10 bg-slate-800/50 rounded-b-[24px] flex gap-2">
                    <input type="text" id="chatInput" class="form-input rounded-full" placeholder="Escreva a sua mensagem...">
                    <button onclick="sendChatMessage()" class="bg-cyan-500 text-slate-900 rounded-full w-12 h-12 flex items-center justify-center shrink-0 hover:bg-cyan-400 transition-colors">➤</button>
                </div>
            </div>
        </div>
    </main>

    <main id="page-employeeArea" class="page max-w-6xl mx-auto px-6 pt-16">
        <h2 class="text-3xl font-bold text-white mb-8">Painel do <span class="text-purple-400" id="empRoleDisplay">Funcionário</span></h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 flex items-center gap-2"><span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Fila de Espera</h3>
                <div id="queueList" class="space-y-3 max-h-[400px] overflow-y-auto">
                    </div>
                <h3 class="text-white font-bold mb-4 mt-8">Meus Atendimentos</h3>
                <div id="myTicketsList" class="space-y-3 max-h-[400px] overflow-y-auto">
                    </div>
            </div>
            <div class="glass-card flex flex-col h-[600px] col-span-2 relative">
                <div id="empActiveChatOverlay" class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm z-10 flex items-center justify-center rounded-24 text-slate-400">Selecione um chamado.</div>
                <div class="p-4 border-b border-white/10 bg-slate-800/50 rounded-t-[24px] flex justify-between items-center">
                    <h3 class="text-white font-bold" id="empChatHeaderTitle">Chat com Cliente</h3>
                    <button onclick="resolveTicket()" class="text-xs bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded border border-emerald-500/30 hover:bg-emerald-500 hover:text-white transition-colors">Marcar como Resolvido</button>
                </div>
                <div class="flex-1 p-4 overflow-y-auto flex flex-col" id="empChatMessages"></div>
                <div class="p-4 border-t border-white/10 flex gap-2">
                    <input type="text" id="empChatInput" class="form-input rounded-full" placeholder="Escreva a sua resposta...">
                    <button onclick="sendEmpChatMessage()" class="bg-purple-500 text-white rounded-full w-12 h-12 flex items-center justify-center shrink-0">➤</button>
                </div>
            </div>
        </div>
    </main>

    <main id="page-adminArea" class="page max-w-7xl mx-auto px-6 pt-16">
        <h2 class="text-4xl font-bold text-white mb-8">Administração <span class="text-amber-400">Geral</span></h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="glass-card p-6 border-t-4 border-t-blue-500">
                <p class="text-slate-400 text-sm uppercase font-bold">Total Clientes</p>
                <p class="text-4xl font-bold text-white mt-2" id="metricClients">0</p>
            </div>
            <div class="glass-card p-6 border-t-4 border-t-amber-500">
                <p class="text-slate-400 text-sm uppercase font-bold">Atendimentos Ativos</p>
                <p class="text-4xl font-bold text-white mt-2" id="metricActiveChats">0</p>
            </div>
            <div class="glass-card p-6 border-t-4 border-t-emerald-500">
                <p class="text-slate-400 text-sm uppercase font-bold">Atendimentos Resolvidos</p>
                <p class="text-4xl font-bold text-white mt-2" id="metricResolvedChats">0</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4">Volume de Atendimentos</h3>
                <canvas id="metricsChart" height="200"></canvas>
            </div>
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4">Gerir Funcionários</h3>
                <form onsubmit="createEmployee(event)" class="space-y-3 mb-6 bg-slate-800/50 p-4 rounded-xl border border-white/5">
                    <input type="text" id="newEmpName" required placeholder="Nome do Funcionário" class="form-input text-sm">
                    <input type="email" id="newEmpEmail" required placeholder="Email Profissional" class="form-input text-sm">
                    <div class="flex gap-3">
                        <select id="newEmpRole" class="form-input text-sm flex-1 bg-slate-900">
                            <option value="Suporte N1">Suporte N1</option>
                            <option value="Suporte N2">Suporte N2</option>
                            <option value="Engenheiro">Engenheiro Especialista</option>
                        </select>
                        <input type="password" id="newEmpPass" required placeholder="Senha Base" class="form-input text-sm flex-1">
                    </div>
                    <button type="submit" class="w-full bg-amber-500 text-slate-900 font-bold py-2 rounded-lg hover:bg-amber-400">Criar Conta</button>
                </form>
                
                <div class="overflow-y-auto max-h-[300px]">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs text-amber-400 uppercase bg-slate-800"><tr><th class="p-3">Nome</th><th class="p-3">Cargo</th><th class="p-3 text-right">Ação</th></tr></thead>
                        <tbody id="adminEmployeeList" class="divide-y divide-slate-700/50"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // --- NAVEGAÇÃO E ESTADO GLOBAL ---
        let currentUser = null; 
        let authMode = 'login';
        let pollingInterval = null;
        
        // Estado do Chat
        let currentTicketId = null;

        function navigate(pageId) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active-nav'));
            document.getElementById('page-' + pageId).classList.add('active');
            
            // Lógica ao entrar na página
            if (pollingInterval) clearInterval(pollingInterval);
            
            if (pageId === 'clientArea') {
                loadClientTickets();
                pollingInterval = setInterval(loadClientTickets, 3000);
            } else if (pageId === 'employeeArea') {
                document.getElementById('empRoleDisplay').innerText = currentUser.job_role;
                loadEmployeeDashboard();
                pollingInterval = setInterval(loadEmployeeDashboard, 3000);
            } else if (pageId === 'adminArea') {
                loadAdminData();
            }
            window.scrollTo(0,0);
        }

        // --- AUTENTICAÇÃO ---
        function setAuthMode(mode) {
            authMode = mode;
            document.getElementById('authName').classList.toggle('hidden', mode === 'login');
            document.getElementById('authName').required = mode === 'register';
            document.getElementById('authBtn').innerText = mode === 'login' ? 'Entrar no Sistema' : 'Criar Conta de Cliente';
            
            document.getElementById('tabLogin').className = mode === 'login' ? "text-cyan-400 font-bold border-b-2 border-cyan-400 pb-1" : "text-slate-400 font-bold pb-1";
            document.getElementById('tabRegister').className = mode === 'register' ? "text-cyan-400 font-bold border-b-2 border-cyan-400 pb-1" : "text-slate-400 font-bold pb-1";
        }

        async function handleAuth(e) {
            e.preventDefault();
            const data = {
                action: authMode === 'login' ? 'login' : 'register_client',
                email: document.getElementById('authEmail').value,
                password: document.getElementById('authPassword').value
            };
            if (authMode === 'register') data.name = document.getElementById('authName').value;

            const res = await fetch('?api=auth', { method: 'POST', body: JSON.stringify(data) });
            const json = await res.json();

            if (json.error) return alert(json.error);
            
            currentUser = json.user;
            updateNav();
        }

        function updateNav() {
            const btn = document.getElementById('navLoginBtn');
            document.getElementById('navClient').classList.add('hidden');
            document.getElementById('navEmployee').classList.add('hidden');
            document.getElementById('navAdmin').classList.add('hidden');

            if (currentUser) {
                btn.innerText = 'Sair';
                btn.classList.replace('btn-primary', 'border-red-500');
                btn.onclick = () => { currentUser = null; updateNav(); navigate('home'); };
                
                if (currentUser.role === 'client') { document.getElementById('navClient').classList.remove('hidden'); navigate('clientArea'); }
                if (currentUser.role === 'employee') { document.getElementById('navEmployee').classList.remove('hidden'); navigate('employeeArea'); }
                if (currentUser.role === 'admin') { document.getElementById('navAdmin').classList.remove('hidden'); navigate('adminArea'); }
            } else {
                btn.innerText = 'Fazer Login';
                btn.classList.replace('border-red-500', 'btn-primary');
                btn.onclick = () => navigate('login');
            }
        }

        // ==========================================
        // 1. REQUISITO: PORTAL DO CLIENTE
        // ==========================================
        async function startNewTicket() {
            const res = await fetch('?api=tickets', {
                method: 'POST',
                body: JSON.stringify({ action: 'create', clientId: currentUser.id, clientName: currentUser.name })
            });
            const data = await res.json();
            if (data.success) {
                currentTicketId = data.ticket.id;
                loadClientTickets();
            }
        }

        async function loadClientTickets() {
            const res = await fetch('?api=tickets');
            const tickets = await res.json();
            const myTickets = tickets.filter(t => t.clientId === currentUser.id);
            
            const list = document.getElementById('clientTicketList');
            list.innerHTML = myTickets.map(t => `
                <div onclick="openClientChat(${t.id})" class="p-3 bg-slate-800 rounded-lg cursor-pointer hover:bg-slate-700 transition border-l-4 ${t.status === 'aberto' ? 'border-amber-500' : (t.status === 'resolvido' ? 'border-emerald-500' : 'border-cyan-500')}">
                    <div class="flex justify-between items-center">
                        <span class="text-white font-bold text-sm">#${t.id}</span>
                        <span class="text-[10px] px-2 py-1 rounded bg-black/50 text-slate-300 uppercase">${t.status}</span>
                    </div>
                    <div class="text-xs text-slate-400 mt-1">Atualizado: ${t.messages[t.messages.length-1].time}</div>
                </div>
            `).join('') || '<p class="text-sm text-slate-500">Sem chamados abertos.</p>';

            if (currentTicketId) renderChatMessages(tickets.find(t => t.id === currentTicketId), 'chatMessages', 'activeChatOverlay', 'chatHeaderTitle');
        }

        function openClientChat(id) {
            currentTicketId = id;
            loadClientTickets();
        }

        async function sendChatMessage() {
            const input = document.getElementById('chatInput');
            if (!input.value.trim() || !currentTicketId) return;
            
            await fetch('?api=tickets', {
                method: 'POST',
                body: JSON.stringify({ action: 'message', ticketId: currentTicketId, role: 'client', content: input.value })
            });
            input.value = '';
            loadClientTickets();
        }

        // Renderiza bolhas de chat (Usado por Clientes e Funcionários)
        function renderChatMessages(ticket, containerId, overlayId, titleId) {
            if (!ticket) return;
            document.getElementById(overlayId).classList.add('hidden');
            document.getElementById(titleId).innerText = `Atendimento #${ticket.id} (${ticket.status})`;
            
            const container = document.getElementById(containerId);
            container.innerHTML = ticket.messages.map(m => {
                if (m.role === 'system') return `<div class="chat-bubble chat-system">${m.content} <span class="text-[10px] ml-2">${m.time}</span></div>`;
                const isMine = (containerId === 'chatMessages' && m.role === 'client') || (containerId === 'empChatMessages' && m.role === 'employee');
                return `
                    <div class="chat-bubble ${isMine ? 'chat-client' : 'chat-employee'}">
                        <div class="text-white">${m.content}</div>
                        <div class="text-[10px] text-white/50 text-right mt-1">${m.time}</div>
                    </div>
                `;
            }).join('');
            container.scrollTop = container.scrollHeight;
        }

        // ==========================================
        // PAINEL FUNCIONÁRIO (Responder a Chamados)
        // ==========================================
        async function loadEmployeeDashboard() {
            const res = await fetch('?api=tickets');
            const tickets = await res.json();
            
            const queue = tickets.filter(t => t.status === 'aberto');
            const mine = tickets.filter(t => t.employeeId === currentUser.id && t.status === 'em_atendimento');

            document.getElementById('queueList').innerHTML = queue.map(t => `
                <div class="p-3 bg-slate-800 rounded-lg flex justify-between items-center border border-white/5">
                    <div>
                        <div class="text-sm font-bold text-white">${t.clientName}</div>
                        <div class="text-xs text-slate-400">#${t.id}</div>
                    </div>
                    <button onclick="assignTicket(${t.id})" class="text-xs bg-cyan-500 text-slate-900 px-3 py-1 rounded font-bold hover:bg-cyan-400">Assumir</button>
                </div>
            `).join('') || '<p class="text-sm text-slate-500">Fila vazia.</p>';

            document.getElementById('myTicketsList').innerHTML = mine.map(t => `
                <div onclick="openEmpChat(${t.id})" class="p-3 bg-slate-800 rounded-lg cursor-pointer hover:bg-slate-700 transition border-l-4 border-purple-500">
                    <div class="text-sm font-bold text-white">${t.clientName} <span class="text-xs font-normal text-slate-400 ml-2">#${t.id}</span></div>
                </div>
            `).join('') || '<p class="text-sm text-slate-500">Nenhum atendimento em curso.</p>';

            if (currentTicketId) renderChatMessages(tickets.find(t => t.id === currentTicketId), 'empChatMessages', 'empActiveChatOverlay', 'empChatHeaderTitle');
        }

        async function assignTicket(ticketId) {
            await fetch('?api=tickets', { method: 'POST', body: JSON.stringify({ action: 'assign', ticketId, employeeId: currentUser.id }) });
            currentTicketId = ticketId;
            loadEmployeeDashboard();
        }

        function openEmpChat(id) { currentTicketId = id; loadEmployeeDashboard(); }

        async function sendEmpChatMessage() {
            const input = document.getElementById('empChatInput');
            if (!input.value.trim() || !currentTicketId) return;
            await fetch('?api=tickets', { method: 'POST', body: JSON.stringify({ action: 'message', ticketId: currentTicketId, role: 'employee', content: input.value }) });
            input.value = '';
            loadEmployeeDashboard();
        }

        // ==========================================
        // 2 & 5. REQUISITOS: PAINEL ADMIN (Métricas e Funcionários)
        // ==========================================
        let adminChart = null;

        async function loadAdminData() {
            // Load Metrics
            const mRes = await fetch('?api=metrics');
            const metrics = await mRes.json();
            document.getElementById('metricClients').innerText = metrics.total_clients;
            document.getElementById('metricActiveChats').innerText = metrics.tickets_abertos;
            document.getElementById('metricResolvedChats').innerText = metrics.tickets_resolvidos;

            // Desenhar Gráfico Chart.js
            const ctx = document.getElementById('metricsChart').getContext('2d');
            if (adminChart) adminChart.destroy();
            adminChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Abertos/Em Curso', 'Resolvidos'],
                    datasets: [{
                        data: [metrics.tickets_abertos, metrics.tickets_resolvidos],
                        backgroundColor: ['#f59e0b', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: { plugins: { legend: { labels: { color: '#fff' } } } }
            });

            // Load Employees
            const eRes = await fetch('?api=employees');
            const employees = await eRes.json();
            document.getElementById('adminEmployeeList').innerHTML = employees.map(e => `
                <tr class="hover:bg-slate-800/30">
                    <td class="p-3 text-white">${e.name}</td>
                    <td class="p-3 text-purple-400 text-xs font-bold uppercase">${e.job_role}</td>
                    <td class="p-3 text-right"><button onclick="deleteEmployee(${e.id})" class="text-red-400 hover:text-red-300 text-xs">Excluir</button></td>
                </tr>
            `).join('');
        }

        async function createEmployee(e) {
            e.preventDefault();
            const data = {
                action: 'create',
                name: document.getElementById('newEmpName').value,
                email: document.getElementById('newEmpEmail').value,
                job_role: document.getElementById('newEmpRole').value,
                password: document.getElementById('newEmpPass').value
            };
            await fetch('?api=employees', { method: 'POST', body: JSON.stringify(data) });
            e.target.reset();
            loadAdminData();
        }

        async function deleteEmployee(id) {
            if (!confirm('Excluir este funcionário?')) return;
            await fetch('?api=employees', { method: 'POST', body: JSON.stringify({ action: 'delete', id }) });
            loadAdminData();
        }
    </script>
</body>
</html>
