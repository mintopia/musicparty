<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ThemeStylesheetController extends Controller
{
    public function __invoke(Request $request, string $code): Response
    {
        /** @var array{css: string, version: string}|null $rendered */
        $rendered = Cache::rememberForever("theme.css.{$code}", function () use ($code): ?array {
            $theme = Theme::whereCode($code)->first();

            return $theme ? ['css' => $theme->renderCss(), 'version' => $theme->version()] : null;
        });
        abort_if($rendered === null, 404);

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
