<?php

/**
 * Copyright 2017, 2018 Alexey Kopytko <alexey@kopytko.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Tests\Pipeline\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\StringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\FloatType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\MixedType;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\CallbackResolver;
use Pipeline\PHPStan\FilterTypeNarrowingHelper;

/**
 * @covers \Pipeline\PHPStan\CallbackResolver
 */
class CallbackResolverTest extends TestCase
{
    private CallbackResolver $resolver;
    /** @var FilterTypeNarrowingHelper&\PHPUnit\Framework\MockObject\MockObject */
    private FilterTypeNarrowingHelper $helper;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(FilterTypeNarrowingHelper::class);
        $this->resolver = new CallbackResolver($this->helper);
    }

    public function testResolveCallbackTypeReturnsNullWhenArgIsNull(): void
    {
        $result = $this->resolver->resolveCallbackType(null);

        $this->assertNull($result);
    }

    public function testResolveCallbackTypeHandlesStringCallback(): void
    {
        $arg = new Arg(new String_('is_string'));

        $this->helper->expects($this->once())
            ->method('extractFunctionNameFromStringCallback')
            ->with($arg->value)
            ->willReturn('is_string');

        $this->helper->expects($this->once())
            ->method('getTargetTypeForFunction')
            ->with('is_string')
            ->willReturn(new StringType());

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertInstanceOf(StringType::class, $result);
    }

    public function testResolveCallbackTypeReturnsNullWhenStringCallbackNotRecognized(): void
    {
        $arg = new Arg(new String_('unknown_function'));

        $this->helper->expects($this->once())
            ->method('extractFunctionNameFromStringCallback')
            ->with($arg->value)
            ->willReturn(null);

        $this->helper->expects($this->never())
            ->method('getTargetTypeForFunction');

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertNull($result);
    }

    public function xtestResolveCallbackTypeHandlesFirstClassCallable(): void
    {
        $funcCall = new FuncCall(new Name('is_int'));
        $arg = new Arg($funcCall);

        $this->helper->expects($this->once())
            ->method('extractFunctionNameFromFirstClassCallable')
            ->with($funcCall)
            ->willReturn('is_int');

        $this->helper->expects($this->once())
            ->method('getTargetTypeForFunction')
            ->with('is_int')
            ->willReturn(new IntegerType());

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertInstanceOf(IntegerType::class, $result);
    }

    public function testResolveCallbackTypeReturnsNullWhenFirstClassCallableNotRecognized(): void
    {
        $funcCall = new FuncCall(new Name('unknown_function'));
        $arg = new Arg($funcCall);

        $this->helper->expects($this->once())
            ->method('extractFunctionNameFromFirstClassCallable')
            ->with($funcCall)
            ->willReturn(null);

        $this->helper->expects($this->never())
            ->method('getTargetTypeForFunction');

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertNull($result);
    }

    public function xtestResolveCallbackTypeReturnsNullWhenTargetTypeNotFound(): void
    {
        $arg = new Arg(new String_('custom_function'));

        $this->helper->expects($this->once())
            ->method('extractFunctionNameFromStringCallback')
            ->with($arg->value)
            ->willReturn('custom_function');

        $this->helper->expects($this->once())
            ->method('getTargetTypeForFunction')
            ->with('custom_function')
            ->willReturn(null);

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertNull($result);
    }

    public function testResolveCallbackTypeIgnoresOtherExpressionTypes(): void
    {
        $arg = new Arg(new Variable('callback'));

        $this->helper->expects($this->never())
            ->method('extractFunctionNameFromStringCallback');

        $this->helper->expects($this->never())
            ->method('extractFunctionNameFromFirstClassCallable');

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertNull($result);
    }

    /**
     * @dataProvider provideKnownFunctions
     */
    public function xtestResolveCallbackTypeHandlesKnownFunctions(string $functionName, string $expectedTypeClass): void
    {
        $arg = new Arg(new String_($functionName));

        $expectedType = match ($expectedTypeClass) {
            ArrayType::class => new ArrayType(new MixedType(), new MixedType()),
            ObjectType::class => new ObjectType('object'),
            default => new $expectedTypeClass(),
        };

        $this->helper->expects($this->once())
            ->method('extractFunctionNameFromStringCallback')
            ->willReturn($functionName);

        $this->helper->expects($this->once())
            ->method('getTargetTypeForFunction')
            ->with($functionName)
            ->willReturn($expectedType);

        $result = $this->resolver->resolveCallbackType($arg);

        $this->assertInstanceOf($expectedTypeClass, $result);
    }

    public static function provideKnownFunctions(): iterable
    {
        yield 'is_string' => ['is_string', StringType::class];
        yield 'is_int' => ['is_int', IntegerType::class];
        yield 'is_float' => ['is_float', FloatType::class];
        yield 'is_bool' => ['is_bool', BooleanType::class];
        yield 'is_array' => ['is_array', ArrayType::class];
        yield 'is_object' => ['is_object', ObjectType::class];
    }
}
