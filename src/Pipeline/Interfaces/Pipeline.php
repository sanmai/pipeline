<?php

namespace Pipeline\Interfaces;

interface Pipeline extends \IteratorAggregate
{
    /**
     * @param callable $func
     *
     * @return PipelineInterface
     */
    public function map(callable $func);

    /**
     * @param callable $func - must yield values (return a generator)
     *
     * @return PipelineInterface
     */
    public function filter(callable $func);

    /**
     * @param callable $func    (mixed $carry, mixed $item)
     * @param mixed    $initial
     *
     * @return mixed
     */
    public function reduce(callable $func, $initial);
}
