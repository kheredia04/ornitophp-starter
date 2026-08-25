<?php

declare(strict_types=1);

$user = is_array($user ?? null) ? $user : [];
$name = (string) ($user['name'] ?? '');
$email = (string) ($user['email'] ?? '');
?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="topbar-brand" href="/">
            <img src="/assets/img/icon.png" alt="" width="28" height="28">
            <?= e(config('app.name', 'OrnitoPHP')) ?>
        </a>
        <div class="topbar-user">
            <span>Signed in as <code><?= e($email) ?></code></span>
            <form method="POST" action="/logout" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">Log out</button>
            </form>
        </div>
    </div>
</header>

<img class="dash-logo" src="/assets/img/logo-horizontal.png" alt="<?= e(config('app.name', 'OrnitoPHP')) ?> wordmark">

<h1>Welcome back, <?= e($name) ?>!</h1>
<p>You are seeing this page because the session carries your <code>user_id</code> — <code>App\Middleware\Authenticate</code> checked it before this controller ever ran.</p>

<footer>
    <a href="/">&larr; Back home</a>
</footer>
