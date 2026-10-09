<?php

namespace App\Services\AsyncApi;

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

            if (! class_exists($class)) {
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
}
