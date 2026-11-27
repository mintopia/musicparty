<?php

namespace App\Exceptions;

use App\Models\Party;
use App\Models\PartyMember;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorPageResponder
{
    private const array PAGES = [
        403 => [
            'title' => 'You can\'t open that page',
            'message' => 'Your account doesn\'t have access to this. If that seems wrong, ask the host or the site admin.',
        ],
        404 => [
            'title' => 'We can\'t find that page',
            'message' => 'The link may be old, or the page moved. Check the address, or go back to your parties.',
        ],
        500 => [
            'title' => 'Something went wrong on our side',
            'message' => 'Your Requests and votes are safe. Try again in a minute. If it keeps happening, tell the host or the site admin.',
        ],
    ];

    private const array PAGE_LABELS = [
        'dashboard' => 'the Dashboard',
        'ai-reviews' => 'AI reviews',
        'moderation' => 'Moderation',
        'users' => 'Members',
        'songs' => 'Requests',
        'mods' => 'Settings',
        'player' => 'the Player',
    ];

    public function __invoke(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (config('app.debug') || $request->expectsJson() || ! isset(self::PAGES[$status])) {
            return $response;
        }

        $partyNotFound = $status === 404 && $this->isPartyNotFound($e);
        $props = [
            'status' => $status,
            'title' => self::PAGES[$status]['title'],
            'message' => self::PAGES[$status]['message'],
            'partyNotFound' => $partyNotFound,
            'typedCode' => null,
            'icon' => null,
            'actions' => [],
            'breadcrumbs' => $this->breadcrumbs($status === 500 ? 'Error' : 'Page not found'),
        ];

        if ($partyNotFound) {
            $props = $this->partyNotFoundProps($props, $request, $e);
        } elseif ($status === 404 && ($disabled = $this->findException($e, PartyFeatureDisabledException::class)) instanceof PartyFeatureDisabledException) {
            $props = $this->featureDisabledProps($props, $request, $disabled);
        } elseif ($status === 403) {
            $props = $this->forbiddenProps($props, $request);
        }

        try {
            $page = Inertia::render('Error', $props)->toResponse($request);
        } catch (Throwable) {
            return $response;
        }

        return $page->setStatusCode($status);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function partyNotFoundProps(array $props, Request $request, Throwable $e): array
    {
        $typed = $request->route('party');
        if (! is_string($typed)) {
            $model = $this->findException($e, ModelNotFoundException::class);
            $ids = $model instanceof ModelNotFoundException ? $model->getIds() : [];
            $typed = is_string($ids[0] ?? null) ? $ids[0] : '';
        }

        return [
            ...$props,
            'title' => 'No party with that code',
            'message' => 'Check the code on the TV, or ask the host for the link. Codes are four letters.',
            'typedCode' => mb_strtoupper($typed),
            'breadcrumbs' => $this->breadcrumbs('Party not found'),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function featureDisabledProps(array $props, Request $request, PartyFeatureDisabledException $disabled): array
    {
        $party = $request->route('party');
        $user = $request->user();
        $isOwner = $party instanceof Party && $user !== null && $user->can('update', $party);

        $actions = [];
        if ($party instanceof Party) {
            if ($isOwner) {
                $actions[] = ['label' => 'Open Mods settings', 'href' => route('parties.mods.edit', $party), 'icon' => 'ti-settings'];
            }
            $actions[] = ['label' => 'Go to Moderation', 'href' => route('parties.moderation.index', $party), 'icon' => 'ti-shield-check'];
        }

        return [
            ...$props,
            'title' => $disabled->title,
            'message' => $isOwner ? $disabled->ownerMessage : $disabled->moderatorMessage,
            'icon' => $disabled->icon,
            'actions' => $actions,
            'breadcrumbs' => $this->breadcrumbs('Moderation', $party instanceof Party ? $party : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function forbiddenProps(array $props, Request $request): array
    {
        $party = $request->route('party');
        $user = $request->user();

        if (! $party instanceof Party || $user === null) {
            return [...$props, 'breadcrumbs' => $this->breadcrumbs('Not allowed')];
        }

        $member = $party->members()->whereUserId($user->id)->with('role')->first();
        $owner = $party->user->nickname;
        $askOwner = "Ask {$owner}, the owner";

        if ($member instanceof PartyMember && $member->banned) {
            return [
                ...$props,
                'title' => "You can't join {$party->name}",
                'message' => "The host has removed you from this party. {$askOwner}, if you think that's a mistake.",
                'icon' => 'ti-ban',
                'breadcrumbs' => $this->breadcrumbs('Not allowed', $party),
                'actions' => [['label' => 'Go to Home', 'href' => route('home'), 'icon' => 'ti-home']],
            ];
        }

        $role = $member?->role->code ?? 'user';
        $who = $this->routeRequiresOwner($request) ? 'the owner' : 'the owner and moderators';

        return [
            ...$props,
            'title' => "You can't open {$this->pageLabel($request)}",
            'message' => "Only {$who} of {$party->name} can. You're in this party as a {$role}. {$askOwner}, if you need access.",
            'icon' => 'ti-lock',
            'breadcrumbs' => $this->breadcrumbs('Not allowed', $party),
            'actions' => [['label' => 'Back to the Queue', 'href' => route('parties.show', $party), 'icon' => 'ti-playlist']],
        ];
    }

    /**
     * @return list<array{label: string, href?: string}>
     */
    private function breadcrumbs(string $current, ?Party $party = null): array
    {
        $trail = [['label' => 'Home', 'href' => route('home')]];
        if ($party instanceof Party) {
            $trail[] = ['label' => $party->name, 'href' => route('parties.show', $party)];
        }
        $trail[] = ['label' => $current];

        return $trail;
    }

    private function routeRequiresOwner(Request $request): bool
    {
        return in_array('can:update,party', $request->route()?->gatherMiddleware() ?? [], true)
            || in_array('can:player,party', $request->route()?->gatherMiddleware() ?? [], true);
    }

    private function pageLabel(Request $request): string
    {
        $segments = explode('.', (string) $request->route()?->getName());

        return self::PAGE_LABELS[$segments[1] ?? ''] ?? 'that page';
    }

    /**
     * @template T of Throwable
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function findException(Throwable $e, string $class): ?Throwable
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof $class) {
                return $current;
            }
        }

        return null;
    }

    private function isPartyNotFound(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ModelNotFoundException) {
                return $current->getModel() === Party::class;
            }
        }

        return false;
    }
}
