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
        try {
            // Limpar cache antigo
            $this->clearOldCache();
            
            // Verificar se já temos temas em cache
            $cacheFileName = $this->getThemesCacheFileName();
            $cachedThemes = $this->loadThemesFromCache($cacheFileName);
            
            if ($cachedThemes && count($cachedThemes) >= 20) {
                // Retornar 10 temas aleatórios do cache
                $randomThemes = array_rand(array_flip($cachedThemes), 10);
                return response()->json([
                    'success' => true,
                    'themes' => array_values($randomThemes)
                ]);
            }
            
            // Gerar novos temas via IA
            $newThemes = $this->generateThemesFromAI($cachedThemes ?? []);
            
            if (!empty($newThemes)) {
                // Mesclar com temas existentes e salvar
                $allThemes = array_unique(array_merge($cachedThemes ?? [], $newThemes));
                $this->saveThemesToCache($cacheFileName, $allThemes);
                
                return response()->json([
                    'success' => true,
                    'themes' => array_slice($newThemes, 0, 10)
                ]);
            }
            
            return $this->fallbackThemes();
        } catch (\Exception $e) {
            Log::error('Erro ao gerar temas: ' . $e->getMessage());
            return $this->fallbackThemes();
        }
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
     * Gera nome do arquivo de cache para temas
     */
    private function getThemesCacheFileName(): string
    {
        $date = Carbon::now()->format('Ymd');
        return "cache/themes_{$date}.json";
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
     * Remove arquivos de cache antigos (mais de 24 horas)
     */
    private function clearOldCache(): void
    {
        try {
            $files = Storage::files('cache');
            $yesterday = Carbon::now()->subDay()->format('Ymd');
            
            foreach ($files as $file) {
                if (preg_match('/_(\\d{8})\\.json$/', $file, $matches)) {
                    $fileDate = $matches[1];
                    if ($fileDate < $yesterday) {
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
     * Carrega temas do cache
     */
    private function loadThemesFromCache(string $fileName): ?array
    {
        try {
            if (Storage::exists($fileName)) {
                $content = Storage::get($fileName);
                $data = json_decode($content, true);
                return $data['themes'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao carregar temas do cache: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Salva temas no cache
     */
    private function saveThemesToCache(string $fileName, array $themes): void
    {
        try {
            $data = [
                'created_at' => Carbon::now()->toISOString(),
                'themes' => array_values(array_unique($themes))
            ];
            
            Storage::put($fileName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::info("Temas salvos no cache: {$fileName}");
        } catch (\Exception $e) {
            Log::error('Erro ao salvar temas no cache: ' . $e->getMessage());
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
     * Gera temas usando IA
     */
    private function generateThemesFromAI(array $existingThemes = []): array
    {
        $prompt = 'Gere exatamente 10 temas diferentes para um jogo de família do tipo "descobrir o impostor". ';
        $prompt .= 'Cada tema deve ser uma categoria simples como: animais, frutas, profissões, etc. ';
        $prompt .= 'Responda apenas com os temas separados por vírgula, sem numeração ou explicações. ';
        $prompt .= 'Os temas também devem ser formatados com a primeira letra maiúscula. ';
        $prompt .= 'Os temas devem ser temas simples e fáceis, para que crianças consiguam participar.';
        
        if (!empty($existingThemes)) {
            $existingList = implode(', ', $existingThemes);
            $prompt .= " NÃO repita estes temas que já existem: {$existingList}. Gere apenas temas novos e diferentes.";
        }

        $payload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ];

        $data = $this->makeGroqApiRequest($payload);

        if ($data && isset($data['choices'][0]['message']['content'])) {
            try {
                $content = $data['choices'][0]['message']['content'];
                $themes = array_map('trim', explode(',', $content));
                
                // Filtrar temas que já existem (proteção adicional)
                $newThemes = array_filter($themes, function($theme) use ($existingThemes) {
                    return !in_array($theme, $existingThemes, true);
                });
                
                return array_filter($newThemes);
            } catch (\Throwable $th) {
                Log::error('Erro ao processar temas da IA: ' . $th->getMessage());
            }
        }

        return [];
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
            'Animais',
            'Frutas',
            'Profissões',
            'Objetos da Casa',
            'Veículos',
            'Cores',
            'Países',
            'Comidas',
            'Esportes',
            'Filmes Famosos'
        ];

        return response()->json([
            'success' => true,
            'themes' => $themes
        ]);
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
            'filmes famosos' => ['titanic', 'drama']
        ];

        $themeKey = strtolower($theme);
        $data = $fallbackData[$themeKey] ?? ['palavra', 'característica'];
        
        // Adicionar ao histórico
        $this->addToHistory($theme, $data[0], $data[1]);

        return $this->distributeRoles($data[0], $data[1], $playerCount);
    }
}
