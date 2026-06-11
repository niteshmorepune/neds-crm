<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $term = (string) $request->string('q')->trim();

        return view('search.index', [
            'term' => $term,
            'sections' => $term === '' ? [] : $search->search($request->user(), $term),
        ]);
    }
}
