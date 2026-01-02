<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GameController extends Controller
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/responses';

    /**
     * Exibe a página principal do jogo
     */
    public function index(): View
    {
        return view('game.index');
    }

    /**
     * Gera novos temas usando IA
     */
    public function generateThemes(): JsonResponse
    {
        try {
            $payload = [
                'model' => 'llama-3.3-70b-versatile',
                'input' => 'Gere exatamente 10 temas diferentes para um jogo de família do tipo "descobrir o impostor". Cada tema deve ser uma categoria simples como: animais, frutas, profissões, etc. Responda apenas com os temas separados por vírgula, sem numeração ou explicações. Os temas também devem ser formatados com a primeira letra maiúscula. Os temas devem ser temas simples e fáceis, para que crianças consiguam particiar.',
            ];

            $data = $this->makeGroqApiRequest($payload);

            if ($data) {
                try {
                    $content = $data['output'][1]['content'][0]['text'] ?? '';
                    $themes = array_map('trim', explode(',', $content));
                    $themes = array_filter($themes);
                } catch (\Throwable $th) {
                    $themes = [];
                }

                if (count($themes) >= 8) {
                    return response()->json([
                        'success' => true,
                        'themes' => array_slice($themes, 0, 10)
                    ]);
                }
            }

            return $this->fallbackThemes();
        } catch (\Exception $e) {
            Log::error('Erro ao gerar temas: ' . $e->getMessage());
            return $this->fallbackThemes();
        }
    }

    /**
     * Gera palavras para um tema específico
     */
    public function generateWords(Request $request): JsonResponse
    {
        $theme = $request->input('theme');
        $playerCount = $request->input('player_count', 5);

        try {
            $payload = [
                'model' => 'llama-3.3-70b-versatile',
                'input' => "Para o tema '{$theme}', gere 1 palavra principal que todos os jogadores receberão e 1 dica relacionada (mas diferente e que não seja direta) que o impostor receberá. Formato: palavra|dica. Exemplo para tema 'animais': cachorro|peludo. Lembrando que a palavra e a dica devem ser simples e fáceis de entender para crianças. Outro ponto importante é que a dica não deve ser sinônimo ou muito parecida com a palavra principal para que seja mais dificil que o impostor descubra a palavra.",
            ];

            $data = $this->makeGroqApiRequest($payload);

            if ($data) {
                try {
                    $content = trim($data['output'][1]['content'][0]['text'] ?? '');
                    $parts = explode('|', $content);

                    if (count($parts) >= 2) {
                        $word = trim($parts[0]);
                        $hint = trim($parts[1]);

                        return $this->distributeRoles($word, $hint, $playerCount);
                    }
                } catch (\Throwable $th) {
                    Log::error('Erro ao processar resposta da IA: ' . $th->getMessage());
                }
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
            'animais' => ['cachorro', 'animal de estimação'],
            'frutas' => ['maçã', 'fruta vermelha'],
            'profissões' => ['médico', 'trabalha no hospital'],
            'objetos da casa' => ['sofá', 'móvel da sala'],
            'veículos' => ['carro', 'meio de transporte'],
            'cores' => ['azul', 'cor do céu'],
            'países' => ['brasil', 'país da américa do sul'],
            'comidas' => ['pizza', 'comida italiana'],
            'esportes' => ['futebol', 'esporte com bola'],
            'filmes famosos' => ['titanic', 'filme de romance no navio']
        ];

        $themeKey = strtolower($theme);
        $data = $fallbackData[$themeKey] ?? ['palavra', 'dica da palavra'];

        return $this->distributeRoles($data[0], $data[1], $playerCount);
    }
}
