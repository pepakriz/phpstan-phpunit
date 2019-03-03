<?php declare(strict_types = 1);

namespace PHPStan\Type\PHPUnit;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;

class GetMockBuilderDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return 'PHPUnit\Framework\TestCase';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getMockBuilder';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$parametersAcceptor = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants());
		$mockBuilderType = $parametersAcceptor->getReturnType();
		if (count($methodCall->args) === 0) {
			return $mockBuilderType;
		}

		if (!$mockBuilderType instanceof TypeWithClassName) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		$argType = $scope->getType($methodCall->args[0]->value);

		$resultTypes = [];
		foreach (TypeUtils::getConstantStrings($argType) as $constantStringType) {
			$resultTypes[] = new MockBuilderType($mockBuilderType, $constantStringType->getValue());
			$argType = TypeCombinator::remove($argType, $constantStringType);
		}

		if (!$argType instanceof NeverType) {
			$resultTypes[] = $mockBuilderType;
		}

		return TypeCombinator::union(...$resultTypes);
	}

}
