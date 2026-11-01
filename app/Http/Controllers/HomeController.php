<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Guests see the simple public home page; signed-in users go straight to their own panel. */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        return $user ? redirect()->route($user->homeRoute()) : view('home');
    }
}
