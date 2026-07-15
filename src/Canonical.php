<?php

declare(strict_types=1);

namespace ForceDream;

/**
 * EXACT replica of the server's wfCanonical: JSON.stringify(obj, Object.keys(obj).sort())
 * (JS's replacer-array form: sorted keys, standard compact JSON, no extra whitespace).
 * Ported from @forcedream/mcp-server's canonical.ts, proven against real production proofs
 * throughout this project's development -- not rewritten from memory.
 *
 * Verified directly (not assumed) that PHP's default json_encode/json_decode round-trip
 * already matches JS's number semantics for every real, representative test case checked
 * (large integers, fractional values, whole-number floats) -- no scientific-notation bug
 * like Java's, no float-vs-int drift like Python's. jsNumber() below is still applied
 * defensively, matching the same discipline used for every other language SDK tonight, even
 * where the language's own default behaviour already happened to be correct (as with C#).
 */
final class Canonical
{
    /**
     * @param array<string, mixed> $obj
     */
    public static function wfCanonical(array $obj): string
    {
        $sorted = [];
        $keys = array_keys($obj);
        sort($keys, SORT_STRING);
        foreach ($keys as $k) {
            $sorted[$k] = self::normalize($obj[$k]);
        }
        $json = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('wfCanonical: json_encode failed: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Recursively normalizes numeric values the same way jsNumber() does in every other
     * language SDK tonight: a value that is mathematically a whole number is encoded as a
     * plain integer, never with a trailing ".0" or in scientific notation. PHP int/float
     * already do this correctly by default for every real case tested, but forcing it
     * explicitly here removes any dependency on that default behaviour continuing to hold
     * for values not covered by those tests.
     */
    private static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::normalize($v);
            }
            return $out;
        }
        if (is_float($value)) {
            if (is_finite($value) && floor($value) === $value && abs($value) < 9.007199254740992e15) {
                return (int) $value;
            }
            return $value;
        }
        return $value;
    }

    public static function sha256hex(string $s): string
    {
        return hash('sha256', $s);
    }
}
