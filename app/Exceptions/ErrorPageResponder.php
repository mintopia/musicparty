<?php

namespace App\Exceptions;

use App\Models\Party;
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
            'message' => 'Your requests and votes are safe. Try again in a minute. If it keeps happening, tell the host or the site admin.',
        ],
    ];

    public function __invoke(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (config('app.debug') || $request->expectsJson() || ! isset(self::PAGES[$status])) {
            return $response;
        }

        $partyNotFound = $status === 404 && $this->isPartyNotFound($e);

        try {
            $page = Inertia::render('Error', [
                'status' => $status,
                'title' => $partyNotFound ? 'No party with that code' : self::PAGES[$status]['title'],
                'message' => $partyNotFound
                    ? 'Check the code on the TV, or ask the host for the link. Codes are four letters.'
                    : self::PAGES[$status]['message'],
                'partyNotFound' => $partyNotFound,
            ])->toResponse($request);
        } catch (Throwable) {
            return $response;
        }

        return $page->setStatusCode($status);
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
