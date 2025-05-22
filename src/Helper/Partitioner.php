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

namespace Pipeline\Helper;

use WeakReference;
use Generator;
use SplQueue;
use Closure;

final class Partitioner
{
    private Generator     $source;
    private SplQueue      $bufferTrue;
    private SplQueue      $bufferFalse;
    private bool          $done      = false;
    private Closure      $predicate;
    private ?WeakReference $weakTrue  = null;
    private ?WeakReference $weakFalse = null;

    private function __construct(Generator $source, Closure $predicate)
    {
        $this->source     = $source;
        $this->predicate  = $predicate;
        $this->bufferTrue  = new SplQueue();
        $this->bufferFalse = new SplQueue();
    }

    private function pullNext(): void
    {
        if (! $this->source->valid()) {
            $this->done = true;
            return;
        }

        $val = $this->source->current();
        $this->source->next();

        // enqueue only if that side still has a live iterator
        if (($this->predicate)($val)) {
            if (null !== $this->weakTrue->get()) {
                $this->bufferTrue->enqueue($val);
            }
        } else {
            if (null !== $this->weakFalse->get()) {
                $this->bufferFalse->enqueue($val);
            }
        }
    }

    private function sideGen(bool $wantTrue): Generator
    {
        while (true) {
            $buf = $wantTrue ? $this->bufferTrue : $this->bufferFalse;
            if (! $buf->isEmpty()) {
                yield $buf->dequeue();
                continue;
            }
            if ($this->done) {
                break;
            }
            $this->pullNext();
        }
    }

    /**
     * Partition a Generator into two lazy iterators [trueSide, falseSide].
     *
     * @param Generator         $source
     * @param callable(mixed):bool $predicate
     * @return Generator[]       [$trueGen, $falseGen]
     */
    public static function partition(Generator $source, callable $predicate): array
    {
        $p        = new self($source, Closure::fromCallable($predicate));
        // rewind once if you still want “always from start”
        // $source->rewind();

        $genTrue  = $p->sideGen(true);
        $genFalse = $p->sideGen(false);

        // now create weak refs *after* creating the generators,
        // so get() will go null as soon as they are dropped
        $p->weakTrue  = WeakReference::create($genTrue);
        $p->weakFalse = WeakReference::create($genFalse);

        return [ $genTrue, $genFalse ];
    }
}
