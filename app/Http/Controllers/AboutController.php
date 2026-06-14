<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * About page (FASE 4D).
 *
 * Static public page telling the founding story. No auth required,
 * no data fetch — pure Inertia render with no shared props.
 */
class AboutController extends Controller
{
    /**
     * Render the public About page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('About');
    }
}