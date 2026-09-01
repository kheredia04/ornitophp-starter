<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'OrnitoPHP App') ?></title>
    <link rel="icon" type="image/png" href="/assets/img/icon.png">
<?php
    // Visual preset switch — one line in config/app.php selects the look.
    // 'app'      Our hand-written dark theme (public/assets/css/app.css).
    // 'bootstrap' Bootstrap 5 CDN: nearly drop-in because our views already
    //              use Bootstrap-flavoured class names (btn, card, form-control…).
    // 'tailwind'  Tailwind CDN + a thin overrides file that maps our semantic
    //              classes to utility equivalents (see tailwind-overrides.css).
    $style = config('app.style', 'app');
?>
<?php if ($style === 'bootstrap'): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1XESKbF5Q9fDp6Yb2f3x6g6p2a8cU"
          crossorigin="anonymous">
    <!-- Our dark theme overrides Bootstrap to keep the brand palette. -->
    <link rel="stylesheet" href="/assets/css/app.css">
<?php elseif ($style === 'tailwind'): ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <!-- Semantic-class bridge: maps our component names to Tailwind utilities. -->
    <link rel="stylesheet" href="/assets/css/tailwind-overrides.css">
<?php else: ?>
    <link rel="stylesheet" href="/assets/css/app.css">
<?php endif; ?>
<?php if ($style === 'app'): ?>
    <!-- Apply saved theme before first paint to prevent flash of wrong theme.
         Reads localStorage('ornito-theme') or falls back to the OS preference.
         data-theme on <html> drives the CSS variable overrides in app.css. -->
    <script>
        (function () {
            var stored = localStorage.getItem('ornito-theme');
            var theme = stored || (matchMedia('(prefers-color-scheme:light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
<?php endif; ?>
</head>
<body>
    <main class="container">
        <?= $content /* Rendered template HTML — intentionally not escaped again here. */ ?>
    </main>
<?php if (config('app.debug')): ?>
    <?php
        // Debug bar: per-request query counter from the framework boundary
        // (Model CRUD + QueryBuilder). Same SQL repeated 5+ times is the
        // classic N+1 smell — the bar flags it so you SEE it while learning.
        $querySummary = Ornito\Database\QueryLog::summary();
    ?>
    <footer class="debug-bar">
        <span><?= $querySummary['count'] ?> queries · <?= round($querySummary['total_ms'], 1) ?> ms</span>
        <?php foreach (array_slice($querySummary['repeated'], 0, 3) as $repeatedSql => $runs): ?>
            <span class="debug-bar__warning" title="<?= e($repeatedSql) ?>">same query &times;<?= $runs ?></span>
        <?php endforeach; ?>
        <?php if (count($querySummary['repeated']) > 3): ?>
            <span>+<?= count($querySummary['repeated']) - 3 ?> more</span>
        <?php endif; ?>
    </footer>
<?php endif; ?>
<?php if ($style === 'app'): ?>
    <button class="theme-toggle" type="button" aria-label="Toggle dark/light theme">
        <span class="theme-toggle-icon--dark">&#9790;</span>
        <span class="theme-toggle-icon--light">&#9728;</span>
    </button>
    <script>
        (function () {
            var btn = document.querySelector('.theme-toggle');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('ornito-theme', next);
            });
        })();
    </script>
<?php endif; ?>
</body>
</html>
