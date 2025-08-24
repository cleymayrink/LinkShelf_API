<?php
// Em app/Services/LinkMetadataService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class LinkMetadataService
{
    private Crawler $crawler;
    protected $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function fetch(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
            ])->timeout(15)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            $this->crawler = new Crawler($html, $url);

            $title = $this->extractTitle($this->crawler);
            $imageUrl = $this->extractImageUrl($this->crawler, $title);

            if ($imageUrl) {
                if (!$this->geminiService->isImageSafe($imageUrl)) {
                    $imageUrl = null;
                }
            }

            $textContent = $this->extractTextContent($this->crawler);

            return [
                'title' => $title,
                'image_url' => $imageUrl,
                'text_content' => $textContent,
            ];
        } catch (\Exception $e) {
            Log::error("Falha ao buscar metadados da URL: {$url}. Erro: " . $e->getMessage());
            return null;
        }
    }

    private function extractTitle(Crawler $crawler): string
    {
        // 1º: Tenta o título do Open Graph (og:title)
        $ogNode = $crawler->filter('meta[property="og:title"]');
        if ($ogNode->count() > 0) {
            return trim($ogNode->first()->attr('content', ''));
        }

        // 2º: Tenta a tag <title>
        $titleNode = $crawler->filter('title');
        if ($titleNode->count() > 0) {
            return trim($titleNode->first()->text());
        }

        // 3º: Tenta a primeira tag <h1>
        $h1Node = $crawler->filter('h1')->first();
        if ($h1Node->count() > 0) {
            return trim($h1Node->text());
        }

        return '';
    }

    private function extractImageUrl(Crawler $crawler, string $pageTitle): ?string
    {
        // 1º: Tenta a imagem do Open Graph (og:image)
        $node = $crawler->filter('meta[property="og:image"]')->first();
        if ($node->count() > 0) {
            return $this->getAbsoluteUrl($node->attr('content'));
        }

        // 2º: Tenta a imagem do Twitter Card
        $node = $crawler->filter('meta[name="twitter:image"]')->first();
        if ($node->count() > 0) {
            return $this->getAbsoluteUrl($node->attr('content'));
        }

        // 3º: Tenta a tag <link rel="image_src">
        $node = $crawler->filter('link[rel="image_src"]')->first();
        if ($node->count() > 0) {
            return $this->getAbsoluteUrl($node->attr('href'));
        }

        // 4º: Encontra a melhor imagem baseada na correspondência com o título
        $bestImage = $this->findBestImageByTitleMatch($crawler, $pageTitle);
        if ($bestImage) {
            return $bestImage;
        }

        return null;
    }

    /**
     * Encontra a imagem mais relevante comparando o alt/title com o título da página.
     */
    private function findBestImageByTitleMatch(Crawler $crawler, string $pageTitle): ?string
    {
        // Pega as palavras-chave do título da página
        $titleWords = preg_split('/\s+/', strtolower($pageTitle));
        if (empty($titleWords)) {
            return null;
        }

        $bestImage = null;
        $highestScore = 0;

        // Itera sobre todas as imagens dentro do conteúdo principal (<article> ou <main>)
        $crawler->filter('article img, main img, body img')->each(
            function (Crawler $imageNode) use ($titleWords, &$bestImage, &$highestScore) {
                $altText = strtolower($imageNode->attr('alt', ''));
                $titleText = strtolower($imageNode->attr('title', ''));
                $imageText = $altText . ' ' . $titleText;

                $score = 0;
                // Calcula a pontuação baseada em quantas palavras do título aparecem no alt/title da imagem
                foreach ($titleWords as $word) {
                    if (strlen($word) > 3 && str_contains($imageText, $word)) {
                        $score++;
                    }
                }

                if ($score > $highestScore) {
                    $highestScore = $score;
                    $src = $imageNode->attr('src') ?: $imageNode->attr('data-src');
                    if ($src) {
                        $bestImage = $this->getAbsoluteUrl($src);
                    }
                }
            }
        );

        return $bestImage;
    }

    /**
     * Garante que uma URL seja absoluta.
     *
     * @param string $url
     * @return string
     */
    private function getAbsoluteUrl(string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $baseUri = $this->crawler->getUri();

        $uri = new \GuzzleHttp\Psr7\Uri($baseUri);
        $relativeUri = new \GuzzleHttp\Psr7\Uri($url);
        $absoluteUri = \GuzzleHttp\Psr7\UriResolver::resolve($uri, $relativeUri);

        return (string) $absoluteUri;
    }

    /**
     * Extrai o conteúdo de texto relevante da página, focando em blocos de conteúdo principal.
     */
    private function extractTextContent(Crawler $crawler): string //
    {
        // Cria uma cópia do crawler para manipular sem afetar outras extrações
        $contentCrawler = clone $crawler;

        // Remove scripts, estilos e elementos de navegação/rodapé/anúncio para limpar o texto
        $contentCrawler->filter('script, style, nav, footer, header, .sidebar, .ad, .advertisement, [role="navigation"], [role="complementary"], .ads, .promo, .banner')
            ->each(function (Crawler $node) {
                foreach ($node as $n) {
                    if ($n->parentNode) {
                        $n->parentNode->removeChild($n);
                    }
                }
            });

        $mainContentText = '';

        // Tenta encontrar o conteúdo principal em elementos semânticos ou de conteúdo comum
        $mainContentNode = $contentCrawler->filter('main, article, .main-content, .post-content, .entry-content, .content, #content, #main, #article')
            ->first();

        if ($mainContentNode->count() > 0) {
            // Se um nó de conteúdo principal for encontrado, extrai texto das tags dentro dele
            $mainContentText = $mainContentNode->filter('p, h1, h2, h3, h4, h5, h6, li, blockquote, span, div')
                ->each(function (Crawler $node) {
                    // Retorna o texto, ignorando elementos vazios ou com muito pouco texto (navegação residual)
                    $text = trim($node->text());
                    return strlen($text) > 10 ? $text : ''; // Filtra texto muito curto
                });
            // Filtra strings vazias resultantes de elementos com pouco texto
            $mainContentText = array_filter($mainContentText);
            $mainContentText = implode("\n", $mainContentText);
        } else {
            // Fallback: Se nenhum contêiner principal específico for encontrado, tenta no corpo da página
            $mainContentText = $contentCrawler->filter('body p, body h1, body h2, body h3, body h4, body h5, body h6, body li, body blockquote, body span, body div')
                ->each(function (Crawler $node) {
                    $text = trim($node->text());
                    return strlen($text) > 10 ? $text : '';
                });
            $mainContentText = array_filter($mainContentText);
            $mainContentText = implode("\n", $mainContentText);
        }

        // Remove múltiplas quebras de linha e espaços em branco desnecessários
        $mainContentText = preg_replace('/\s*\n\s*/', "\n", $mainContentText);
        $mainContentText = trim($mainContentText);

        return $mainContentText;
    }
}
