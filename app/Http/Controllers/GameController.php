<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Carbon\Carbon;

class GameController extends Controller
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * Exibe a página principal do jogo
     */
    public function index(): View
    {
        return view('game.index');
    }

    /**
     * Gera novos temas usando IA ou cache
     */
    public function generateThemes(): JsonResponse
    {
        return $this->fallbackThemes();
    }

    /**
     * Gera palavras para um tema específico usando IA ou cache
     */
    public function generateWords(Request $request): JsonResponse
    {
        $theme = $request->input('theme');
        $playerCount = $request->input('player_count', 5);

        try {
            // Limpar cache antigo
            $this->clearOldCache();

            // Lógica especial para Clash Royale
            if (strtolower($theme) === 'clash royale') {
                return $this->generateClashRoyaleWords($playerCount);
            }

            // Verificar se já temos palavras em cache para este tema
            $cacheFileName = $this->getWordsCacheFileName($theme);
            $cachedWords = $this->loadWordsFromCache($cacheFileName);

            if ($cachedWords && !empty($cachedWords['words']) && count($cachedWords['words']) >= 10) {
                // Selecionar palavra aleatória e uma de suas dicas, evitando histórico
                $selectedPair = $this->selectWordAvoidingHistory($cachedWords['words'], $theme);

                if ($selectedPair) {
                    // Adicionar ao histórico
                    $this->addToHistory($theme, $selectedPair['word'], $selectedPair['hint']);

                    return $this->distributeRoles($selectedPair['word'], $selectedPair['hint'], $playerCount);
                }
            }

            // Gerar novas palavras via IA
            $result = $this->generateWordsFromAI($theme, $cachedWords);

            if ($result) {
                // Adicionar ao histórico
                $this->addToHistory($theme, $result['word'], $result['hint']);

                return $this->distributeRoles($result['word'], $result['hint'], $playerCount);
            }

            return $this->fallbackWords($theme, $playerCount);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar palavras: ' . $e->getMessage());
            return $this->fallbackWords($theme, $playerCount);
        }
    }

    /**
     * Faz requisição para a API Groq
     */
    private function makeGroqApiRequest(array $payload): ?array
    {
        $apiKey = env('GROQ_API_KEY');

        if (!$apiKey) {
            return null;
        }

        $url = self::GROQ_API_URL;
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        $postData = json_encode($payload);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        Log::info('Groq API Response - HTTP Code: ' . $httpCode);
        Log::info('Groq API Response - Body: ' . $response);
        if ($error) {
            Log::error('cURL Error: ' . $error);
        }

        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * Distribui as palavras entre os jogadores
     */
    private function distributeRoles(string $word, string $hint, int $playerCount): JsonResponse
    {
        $players = [];
        $impostorIndex = rand(0, $playerCount - 1);

        for ($i = 0; $i < $playerCount; $i++) {
            $players[] = [
                'player' => $i + 1,
                'word' => $i === $impostorIndex ? $hint : $word,
                'is_impostor' => $i === $impostorIndex,
                'role' => $i === $impostorIndex ? 'Impostor' : 'Jogador'
            ];
        }

        $firstPlayer = rand(1, $playerCount);

        return response()->json([
            'success' => true,
            'players' => $players,
            'first_player' => $firstPlayer,
            'impostor_player' => $impostorIndex + 1
        ]);
    }

    /**
     * Gera nome do arquivo de cache para palavras de um tema
     */
    private function getWordsCacheFileName(string $theme): string
    {
        $date = Carbon::now()->format('Ymd');
        $themeSlug = str_replace([' ', 'ã', 'ç', 'á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'ô'], ['_', 'a', 'c', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'o'], strtolower($theme));
        return "cache/words_{$themeSlug}_{$date}.json";
    }

    /**
     * Remove arquivos de cache antigos de palavras e histórico (mais de 24 horas)
     */
    private function clearOldCache(): void
    {
        try {
            $files = Storage::files('cache');
            $yesterday = Carbon::now()->subDay()->format('Ymd');

            foreach ($files as $file) {
                // Proteger arquivo fixo do Clash Royale
                if (str_contains($file, 'words_clash_royale.json')) {
                    continue;
                }

                // Proteger arquivos de temas (não devem ser apagados)
                if (str_contains($file, 'themes')) {
                    continue;
                }

                // Apagar apenas arquivos de palavras e histórico antigos
                if (preg_match('/_(\\d{8})\\.json$/', $file, $matches)) {
                    $fileDate = $matches[1];
                    if ($fileDate < $yesterday && (str_contains($file, 'words_') || str_contains($file, 'history_'))) {
                        Storage::delete($file);
                        Log::info("Cache antigo removido: {$file}");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao limpar cache: ' . $e->getMessage());
        }
    }

    /**
     * Carrega palavras do cache
     */
    private function loadWordsFromCache(string $fileName): ?array
    {
        try {
            if (Storage::exists($fileName)) {
                $content = Storage::get($fileName);
                $data = json_decode($content, true);
                return [
                    'words' => $data['words'] ?? []
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao carregar palavras do cache: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Salva palavras no cache
     */
    private function saveWordsToCache(string $fileName, array $wordsWithHints): void
    {
        try {
            $data = [
                'created_at' => Carbon::now()->toISOString(),
                'words' => $wordsWithHints
            ];

            Storage::put($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::info("Palavras salvas no cache: {$fileName}");
        } catch (\Exception $e) {
            Log::error('Erro ao salvar palavras no cache: ' . $e->getMessage());
        }
    }

    /**
     * Gera palavras usando IA para um tema
     */
    private function generateWordsFromAI(string $theme, ?array $cachedWords = null): ?array
    {
        $existingWords = $cachedWords['words'] ?? [];

        // Determinar quantas palavras ainda precisamos
        $currentWordCount = count($existingWords);

        if ($currentWordCount >= 20) {
            // Já temos palavras suficientes, selecionar uma aleatória
            $availableWords = array_keys($existingWords);
            $randomWord = $availableWords[array_rand($availableWords)];
            $wordHints = $existingWords[$randomWord];
            $randomHint = $wordHints[array_rand($wordHints)];

            return [
                'word' => $randomWord,
                'hint' => $randomHint
            ];
        }

        // Gerar palavras via IA
        $prompt = "Para o tema '{$theme}', gere EXATAMENTE 10 palavras com EXATAMENTE 3 dicas cada uma. ";
        $prompt .= "Cada palavra deve ser simples para crianças entenderem. ";
        $prompt .= "IMPORTANTE: As dicas devem ser características GENÉRICAS que se apliquem a VÁRIAS palavras do tema, não específicas demais. ";
        $prompt .= "Isso torna o jogo mais desafiador, pois o impostor terá dicas que podem confundir com outras opções. ";
        $prompt .= "As dicas não devem ser sinônimos da palavra nem muito óbvias. ";
        $prompt .= "Devem ser baseadas em características, comportamentos ou propriedades mais amplas. ";
        $prompt .= "FORMATO OBRIGATÓRIO (sem numeração, sem explicações): palavra1:dica1,dica2,dica3|palavra2:dica1,dica2,dica3|palavra3:dica1,dica2,dica3 ";
        $prompt .= "Exemplo EXATO para tema 'animais': gato:carnívoro,reflexos rápidos,peludo|cão:doméstico,carnívoro,leal|abelha:pequeno,organizado,trabalha em grupo|pássaro:voa,constrói ninhos,pequeno. ";
        $prompt .= "NÃO inclua numeração, NÃO inclua explicações, NÃO inclua texto adicional. APENAS o formato solicitado.";
        $prompt .= "Tudo deve ser única e exclusivamente em Português do Brasil. Caso o tema seja Clash Royale, as palavras devem ser SOMENTE cartas e você deve pesquisar na wiki do jogo para ter certeza que a carta existe na versão mais recente do jogo, e a dica deve ser sobre características genéricas da carta. Exemplo: Corredor:4 de elixir,raro,rápido";

        if (!empty($existingWords)) {
            $existingWordsList = implode(', ', array_keys($existingWords));
            $prompt .= ". NÃO repita estas palavras que já existem: {$existingWordsList}";
        }

        $prompt .= ". Gere apenas palavras novas com dicas genéricas e desafiadoras.";

        $payload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];

        $data = $this->makeGroqApiRequest($payload);

        if ($data && isset($data['choices'][0]['message']['content'])) {
            try {
                $content = trim($data['choices'][0]['message']['content']);
                $wordGroups = array_map('trim', explode('|', $content));

                $newWordsWithHints = [];

                // Processar cada grupo palavra:dica1,dica2,dica3
                foreach ($wordGroups as $group) {
                    if (strpos($group, ':') !== false) {
                        list($word, $hintsString) = explode(':', $group, 2);
                        $word = trim($word);
                        $hints = array_map('trim', explode(',', $hintsString));

                        // Verificar se a palavra não já existe e tem exatamente 3 dicas
                        if (!isset($existingWords[$word]) && count($hints) === 3 && !empty(array_filter($hints))) {
                            $newWordsWithHints[$word] = array_filter($hints);
                        }
                    }
                }

                if (!empty($newWordsWithHints)) {
                    // Mesclar com palavras existentes
                    $allWords = array_merge($existingWords, $newWordsWithHints);

                    // Limitar a 20 palavras no total
                    if (count($allWords) > 20) {
                        $allWords = array_slice($allWords, 0, 20, true);
                    }

                    // Salvar no cache
                    $cacheFileName = $this->getWordsCacheFileName($theme);
                    $this->saveWordsToCache($cacheFileName, $allWords);

                    // Retornar uma palavra e dica aleatórias das novas
                    $randomWord = array_rand($newWordsWithHints);
                    $wordHints = $newWordsWithHints[$randomWord];
                    $randomHint = $wordHints[array_rand($wordHints)];

                    return [
                        'word' => $randomWord,
                        'hint' => $randomHint
                    ];
                }
            } catch (\Throwable $th) {
                Log::error('Erro ao processar palavras da IA: ' . $th->getMessage());
            }
        }

        return null;
    }

    /**
     * Gera nome do arquivo de histórico para um tema
     */
    private function getHistoryFileName(string $theme): string
    {
        $date = Carbon::now()->format('Ymd');
        $themeSlug = str_replace([' ', 'ã', 'ç', 'á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'ô'], ['_', 'a', 'c', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'o'], strtolower($theme));
        return "cache/history_{$themeSlug}_{$date}.json";
    }

    /**
     * Carrega histórico de palavras usadas
     */
    private function loadHistory(string $theme): array
    {
        try {
            $fileName = $this->getHistoryFileName($theme);
            if (Storage::exists($fileName)) {
                $content = Storage::get($fileName);
                $data = json_decode($content, true);
                return $data['history'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao carregar histórico: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Adiciona palavra-dica ao histórico
     */
    private function addToHistory(string $theme, string $word, string $hint): void
    {
        try {
            $history = $this->loadHistory($theme);

            // Adicionar novo item
            $newItem = [
                'word' => $word,
                'hint' => $hint,
                'timestamp' => Carbon::now()->toISOString()
            ];

            array_unshift($history, $newItem);

            // Manter apenas os últimos 15 itens
            $history = array_slice($history, 0, 15);

            // Salvar histórico atualizado
            $fileName = $this->getHistoryFileName($theme);
            $data = [
                'created_at' => Carbon::now()->toISOString(),
                'history' => $history
            ];

            Storage::put($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            Log::error('Erro ao salvar histórico: ' . $e->getMessage());
        }
    }

    /**
     * Seleciona palavra evitando histórico recente
     */
    private function selectWordAvoidingHistory(array $wordsWithHints, string $theme): ?array
    {
        $history = $this->loadHistory($theme);
        $recentPairs = [];

        // Criar array de pares recentes (últimas 10 combinações)
        foreach (array_slice($history, 0, 10) as $item) {
            $recentPairs[] = $item['word'] . '|' . $item['hint'];
        }

        // Tentar até 20 vezes encontrar um par que não esteja no histórico
        $attempts = 0;
        while ($attempts < 20) {
            $availableWords = array_keys($wordsWithHints);
            $randomWord = $availableWords[array_rand($availableWords)];
            $wordHints = $wordsWithHints[$randomWord];
            $randomHint = $wordHints[array_rand($wordHints)];

            $pairKey = $randomWord . '|' . $randomHint;

            if (!in_array($pairKey, $recentPairs)) {
                return [
                    'word' => $randomWord,
                    'hint' => $randomHint
                ];
            }

            $attempts++;
        }

        // Se não conseguir evitar histórico, retornar qualquer par
        $availableWords = array_keys($wordsWithHints);
        $randomWord = $availableWords[array_rand($availableWords)];
        $wordHints = $wordsWithHints[$randomWord];
        $randomHint = $wordHints[array_rand($wordHints)];

        return [
            'word' => $randomWord,
            'hint' => $randomHint
        ];
    }

    /**
     * Temas de fallback caso a IA não funcione
     */
    private function fallbackThemes(): JsonResponse
    {
        $themes = [
            "Profissões",
            "Frutas",
            "Animais",
            "Países",
            "Esportes",
            "Jovens Músicos",
            "Personagens De Histórias",
            "Jogos De Tabuleiro",
            "Times De Futebol",
            "Clash Royale",
            "Veículos",
            "Cidades",
            "Alimentos",
            "Dinossauros",
            "Super-Heróis",
            "Instrumentos Musicais",
            "Festas",
            "Brinquedos",
            "Aventuras",
            "Prédios Históricos"
        ];

        return response()->json([
            'success' => true,
            'themes' => $themes
        ]);
    }

    /**
     * Gera palavras específicas para o tema Clash Royale
     */
    private function generateClashRoyaleWords(int $playerCount): JsonResponse
    {
        try {
            // Carregar lista fixa de cartas do Clash Royale
            $cardsFile = 'cache/words_clash_royale.json';

            if (!Storage::exists($cardsFile)) {
                Log::error('Arquivo de cartas do Clash Royale não encontrado');
                return $this->fallbackWords('Clash Royale', $playerCount);
            }

            $cardsContent = Storage::get($cardsFile);
            $cards = json_decode($cardsContent, true);

            if (!is_array($cards) || empty($cards)) {
                Log::error('Formato inválido do arquivo de cartas do Clash Royale');
                return $this->fallbackWords('Clash Royale', $playerCount);
            }

            // Carregar histórico para evitar repetições
            $history = $this->loadHistory('Clash Royale');
            $recentCards = array_column(array_slice($history, 0, 10), 'word');

            // Selecionar carta que não esteja no histórico recente
            $availableCards = array_diff($cards, $recentCards);

            if (empty($availableCards)) {
                // Se todas as cartas foram usadas recentemente, usar qualquer uma
                Log::info('Todas as cartas do Clash Royale foram usadas recentemente, selecionando qualquer carta.');
                $availableCards = $cards;
            }

            $selectedCard = $availableCards[array_rand($availableCards)];

            // Gerar dica usando IA
            $hint = $this->generateClashRoyaleHint($selectedCard);

            if (!$hint) {
                // Fallback para dicas genéricas
                $hint = 'Carta do Clash Royale';
            }

            // Adicionar ao histórico
            $this->addToHistory('Clash Royale', $selectedCard, $hint);

            return $this->distributeRoles($selectedCard, $hint, $playerCount);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar palavras do Clash Royale: ' . $e->getMessage());
            return $this->fallbackWords('Clash Royale', $playerCount);
        }
    }

    /**
     * Gera dica específica para uma carta do Clash Royale usando IA
     */
    private function generateClashRoyaleHint(string $cardName): ?string
    {
        $prompt = "Para a carta '{$cardName}' do jogo Clash Royale, gere UMA dica genérica em português que seja aplicável a várias cartas do jogo. ";
        $prompt .= "A dica deve ser sobre características como: tipo de tropa, custo de elixir (baixo/médio/alto), velocidade (lenta/normal/rápida), alcance (corpo a corpo/longo alcance), raridade (comum/rara/épica/lendária), etc. ";
        $prompt .= "NÃO mencione o nome da carta. NÃO seja muito específico. Seja genérico para tornar o jogo desafiador. ";
        $prompt .= "Exemplos: 'custo alto de elixir', 'ataca à distância', 'tropa terrestre', 'velocidade rápida', 'raridade épica'. ";
        $prompt .= "Responda APENAS com a dica, sem explicações adicionais.";

        $payload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 100
        ];

        $data = $this->makeGroqApiRequest($payload);

        if ($data && isset($data['choices'][0]['message']['content'])) {
            $hint = trim($data['choices'][0]['message']['content']);

            // Remover aspas se existirem
            $hint = trim($hint, '"\'');

            return !empty($hint) ? $hint : null;
        }

        return null;
    }

    /**
     * Palavras de fallback caso a IA não funcione
     */
    private function fallbackWords(string $theme, int $playerCount): JsonResponse
    {
        $fallbackData = [
            'animais' => ['cachorro', 'doméstico'],
            'frutas' => ['maçã', 'doce'],
            'profissões' => ['médico', 'ajuda pessoas'],
            'objetos da casa' => ['sofá', 'confortável'],
            'veículos' => ['carro', 'transporte'],
            'cores' => ['azul', 'frio'],
            'países' => ['brasil', 'américa do sul'],
            'comidas' => ['pizza', 'quente'],
            'esportes' => ['futebol', 'time'],
            'filmes famosos' => ['titanic', 'drama'],
            'clash royale' => ['Gigante', 'custo alto de elixir']
        ];

        $themeKey = strtolower($theme);
        $data = $fallbackData[$themeKey] ?? ['palavra', 'característica'];

        // Adicionar ao histórico
        $this->addToHistory($theme, $data[0], $data[1]);

        return $this->distributeRoles($data[0], $data[1], $playerCount);
    }
}
