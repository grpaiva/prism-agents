<?php

namespace Orchestra\Sidekick\Http;

if (! \function_exists('Orchestra\Sidekick\safe_int')) {
    /**
     * Convert large id higher than Number.MAX_SAFE_INTEGER to string.
     *
     * https://stackoverflow.com/questions/47188449/json-max-int-number/47188576
     *
     * @api
     *
     * @param  mixed  $value
     */
    function safe_int($value): int|string
    {
        $jsonMaxInt = 9007199254740991;

        if (\is_int($value) && abs($value) < $jsonMaxInt) {
            return $value;
        } elseif (filter_var($value, FILTER_VALIDATE_INT) && abs($value) < $jsonMaxInt) {
            return (int) $value;
        }

        return (string) $value;
    }
}
