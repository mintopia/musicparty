<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class DeployConfig
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private array $data) {}

    public static function compose(string $relativePath): self
    {
        return self::yaml($relativePath);
    }

    public static function workflow(string $relativePath): self
    {
        return self::yaml($relativePath);
    }

    public static function yaml(string $relativePath): self
    {
        $parsed = Yaml::parse(self::read($relativePath));

        return new self(is_array($parsed) ? $parsed : []);
    }

    /**
     * @return array<string, string>
     */
    public static function env(string $relativePath): array
    {
        $values = [];

        foreach (preg_split('/\R/', self::read($relativePath)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }

        return $values;
    }

    /**
     * @return list<array{instruction: string, arguments: string, stage: int}>
     */
    public static function dockerfile(string $relativePath): array
    {
        $instructions = [];
        $stage = -1;
        $joined = preg_replace('/\\\\\R/', ' ', self::read($relativePath)) ?? '';

        foreach (preg_split('/\R/', $joined) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$instruction, $arguments] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, '');
            $instruction = strtoupper($instruction);

            if ($instruction === 'FROM') {
                $stage++;
            }

            $instructions[] = ['instruction' => $instruction, 'arguments' => trim($arguments), 'stage' => $stage];
        }

        return $instructions;
    }

    /**
     * @return array<string, string>
     */
    public static function ini(string $relativePath): array
    {
        $parsed = parse_ini_string(self::read($relativePath), false, INI_SCANNER_RAW);

        return array_map(strval(...), $parsed === false ? [] : $parsed);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function services(): array
    {
        return $this->data['services'] ?? [];
    }

    public function hasService(string $name): bool
    {
        return array_key_exists($name, $this->services());
    }

    /**
     * @return array<string, mixed>
     */
    public function service(string $name): array
    {
        return $this->services()[$name] ?? throw new RuntimeException("Service [{$name}] is not defined.");
    }

    /**
     * @return list<string>
     */
    public function command(string $service, string $key = 'command'): array
    {
        $value = $this->service($service)[$key] ?? [];

        if (is_string($value)) {
            return str_getcsv($value, ' ', '"', '');
        }

        return array_values(array_map(strval(...), (array) $value));
    }

    /**
     * @return list<string>
     */
    public function entrypoint(string $service): array
    {
        return $this->command($service, 'entrypoint');
    }

    /**
     * @return list<string>
     */
    public function envFiles(string $service): array
    {
        $value = $this->service($service)['env_file'] ?? [];

        return array_values(array_map(
            fn (mixed $entry): string => is_array($entry) ? (string) ($entry['path'] ?? '') : (string) $entry,
            (array) $value,
        ));
    }

    /**
     * @return array<string, string|null>
     */
    public function environment(string $service): array
    {
        $normalised = [];

        foreach ((array) ($this->service($service)['environment'] ?? []) as $key => $value) {
            if (is_int($key)) {
                [$key, $value] = array_pad(explode('=', (string) $value, 2), 2, null);
            }

            $normalised[(string) $key] = $value === null ? null : (string) $value;
        }

        return $normalised;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthcheck(string $service): array
    {
        return (array) ($this->service($service)['healthcheck'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function volumes(string $service): array
    {
        return array_values(array_map(
            fn (mixed $entry): string => is_array($entry)
                ? ($entry['source'] ?? '').':'.($entry['target'] ?? '')
                : (string) $entry,
            (array) ($this->service($service)['volumes'] ?? []),
        ));
    }

    private static function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 2).'/'.$relativePath;
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $contents === false ? throw new RuntimeException("Cannot read [{$relativePath}].") : $contents;
    }
}
