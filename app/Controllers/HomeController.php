<?php

declare(strict_types=1);

namespace App\Controllers;

use Ornito\Controller;
use Ornito\Http\Request;
use Ornito\Http\Response;

/**
 * Example controller — replace with your own.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home', [
            'title' => 'Welcome',
            'appName' => (string) config('app.name', 'OrnitoPHP'),
        ]);
    }
}
