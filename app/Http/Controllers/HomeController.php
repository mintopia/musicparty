<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function home()
    {
        if (Setting::fetch('defaultparty')) {
            return response()->redirectToRoute('parties.show', Setting::fetch('defaultparty'));
        }
        return view('home.home');
    }

    public function proxy(Request $request)
    {
        $cookiesArr = [];
        foreach ($request->input('cookies') as $name => $value) {
            $cookiesArr[] = "{$name}=" . urlencode($value);
        }
        $cookies = implode('; ', $cookiesArr);

        $curl = curl_init();
        $timestamp = time();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://open.spotify.com/get_access_token?reason=transport&productType=web-player&totpVer=5&ts={$timestamp}000&totp={$request->input('code')}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                "Cookie: {$cookies}"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $responseData = json_decode($response);
        Log::info($response);
        return response($responseData->accessToken)->header('Content-Type', 'text/plain');
    }
}
