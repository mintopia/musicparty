<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\Request;

class SpotifyAPIRequest extends Request
{
    public function send($method, $url, $parameters = [], $headers = [])
    {
        Log::debug("[API] {$method} {$url}");
        return parent::send($method, $url, $parameters, $headers);
    }
}
