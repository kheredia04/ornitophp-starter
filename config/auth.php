<?php

declare(strict_types=1);

/**
 * Authentication configuration.
 *
 * Login throttling is LAYERED — three independent buckets are checked for
 * every attempt, and any exhausted bucket rejects it:
 *
 *   'account_ip'  email + client IP       tightest: one address, one account
 *   'ip'          client IP alone         stops rotating emails from one host
 *   'account'     email alone             stops distributed attacks across IPs
 *
 * A successful login clears the per-account buckets (account_ip, account);
 * the pure ip bucket persists on purpose so shared addresses (NAT, offices)
 * are not reset by a single successful neighbour login.
 *
 * Registration is throttled by IP only ('register.throttle.ip'): a
 * per-email bucket would be rotatable and there is no account to throttle
 * before it exists. The endpoint uses LoginThrottle::fromBuckets() under a
 * dedicated "r:" prefix so /register probes never mutate the login counters.
 */
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
    'register' => [
        'throttle' => [
            'ip' => [
                'enabled' => true,
                'max_attempts' => 20,
                'decay_seconds' => 3600,
            ],
        ],
    ],
];