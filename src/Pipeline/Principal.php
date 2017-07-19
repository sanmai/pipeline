<?php

namespace Pipeline;

/**
 * Principal abstract pipeline with no defaults.
 *
 * You're not expected to use this class directly, but you may subclass it as you see fit.
 */
abstract class Principal implements Interfaces\Pipeline
{
    /**
     * Pre-primed pipeline.
     *
     * @var \Traversable
     */
    private $pipeline;

    /**
     * Optional source of data
     *
     * @param \Traversable $input
     */
    public function __construct(\Traversable $input = null)
    {
        $this->pipeline = $input;
    }

    public function map(callable $func)
    {
        assert((new \ReflectionFunction($func))->isGenerator(), "Callback must be a generator");
        assert($this->pipeline || (new \ReflectionFunction($func))->getNumberOfRequiredParameters() == 0, "Initial generator must not require parameters");

        if (!$this->pipeline) {
            $this->pipeline = call_user_func($func);

            return $this;
        }

        $previous = $this->pipeline;
        $this->pipeline = call_user_func(function () use ($previous, $func) {
            foreach ($previous as $value) {
                // `yield from` does not reset the keys
                // iterator_to_array() goes nuts
                // http://php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
                foreach ($func($value) as $value) {
                    yield $value;
                }
            }
        });

        return $this;
    }

    public function filter(callable $func)
    {
        $this->pipeline = new \CallbackFilterIterator($this->pipeline, $func);

        return $this;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        return $this->pipeline;
    }

    public function reduce(callable $func, $initial = null)
    {
        foreach ($this as $value) {
            $initial = $func($initial, $value);
        }

        return $initial;
    }
}
