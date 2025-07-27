<?php
// Em app/Services/GeminiService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    public function generateSummaryAndTags(string $textContent, string $url): ?array
    {
        if (empty(trim($textContent)) || !$this->apiKey) {
            return null;
        }

        // Limita o texto para não exceder os limites da API e os custos
        $truncatedText = substr($textContent, 0, 15000);

         $prompt = "Com base no texto de um site fornecido, gere um JSON com três chaves: 'title', 'summary', e 'tags'. ".
              "A 'summary' deve ser um resumo direto e conciso (máximo 3 frases) do conteúdo do site, sem introduções. ".
              "Para a chave 'tags', forneça um array de 3 a 5 tópicos ou categorias que descrevam o assunto principal do site. ".
              "As tags devem ser curtas (1 a 3 palavras), em português, e ideais para agrupar e organizar links sobre tecnologia, programação, notícias, etc. ".
              "O texto para análise é: \n\n" . $truncatedText .
              "caso o texto use a URl do site para contexto: " . $url;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Erro na API do Gemini: ' . $response->body());
                return null;
            }

            $jsonString = $response->json('candidates.0.content.parts.0.text');
            $cleanJsonString = trim(str_replace(['```json', '```'], '', $jsonString));

            $data = json_decode($cleanJsonString, true);

            return [
                'summary' => $data['summary'] ?? 'Não foi possível gerar um resumo.',
                'tags' => $data['tags'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Exceção ao chamar a API do Gemini: ' . $e->getMessage());
            return null;
        }
    }
}
