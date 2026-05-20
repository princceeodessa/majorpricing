<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function show(): RedirectResponse
    {
        return redirect()->route('catalog.index');
    }

    public function sitemap(): Response
    {
        $urls = collect([
            [
                'loc' => route('home'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
        ]);

        return response()
            ->view('home.sitemap', [
                'urls' => $urls,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
