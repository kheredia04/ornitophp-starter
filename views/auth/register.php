<?php

declare(strict_types=1);
?>
<div class="card auth-card">
    <img class="auth-icon" src="/assets/img/icon.png" alt="<?= e(config('app.name', 'OrnitoPHP')) ?> logo" width="56" height="56">
    <h1>Create your account</h1>

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

    <form method="POST" action="/register">
        <?= csrf_field() ?>
        <div class="field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= e($oldName ?? '') ?>">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($oldEmail ?? '') ?>">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control">
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>

    <p class="auth-hint">Already have an account? <a href="/login">Log in</a></p>
</div>
