<?php

namespace App\Services\Requests;

use App\Models\Party;
use App\Models\PartyMember;
use App\Services\Contracts\MusicProvider;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\Requests\Rules\AcceptingRequestsRule;
use App\Services\Requests\Rules\BannedMemberRule;
use App\Services\Requests\Rules\ExplicitRule;
use App\Services\Requests\Rules\MaxLengthRule;
use App\Services\Requests\Rules\MinLengthRule;
use App\Services\Requests\Rules\ModerationRule;
use App\Services\Requests\Rules\NextTrackRule;
use App\Services\Requests\Rules\RecentlyPlayedRule;
use App\Services\Requests\Rules\ReservedTrackRule;
use App\Services\Requests\Rules\SimilarTrackRule;
use App\Services\Requests\Rules\TooManyRequestsRule;

class RequestRules
{
    /** @var list<RequestRule> */
    protected array $rules;

    /**
     * @param  list<RequestRule>|null  $rules  Core stage rules
     * @param  list<RequestRule>  $modRules
     * @param  list<RequestRule>  $aiRules
     */
    public function __construct(?array $rules = null, protected array $modRules = [], protected array $aiRules = [])
    {
        $this->rules = $rules ?? self::standardRules();
    }

    /**
     * @return list<RequestRule>
     */
    public static function standardRules(): array
    {
        return [
            new ReservedTrackRule(app(MusicProviderFactory::class)),
            new AcceptingRequestsRule,
            new TooManyRequestsRule,
            new BannedMemberRule,
            new MinLengthRule,
            new MaxLengthRule,
            new ExplicitRule,
            new ModerationRule,
            new SimilarTrackRule,
            new RecentlyPlayedRule,
            new NextTrackRule,
        ];
    }

    public function check(Party $party, PartyMember $member, Track $track, RequestMode $mode = RequestMode::Submit): RequestDecision
    {
        return $this->checkMany($party, $member, [$track], $mode)[$track->id];
    }

    /**
     * @param  list<Track>  $tracks
     * @return array<string, RequestDecision> keyed by track id
     */
    public function checkMany(Party $party, PartyMember $member, array $tracks, RequestMode $mode = RequestMode::Submit): array
    {
        $context = new RequestContext($party, $member, $tracks, $mode);
        $decisions = [];
        foreach ($tracks as $track) {
            $decisions[$track->id] = $this->decide($context, $track);
        }

        return $decisions;
    }

    public function checkById(Party $party, PartyMember $member, MusicProvider $provider, string $id): RequestDecision
    {
        $track = $provider->findTrack($id);

        return $track === null ? RequestDecision::reject('Song is not found in Spotify') : $this->check($party, $member, $track);
    }

    protected function decide(RequestContext $context, Track $track): RequestDecision
    {
        $stages = [$this->rules, $this->modRules];
        if ($context->mode === RequestMode::Submit) {
            $stages[] = $this->aiRules;
        }

        $flag = null;
        foreach ($stages as $rules) {
            foreach ($rules as $rule) {
                $decision = $rule->decide($context, $track);
                if ($decision->outcome === RequestOutcome::Reject) {
                    return $decision;
                }
                $flag ??= $decision->isFlagged() ? $decision : null;
            }
        }

        return $flag ?? RequestDecision::allow();
    }
}
