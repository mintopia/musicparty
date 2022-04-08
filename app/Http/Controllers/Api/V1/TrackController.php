<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchTrackRequest;
use App\Models\Party;
use App\Services\SpotifySearchService;
use App\Transformers\Api\V1\TrackTransformer;

class TrackController extends Controller
{
    protected function index(SearchTrackRequest $request, Party $party)
    {
        $search = new SpotifySearchService($party, $request->user());
        $results = $search->search($request->input('query'), $request->input('page', 1), 50);
        return fractal($results, new TrackTransformer())->respond();
    }
}
