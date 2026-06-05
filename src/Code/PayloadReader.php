<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class PayloadReader
{
    /** @param array<string, mixed> $data */
    public static function int(array $data, string $field): int
    {
        $value = $data[$field] ?? null;

        if (!is_int($value)) {
            throw InvalidCodePayload::field($field, 'int');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function string(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value)) {
            throw InvalidCodePayload::field($field, 'string');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $field): bool
    {
        $value = $data[$field] ?? null;

        if (!is_bool($value)) {
            throw InvalidCodePayload::field($field, 'bool');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        if ($value !== null && !is_string($value)) {
            throw InvalidCodePayload::field($field, 'string|null');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function object(array $data, string $field): array
    {
        $value = $data[$field] ?? null;

        if (!is_array($value) || array_is_list($value)) {
            throw InvalidCodePayload::field($field, 'object');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    public static function listOfObjects(array $data, string $field): array
    {
        $value = $data[$field] ?? null;

        if (!is_array($value) || !array_is_list($value)) {
            throw InvalidCodePayload::field($field, 'list<object>');
        }

        foreach ($value as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw InvalidCodePayload::field($field, 'list<object>');
            }
        }

        return $value;
    }
}
