// Configuração CSRF
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Estado do jogo
let selectedTheme = null;
let themes = [];
let players = [];
let currentPlayerIndex = 0;
let firstPlayer = null;

// Inicializar aplicação
document.addEventListener('DOMContentLoaded', function () {
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
    const themeDisplay = document.getElementById('selected-theme-display');

    document.getElementById('player-word-screen').classList.remove('hidden');
    document.getElementById('player-word-screen').style.display = 'block';

    // Preencher o tema selecionado
    if (themeDisplay && selectedTheme) {
        themeDisplay.textContent = selectedTheme;
    } else {
        if (themeDisplay) {
            themeDisplay.textContent = 'Tema não definido';
        }
    }

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
    const firstPlayerInfo = document.getElementById('first-player-info');

    firstPlayerInfo.innerHTML = `
        <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 15px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0; font-size: 1.8rem;">🚀 Jogador ${firstPlayer} inicia a rodada!</h3>
            <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">Agora vocês podem começar a discussão</p>
        </div>
    `;

    document.getElementById('player-call-screen').style.display = 'none';
    document.getElementById('player-word-screen').style.display = 'none';
    document.getElementById('final-screen').style.display = 'block';
    document.getElementById('final-screen').classList.add('fade-in');
    document.getElementById('final-screen').classList.remove('hidden');
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

    window.location.reload();
}

// Mostrar loading
function showLoading() {
    document.getElementById('loading').classList.add('show');
}

// Esconder loading
function hideLoading() {
    document.getElementById('loading').classList.remove('show');
}

// Exportar funções globais para uso no HTML
window.generateNewThemes = generateNewThemes;
window.selectTheme = selectTheme;
window.startGame = startGame;
window.showCurrentPlayerWord = showCurrentPlayerWord;
window.nextPlayer = nextPlayer;
window.resetGame = resetGame;