<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LinkController extends Controller
{

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $links = $user->links()->latest()->get();
        return response()->json($links);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        // TODO buscar metadados e usar IA

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $link = $user->links()->create([
            'url' => $validated['url'],
            'title' => 'Título',
            'summary' => 'Resumo'
        ]);

        return response()->json($link, 201);
    }
}
