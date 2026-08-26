<?php

declare(strict_types=1);

namespace App\Controllers;

use Ornito\Controller;
use Ornito\Http\Request;
use Ornito\Http\Response;
use Ornito\Session\Session;

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
            'isAuthenticated' => is_array(Session::get('user')),
            'authEnabled' => auth_module_enabled(),
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        return $this->view('home', [
            'title' => 'Hello',
            'appName' => "Hello, {$name}!",
        ]);
    }

    /**
     * Authenticated-only page (route runs through App\Middleware\Authenticate):
     * greets the user stored in the session at login time.
     */
    public function dashboard(Request $request): Response
    {
        $user = Session::get('user');
        $user = is_array($user) ? $user : [];

        return $this->view('dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
        ]);
    }
}
