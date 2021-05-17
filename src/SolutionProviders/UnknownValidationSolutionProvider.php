<?php

namespace Spatie\Ignition\SolutionProviders;

use BadMethodCallException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Ignition\Support\StringComparator;
use Spatie\IgnitionContracts\BaseSolution;
use Spatie\IgnitionContracts\HasSolutionsForThrowable;
use Throwable;

class UnknownValidationSolutionProvider implements HasSolutionsForThrowable
{
    protected const REGEX = '/Illuminate\\\\Validation\\\\Validator::(?P<method>validate(?!(Attribute|UsingCustomRule))[A-Z][a-zA-Z]+)/m';

    public function canSolve(Throwable $throwable): bool
    {
        if (! $throwable instanceof BadMethodCallException) {
            return false;
        }

        return ! is_null($this->getMethodFromExceptionMessage($throwable->getMessage()));
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            BaseSolution::create('Unknown Validation Rule')
                ->setSolutionDescription($this->getSolutionDescription($throwable)),
        ];
    }

    protected function getSolutionDescription(Throwable $throwable): string
    {
        $method = $this->getMethodFromExceptionMessage($throwable->getMessage());

        $possibleMethod = StringComparator::findSimilarText(
            $this->getAvailableMethods()->toArray(),
            $method
        );

        if (empty($possibleMethod)) {
            return '';
        }

        $rule = Str::snake(str_replace('validate', '', $possibleMethod));

        return "Did you mean `{$rule}` ?";
    }

    protected function getMethodFromExceptionMessage(string $message): ?string
    {
        if (! preg_match(self::REGEX, $message, $matches)) {
            return null;
        }

        return $matches['method'];
    }

    protected function getAvailableMethods(): Collection
    {
        $class = new ReflectionClass(Validator::class);

        $extensions = Collection::make((app('validator')->make([], []))->extensions)
            ->keys()
            ->map(function (string $extension) {
                return 'validate'.Str::studly($extension);
            });

        return Collection::make($class->getMethods())
            ->filter(function (ReflectionMethod $method) {
                return preg_match('/(validate(?!(Attribute|UsingCustomRule))[A-Z][a-zA-Z]+)/', $method->name);
            })
            ->map(function (ReflectionMethod $method) {
                return $method->name;
            })
            ->merge($extensions);
    }
}
