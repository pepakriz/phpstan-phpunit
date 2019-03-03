<?php declare(strict_types = 1);

namespace PHPStan\Type\PHPUnit;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType;

class MockBuilderDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension, \PHPStan\Reflection\BrokerAwareExtension
{

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	public function setBroker(Broker $broker): void
	{
		$this->broker = $broker;
	}

	public function getClass(): string
	{
		$testCase = $this->broker->getClass('PHPUnit\Framework\TestCase');
		$mockBuilderType = ParametersAcceptorSelector::selectSingle(
			$testCase->getNativeMethod('getMockBuilder')->getVariants()
		)->getReturnType();
		if (!$mockBuilderType instanceof TypeWithClassName) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		return $mockBuilderType->getClassName();
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return true;
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		if (!in_array(
			$methodReflection->getName(),
			[
				'getMock',
				'getMockForAbstractClass',
			],
			true
		)) {
			return $calledOnType;
		}

		$parametersAcceptor = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants());

		$calledOnTypes = $calledOnType instanceof UnionType
			? $calledOnType->getTypes()
			: [$calledOnType];

		/** @var Type[] $returnTypes */
		$returnTypes = [];
		foreach ($calledOnTypes as $nestedCalledOnType) {
			if (!$nestedCalledOnType instanceof MockBuilderType) {
				return $parametersAcceptor->getReturnType();
			}

			$returnTypes[] = new ObjectType($nestedCalledOnType->getMockedClass());
		}

		return $returnTypes[] = TypeCombinator::intersect(
			TypeCombinator::union(...$returnTypes),
			$parametersAcceptor->getReturnType()
		);
	}

}
