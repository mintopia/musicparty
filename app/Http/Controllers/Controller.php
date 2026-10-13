<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ResolvesAuthenticatedUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ResolvesAuthenticatedUser;
    use ValidatesRequests;
}
