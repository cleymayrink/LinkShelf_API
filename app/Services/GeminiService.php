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

    private function getSafetySettings()
    {
        return [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
        ];
    }

    public function isImageSafe(string $imageUrl): bool
    {
        try {
            // Baixa o conteúdo da imagem
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                return true; // Não foi possível baixar, assume como seguro por enquanto
            }

            // Detecta o tipo MIME da imagem
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);

            // Monta a requisição para o modelo de visão do Gemini
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => base64_encode($imageContent)
                                ]
                            ],
                            [
                                'text' => 'Does this image contain sexually explicit content, violence, or hate symbols? Answer only "Yes" or "No".'
                            ]
                        ]
                    ]
                ],
                'safetySettings' => $this->getSafetySettings(),
            ]);

            if ($response->failed()) {
                return true; // Falha na API, assume como seguro para não bloquear links válidos
            }

            $responseData = $response->json();

            // Verifica se o prompt ou a imagem foram bloqueados pelas configurações de segurança
            if (isset($responseData['promptFeedback']['blockReason'])) {
                return false; // Conteúdo bloqueado
            }

            $textResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Se a resposta textual do Gemini indicar conteúdo impróprio, retorna false
            if (stripos(trim($textResponse), 'Yes') === 0) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Image safety check failed', ['error' => $e->getMessage(), 'url' => $imageUrl]);
            return true; // Em caso de erro, não bloqueia
        }
    }

    public function generateSummaryAndTags(string $textContent, string $url): ?array
    {
        if (empty(trim($textContent)) || !$this->apiKey) {
            return null;
        }

        // Limita o texto para não exceder os limites da API e os custos
        $truncatedText = substr($textContent, 0, 15000);

        $prompt = "Com base no texto de um site fornecido, gere um JSON com três chaves: 'title', 'summary', e 'tags'. " .
            "A 'summary' deve ser um resumo direto e conciso (máximo 3 frases) do conteúdo do site, sem introduções. " .
            "Para a chave 'tags', forneça um array de 3 a 5 tópicos ou categorias que descrevam o assunto principal do site. " .
            "As tags devem ser curtas (1 a 3 palavras), em português, e ideais para agrupar e organizar links sobre tecnologia, programação, notícias, etc. " .
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
                ],
                'safetySettings' => $this->getSafetySettings(),
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
            ]);

            if ($response->failed()) {
                Log::error('Gemini API request failed', ['response' => $response->body()]);
                return null;
            }

            // VERIFICA SE O CONTEÚDO FOI BLOQUEADO
            $responseData = $response->json();
            if (isset($responseData['promptFeedback']['blockReason'])) {
                Log::warning('Gemini content blocked', [
                    'reason' => $responseData['promptFeedback']['blockReason'],
                    'ratings' => $responseData['promptFeedback']['safetyRatings']
                ]);
                // Retorna um indicador de que o conteúdo é impróprio
                return ['error' => 'blocked', 'reason' => $responseData['promptFeedback']['blockReason']];
            }

            // Se não houver candidato ou texto, a resposta pode ser inválida
            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                return null;
            }

            $jsonString = $responseData['candidates'][0]['content']['parts'][0]['text'];
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
