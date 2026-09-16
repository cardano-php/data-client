<?php

namespace CardanoPhp\DataClient\Support;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;

/**
 * Reading numbers out of a provider response.
 *
 * Chain data arrives as a mixture of JSON numbers and decimal strings, and which of the
 * two a given field uses is the provider's choice, not the ledger's: Koios reports
 * `min_fee_a` as a number and `coins_per_utxo_size` as a string in the same object.
 * Parsing has to accept both and refuse everything else, because PHP will happily read
 * "4310 ADA" as 4310 and a float as an integer that lost its fraction.
 *
 * @internal
 */
final class Number
{
    /**
     * A whole number as the ledger means it, from an int, an integral float, or a decimal
     * string. Anything else, including a fractional value or a numeric string with
     * trailing text, is a response this package cannot read.
     */
    public static function integer(string $field, mixed $value): int
    {
        $decimal = self::decimal($field, $value);

        if (bccomp($decimal, (string) PHP_INT_MAX) === 1 || bccomp($decimal, (string) PHP_INT_MIN) === -1) {
            // Lovelace and every protocol parameter fit in a 64-bit integer, so a value
            // that does not is either a different unit or a broken provider. Casting it
            // would wrap silently and spend the wrong amount.
            throw MalformedProviderResponse::outOfRange($field, $decimal);
        }

        return (int) $decimal;
    }

    /**
     * A whole number kept as a decimal string, for quantities that are not bounded by
     * PHP's integer range. Native token quantities are minted as ledger integers wider
     * than the values this process can hold, so they stay text until something needs to
     * compare them, and then bcmath does the comparing.
     */
    public static function decimal(string $field, mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // A JSON number with no fraction is still a float to PHP. One with a fraction
            // is not a quantity the ledger produced, and rounding it would invent a value.
            if (! is_finite($value) || floor($value) !== $value) {
                throw MalformedProviderResponse::notAnInteger($field, $value);
            }

            return number_format($value, 0, '.', '');
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            // Leading zeroes and a lone minus sign are handled by the pattern; ltrim would
            // turn "000" into "".
            return $value === '-0' ? '0' : $value;
        }

        throw MalformedProviderResponse::notAnInteger($field, $value);
    }

    /**
     * A fractional parameter: the fee prices, the voting thresholds, the monetary
     * expansion rate. These are ratios rather than counts, so a float is the right shape
     * for them and a whole number is a legitimate value of one.
     */
    public static function float(string $field, mixed $value): float
    {
        if (is_int($value)) {
            return (float) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw MalformedProviderResponse::notANumber($field, $value);
            }

            return $value;
        }

        // A ratio reported as a string is how some providers avoid the precision loss of
        // a JSON float. Anything that is not entirely a number is refused, because PHP's
        // own cast reads "0.51 of the stake" as 0.51 and loses the part that said so.
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw MalformedProviderResponse::notANumber($field, $value);
    }
}
