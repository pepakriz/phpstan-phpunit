<?php declare(strict_types = 1);

namespace PHPStan\Type\PHPUnit;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;

class CreateMockDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var int[] */
	private $methods = [
		'createMock' => 0,
		'createConfiguredMock' => 0,
		'createPartialMock' => 0,
		'createTestProxy' => 0,
		'getMockForAbstractClass' => 0,
		'getMockFromWsdl' => 1,
	];

	public function getClass(): string
	{
		return 'PHPUnit\Framework\TestCase';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return array_key_exists($methodReflection->getName(), $this->methods);
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$argumentIndex = $this->methods[$methodReflection->getName()];
		$parametersAcceptor = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants());
		if (!isset($methodCall->args[$argumentIndex])) {
			return $parametersAcceptor->getReturnType();
		}
		$argType = $scope->getType($methodCall->args[$argumentIndex]->value);

		$resultTypes = [];
		foreach (TypeUtils::getConstantStrings($argType) as $constantStringType) {
			$resultTypes[] = new ObjectType($constantStringType->getValue());
			$argType = TypeCombinator::remove($argType, $constantStringType);
		}

		if (!$argType instanceof NeverType) {
			$resultTypes[] = $parametersAcceptor->getReturnType();
		}

		return TypeCombinator::intersect(
			TypeCombinator::union(...$resultTypes),
			$parametersAcceptor->getReturnType()
		);
	}

}
