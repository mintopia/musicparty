<?php

namespace Tests\Architecture\Support;

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Admin\Models\Integration;
use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\Models\Role;
use App\Domain\Admin\Models\Setting;
use App\Domain\Identity\Models\AccessToken;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\Jobs\ProcessPlayerFrame;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Stats\Models\PartyStat;
use App\Domain\Theming\Models\InstanceTheme;
use App\Support\Realtime\PlayerConnections;
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
        'forceFill',
        'increment',
        'decrement',
        'touch',
        'push',
        'restore',
        'insertOrIgnore',
        'insertGetId',
        'updateOrInsert',
        'truncate',
    ];

    /**
     * @param  array<int, string>  $members
     * @return array<string, array{members: array<int, string>, models: array<int, string>}>
     */
    public static function contextsWithMembers(string $context, array $members): array
    {
        $result = [];

        foreach (self::contexts() as $name => $definition) {
            if ($name === $context) {
                $definition['members'] = [...$definition['members'], ...$members];
            }

            $result[$name] = $definition;
        }

        return $result;
    }

    /**
     * @return array<string, array{members: array<int, string>, models: array<int, string>}>
     */
    public static function contexts(): array
    {
        return [
            'admin' => [
                'members' => [
                    'App\\Domain\\Admin\\',
                ],
                'models' => [
                    Setting::class,
                    ProviderSetting::class,
                    Role::class,
                    AdminAuditEntry::class,
                    AdminHostSession::class,
                    Integration::class,
                ],
            ],
            'identity' => [
                'members' => [
                    'App\\Domain\\Identity\\',
                ],
                'models' => [
                    User::class,
                    AccessToken::class,
                    LinkedAccount::class,
                    SocialProvider::class,
                ],
            ],
            'membership' => [
                'members' => [
                    'App\\Domain\\Membership\\',
                ],
                'models' => [
                    PartyMember::class,
                ],
            ],
            'mod' => [
                'members' => [
                    'App\\Domain\\Mod\\',
                ],
                'models' => [
                    PartyMod::class,
                ],
            ],
            'music' => [
                'members' => [
                    'App\\Domain\\Music\\',
                ],
                'models' => [
                ],
            ],
            'party' => [
                'members' => [
                    'App\\Domain\\Party\\',
                ],
                'models' => [
                    Party::class,
                    PartyLogEntry::class,
                    BlocklistEntry::class,
                ],
            ],
            'playback' => [
                'members' => [
                    'App\\Domain\\Playback\\',
                ],
                'models' => [
                ],
            ],
            'queue' => [
                'members' => [
                    'App\\Domain\\Queue\\',
                ],
                'models' => [
                    TrackRequest::class,
                    RequestVote::class,
                    Play::class,
                    Rating::class,
                ],
            ],
            'stats' => [
                'members' => [
                    'App\\Domain\\Stats\\',
                ],
                'models' => [
                    PartyStat::class,
                ],
            ],
            'theming' => [
                'members' => [
                    'App\\Domain\\Theming\\',
                ],
                'models' => [
                    InstanceTheme::class,
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

    public const LISTENER_ALLOWED_IMPORTS = [
        'Illuminate\\',
        'Laravel\\Reverb\\',
        ProcessPlayerFrame::class,
        PlayerConnections::class,
    ];

    /**
     * @param  array<int, class-string>  $classes
     * @return array<int, class-string>
     */
    public static function reverbMessageListeners(array $classes): array
    {
        return array_values(array_filter($classes, static function (string $class): bool {
            $reflection = new ReflectionClass($class);

            return ! $reflection->isInterface()
                && $reflection->hasMethod('handle')
                && str_contains(self::methodSource($reflection->getMethod('handle')), 'MessageReceived');
        }));
    }

    /**
     * @param  array<int, class-string>  $classes
     * @return array<string, array<int, string>>
     */
    public static function listenerDomainViolations(array $classes): array
    {
        $violations = [];

        foreach (self::reverbMessageListeners($classes) as $class) {
            $source = (string) file_get_contents(new ReflectionClass($class)->getFileName());
            $found = [];

            foreach (self::imports($source) as $import) {
                $allowed = false;

                foreach (self::LISTENER_ALLOWED_IMPORTS as $prefix) {
                    $allowed = $allowed || str_starts_with($import, $prefix);
                }

                if (! $allowed) {
                    $found[] = $import;
                }
            }

            if (preg_match('/\\\\?App\\\\(Domain|Models|Actions)\\\\/', preg_replace('/^(?:use|namespace)\\s.*$/m', '', $source) ?? '', $match)) {
                $found[] = $match[0];
            }

            if ($found !== []) {
                $violations[$class] = array_values(array_unique($found));
            }
        }

        return $violations;
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
     * @return array<int, class-string>
     */
    public static function missingBroadcastAs(array $classes): array
    {
        return array_values(array_filter(
            self::broadcastEvents($classes),
            static fn (string $class): bool => ! new ReflectionClass($class)->hasMethod('broadcastAs'),
        ));
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
        $owner = self::modelOwners($contexts);

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
     * @return array<string, string>
     */
    public static function modelOwners(array $contexts): array
    {
        $owner = [];

        foreach ($contexts as $name => $definition) {
            foreach ($definition['models'] as $model) {
                $owner[$model] = $name;
            }
        }

        return $owner;
    }

    /**
     * @param  array<string, array{members: array<int, string>, models: array<int, string>}>  $contexts
     * @param  array<string, string>  $owner
     */
    public static function contextOf(string $class, array $contexts, array $owner): ?string
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
     * @param  ReflectionClass<object>  $reflection
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
     * @return array<int, class-string>
     */
    private static function modelClasses(): array
    {
        return array_values(array_filter(
            self::appClasses(),
            static fn (string $class): bool => str_contains($class, '\\Models\\') && is_subclass_of($class, Model::class),
        ));
    }

    /**
     * @return array<int, ReflectionMethod>
     */
    private static function supportingMethods(string $name): array
    {
        $methods = [];

        foreach (self::modelClasses() as $model) {
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

    /**
     * @param  array<int, class-string>  $classes
     * @return array<int, class-string>
     */
    public static function partyRoleAuthorisationViolations(array $classes): array
    {
        $pattern = '/(===|!==)\s*\$?[\w>:-]*PartyRole::|PartyRole::\w+\s*(===|!==)|in_array\([^;]*PartyRole::|match\s*\([^)]*->role\)/';

        return array_values(array_filter($classes, static function (string $class) use ($pattern): bool {
            $file = new ReflectionClass($class)->getFileName();

            return str_contains($class, '\\Actions\\')
                && $file !== false
                && preg_match($pattern, (string) file_get_contents($file)) === 1;
        }));
    }
}
