<?php

declare(strict_types=1);
?>
<div class="card auth-card">
    <img class="auth-icon" src="/assets/img/icon.png" alt="<?= e(config('app.name', 'OrnitoPHP')) ?> logo" width="56" height="56">
    <h1>Log in to <?= e(config('app.name', 'OrnitoPHP')) ?></h1>

    <?php if (!empty($errorMessage)) { ?>
        <div class="alert alert-error"><?= e((string) $errorMessage) ?></div>
    <?php } elseif (!empty($errors)) { ?>
        <div class="alert alert-error">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $message) { ?>
                    <li><?= e((string) $message) ?></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <form method="POST" action="/login">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($oldEmail ?? '') ?>" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <p class="auth-hint">Demo account: pato@ornitophp.dev / platypus123</p>
    <p class="auth-hint">New here? <a href="/register">Create an account</a></p>
</div>
