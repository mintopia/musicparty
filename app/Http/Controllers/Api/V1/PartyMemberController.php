<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Party\Actions\BanMember;
use App\Domain\Party\Actions\ChangeMemberRole;
use App\Domain\Party\Actions\ListPartyMembers;
use App\Domain\Party\Actions\UnbanMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeMemberRoleRequest;
use App\Http\Resources\V1\PartyMemberResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartyMemberController extends Controller
{
    public function index(Request $request, ListPartyMembers $listMembers, Party $party): AnonymousResourceCollection
    {
        $this->authorize('viewMembers', $party);

        return PartyMemberResource::collection($listMembers($party, $request->user()?->can('moderate', $party) ?? false));
    }

    public function role(ChangeMemberRoleRequest $request, ChangeMemberRole $changeRole, Party $party, PartyMember $member): PartyMemberResource
    {
        $this->authorize('manageRoles', $party);
        $this->ensureBelongsTo($party, $member);

        return new PartyMemberResource($changeRole($this->currentUser($request), $party, $member, $request->role()));
    }

    public function ban(Request $request, BanMember $banMember, Party $party, PartyMember $member): PartyMemberResource
    {
        $this->authorize('moderate', $party);
        $this->ensureBelongsTo($party, $member);

        return new PartyMemberResource($banMember($this->currentUser($request), $party, $member));
    }

    public function unban(Request $request, UnbanMember $unbanMember, Party $party, PartyMember $member): PartyMemberResource
    {
        $this->authorize('moderate', $party);
        $this->ensureBelongsTo($party, $member);

        return new PartyMemberResource($unbanMember($this->currentUser($request), $party, $member));
    }

    private function ensureBelongsTo(Party $party, PartyMember $member): void
    {
        abort_unless($member->party_id === $party->id, 404);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
