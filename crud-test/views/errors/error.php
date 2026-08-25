<?php

declare(strict_types=1);

$status = $status ?? 500;
$message = $message ?? 'Something went wrong.';
?>
<h1><?= (int) $status ?></h1>
<p><?= e($message) ?></p>
<?php if (!empty($debug)): ?>
<pre><?= e((string) $debug) ?></pre>
<?php endif; ?>
<p><a href="/">← Back home</a></p>
