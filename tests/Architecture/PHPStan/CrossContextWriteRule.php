<?php

namespace Tests\Architecture\PHPStan;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use Tests\Architecture\Support\ArchitectureRules;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
class CrossContextWriteRule implements Rule
{
    /**
     * @var array<int, string>
     */
    private readonly array $writeMethods;

    /**
     * @var array<string, string>
     */
    private array $owners;

    /**
     * @param  array<string, array{members: array<int, string>, models: array<int, string>}>  $contexts
     */
    public function __construct(private readonly array $contexts)
    {
        $this->owners = ArchitectureRules::modelOwners($contexts);
        $this->writeMethods = array_map(strtolower(...), array_merge(array_diff(ArchitectureRules::WRITE_METHODS, ['forceFill']), [
            'saveQuietly',
            'updateQuietly',
            'deleteQuietly',
            'createMany',
            'saveMany',
            'attach',
            'detach',
            'sync',
            'syncWithoutDetaching',
            'toggle',
            'updateExistingPivot',
            'incrementEach',
            'decrementEach',
        ]));
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    /**
     * @return array<int, IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $name = $this->writeMethodName($node);
        $caller = $scope->getClassReflection()?->getName();

        if ($name === null || $caller === null) {
            return [];
        }

        $callerContext = ArchitectureRules::contextOf($caller, $this->contexts, $this->owners);

        if ($callerContext === null) {
            return [];
        }

        $errors = [];

        foreach ($this->calleeModels($node, $scope) as $model) {
            $modelContext = $this->owners[$model] ?? null;

            if ($modelContext === null || $modelContext === $callerContext) {
                continue;
            }

            $short = substr((string) strrchr('\\'.$model, '\\'), 1);
            $errors[] = RuleErrorBuilder::message("Context {$callerContext} must not write {$modelContext} model {$short} via {$name}(); call an action in {$modelContext} instead.")
                ->identifier('architecture.crossContextWrite')
                ->build();
        }

        return $errors;
    }

    private function writeMethodName(Node $node): ?string
    {
        if (! ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            || ! $node->name instanceof Identifier
            || ! in_array(strtolower($node->name->name), $this->writeMethods, true)) {
            return null;
        }

        return $node->name->name;
    }

    /**
     * @return array<int, string>
     */
    private function calleeModels(Node $node, Scope $scope): array
    {
        if ($node instanceof StaticCall) {
            $type = $node->class instanceof Name
                ? $scope->resolveTypeByName($node->class)
                : $scope->getType($node->class);
        } elseif ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $type = $scope->getType($node->var);
        } else {
            return [];
        }

        return $this->modelsOf($type);
    }

    /**
     * @return array<int, string>
     */
    private function modelsOf(Type $type): array
    {
        $models = [];

        foreach ($type->getObjectClassReflections() as $reflection) {
            if ($reflection->is(Model::class)) {
                $models[] = $reflection->getName();

                continue;
            }

            foreach ([[EloquentBuilder::class, 'TModel'], [Relation::class, 'TRelatedModel']] as [$base, $template]) {
                if ($reflection->is($base)) {
                    array_push($models, ...$type->getTemplateType($base, $template)->getObjectClassNames());
                }
            }
        }

        return array_values(array_unique($models));
    }
}
