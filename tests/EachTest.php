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

namespace Tests\Pipeline;

use LogicException;
use PHPUnit\Framework\TestCase;
use Pipeline\Standard;
use function Pipeline\map;
use function Pipeline\take;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class EachTest extends TestCase
{
    private array $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = [];
    }

    protected function spy($value): void
    {
        $this->output[] = $value;
    }

    public function testUninitialized(): void
    {
        $pipeline = new Standard();

        $pipeline->each(fn ($value) => $this->spy($value));

        $this->assertSame([], $this->output);
    }

    public function testEmpty(): void
    {
        $pipeline = take([]);

        $pipeline->each(fn ($value) => $this->spy($value));

        $this->assertSame([], $this->output);
    }

    public function testEmptyGenerator(): void
    {
        $pipeline = map(static fn () => yield from []);

        $pipeline->each(fn ($value) => $this->spy($value));

        $this->assertSame([], $this->output);
    }

    public function testNotEmpty(): void
    {
        $pipeline = take([1, 2, 3, 4]);

        $pipeline->each(fn (int $value) => $this->spy($value));

        $this->assertSame([1, 2, 3, 4], $this->output);
    }

    public function testInterrupt(): void
    {
        $pipeline = map(fn () => yield from [1, 2, 3, 4]);

        $pipeline->cast(function (int $value) {
            if (3 === $value) {
                throw new LogicException();
            }

            return $value;
        });

        try {
            $pipeline->each(function (int $value): void {
                $this->spy($value);
            });
        } catch (LogicException $_) {
            $this->assertSame([1, 2], $this->output);
        }

        $pipeline->each(function (int $value): void {
            $this->spy($value);
        });

        $this->assertSame([1, 2], $this->output);
        $this->assertSame([], $pipeline->toArray());
    }
}
