<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\BanMember;
use App\Domain\Membership\Actions\ChangeMemberRole;
use App\Domain\Membership\Actions\ListMemberRequests;
use App\Domain\Membership\Actions\ListMemberVoteHistory;
use App\Domain\Membership\Actions\ListPartyMembers;
use App\Domain\Membership\Actions\UnbanMember;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeMemberRoleRequest;
use App\Http\Requests\ListMemberVotesRequest;
use App\Http\Requests\ListPartyMembersRequest;
use App\Http\Resources\V1\MemberRequestResource;
use App\Http\Resources\V1\MemberVoteResource;
use App\Http\Resources\V1\PartyMemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyMemberController extends Controller
{
    public function index(ListPartyMembersRequest $request, ListPartyMembers $listMembers, Party $party): AnonymousResourceCollection
    {
        return PartyMemberResource::collection($listMembers(
            $party,
            true,
            $request->search(),
            $request->role(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
        ));
    }

    /**
     * @response array{data: array{requests: MemberRequestResource[]}}
     */
    public function show(ListMemberVotesRequest $request, ListMemberRequests $listRequests, Party $party, PartyMember $member): JsonResource
    {
        return new JsonResource([
            ...new PartyMemberResource($member->loadMissing('user.accounts'))->resolve($request),
            'requests' => MemberRequestResource::collection($listRequests($member))->resolve($request),
        ]);
    }

    public function votes(ListMemberVotesRequest $request, ListMemberVoteHistory $listVotes, Party $party, PartyMember $member): AnonymousResourceCollection
    {
        return MemberVoteResource::collection($listVotes($member, $request->search(), $request->perPage()));
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
