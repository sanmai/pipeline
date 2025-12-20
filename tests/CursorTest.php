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

use NoRewindIterator;
use PHPUnit\Framework\TestCase;

use function Pipeline\fromArray;

/**
 * @covers \Pipeline\Standard
 *
 * @internal
 */
final class CursorTest extends TestCase
{
    public function testIterateTwiceFails()
    {
        $pipeline = fromArray([1, 2, 3, 4, 5])->stream();

        foreach ($pipeline as $i) {
            if (2 === $i) {
                break;
            }
        }

        $this->expectExceptionMessage("Cannot rewind a generator");
        foreach ($pipeline as $i) {
            echo "$i\n";
        }

        $this->assertSame($i, 5);
    }

    public function testIterateTwiceSucceeds()
    {
        $pipeline = fromArray([1, 2, 3, 4, 5])->stream();

        $pipeline = new NoRewindIterator($pipeline->getIterator());

        foreach ($pipeline as $i) {
            echo "testIterateTwiceSucceeds-1: $i\n";

            if (2 === $i) {
                break;
            }
        }

        foreach ($pipeline as $i) {
            echo "testIterateTwiceSucceeds-2: $i\n";
        }

        $this->assertSame($i, 5);
    }

    public function testIterateTwiceSucceeds2()
    {
        $pipeline = fromArray([1, 2, 3, 4, 5])->stream();

        $pipeline->toList()

        $pipeline = new NoRewindIterator($pipeline->getIterator());

        foreach ($pipeline as $i) {
            echo "testIterateTwiceSucceeds-1: $i\n";

            if (2 === $i) {
                break;
            }
        }

        foreach ($pipeline as $i) {
            echo "testIterateTwiceSucceeds-2: $i\n";
        }



        $this->assertSame($i, 5);
    }

}
