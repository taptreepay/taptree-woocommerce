<?php
/**
 * Pure-PHP CRC32C (Castagnoli) — polynomial 0x1EDC6F41 (reflected: 0x82F63B78).
 *
 * Used to verify the trailing 4-byte checksum of v2 API keys (tt_<env>_<scope>_<rand>_<crc>).
 * No PHP extension dependency (hash('crc32c', ...) is only available with the hash extension
 * built against a modern libxxhash; not guaranteed on shared WP hosts).
 *
 * Compatible with PHP 7.4+.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!function_exists('taptree_crc32c')) {
    /**
     * Compute CRC32C of a binary string.
     *
     * @param string $data
     * @return int 32-bit unsigned integer (as PHP int; on 64-bit always fits).
     */
    function taptree_crc32c($data)
    {
        static $table = null;
        if ($table === null) {
            $table = [];
            for ($i = 0; $i < 256; $i++) {
                $c = $i;
                for ($j = 0; $j < 8; $j++) {
                    $c = ($c & 1) ? (0x82F63B78 ^ ($c >> 1)) : ($c >> 1);
                }
                $table[$i] = $c & 0xFFFFFFFF;
            }
        }

        $crc = 0xFFFFFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc = ($table[($crc ^ ord($data[$i])) & 0xFF] ^ ($crc >> 8)) & 0xFFFFFFFF;
        }
        return ($crc ^ 0xFFFFFFFF) & 0xFFFFFFFF;
    }
}

if (!function_exists('taptree_base32_encode_bytes')) {
    /**
     * RFC 4648 base32 encoding of a raw byte string, no padding, uppercase.
     * For 4 input bytes (32 bits) the output is ceil(32/5) = 7 chars; we
     * truncate the trailing low-bit char to land on 6 chars (30 bits of
     * checksum entropy — sufficient to catch typos; collision risk 1/2^30).
     *
     * Returned chars are uppercase A-Z and 2-7.
     *
     * @param string $bytes
     * @return string
     */
    function taptree_base32_encode_bytes($bytes)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        $bitsLen = strlen($bits);
        for ($i = 0; $i + 5 <= $bitsLen; $i += 5) {
            $out .= $alphabet[bindec(substr($bits, $i, 5))];
        }
        return $out;
    }
}

if (!function_exists('taptree_compute_crc_tail')) {
    /**
     * Given the prefix portion of a v2 key (everything before the final '_crc'
     * segment, i.e. "tt_<env>_<scope>_<rand>"), compute the expected 6-char
     * base32 CRC32C tail.
     *
     * @param string $prefix
     * @return string 6 uppercase base32 chars
     */
    function taptree_compute_crc_tail($prefix)
    {
        $crc = taptree_crc32c($prefix);
        // pack as 4 big-endian bytes
        $bytes = chr(($crc >> 24) & 0xFF)
            . chr(($crc >> 16) & 0xFF)
            . chr(($crc >> 8) & 0xFF)
            . chr($crc & 0xFF);
        $b32 = taptree_base32_encode_bytes($bytes);
        return substr($b32, 0, 6);
    }
}
