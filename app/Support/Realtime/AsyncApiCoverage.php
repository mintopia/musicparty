<?php

namespace App\Support\Realtime;

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
