<?php
/**
 * TapTree API-key parser.
 *
 * Supports two formats:
 *  - v2: tt_<env>_<scope>_<rand>_<crc>
 *      env   = live | sbx        (`test` rejected — legacy artifact)
 *      scope = pri | pub         (`pub` rejected for server-side use)
 *      rand  = 32 base64url chars
 *      crc   = 6 base32 chars of CRC32C over "tt_<env>_<scope>_<rand>"
 *  - legacy: any non-empty string that does NOT start with "tt_".
 *
 * The parser is intentionally tolerant on the legacy path — existing 2.1.2
 * users have plain acceptor tokens with no documented shape, and the
 * authoritative validity check still goes through TapTree's /acceptor
 * endpoint.
 *
 * Compatible with PHP 7.4+.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once __DIR__ . '/crc32c.php';

if (!function_exists('taptree_parse_api_key')) {
    /**
     * Parse and structurally validate a TapTree API key.
     *
     * Result shape:
     *   [
     *     'format' => 'v2' | 'legacy',
     *     'env'    => 'live' | 'sbx' | null,    // null for legacy
     *     'scope'  => 'pri'  | 'pub' | null,    // null for legacy
     *     'valid'  => bool,                      // structural only; does NOT call the API
     *     'reason' => string|null,               // human-readable rejection reason
     *   ]
     *
     * @param string $key
     * @return array
     */
    function taptree_parse_api_key($key)
    {
        $key = is_string($key) ? trim($key) : '';

        if ($key === '') {
            return [
                'format' => 'legacy',
                'env'    => null,
                'scope'  => null,
                'valid'  => false,
                'reason' => 'empty',
            ];
        }

        // Legacy path — anything that does not start with "tt_".
        if (strncmp($key, 'tt_', 3) !== 0) {
            return [
                'format' => 'legacy',
                'env'    => null,
                'scope'  => null,
                'valid'  => true,
                'reason' => null,
            ];
        }

        // v2 path. Expect exactly 5 underscore-separated parts: tt, env, scope, rand, crc.
        $parts = explode('_', $key);
        if (count($parts) !== 5) {
            return [
                'format' => 'v2',
                'env'    => null,
                'scope'  => null,
                'valid'  => false,
                'reason' => 'malformed: expected 5 parts (tt_env_scope_rand_crc)',
            ];
        }

        list($prefix, $env, $scope, $rand, $crc) = $parts;

        if ($prefix !== 'tt') {
            return [
                'format' => 'v2',
                'env'    => null,
                'scope'  => null,
                'valid'  => false,
                'reason' => 'malformed: prefix must be "tt"',
            ];
        }

        // V3 C1 fix: accept env=test on PARSE (legacy artifact, never minted but
        // hand-craftable). Cross-impl parity with @taptreepay/api-key-format
        // Node package at packages/api-key-format/index.js:141. Downstream callers
        // (SettingsHelper env-slot check) decide whether a tt_test_* key is
        // acceptable in the current configuration slot. The v2 mint flow on the
        // server side NEVER produces tt_test_* — buildApiKey() rejects.
        if ($env !== 'live' && $env !== 'sbx' && $env !== 'test') {
            return [
                'format' => 'v2',
                'env'    => null,
                'scope'  => null,
                'valid'  => false,
                'reason' => 'unknown env: ' . $env,
            ];
        }

        if ($scope !== 'pri' && $scope !== 'pub') {
            return [
                'format' => 'v2',
                'env'    => $env,
                'scope'  => null,
                'valid'  => false,
                'reason' => 'unknown scope: ' . $scope,
            ];
        }

        // rand: 32 base64url chars (A-Z a-z 0-9 - _). We disallow '_' inside
        // rand because we split on '_'; in practice rand should never contain
        // '_' for that exact reason — the v2 generator emits base64url with
        // '_' substituted out.
        if (strlen($rand) !== 32 || preg_match('/[^A-Za-z0-9\-]/', $rand) === 1) {
            return [
                'format' => 'v2',
                'env'    => $env,
                'scope'  => $scope,
                'valid'  => false,
                'reason' => 'malformed rand segment (expected 32 chars [A-Za-z0-9-])',
            ];
        }

        // crc: 6 uppercase base32 chars (A-Z 2-7).
        if (strlen($crc) !== 6 || preg_match('/[^A-Z2-7]/', $crc) === 1) {
            return [
                'format' => 'v2',
                'env'    => $env,
                'scope'  => $scope,
                'valid'  => false,
                'reason' => 'malformed crc segment (expected 6 chars [A-Z2-7])',
            ];
        }

        $expected = taptree_compute_crc_tail($prefix . '_' . $env . '_' . $scope . '_' . $rand);
        if (!hash_equals($expected, $crc)) {
            return [
                'format' => 'v2',
                'env'    => $env,
                'scope'  => $scope,
                'valid'  => false,
                'reason' => 'crc mismatch (key was modified or mistyped)',
            ];
        }

        if ($scope === 'pub') {
            return [
                'format' => 'v2',
                'env'    => $env,
                'scope'  => 'pub',
                'valid'  => false,
                'reason' => 'publishable key (pub) cannot be used server-side; use a pri key',
            ];
        }

        return [
            'format' => 'v2',
            'env'    => $env,
            'scope'  => $scope,
            'valid'  => true,
            'reason' => null,
        ];
    }
}
