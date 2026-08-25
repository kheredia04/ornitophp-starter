<?php

declare(strict_types=1);
?>
<section class="hero">
    <img src="/assets/img/icon.png" alt="<?= e($appName) ?> logo" width="96" height="96">
    <h1>Welcome to <?= e($appName) ?></h1>
    <p>A tiny educational PHP framework: front controller, middleware pipeline, prepared statements and real sessions — built to be read.</p>
    <div class="hero-actions">
        <?php if (!empty($authEnabled)): ?>
            <?php if (!empty($isAuthenticated)): ?>
                <form method="POST" action="/logout" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">Log out</button>
                </form>
            <?php else: ?>
                <a class="btn btn-primary" href="/login">Log in</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<footer>
    <p>Rendered by <code>Ornito\Controller::view()</code> &middot; template captured into <code>$content</code> inside <code>views/layout.php</code>.</p>
</footer>
