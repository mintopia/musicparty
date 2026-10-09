<?php

namespace Tests\Fixtures\Spotify;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use stdClass;

final class SpotifyFake
{
    /**
     * @return array<string, mixed>
     */
    public static function fixture(string $name): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents(__DIR__."/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function catalogue(?stdClass $control = null): void
    {
        $control ??= new stdClass;
        $catalogue = self::fixture('catalogue');

        Http::preventStrayRequests();
        Http::fake([
            'accounts.spotify.com/*' => Http::response(self::fixture('token')),
            'api.spotify.com/v1/search*' => function (Request $request) use ($catalogue, $control) {
                if (isset($control->next)) {
                    return self::consume($control);
                }

                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $needle = mb_strtolower((string) $query['q']);
                $matches = array_values(array_filter($catalogue, fn (array $track): bool => str_contains(mb_strtolower($track['name'].' '.$track['artists'][0]['name']), $needle)));
                $limit = (int) $query['limit'];
                $offset = (int) $query['offset'];

                return Http::response(['tracks' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => count($matches),
                    'items' => array_slice($matches, $offset, $limit),
                ]]);
            },
            'api.spotify.com/v1/tracks/*' => function (Request $request) use ($catalogue, $control) {
                if (isset($control->next)) {
                    return self::consume($control);
                }

                $id = basename((string) parse_url($request->url(), PHP_URL_PATH));
                $found = array_values(array_filter($catalogue, fn (array $track): bool => $track['id'] === $id));

                return $found === [] ? Http::response(self::fixture('error-not-found'), 404) : Http::response($found[0]);
            },
        ]);
    }

    private static function consume(stdClass $control): mixed
    {
        $response = $control->next;
        unset($control->next);

        return $response;
    }
}
