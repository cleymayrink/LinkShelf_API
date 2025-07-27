<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link; // Importar o modelo Link
use App\Models\Folder; // Importar o modelo Folder
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Perform a unified search across links and folders for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = $request->query('q'); // O termo de pesquisa
        $user = $request->user();

        if (empty($query)) {
            return response()->json([
                'links' => [],
                'folders' => []
            ]);
        }

        // Pesquisar links
        $links = $user->links()
                      ->with('tags')
                      ->where(function ($q) use ($query) {
                          $q->where('title', 'LIKE', '%' . $query . '%')
                            ->orWhere('summary', 'LIKE', '%' . $query . '%')
                            ->orWhereHas('tags', function ($qr) use ($query) {
                                $qr->where('name', 'LIKE', '%' . $query . '%');
                            });
                      })
                      ->latest()
                      ->limit(10) // Limitar resultados de links
                      ->get();

        // Pesquisar pastas
        $folders = $user->folders()
                        ->with('tags')
                        ->where(function ($q) use ($query) {
                            $q->where('name', 'LIKE', '%' . $query . '%')
                              ->orWhereHas('tags', function ($qr) use ($query) {
                                  $qr->where('name', 'LIKE', '%' . $query . '%');
                              });
                        })
                        ->latest()
                        ->limit(5) // Limitar resultados de pastas
                        ->get();

        return response()->json([
            'links' => $links,
            'folders' => $folders
        ]);
    }
}
