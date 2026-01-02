<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descobrir o Impostor - Jogo</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 50%, #0d1421 100%);
            color: #e2e8f0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            position: relative;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
        }

        .title {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            background: linear-gradient(45deg, #60a5fa, #a78bfa, #f472b6);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            text-shadow: 0 0 30px rgba(96, 165, 250, 0.3);
        }

        .subtitle {
            font-size: 1.1rem;
            color: #94a3b8;
            font-weight: 400;
        }

        .game-section {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .game-section:hover {
            border-color: rgba(96, 165, 250, 0.3);
            box-shadow: 0 15px 50px rgba(96, 165, 250, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .theme-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .theme-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(96, 165, 250, 0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .theme-card:hover::before {
            opacity: 1;
        }

        .theme-card:hover {
            border-color: #60a5fa;
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(96, 165, 250, 0.2);
        }

        .theme-card.selected {
            border-color: #10b981;
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
        }

        .theme-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 5px;
        }

        .btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .players-section {
            margin-top: 20px;
        }

        .player-count {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .player-count label {
            font-weight: 500;
            color: #f1f5f9;
        }

        .player-count input {
            background: rgba(51, 65, 85, 0.6);
            border: 2px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px;
            padding: 10px 15px;
            color: #f1f5f9;
            font-size: 1rem;
            width: 80px;
            text-align: center;
        }

        .player-count input:focus {
            outline: none;
            border-color: #60a5fa;
        }

        .players-distribution {
            display: none;
        }

        .player-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .player-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border: 2px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .player-card.impostor {
            border-color: #ef4444;
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
        }

        .player-card h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #f1f5f9;
        }

        .player-word {
            font-size: 1.5rem;
            font-weight: 700;
            color: #60a5fa;
            margin: 15px 0;
            padding: 10px;
            background: rgba(96, 165, 250, 0.1);
            border-radius: 8px;
            border: 1px dashed rgba(96, 165, 250, 0.3);
        }

        .player-card.impostor .player-word {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.1);
            border-color: rgba(251, 191, 36, 0.3);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .role-badge.player {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .role-badge.impostor {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .first-player-info {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            border-radius: 12px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .first-player-info h3 {
            color: #10b981;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        #player-call-screen, #player-word-screen, #final-screen {
            padding: 40px 20px;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        #current-player-card {
            margin: 20px 0;
        }

        .text-2xl {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .text-3xl {
            font-size: 1.875rem;
            font-weight: 700;
        }

        .text-lg {
            font-size: 1.125rem;
        }

        .text-green-400 {
            color: #10b981;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mb-6 {
            margin-bottom: 1.5rem;
        }

        .mt-6 {
            margin-top: 1.5rem;
        }

        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid rgba(96, 165, 250, 0.3);
            border-radius: 50%;
            border-top-color: #60a5fa;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .hidden {
            display: none !important;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(96, 165, 250, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(96, 165, 250, 0); }
            100% { box-shadow: 0 0 0 0 rgba(96, 165, 250, 0); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .game-section {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .themes-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .player-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">🎭 Descobrir o Impostor</h1>
            <p class="subtitle">Um jogo divertido para toda família!</p>
        </div>

        <!-- Seção de Escolha de Tema -->
        <div class="game-section" id="theme-section">
            <h2 class="section-title">
                🎯 Escolha um Tema
            </h2>
            
            <div class="themes-grid" id="themes-grid">
                <!-- Temas serão carregados aqui -->
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="generateNewThemes()">
                    🔄 Gerar Novos Temas
                </button>
            </div>
        </div>

        <!-- Seção de Configuração de Jogadores -->
        <div class="game-section hidden" id="players-section">
            <h2 class="section-title">
                👥 Configurar Jogadores
            </h2>
            
            <div class="player-count">
                <label for="player-count-input">Número de jogadores:</label>
                <input type="number" id="player-count-input" min="3" max="10" value="5">
            </div>
            
            <div style="text-align: center;">
                <button class="btn btn-success" onclick="startGame()">
                    🎮 Iniciar Jogo
                </button>
            </div>
        </div>

        <!-- Seção de Distribuição Individual -->
        <div class="game-section players-distribution hidden" id="distribution-section">
            <h2 class="section-title">
                📋 Palavras dos Jogadores
            </h2>
            
            <!-- Tela de chamada do jogador -->
            <div id="player-call-screen" class="text-center">
                <h3 id="player-call-title" class="text-2xl mb-4">Vez do Jogador 1</h3>
                <p class="text-lg mb-6">Clique no botão abaixo para ver sua palavra (apenas você deve olhar!)</p>
                <button class="btn btn-success pulse" id="show-player-word" onclick="showCurrentPlayerWord()">
                    🎯 Exibir Minha Palavra
                </button>
            </div>
            
            <!-- Tela da palavra do jogador -->
            <div id="player-word-screen" class="text-center hidden">
                <div id="current-player-card" class="player-card mx-auto" style="max-width: 400px;">
                    <!-- Conteúdo será preenchido dinamicamente -->
                </div>
                <button class="btn btn-secondary mt-6" id="next-player-btn" onclick="nextPlayer()">
                    ➡️ Próximo Jogador
                </button>
            </div>
            
            <!-- Tela final com primeiro jogador -->
            <div id="final-screen" class="text-center hidden">
                <h2 class="text-3xl mb-6 text-green-400">🎉 Todos os jogadores já viram suas palavras!</h2>
                <div class="first-player-info" id="first-player-info">
                    <!-- Informação do primeiro jogador -->
                </div>
                <div style="margin-top: 30px;">
                    <button class="btn" onclick="resetGame()">
                        🔄 Novo Jogo
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 15px;">Gerando conteúdo com IA...</p>
        </div>
    </div>

    <script>
        // Configuração CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Estado do jogo
        let selectedTheme = null;
        let themes = [];
        let players = [];
        let currentPlayerIndex = 0;
        let firstPlayer = null;
        
        // Inicializar aplicação
        document.addEventListener('DOMContentLoaded', function() {
            generateNewThemes();
        });

        // Gerar novos temas
        async function generateNewThemes() {
            showLoading();
            
            try {
                const response = await fetch('/api/generate-themes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    themes = data.themes;
                    renderThemes();
                } else {
                    console.error('Erro ao gerar temas');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
            }
            
            hideLoading();
        }

        // Renderizar temas na interface
        function renderThemes() {
            const themesGrid = document.getElementById('themes-grid');
            themesGrid.innerHTML = '';
            
            themes.forEach((theme, index) => {
                const themeCard = document.createElement('div');
                themeCard.className = 'theme-card';
                themeCard.onclick = () => selectTheme(theme, themeCard);
                
                themeCard.innerHTML = `
                    <h3>${theme}</h3>
                `;
                
                themesGrid.appendChild(themeCard);
                
                // Animação de entrada
                setTimeout(() => {
                    themeCard.classList.add('fade-in');
                }, index * 100);
            });
        }

        // Selecionar tema
        function selectTheme(theme, cardElement) {
            // Remover seleção anterior
            document.querySelectorAll('.theme-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Selecionar novo tema
            cardElement.classList.add('selected');
            selectedTheme = theme;
            
            // Mostrar seção de jogadores
            document.getElementById('players-section').classList.remove('hidden');
            document.getElementById('players-section').classList.add('fade-in');
        }

        // Iniciar jogo
        async function startGame() {
            if (!selectedTheme) {
                alert('Por favor, selecione um tema primeiro!');
                return;
            }
            
            const playerCount = parseInt(document.getElementById('player-count-input').value);
            
            if (playerCount < 3 || playerCount > 10) {
                alert('O número de jogadores deve ser entre 3 e 10!');
                return;
            }
            
            showLoading();
            
            try {
                const response = await fetch('/api/generate-words', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        theme: selectedTheme,
                        player_count: playerCount
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    players = data.players;
                    firstPlayer = data.first_player;
                    currentPlayerIndex = 0;
                    
                    showDistribution();
                    setupPlayerCallScreen();
                } else {
                    console.error('Erro ao gerar palavras');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
            }
            
            hideLoading();
        }

        // Configurar tela de chamada do jogador
        function setupPlayerCallScreen() {
            const callTitle = document.getElementById('player-call-title');
            callTitle.textContent = `Vez do Jogador ${currentPlayerIndex + 1}`;
            
            document.getElementById('player-call-screen').style.display = 'block';
            document.getElementById('player-word-screen').style.display = 'none';
            document.getElementById('final-screen').style.display = 'none';
        }

        // Mostrar palavra do jogador atual
        function showCurrentPlayerWord() {
            const player = players[currentPlayerIndex];
            const playerCard = document.getElementById('current-player-card');
            const nextBtn = document.getElementById('next-player-btn');
            
            document.getElementById('player-word-screen').classList.remove('hidden');
            document.getElementById('player-word-screen').style.display = 'block';

            playerCard.className = `player-card ${player.is_impostor ? 'impostor' : ''}`;
            playerCard.innerHTML = `
                <h4>Jogador ${player.player}</h4>
                <div class="player-word">${player.word}</div>
                <div class="role-badge ${player.is_impostor ? 'impostor' : 'player'}">
                    ${player.role}
                </div>
            `;
            
            // Se for o último jogador, mudar texto do botão
            if (currentPlayerIndex === players.length - 1) {
                nextBtn.innerHTML = '✅ Finalizar';
            } else {
                nextBtn.innerHTML = '➡️ Próximo Jogador';
            }
            
            document.getElementById('player-call-screen').style.display = 'none';
            document.getElementById('player-word-screen').classList.add('fade-in');
        }

        // Próximo jogador
        function nextPlayer() {
            currentPlayerIndex++;
            
            if (currentPlayerIndex >= players.length) {
                // Todos os jogadores viram, mostrar tela final
                showFinalScreen();
            } else {
                // Próximo jogador
                setupPlayerCallScreen();
            }
        }

        // Mostrar tela final
        function showFinalScreen() {
            const impostorPlayer = players.find(p => p.is_impostor);
            const firstPlayerInfo = document.getElementById('first-player-info');
            
            firstPlayerInfo.innerHTML = `
                <h3>🎯 Jogador ${firstPlayer} começa o jogo!</h3>
                <p>O impostor é o jogador ${impostorPlayer.player} (mas isso é segredo! 🤫)</p>
            `;
            
            document.getElementById('player-call-screen').style.display = 'none';
            document.getElementById('player-word-screen').style.display = 'none';
            document.getElementById('final-screen').style.display = 'block';
            document.getElementById('final-screen').classList.add('fade-in');
        }

        // Renderizar cards dos jogadores (função removida, não é mais usada)
        function renderPlayerCards(firstPlayer, impostorPlayer) {
            // Esta função não é mais necessária
        }

        // Mostrar seção de distribuição
        function showDistribution() {
            document.getElementById('theme-section').style.display = 'none';
            document.getElementById('players-section').style.display = 'none';
            document.getElementById('distribution-section').classList.remove('hidden');
            document.getElementById('distribution-section').classList.add('fade-in');
            document.getElementById('distribution-section').style.display = 'block';
        }

        // Resetar jogo
        function resetGame() {
            selectedTheme = null;
            players = [];
            currentPlayerIndex = 0;
            firstPlayer = null;
            
            document.getElementById('theme-section').style.display = 'block';
            document.getElementById('players-section').classList.add('hidden');
            document.getElementById('distribution-section').classList.add('hidden');
            
            document.querySelectorAll('.theme-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            generateNewThemes();
        }

        // Mostrar loading
        function showLoading() {
            document.getElementById('loading').classList.add('show');
        }

        // Esconder loading
        function hideLoading() {
            document.getElementById('loading').classList.remove('show');
        }
    </script>
</body>
</html>