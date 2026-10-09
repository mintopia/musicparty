<?php

namespace App\Http\Controllers;

use App\Domain\Admin\SiteSettings;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function home(SiteSettings $site): Response|RedirectResponse
    {
        $defaultParty = $site->get('default_party');
        if ($defaultParty !== null && Party::query()->where('code', $defaultParty)->exists()) {
            return redirect('/parties/'.$defaultParty);
        }

        return Inertia::render('Home');
    }

    public function proxy(Request $request): \Illuminate\Http\Response
    {
        $cookiesArr = [];
        foreach ($request->input('cookies') as $name => $value) {
            $cookiesArr[] = "{$name}=".urlencode($value);
        }
        $cookies = implode('; ', $cookiesArr);

        $curl = curl_init();
        $timestamp = time();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://open.spotify.com/get_access_token?reason=transport&productType=web-player&totpVer=5&ts={$timestamp}000&totp={$request->input('code')}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Cookie: {$cookies}",
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $body = is_string($response) ? $response : '';
        $responseData = json_decode($body);
        Log::info($body);

        return response($responseData->accessToken)->header('Content-Type', 'text/plain');
    }
}
