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
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\StrictModeDetector;

/**
 * @covers \Pipeline\PHPStan\StrictModeDetector
 */
class StrictModeDetectorTest extends TestCase
{
    private StrictModeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new StrictModeDetector();
    }

    public function testIsStrictModeReturnsFalseWhenArgIsNull(): void
    {
        $scope = $this->createMock(Scope::class);

        $result = $this->detector->isStrictMode(null, $scope);

        $this->assertFalse($result);
    }

    public function xtestIsStrictModeReturnsTrueWhenArgIsTrue(): void
    {
        $arg = new Arg(new ConstFetch(new Name('true')));
        $scope = $this->createMock(Scope::class);

        $scope->expects($this->once())
            ->method('getType')
            ->with($arg->value)
            ->willReturn(new ConstantBooleanType(true));

        $result = $this->detector->isStrictMode($arg, $scope);

        $this->assertTrue($result);
    }

    public function xtestIsStrictModeReturnsFalseWhenArgIsFalse(): void
    {
        $arg = new Arg(new ConstFetch(new Name('false')));
        $scope = $this->createMock(Scope::class);

        $scope->expects($this->once())
            ->method('getType')
            ->with($arg->value)
            ->willReturn(new ConstantBooleanType(false));

        $result = $this->detector->isStrictMode($arg, $scope);

        $this->assertFalse($result);
    }

    public function xtestIsStrictModeReturnsFalseWhenArgIsNotBoolean(): void
    {
        $arg = new Arg(new ConstFetch(new Name('someConstant')));
        $scope = $this->createMock(Scope::class);

        $scope->expects($this->once())
            ->method('getType')
            ->with($arg->value)
            ->willReturn(new ConstantStringType('not a boolean'));

        $result = $this->detector->isStrictMode($arg, $scope);

        $this->assertFalse($result);
    }

    public function xtestIsStrictModeReturnsFalseWhenArgIsMixedType(): void
    {
        $arg = new Arg(new ConstFetch(new Name('variable')));
        $scope = $this->createMock(Scope::class);

        $scope->expects($this->once())
            ->method('getType')
            ->with($arg->value)
            ->willReturn(new MixedType());

        $result = $this->detector->isStrictMode($arg, $scope);

        $this->assertFalse($result);
    }
}
