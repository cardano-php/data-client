<?php

namespace CardanoPhp\DataClient\Exceptions;

/**
 * The provider answered, and the answer is not one this package can read.
 *
 * A field that should hold a number holds something else, a row that should be an object
 * is a string, a list that should hold at least one row is empty.
 *
 * Unknown extra fields are not malformed. Providers gain fields at every hard fork, and a
 * reader that refuses a response because it carries a field it has never seen refuses the
 * whole chain on the day of the fork.
 */
class MalformedProviderResponse extends ProviderException
{
    public static function notAnInteger(string $field, mixed $value): self
    {
        return self::invalid($field, 'a whole number', $value);
    }

    public static function notANumber(string $field, mixed $value): self
    {
        return self::invalid($field, 'a number', $value);
    }

    public static function outOfRange(string $field, string $value): self
    {
        return new self("{$field} is {$value}, which is outside the range this package can compute with");
    }

    public static function invalid(string $field, string $expected, mixed $value): self
    {
        return new self(sprintf(
            '%s should be %s and the provider returned %s',
            $field,
            $expected,
            is_scalar($value) ? var_export($value, true) : get_debug_type($value),
        ));
    }

    public static function shape(string $endpoint, string $expected): self
    {
        return new self("The {$endpoint} response is not {$expected}");
    }
}
