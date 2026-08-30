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
];