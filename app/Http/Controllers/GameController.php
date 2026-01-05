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
        // Registrar acesso com informações detalhadas
        $this->logAccess();

        return view('game.index');
    }

    /**
     * Registra acesso ao jogo com informações detalhadas do dispositivo
     */
    private function logAccess(): void
    {
        try {
            $request = request();

            // Capturar IP real (considerando proxies/load balancers)
            $clientIp = $this->getRealIpAddress($request);

            // Capturar informações do dispositivo/navegador
            $userAgent = $request->header('User-Agent', 'Desconhecido');
            $acceptLanguage = $request->header('Accept-Language', 'Não informado');
            $referer = $request->header('Referer', 'Acesso direto');
            $host = $request->header('Host', 'Não informado');

            // Informações da requisição
            $uri = $request->getRequestUri();
            $scheme = $request->getScheme();
            $timestamp = Carbon::now()->toISOString();

            // Tentar extrair informações básicas do User-Agent
            $deviceInfo = $this->parseUserAgent($userAgent);

            // Log resumido para visualização rápida
            Log::info("🎮 ACESSO: {$clientIp} | {$deviceInfo['platform']} | {$deviceInfo['browser']}" .
                ($deviceInfo['is_mobile'] ? ' | 📱 Mobile' : '') .
                ($deviceInfo['is_bot'] ? ' | 🤖 Bot' : ''));

            // Log formatado e detalhado
            $logMessage = "\n" . str_repeat("=", 50) . "\n";
            $logMessage .= "🎮 ACESSO DETALHADO AO JOGO\n";
            $logMessage .= str_repeat("=", 50) . "\n";
            $logMessage .= "📅 Data/Hora: {$timestamp}\n";
            $logMessage .= "🌐 IP Address: {$clientIp}\n";
            $logMessage .= "💻 Dispositivo: {$deviceInfo['platform']}\n";
            $logMessage .= "🌍 Navegador: {$deviceInfo['browser']}\n";
            $logMessage .= "📱 Mobile: " . ($deviceInfo['is_mobile'] ? 'Sim' : 'Não') . "\n";
            $logMessage .= "🤖 Bot: " . ($deviceInfo['is_bot'] ? 'Sim' : 'Não') . "\n";
            $logMessage .= "🔗 URL: {$scheme}://{$host}{$uri}\n";
            $logMessage .= "🌐 Idioma: {$acceptLanguage}\n";
            $logMessage .= "🔄 Referer: {$referer}\n";
            $logMessage .= str_repeat("=", 50);

            Log::info($logMessage);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar acesso: ' . $e->getMessage());
        }
    }

    /**
     * Obtém o IP real do cliente considerando proxies e load balancers
     */
    private function getRealIpAddress($request): string
    {
        // Headers possíveis que contêm o IP real
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',           // Nginx proxy
            'HTTP_X_FORWARDED_FOR',     // Proxy padrão
            'HTTP_X_FORWARDED',         // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
            'HTTP_CLIENT_IP',           // Proxy
            'REMOTE_ADDR'               // IP direto
        ];

        foreach ($ipHeaders as $header) {
            $ip = $request->server($header);

            if (!empty($ip) && $ip !== 'unknown') {
                // Se for uma lista (X-Forwarded-For pode ter múltiplos IPs)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validar se é um IP válido
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }

                // Se não passou na validação mas não é localhost, retornar mesmo assim
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $request->ip() ?? 'IP não identificado';
    }

    /**
     * Extrai informações básicas do User-Agent
     */
    private function parseUserAgent(string $userAgent): array
    {
        $info = [
            'platform' => 'Desconhecido',
            'browser' => 'Desconhecido',
            'is_mobile' => false,
            'is_bot' => false
        ];

        // Detectar se é bot
        $botSignatures = ['bot', 'crawl', 'spider', 'scan', 'index'];
        foreach ($botSignatures as $signature) {
            if (str_contains(strtolower($userAgent), $signature)) {
                $info['is_bot'] = true;
                break;
            }
        }

        // Detectar plataforma/OS
        if (preg_match('/Windows NT ([\\d\\.]+)/', $userAgent, $matches)) {
            $info['platform'] = 'Windows ' . $this->getWindowsVersion($matches[1]);
        } elseif (str_contains($userAgent, 'Mac OS X')) {
            preg_match('/Mac OS X ([\\d_]+)/', $userAgent, $matches);
            $info['platform'] = 'macOS ' . (isset($matches[1]) ? str_replace('_', '.', $matches[1]) : '');
        } elseif (str_contains($userAgent, 'Linux')) {
            $info['platform'] = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            preg_match('/Android ([\\d\\.]+)/', $userAgent, $matches);
            $info['platform'] = 'Android ' . ($matches[1] ?? '');
            $info['is_mobile'] = true;
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            preg_match('/OS ([\\d_]+)/', $userAgent, $matches);
            $device = str_contains($userAgent, 'iPad') ? 'iPad' : 'iPhone';
            $info['platform'] = $device . ' iOS ' . (isset($matches[1]) ? str_replace('_', '.', $matches[1]) : '');
            $info['is_mobile'] = true;
        }

        // Detectar navegador
        if (str_contains($userAgent, 'Chrome/') && !str_contains($userAgent, 'Edg/')) {
            preg_match('/Chrome\\/([\\.\\d]+)/', $userAgent, $matches);
            $info['browser'] = 'Chrome ' . ($matches[1] ?? '');
        } elseif (str_contains($userAgent, 'Firefox/')) {
            preg_match('/Firefox\\/([\\.\\d]+)/', $userAgent, $matches);
            $info['browser'] = 'Firefox ' . ($matches[1] ?? '');
        } elseif (str_contains($userAgent, 'Safari/') && !str_contains($userAgent, 'Chrome')) {
            preg_match('/Version\\/([\\.\\d]+)/', $userAgent, $matches);
            $info['browser'] = 'Safari ' . ($matches[1] ?? '');
        } elseif (str_contains($userAgent, 'Edg/')) {
            preg_match('/Edg\\/([\\.\\d]+)/', $userAgent, $matches);
            $info['browser'] = 'Edge ' . ($matches[1] ?? '');
        } elseif (str_contains($userAgent, 'Opera') || str_contains($userAgent, 'OPR/')) {
            $info['browser'] = 'Opera';
        }

        return $info;
    }

    /**
     * Converte versão do Windows NT para nome amigável
     */
    private function getWindowsVersion(string $ntVersion): string
    {
        $versions = [
            '10.0' => '10/11',
            '6.3' => '8.1',
            '6.2' => '8',
            '6.1' => '7',
            '6.0' => 'Vista',
            '5.1' => 'XP',
            '5.0' => '2000'
        ];

        return $versions[$ntVersion] ?? $ntVersion;
    }

    /**
     * Gera novos temas usando IA ou cache
     */
    public function generateThemes(): JsonResponse
    {
        return $this->fallbackThemes();
    }

    /**
     * Gera palavras para um tema específico usando palavras fixas em cache
     */
    public function generateWords(Request $request): JsonResponse
    {
        $theme = $request->input('theme');
        $playerCount = $request->input('player_count', 5);

        try {
            // Limpar histórico antigo (4 horas)
            $this->clearOldCache();

            // Lógica especial para Clash Royale
            if (strtolower($theme) === 'clash royale') {
                return $this->generateClashRoyaleWords($playerCount);
            }

            // Carregar palavras fixas do cache
            $cacheFileName = $this->getWordsCacheFileName($theme);
            $cachedWords = $this->loadWordsFromCache($cacheFileName);

            if (!$cachedWords || empty($cachedWords['words'])) {
                // Se não há palavras em cache, usar fallback
                return $this->fallbackWords($theme, $playerCount);
            }

            // Selecionar palavra usando o sistema de histórico inteligente
            $selectedPair = $this->selectWordWithSmartHistory($cachedWords['words'], $theme);

            if ($selectedPair) {
                // Adicionar ao histórico
                $this->addToHistory($theme, $selectedPair['word'], $selectedPair['hint']);

                return $this->distributeRoles($selectedPair['word'], $selectedPair['hint'], $playerCount);
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
        $themeSlug = str_replace([' ', 'ã', 'ç', 'á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'ô'], ['_', 'a', 'c', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'o'], strtolower($theme));
        return "cache/words_{$themeSlug}.json";
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
        $prompt = "Para o tema '{$theme}', gere EXATAMENTE 20 palavras com EXATAMENTE 15 dicas cada uma. ";
        $prompt .= "Cada palavra deve ser simples para crianças entenderem. ";
        $prompt .= "IMPORTANTE: As dicas devem ser características GENÉRICAS que se apliquem a VÁRIAS palavras do tema, não específicas demais. ";
        $prompt .= "Isso torna o jogo mais desafiador, pois o impostor terá dicas que podem confundir com outras opções. ";
        $prompt .= "As dicas não devem ser sinônimos da palavra nem muito óbvias. ";
        $prompt .= "Devem ser baseadas em características, comportamentos ou propriedades mais amplas. ";
        $prompt .= "FORMATO OBRIGATÓRIO (sem numeração, sem explicações): palavra1:dica1,dica2,dica3,dica4,dica5,...|palavra2:dica1,dica2,dica3,dica4,dica5,...|palavra3:dica1,dica2,dica3,dica4,dica5,... ";
        $prompt .= "Exemplo EXATO para tema 'animais': gato:carnívoro,reflexos rápidos,peludo,doméstico,pequeno,mamífero,caçador,ágil,independente,noturno|cão:doméstico,carnívoro,leal,social,protetor,mamífero,caçador,obediente,ativo,territorial|abelha:pequeno,organizado,trabalha em grupo,voador,laboriosa,coletora,importante,social,produtiva,comunicativa. ";
        $prompt .= "NÃO inclua numeração, NÃO inclua explicações, NÃO inclua texto adicional. APENAS o formato solicitado.";
        $prompt .= "Tudo deve ser única e exclusivamente em Português do Brasil. Caso o tema seja Clash Royale, as palavras devem ser SOMENTE cartas e você deve pesquisar na wiki do jogo para ter certeza que a carta existe na versão mais recente do jogo, e a dica deve ser sobre características genéricas da carta. Exemplo: Corredor:4 de elixir,raro,rápido,terrestre,tropa,ofensivo,versátil,popular,equilibrado,médio";

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

                // Processar cada grupo palavra:dica1,dica2,dica3,...
                foreach ($wordGroups as $group) {
                    if (strpos($group, ':') !== false) {
                        list($word, $hintsString) = explode(':', $group, 2);
                        $word = trim($word);
                        $hints = array_map('trim', explode(',', $hintsString));

                        // Filtrar dicas vazias
                        $hints = array_filter($hints);

                        // Verificar se a palavra não já existe e tem pelo menos 1 dica
                        if (!isset($existingWords[$word]) && !empty($hints)) {
                            $newWordsWithHints[$word] = $hints;
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
        $themeSlug = str_replace([' ', 'ã', 'ç', 'á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'ô'], ['_', 'a', 'c', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'o'], strtolower($theme));
        return "cache/history_{$themeSlug}.json";
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
     * Seleciona palavra com histórico inteligente - remove antigas quando acabam não usadas
     */
    private function selectWordWithSmartHistory(array $wordsWithHints, string $theme): ?array
    {
        $history = $this->loadHistory($theme);
        $fourHoursAgo = Carbon::now()->subHours(4);
        $recentPairs = [];

        // Criar array de pares recentes (últimas 4 horas)
        foreach ($history as $item) {
            if (isset($item['timestamp'])) {
                $itemTime = Carbon::parse($item['timestamp']);
                if ($itemTime->isAfter($fourHoursAgo)) {
                    $recentPairs[] = $item['word'] . '|' . $item['hint'];
                }
            }
        }

        // Criar array de todos os possíveis pares palavra|dica
        $allPossiblePairs = [];
        foreach ($wordsWithHints as $word => $hints) {
            foreach ($hints as $hint) {
                $allPossiblePairs[] = [
                    'key' => $word . '|' . $hint,
                    'word' => $word,
                    'hint' => $hint
                ];
            }
        }

        // Filtrar pares não usados recentemente
        $availablePairs = array_filter($allPossiblePairs, function ($pair) use ($recentPairs) {
            return !in_array($pair['key'], $recentPairs);
        });

        // Se há pares não usados, selecionar um aleatório
        if (!empty($availablePairs)) {
            $selectedPair = $availablePairs[array_rand($availablePairs)];
            return [
                'word' => $selectedPair['word'],
                'hint' => $selectedPair['hint']
            ];
        }

        // Se todos foram usados, limpar histórico parcialmente e tentar novamente
        Log::info("Todos os pares do tema '{$theme}' foram usados. Limpando histórico parcialmente.");
        $this->clearPartialHistory($theme);

        // Recarregar histórico após limpeza
        $history = $this->loadHistory($theme);
        $recentPairs = [];

        foreach ($history as $item) {
            if (isset($item['timestamp'])) {
                $itemTime = Carbon::parse($item['timestamp']);
                if ($itemTime->isAfter($fourHoursAgo)) {
                    $recentPairs[] = $item['word'] . '|' . $item['hint'];
                }
            }
        }

        // Filtrar novamente
        $availablePairs = array_filter($allPossiblePairs, function ($pair) use ($recentPairs) {
            return !in_array($pair['key'], $recentPairs);
        });

        if (!empty($availablePairs)) {
            $selectedPair = $availablePairs[array_rand($availablePairs)];
            return [
                'word' => $selectedPair['word'],
                'hint' => $selectedPair['hint']
            ];
        }

        // Como último recurso, selecionar qualquer par
        $randomPair = $allPossiblePairs[array_rand($allPossiblePairs)];
        return [
            'word' => $randomPair['word'],
            'hint' => $randomPair['hint']
        ];
    }

    
    // === FUNÇÕES DE IA (MANTIDAS PARA USO FUTURO) ===

    /**
     * Limpa metade do histórico mais antigo quando todos os pares foram usados
     */
    private function clearPartialHistory(string $theme): void
    {
        try {
            $history = $this->loadHistory($theme);

            if (count($history) > 5) {
                // Ordenar por timestamp (mais recentes primeiro)
                usort($history, function ($a, $b) {
                    $timeA = isset($a['timestamp']) ? Carbon::parse($a['timestamp']) : Carbon::now()->subDays(1);
                    $timeB = isset($b['timestamp']) ? Carbon::parse($b['timestamp']) : Carbon::now()->subDays(1);
                    return $timeB->timestamp - $timeA->timestamp;
                });

                // Manter apenas metade mais recente
                $keepCount = intval(count($history) / 2);
                $history = array_slice($history, 0, $keepCount);

                // Salvar histórico reduzido
                $fileName = $this->getHistoryFileName($theme);
                $data = [
                    'created_at' => Carbon::now()->toISOString(),
                    'history' => $history
                ];

                Storage::put($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                Log::info("Histórico do tema '{$theme}' reduzido para {$keepCount} itens.");
            }
        } catch (\Exception $e) {
            Log::error('Erro ao limpar histórico parcial: ' . $e->getMessage());
        }
    }

    // === FUNÇÕES DE IA (MANTIDAS PARA USO FUTURO) ===

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
            "Utensílios Domésticos",
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
