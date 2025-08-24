<?php
// Em app/Http/Controllers/Api/LinkController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Services\GeminiService;
use App\Services\LinkMetadataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LinkController extends Controller
{
    protected $metadataService;
    protected $geminiService;

    public function __construct(LinkMetadataService $metadataService, GeminiService $geminiService)
    {
        $this->metadataService = $metadataService;
        $this->geminiService = $geminiService;
    }

     public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->links()->with('tags')->latest();

        if ($request->has('folder_id')) {
            $folderId = $request->query('folder_id');
            $folder = Folder::with('tags')->find($folderId);

            if (!$folder || $folder->user_id !== $user->id) {
                return response()->json(['message' => 'Pasta não encontrada ou não autorizada.'], 404);
            }

            $folderTagIds = $folder->tags->pluck('id')->toArray();

            if (empty($folderTagIds)) {
                return response()->json([]);
            }

            // foreach ($folderTagIds as $tagId) {
            //     $query->whereHas('tags', function ($q) use ($tagId) {
            //         $q->where('tags.id', $tagId);
            //     });
            // }
            // links que possuem QUALQUER tag da pasta
            $query->whereHas('tags', function ($q) use ($folderTagIds) {
                $q->whereIn('tags.id', $folderTagIds);
            });
        }

        return $query->get();
    }
    public function store(Request $request)
    {

        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $validated['url'];

        $metadata = $this->metadataService->fetch($url);
        if (!$metadata) {
            return response()->json(['message' => 'Não foi possível buscar os dados da URL.'], 422);
        }

        $aiData = $this->geminiService->generateSummaryAndTags($metadata['text_content'], $url);

        if (isset($aiData['error']) && $aiData['error'] === 'blocked') {
            throw ValidationException::withMessages([
                'url' => 'The content of this website is not allowed on the platform.',
            ]);
        }

        if ($aiData) {
            $summary = $aiData['summary'];
            $tags = $aiData['tags'];
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $link = $user->links()->create([
            'url' => $url,
            'title' => $metadata['title'] ?: 'Título não encontrado',
            'image_url' => $metadata['image_url'],
            'summary' => $summary ?? 'Resumo não disponível.',
        ]);

        if (!empty($tags)) {
            $tagIds = [];
            foreach ($tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => trim($tagName)]);
                $tagIds[] = $tag->id;
            }
            $link->tags()->sync($tagIds);
        }

        $link->load('tags');

        return response()->json($link, 201);
    }

    public function update(Request $request, Link $link)
    {
        if ($request->user()->id !== $link->user_id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'new_tag_names' => 'nullable|array',
            'new_tag_names.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $link->title = $request->input('title');
            $link->summary = $request->input('summary');
            $link->save();

            $existingTagNames = $request->input('tags', []);
            $existingTags = Tag::whereIn('name', $existingTagNames)->pluck('id');

            $newTagNames = $request->input('new_tag_names', []);
            $newTagIds = [];
            foreach ($newTagNames as $newTagName) {
                $tag = Tag::firstOrCreate(['name' => $newTagName]);
                $newTagIds[] = $tag->id;
            }

            $link->tags()->sync($existingTags->merge($newTagIds));


            DB::commit();

            return response()->json($link->load('tags'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar link: " . $e->getMessage());
            return response()->json(['message' => 'Erro interno ao atualizar o link.'], 500);
        }
    }

    public function destroy(Link $link)
    {
        if (auth()->user()->id !== $link->user_id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        try {
            $link->delete();
            return response()->json(['message' => 'Link excluído com sucesso.'], 200);
        } catch (\Exception $e) {
            Log::error("Erro ao excluir link: " . $e->getMessage());
            return response()->json(['message' => 'Erro interno ao excluir o link.'], 500);
        }
    }
}
