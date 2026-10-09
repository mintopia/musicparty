<?php

namespace Tests\Architecture\Support;

use App\Jobs\PartyUpdate;
use App\Models\Album;
use App\Models\Artist;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\ProviderSetting;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\Song;
use App\Models\SongRating;
use App\Models\Theme;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Observers\PartyObserver;
use App\Observers\SettingObserver;
use App\Observers\SongRatingObserver;
use App\Observers\ThemeObserver;
use App\Observers\UpcomingSongObserver;
use App\Observers\UserObserver;
use App\Observers\VoteObserver;
use App\Services\PlayedSongAugmentService;
use App\Services\SpotifySearchService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

class ArchitectureRules
{
    public const FORBIDDEN_PAYLOAD_TERMS = [
        'token',
        'secret',
        'email',
        'password',
        'apikey',
        'clientsecret',
        'credential',
    ];

    public const WRITE_METHODS = [
        'create',
        'forceCreate',
        'firstOrCreate',
        'updateOrCreate',
        'insert',
        'upsert',
        'destroy',
        'save',
        'update',
        'delete',
        'forceDelete',
    ];

    /**
     * @return array<string, array{members: array<int, string>, models: array<int, string>}>
     */
    public static function contexts(): array
    {
        return [
            'identity' => [
                'members' => [
                    'App\\Domain\\Identity\\',
                    'App\\Services\\SocialProviders\\',
                    UserObserver::class,
                    SettingObserver::class,
                    'App\\Events\\User\\',
                ],
                'models' => [
                    User::class,
                    LinkedAccount::class,
                    SocialProvider::class,
                    ProviderSetting::class,
                    Setting::class,
                ],
            ],
            'party' => [
                'members' => [
                    'App\\Domain\\Party\\',
                    PartyObserver::class,
                    ThemeObserver::class,
                    UpcomingSongObserver::class,
                    VoteObserver::class,
                    'App\\Events\\Party\\',
                    'App\\Events\\UpcomingSong\\',
                    PartyUpdate::class,
                ],
                'models' => [
                    Party::class,
                    PartyMember::class,
                    PartyModeration::class,
                    UpcomingSong::class,
                    Vote::class,
                    Role::class,
                    Theme::class,
                ],
            ],
            'music' => [
                'members' => [
                    PlayedSongAugmentService::class,
                    UpcomingSongAugmentService::class,
                    SpotifySearchService::class,
                    SongRatingObserver::class,
                ],
                'models' => [
                    Song::class,
                    Album::class,
                    Artist::class,
                    SongRating::class,
                    PlayedSong::class,
                ],
            ],
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public static function classesIn(string $directory, string $namespace): array
    {
        $classes = [];

        foreach (Finder::create()->files()->name('/^[A-Z].*\.php$/')->in($directory) as $file) {
            $relative = substr($file->getRealPath(), strlen(realpath($directory)) + 1, -4);
            $class = $namespace.'\\'.str_replace('/', '\\', $relative);

            if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    private static function appPath(string $suffix = ''): string
    {
        return dirname(__DIR__, 3).'/app'.($suffix === '' ? '' : '/'.$suffix);
    }

    /**
     * @return array<int, class-string>
     */
    public static function appClasses(): array
    {
        return self::classesIn(self::appPath(), 'App');
    }

    /**
     * @param  array<int, class-string>  $classes
     * @return array<int, class-string>
     */
    public static function broadcastEvents(array $classes): array
    {
        return array_values(array_filter($classes, static function (string $class): bool {
            $reflection = new ReflectionClass($class);

            return ! $reflection->isInterface()
                && ! $reflection->isAbstract()
                && ($reflection->implementsInterface(ShouldBroadcast::class)
                    || $reflection->implementsInterface(ShouldBroadcastNow::class));
        }));
    }

    /**
     * @param  array<int, class-string>  $classes
     * @return array<string, array<int, string>>
     */
    public static function modelSerialisationViolations(array $classes): array
    {
        $violations = [];

        foreach (self::broadcastEvents($classes) as $class) {
            $reflection = new ReflectionClass($class);
            $found = [];

            if (in_array(SerializesModels::class, self::allTraits($reflection), true)) {
                $found[] = 'uses SerializesModels';
            }

            foreach ($reflection->getProperties() as $property) {
                if (self::isModelProperty($property)) {
                    $found[] = "property \${$property->getName()} is an Eloquent model";
                }
            }

            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), Model::class, true)) {
                    $found[] = "constructor parameter \${$parameter->getName()} is an Eloquent model";
                }
            }

            if (! $reflection->hasMethod('broadcastWith')) {
                $found[] = 'missing broadcastWith()';
            }

            if ($found !== []) {
                $violations[$class] = array_values(array_unique($found));
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, class-string>  $classes
     * @param  array<int, string>  $supportingMethods
     * @return array<string, array<int, string>>
     */
    public static function forbiddenPayloadKeyViolations(array $classes, array $supportingMethods = ['getState', 'toApi']): array
    {
        $violations = [];

        foreach (self::broadcastEvents($classes) as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod('broadcastWith')) {
                continue;
            }

            $sources = [self::methodSource($reflection->getMethod('broadcastWith'))];

            foreach ($supportingMethods as $name) {
                foreach (self::supportingMethods($name) as $method) {
                    $sources[] = self::methodSource($method);
                }
            }

            $bad = [];

            foreach (self::literalKeys(implode("\n", $sources)) as $key) {
                $normalised = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));

                foreach (self::FORBIDDEN_PAYLOAD_TERMS as $term) {
                    if (str_contains($normalised, $term)) {
                        $bad[] = $key;
                    }
                }
            }

            if ($bad !== []) {
                $violations[$class] = array_values(array_unique($bad));
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, class-string>  $classes
     * @param  array<string, array{members: array<int, string>, models: array<int, string>}>|null  $contexts
     * @return array<string, array<int, string>>
     */
    public static function crossContextWriteViolations(array $classes, ?array $contexts = null): array
    {
        $contexts ??= self::contexts();
        $owner = [];

        foreach ($contexts as $name => $definition) {
            foreach ($definition['models'] as $model) {
                $owner[$model] = $name;
            }
        }

        $violations = [];

        foreach ($classes as $class) {
            $context = self::contextOf($class, $contexts, $owner);

            if ($context === null) {
                continue;
            }

            $source = (string) file_get_contents(new ReflectionClass($class)->getFileName());
            $imports = self::imports($source);
            $found = [];

            foreach ($owner as $model => $modelContext) {
                if ($modelContext === $context) {
                    continue;
                }

                $short = substr(strrchr($model, '\\'), 1);
                $resolves = in_array($model, $imports, true) || str_contains($source, '\\'.$model.'::');

                if (! $resolves) {
                    continue;
                }

                $methods = implode('|', self::WRITE_METHODS);

                if (preg_match('/(?<![\\\\\w])'.preg_quote($short, '/').'::('.$methods.')\(/', $source, $match)
                    || preg_match('/new\s+'.preg_quote($short, '/').'\s*\(/', $source)) {
                    $found[] = "{$context} writes {$modelContext} model {$short}";
                }
            }

            if ($found !== []) {
                $violations[$class] = array_values(array_unique($found));
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, array{members: array<int, string>, models: array<int, string>}>  $contexts
     * @param  array<string, string>  $owner
     */
    private static function contextOf(string $class, array $contexts, array $owner): ?string
    {
        if (isset($owner[$class])) {
            return $owner[$class];
        }

        foreach ($contexts as $name => $definition) {
            foreach ($definition['members'] as $prefix) {
                if ($class === $prefix || str_starts_with($class, $prefix)) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function imports(string $source): array
    {
        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+\w+)?;/m', $source, $matches);

        return $matches[1];
    }

    /**
     * @return array<int, string>
     */
    private static function allTraits(ReflectionClass $reflection): array
    {
        $traits = [];

        for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
            foreach ($class->getTraits() as $trait) {
                $traits[] = $trait->getName();

                foreach ($trait->getTraitNames() as $nested) {
                    $traits[] = $nested;
                }
            }
        }

        return $traits;
    }

    private static function isModelProperty(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            return is_a($type->getName(), Model::class, true);
        }

        return false;
    }

    private static function methodSource(ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    /**
     * @return array<int, ReflectionMethod>
     */
    private static function supportingMethods(string $name): array
    {
        $methods = [];

        foreach (self::classesIn(self::appPath('Models'), 'App\\Models') as $model) {
            $reflection = new ReflectionClass($model);

            if ($reflection->hasMethod($name) && $reflection->getMethod($name)->getDeclaringClass()->getName() === $model) {
                $methods[] = $reflection->getMethod($name);
            }
        }

        return $methods;
    }

    /**
     * @return array<int, string>
     */
    private static function literalKeys(string $source): array
    {
        preg_match_all('/[\'"]([A-Za-z0-9_\-.]+)[\'"]\s*=>/', $source, $arrow);
        preg_match_all('/\[\s*[\'"]([A-Za-z0-9_\-.]+)[\'"]\s*\]\s*=/', $source, $assign);

        return array_merge($arrow[1], $assign[1]);
    }
}
