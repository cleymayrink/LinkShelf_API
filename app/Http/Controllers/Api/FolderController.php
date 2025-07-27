<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FolderController extends Controller
{
    /**
     * Display a listing of the folders for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $request->user()->folders()->with('tags')->get();
    }

    /**
     * Store a newly created folder in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $folder = $request->user()->folders()->create([
                'name' => $request->input('name'),
            ]);

            if ($request->has('tag_ids')) {
                $folder->tags()->attach($request->input('tag_ids'));
            }

            DB::commit();
            return response()->json($folder->load('tags'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar pasta: " . $e->getMessage());
            return response()->json(['message' => 'Erro interno ao criar a pasta.'], 500);
        }
    }

    /**
     * Update the specified folder in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Folder  $folder
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Folder $folder)
    {
        if ($request->user()->id !== $folder->user_id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $folder->name = $request->input('name');
            $folder->save();

            if ($request->has('tag_ids')) {
                $folder->tags()->sync($request->input('tag_ids'));
            } else {
                $folder->tags()->detach();
            }

            DB::commit();
            return response()->json($folder->load('tags'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar pasta: " . $e->getMessage());
            return response()->json(['message' => 'Erro interno ao atualizar a pasta.'], 500);
        }
    }

    /**
     * Remove the specified folder from storage.
     *
     * @param  \App\Models\Folder  $folder
     * @return \Illuminate\Http\Response
     */
    public function destroy(Folder $folder)
    {
        if (auth()->user()->id !== $folder->user_id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        DB::beginTransaction();
        try {
            $folder->tags()->detach();
            $folder->delete();

            DB::commit();
            return response()->json(['message' => 'Pasta excluída com sucesso.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir pasta: " . $e->getMessage());
            return response()->json(['message' => 'Erro interno ao excluir a pasta.'], 500);
        }
    }
}
