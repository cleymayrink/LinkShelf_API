<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json($user ->folders()->with('tags')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
     public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'new_tag_names' => 'nullable|array',
            'new_tag_names.*' => 'string|max:255',
        ]);

        $folder = new Folder();
        $folder->user_id = Auth::id();
        $folder->name = $validatedData['name'];
        $folder->color = $validatedData['color'] ?? null;
        $folder->icon = $validatedData['icon'] ?? null;
        $folder->save();

        if (isset($validatedData['tag_ids'])) {
            $folder->tags()->sync($validatedData['tag_ids']);
        }

        if (isset($validatedData['new_tag_names'])) {
            $newTagIds = [];
            foreach ($validatedData['new_tag_names'] as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
                $newTagIds[] = $tag->id;
            }
            $folder->tags()->syncWithoutDetaching($newTagIds);
        }

        return response()->json($folder->load('tags'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Folder $folder)
    {
        // Verifica se o usuário autenticado é o proprietário da pasta
        if (Auth::id() !== $folder->user_id) {
            abort(403, 'This action is unauthorized for this folder.');
        }

        return response()->json($folder->load('tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Folder $folder)
    {
        // Verifica se o usuário autenticado é o proprietário da pasta
        if (Auth::id() !== $folder->user_id) {
            abort(403, 'This action is unauthorized for this folder.');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'new_tag_names' => 'nullable|array',
            'new_tag_names.*' => 'string|max:255',
        ]);

        $folder->name = $validatedData['name'];
        $folder->color = $validatedData['color'] ?? null;
        $folder->icon = $validatedData['icon'] ?? null;
        $folder->save();

        $tagIdsToSync = isset($validatedData['tag_ids']) ? $validatedData['tag_ids'] : [];

        if (isset($validatedData['new_tag_names'])) {
            foreach ($validatedData['new_tag_names'] as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
                $tagIdsToSync[] = $tag->id;
            }
        }
        $folder->tags()->sync(array_unique($tagIdsToSync));

        return response()->json($folder->load('tags'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Folder $folder)
    {
        // Verifica se o usuário autenticado é o proprietário da pasta
        if (Auth::id() !== $folder->user_id) {
            abort(403, 'This action is unauthorized for this folder.');
        }

        $folder->delete();

        return response()->json(null, 204);
    }
}
