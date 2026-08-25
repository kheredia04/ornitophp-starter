<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'OrnitoPHP App') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="container">
        <?= $content ?>
    </main>
</body>
</html>
