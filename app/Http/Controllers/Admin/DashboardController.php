<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'links' => [
                'horizon' => url('/horizon'),
                'pulse' => url('/pulse'),
            ],
        ]);
    }
}
