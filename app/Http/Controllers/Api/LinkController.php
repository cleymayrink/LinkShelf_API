<?php
// Em app/Http/Controllers/Api/LinkController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Tag;
use App\Services\GeminiService;
use App\Services\LinkMetadataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LinkController extends Controller
{
    protected $metadataService;
    protected $geminiService;

    public function __construct(LinkMetadataService $metadataService, GeminiService $geminiService)
    {
        $this->metadataService = $metadataService;
        $this->geminiService = $geminiService;
    }

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $links =  $user->links()->with('tags')->latest()->get();
        return response()->json($links);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $validated['url'];

        // Passo 1: Buscar metadados
        $metadata = $this->metadataService->fetch($url);
        if (!$metadata) {
            return response()->json(['message' => 'Não foi possível buscar os dados da URL.'], 422);
        }

        // Passo 2: Gerar resumo com IA (se houver texto)
        $aiData = $this->geminiService->generateSummaryAndTags($metadata['text_content']);

        if ($aiData) {
            $summary = $aiData['summary'];
            $tags = $aiData['tags'];
        }

        // Passo 3: Criar o link no banco de dados
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $link = $user->links()->create([
            'url' => $url,
            'title' => $metadata['title'] ?: 'Título não encontrado',
            'image_url' => $metadata['image_url'],
            'summary' => $summary ?? 'Resumo não disponível.', // Usa o resumo da IA ou um padrão
        ]);

        // Passo 4 (futuro): Salvar as tags

        if (!empty($tags)) {
            $tagIds = [];
            foreach ($tags as $tagName) {
                // Procura a tag, ou cria se não existir
                $tag = Tag::firstOrCreate(['name' => trim($tagName)]);
                $tagIds[] = $tag->id;
            }
            // Associa todas as tags encontradas/criadas a este link
            $link->tags()->sync($tagIds);
        }

        $link->load('tags');

        return response()->json($link, 201);
    }
}
