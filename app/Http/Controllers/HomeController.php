<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function home()
    {
        return view('app');
    }

    public function proxy(Request $request)
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

        $responseData = json_decode($response);
        Log::info($response);

        return response($responseData->accessToken)->header('Content-Type', 'text/plain');
    }
}
