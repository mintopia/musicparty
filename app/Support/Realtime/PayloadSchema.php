<?php

namespace App\Support\Realtime;

class PayloadSchema
{
    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public static function violations(mixed $value, array $schema, string $path = '$'): array
    {
        $errors = [];

        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            $matches = 0;

            foreach ($schema['oneOf'] as $option) {
                if (is_array($option) && self::violations($value, $option, $path) === []) {
                    $matches++;
                }
            }

            if ($matches !== 1) {
                $errors[] = "{$path}: matches {$matches} oneOf branches, expected exactly 1";
            }
        }

        if (isset($schema['type']) && ! self::matchesType($value, $schema['type'])) {
            return [...$errors, "{$path}: expected type ".json_encode($schema['type']).', got '.get_debug_type($value)];
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path}: value is not in the documented enum";
        }

        if (isset($schema['maxLength']) && is_string($value) && mb_strlen($value) > (int) $schema['maxLength']) {
            $errors[] = "{$path}: longer than maxLength {$schema['maxLength']}";
        }

        if ($value instanceof \stdClass) {
            $errors = [...$errors, ...self::objectViolations($value, $schema, $path)];
        }

        if (is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $index => $item) {
                $errors = [...$errors, ...self::violations($item, $schema['items'], "{$path}[{$index}]")];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private static function objectViolations(\stdClass $value, array $schema, string $path): array
    {
        $errors = [];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $present = get_object_vars($value);

        foreach (is_array($schema['required'] ?? null) ? $schema['required'] : [] as $required) {
            if (! array_key_exists((string) $required, $present)) {
                $errors[] = "{$path}: missing required property {$required}";
            }
        }

        foreach ($present as $name => $child) {
            $name = (string) $name;

            if (isset($properties[$name]) && is_array($properties[$name])) {
                $errors = [...$errors, ...self::violations($child, $properties[$name], "{$path}.{$name}")];
            } elseif (($schema['additionalProperties'] ?? true) === false) {
                $errors[] = "{$path}: undocumented property {$name}";
            }
        }

        return $errors;
    }

    private static function matchesType(mixed $value, mixed $type): bool
    {
        foreach (is_array($type) ? $type : [$type] as $candidate) {
            $matches = match ($candidate) {
                'null' => $value === null,
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'array' => is_array($value),
                'object' => $value instanceof \stdClass,
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
