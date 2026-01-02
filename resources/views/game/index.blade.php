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
    @vite(['resources/css/game.css', 'resources/js/game.js'])
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Impostor</h1>
        </div>

        <!-- Seção de Escolha de Tema -->
        <div class="game-section" id="theme-section">
            <h2 class="section-title">
                🎯 Escolha um Tema
            </h2>
            
            <div class="themes-grid" id="themes-grid">
                <!-- Temas serão carregados aqui -->
            </div>
        </div>

        <!-- Seção de Configuração de Jogadores -->
        <div class="game-section hidden" id="players-section">
            <h2 class="section-title">
                👥 Configurar Jogadores
            </h2>
            
            <div class="player-count">
                <label for="player-count-input">Número de jogadores:</label>
                <input type="number" id="player-count-input" min="3" max="10" value="3">
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
                <div class="theme-display" style="margin-bottom: 20px;">
                    <h3 style="color: #64748b; font-size: 1.2rem; margin-bottom: 10px;">🎯 Tema do Jogo:</h3>
                    <div id="selected-theme-display" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border-radius: 25px; display: inline-block; font-weight: 500; font-size: 1.1rem;">
                        <!-- Tema será preenchido dinamicamente -->
                    </div>
                </div>
                <div id="current-player-card" class="player-card mx-auto">
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
            <p style="margin-top: 15px;">Gerando conteúdo...</p>
        </div>
    </div>
</body>
</html>