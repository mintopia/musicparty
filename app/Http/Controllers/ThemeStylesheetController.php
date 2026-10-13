<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ThemeStylesheetController extends Controller
{
    private const MISS_TTL_SECONDS = 300;

    public function __invoke(Request $request, string $code): Response
    {
        $key = "theme.css.{$code}";
        /** @var array{css: string, version: string}|false|null $rendered */
        $rendered = Cache::get($key);
        if ($rendered === null) {
            $theme = Theme::whereCode($code)->first();
            if ($theme) {
                $rendered = ['css' => $theme->renderCss(), 'version' => $theme->version()];
                Cache::forever($key, $rendered);
            } else {
                $rendered = false;
                Cache::put($key, false, self::MISS_TTL_SECONDS);
            }
        }
        abort_if($rendered === false, 404);

        $response = response($rendered['css'])
            ->header('Content-Type', 'text/css; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setEtag($rendered['version']);

        $response->header(
            'Cache-Control',
            $request->query('v') === $rendered['version'] ? 'public, max-age=31536000, immutable' : 'public, no-cache',
        );
        $response->isNotModified($request);

        return $response;
    }
}
