<?php

declare(strict_types=1);

namespace staabm\PHPStanDba\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use staabm\PHPStanDba\QueryReflection\PlaceholderValidation;
use staabm\PHPStanDba\QueryReflection\QueryReflection;
use staabm\PHPStanDba\UnresolvableQueryException;

/**
 * @implements Rule<CallLike>
 *
 * @see SyntaxErrorInPreparedStatementMethodRuleTest
 */
final class SyntaxErrorInPreparedStatementMethodRule implements Rule
{
    /**
     * @var list<string>
     */
    private $classMethods;

    /**
     * @param list<string> $classMethods
     */
    public function __construct(array $classMethods)
    {
        $this->classMethods = $classMethods;
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $callLike, Scope $scope): array
    {
        if ($callLike instanceof MethodCall) {
            if (!$callLike->name instanceof Node\Identifier) {
                return [];
            }

            $methodReflection = $scope->getMethodReflection($scope->getType($callLike->var), $callLike->name->toString());
        } elseif ($callLike instanceof New_) {
            if (!$callLike->class instanceof FullyQualified) {
                return [];
            }
            $methodReflection = $scope->getMethodReflection(new ObjectType($callLike->class->toCodeString()), '__construct');
        } else {
            return [];
        }

        if (null === $methodReflection) {
            return [];
        }

        $unsupportedMethod = true;
        foreach ($this->classMethods as $classMethod) {
            sscanf($classMethod, '%[^::]::%s', $className, $methodName);

            if ($methodName === $methodReflection->getName() && $className === $methodReflection->getDeclaringClass()->getName()) {
                $unsupportedMethod = false;
                break;
            }
        }

        if ($unsupportedMethod) {
            return [];
        }

        return $this->checkErrors($callLike, $scope);
    }

    /**
     * @param MethodCall|New_ $callLike
     *
     * @return RuleError[]
     */
    private function checkErrors(CallLike $callLike, Scope $scope): array
    {
        $args = $callLike->getArgs();

        if (\count($args) < 2) {
            return [];
        }

        $queryExpr = $args[0]->value;

        if ($scope->getType($queryExpr) instanceof MixedType) {
            return [];
        }

        $queryReflection = new QueryReflection();
        $parameterTypes = $scope->getType($args[1]->value);
        try {
            $parameters = $queryReflection->resolveParameters($parameterTypes) ?? [];
        } catch (UnresolvableQueryException $exception) {
            return [
                RuleErrorBuilder::message($exception->asRuleMessage())->tip(UnresolvableQueryException::RULE_TIP)->line($callLike->getLine())->build(),
            ];
        }

        $errors = [];
        $placeholderValidation = new PlaceholderValidation();
        try {
            foreach ($queryReflection->resolvePreparedQueryStrings($queryExpr, $parameterTypes, $scope) as $queryString) {
                $queryError = $queryReflection->validateQueryString($queryString);
                if (null !== $queryError) {
                    $error = $queryError->asRuleMessage();
                    $errors[$error] = $error;
                }
            }

            foreach ($placeholderValidation->checkQuery($queryExpr, $scope, $parameters) as $error) {
                // make error messages unique
                $errors[$error] = $error;
            }

            $ruleErrors = [];
            foreach ($errors as $error) {
                $ruleErrors[] = RuleErrorBuilder::message($error)->line($callLike->getLine())->build();
            }

            return $ruleErrors;
        } catch (UnresolvableQueryException $exception) {
            return [
                RuleErrorBuilder::message($exception->asRuleMessage())->tip(UnresolvableQueryException::RULE_TIP)->line($callLike->getLine())->build(),
            ];
        }
    }
}
