<?php

namespace App\Support\Realtime;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use ReflectionClass;
use RuntimeException;

class AsyncApiCoverage
{
    /**
     * @return list<class-string>
     */
    public static function discoverBroadcastEvents(string $directory, string $namespace): array
    {
        $classes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1, -4);
            $class = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
                throw new RuntimeException("Unable to autoload event class {$class}");
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isInstantiable() && ($reflection->implementsInterface(ShouldBroadcast::class) || $reflection->implementsInterface(ShouldBroadcastNow::class))) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    public static function registeredChannelPatterns(): array
    {
        $broadcaster = app(BroadcastManager::class)->driver();

        if (! $broadcaster instanceof Broadcaster) {
            throw new RuntimeException('The broadcaster does not expose its registered channels');
        }

        return array_values($broadcaster->getChannels()->keys()->map(fn (mixed $pattern): string => (string) $pattern)->all());
    }

    /**
     * @param  list<string>  $patterns
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    public static function undocumentedChannels(array $patterns, array $spec): array
    {
        $documented = [];

        foreach (is_array($spec['channels'] ?? null) ? $spec['channels'] : [] as $channel) {
            if (is_array($channel) && isset($channel['address'])) {
                $documented[self::normaliseChannel((string) $channel['address'])] = true;
            }
        }

        $missing = array_values(array_filter($patterns, fn (string $pattern): bool => ! isset($documented[self::normaliseChannel($pattern)])));
        sort($missing);

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<class-string, callable(): object>  $fixtures
     * @return list<string>
     */
    public static function payloadViolations(array $spec, array $fixtures): array
    {
        $messages = $spec['components']['messages'] ?? [];
        $violations = [];

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (! is_array($message) || ! isset($message['x-event-class'], $message['payload'])) {
                continue;
            }

            $class = (string) $message['x-event-class'];
            $name = (string) $message['name'];

            if (! isset($fixtures[$class])) {
                $violations[] = "{$name}: no fixture builds {$class}";

                continue;
            }

            $event = $fixtures[$class]();
            $payload = json_decode((string) json_encode(method_exists($event, 'broadcastWith') ? $event->broadcastWith() : []));

            foreach (PayloadSchema::violations($payload, $message['payload']) as $violation) {
                $violations[] = "{$name}: {$violation}";
            }
        }

        sort($violations);

        return $violations;
    }

    private static function normaliseChannel(string $address): string
    {
        $address = (string) preg_replace('/^(private|presence)-/', '', $address);

        return (string) preg_replace('/\{[^}]+\}/', '{}', $address);
    }

    /**
     * @param  list<class-string>  $classes
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    public static function undocumented(array $classes, array $spec): array
    {
        $messages = $spec['components']['messages'] ?? [];
        $documented = [];

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (is_array($message) && isset($message['x-event-class'])) {
                $documented[(string) $message['x-event-class']] = true;
            }
        }

        $missing = array_values(array_filter($classes, fn (string $class): bool => ! isset($documented[$class])));
        sort($missing);

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<string>  $externalConsumers
     * @return list<string>
     */
    public static function unconsumed(array $spec, string $frontendDirectory, array $externalConsumers = []): array
    {
        $source = self::frontendSource($frontendDirectory);
        $messages = $spec['components']['messages'] ?? [];
        $unconsumed = [];

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (! is_array($message) || ! isset($message['x-event-class'])) {
                continue;
            }

            $name = (string) $message['name'];

            if (in_array($name, $externalConsumers, true)) {
                continue;
            }

            if (preg_match('/[\'"`]\.'.preg_quote($name, '/').'[\'"`]/', $source) !== 1) {
                $unconsumed[] = $name;
            }
        }

        sort($unconsumed);

        return $unconsumed;
    }

    private static function frontendSource(string $directory): string
    {
        $source = '';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || ! in_array($file->getExtension(), ['vue', 'js', 'ts'], true)) {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'__tests__'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $source .= file_get_contents($file->getPathname())."\n";
        }

        return $source;
    }
}
