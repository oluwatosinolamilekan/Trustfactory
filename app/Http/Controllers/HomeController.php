<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return RedirectResponse
     */
    public function __invoke(): RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('products.index');
        }

        return redirect()->route('login');
    }
}

