<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\EnterActAsHost;
use App\Domain\Admin\Actions\LeaveActAsHost;
use App\Domain\Party\Models\Party;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AdminPartyResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function enter(Request $request, Party $party, EnterActAsHost $enterActAsHost): AdminPartyResource
    {
        $enterActAsHost->handle($request->user() ?? throw new AuthenticationException, $party);

        return new AdminPartyResource($party->setAttribute('acting_as_host', true));
    }

    public function leave(Request $request, Party $party, LeaveActAsHost $leaveActAsHost): AdminPartyResource
    {
        $leaveActAsHost->handle($request->user() ?? throw new AuthenticationException, $party);

        return new AdminPartyResource($party->setAttribute('acting_as_host', false));
    }
}
