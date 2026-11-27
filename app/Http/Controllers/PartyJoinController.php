<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyJoinRequest;
use Illuminate\Http\RedirectResponse;

class PartyJoinController extends Controller
{
    public function store(PartyJoinRequest $request): RedirectResponse
    {
        return response()->redirectToRoute('parties.show', $request->string('code')->toString());
    }
}
