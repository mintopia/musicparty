<?php

namespace App\Support\OpenApi;

class RuleSchemaMapper
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array{properties: array<string, array<string, mixed>>, required: list<string>}
     */
    public function map(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $fieldRules) {
            if (str_contains((string) $field, '*') || str_contains((string) $field, '.')) {
                continue;
            }

            $tokens = $this->tokens($fieldRules);
            $properties[(string) $field] = $this->schemaFor($tokens);

            if (in_array('required', $tokens, true) && ! in_array('nullable', $tokens, true)) {
                $required[] = (string) $field;
            }
        }

        ksort($properties);
        sort($required);

        return ['properties' => $properties, 'required' => $required];
    }

    /**
     * @return list<string>
     */
    private function tokens(mixed $fieldRules): array
    {
        $parts = is_string($fieldRules) ? explode('|', $fieldRules) : (is_array($fieldRules) ? $fieldRules : []);
        $tokens = [];

        foreach ($parts as $part) {
            if (is_string($part) && $part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function schemaFor(array $tokens): array
    {
        $schema = ['type' => 'string'];
        $constraints = [];

        foreach ($tokens as $token) {
            [$name, $argument] = array_pad(explode(':', $token, 2), 2, null);

            switch ($name) {
                case 'integer':
                    $schema['type'] = 'integer';
                    break;
                case 'numeric':
                    $schema['type'] = 'number';
                    break;
                case 'boolean':
                case 'bool':
                    $schema['type'] = 'boolean';
                    break;
                case 'array':
                    $schema['type'] = 'array';
                    $schema['items'] = new \stdClass;
                    break;
                case 'uuid':
                    $schema['format'] = 'uuid';
                    break;
                case 'email':
                    $schema['format'] = 'email';
                    break;
                case 'in':
                    $schema['enum'] = $argument === null ? [] : explode(',', $argument);
                    break;
                case 'max':
                case 'min':
                    if ($argument !== null && is_numeric($argument)) {
                        $constraints[$name] = $argument + 0;
                    }
                    break;
                case 'nullable':
                    $schema['nullable'] = true;
                    break;
            }
        }

        $schemaType = $schema['type'];
        foreach ($constraints as $name => $value) {
            $schema[$this->constraintKey($schemaType, $name)] = $value;
        }

        if (isset($schema['enum']) && $schema['type'] === 'integer') {
            $schema['enum'] = array_map(intval(...), (array) $schema['enum']);
        }

        return $schema;
    }

    private function constraintKey(string $type, string $name): string
    {
        $isMax = $name === 'max';

        return match ($type) {
            'integer', 'number' => $isMax ? 'maximum' : 'minimum',
            'array' => $isMax ? 'maxItems' : 'minItems',
            default => $isMax ? 'maxLength' : 'minLength',
        };
    }
}
