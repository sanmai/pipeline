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
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\VariadicPlaceholder;
use PHPUnit\Framework\TestCase;
use Pipeline\PHPStan\ArgumentParser;

/**
 * @covers \Pipeline\PHPStan\ArgumentParser
 */
class ArgumentParserTest extends TestCase
{
    private ArgumentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ArgumentParser();
    }

    public function testExtractArgsFiltersOutVariadicPlaceholder(): void
    {
        $methodCall = new MethodCall(
            new Variable('pipeline'),
            new Identifier('filter'),
            [
                new Arg(new Variable('callback')),
                new VariadicPlaceholder(),
                new Arg(new Variable('strict')),
            ]
        );

        $args = $this->parser->extractArgs($methodCall);

        $this->assertCount(2, $args);
        $this->assertInstanceOf(Arg::class, $args[0]);
        $this->assertInstanceOf(Arg::class, $args[1]);
    }

    public function testExtractArgsReturnsEmptyArrayWhenNoArgs(): void
    {
        $methodCall = new MethodCall(
            new Variable('pipeline'),
            new Identifier('filter'),
            []
        );

        $args = $this->parser->extractArgs($methodCall);

        $this->assertSame([], $args);
    }

    public function testExtractArgsReindexesArray(): void
    {
        $methodCall = new MethodCall(
            new Variable('pipeline'),
            new Identifier('filter'),
            [
                1 => new Arg(new Variable('callback')),
                3 => new VariadicPlaceholder(),
                5 => new Arg(new Variable('strict')),
            ]
        );

        $args = $this->parser->extractArgs($methodCall);

        $this->assertCount(2, $args);
        $this->assertArrayHasKey(0, $args);
        $this->assertArrayHasKey(1, $args);
    }

    public function testGetCallbackArgReturnsFirstArg(): void
    {
        $arg1 = new Arg(new Variable('callback'));
        $arg2 = new Arg(new Variable('strict'));
        $args = [$arg1, $arg2];

        $callbackArg = $this->parser->getCallbackArg($args);

        $this->assertSame($arg1, $callbackArg);
    }

    public function testGetCallbackArgReturnsNullWhenNoArgs(): void
    {
        $callbackArg = $this->parser->getCallbackArg([]);

        $this->assertNull($callbackArg);
    }

    public function testGetStrictArgReturnsSecondArgWhenPositional(): void
    {
        $arg1 = new Arg(new Variable('callback'));
        $arg2 = new Arg(new Variable('strict'));
        $args = [$arg1, $arg2];

        $strictArg = $this->parser->getStrictArg($args);

        $this->assertSame($arg2, $strictArg);
    }

    public function testGetStrictArgReturnsNamedParameter(): void
    {
        $arg1 = new Arg(new Variable('callback'));
        $arg2 = new Arg(new Variable('value'), false, false, [], new Identifier('strict'));
        $args = [$arg1, $arg2];

        $strictArg = $this->parser->getStrictArg($args);

        $this->assertSame($arg2, $strictArg);
    }

    public function testGetStrictArgPrefersNamedOverPositional(): void
    {
        $arg1 = new Arg(new Variable('callback'));
        $arg2 = new Arg(new Variable('positional'));
        $arg3 = new Arg(new Variable('named'), false, false, [], new Identifier('strict'));
        $args = [$arg1, $arg2, $arg3];

        $strictArg = $this->parser->getStrictArg($args);

        $this->assertSame($arg3, $strictArg);
    }

    public function testGetStrictArgReturnsNullWhenOnlyOneArg(): void
    {
        $arg1 = new Arg(new Variable('callback'));
        $args = [$arg1];

        $strictArg = $this->parser->getStrictArg($args);

        $this->assertNull($strictArg);
    }

    public function testGetStrictArgReturnsNullWhenNoArgs(): void
    {
        $strictArg = $this->parser->getStrictArg([]);

        $this->assertNull($strictArg);
    }

    public function testGetStrictArgHandlesArgWithNullName(): void
    {
        $arg1 = new Arg(new Variable('callback'));
        $arg2 = new Arg(new Variable('value'));
        $arg2->name = null;
        $args = [$arg1, $arg2];

        $strictArg = $this->parser->getStrictArg($args);

        $this->assertSame($arg2, $strictArg);
    }
}

