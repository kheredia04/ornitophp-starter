# Login Throttling

OrnitoPHP throttles login attempts with **three independent buckets** checked
together (`Ornito\Security\LoginThrottle`). Any exhausted bucket rejects the
attempt.

## Why layers?

Rate limiting by a single `"email|ip"` key has a hole: an attacker who rotates
emails gets a fresh bucket for every address, so the cap never bites. The fix
is to also count **IP alone** and **email alone** — keys the attacker cannot
rotate:

| Bucket | Key | Default | Stops |
|---|---|---|---|
| `account_ip` | `email\|ip` | 5 per 60s | one address hammering one account |
| `ip` | `ip` | 30 per 15min | one address trying endless emails |
| `account` | `email` | 10 per 5min | endless addresses hitting one email |

"Endless addresses" is the botnet case: even when the attacker can rotate
their IP, the account bucket stays put.

## Behaviour

- An attempt only proceeds when **every** bucket is below its limit.
- On a **failed** attempt, all three buckets are hit.
- On a **successful** login, only the per-account buckets (`account_ip`,
  `account`) are cleared. The pure `ip` bucket persists on purpose: it is
  shared by every account behind that address (NAT, offices, IP rotation),
  so one successful login must not reset the whole neighbourhood's failure
  history.
- `availableIn()` reports the **longest** blocked bucket, so the "try again
  in N seconds" message never lies.

## Configuration

All of this lives in `config/auth.php`:

```php
return [
    'login' => [
        'throttle' => [
            'account_ip' => [
                'max_attempts' => 5,
                'decay_seconds' => 60,
            ],
            'ip' => [
                'enabled' => true,
                'max_attempts' => 30,
                'decay_seconds' => 900,
            ],
            'account' => [
                'enabled' => true,
                'max_attempts' => 10,
                'decay_seconds' => 300,
            ],
        ],
    ],
];
```

Set `'enabled' => false` on a layer to disable it — e.g. turning off the `ip`
bucket when you run behind a trusted proxy chain and do not want to penalise
shared NAT ranges.

This is the OWASP-recommended shape for credential-stuffing defense.