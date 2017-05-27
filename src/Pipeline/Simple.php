<?php
namespace Pipeline;

/**
 * Not your general purpose pipeline
 */
class Simple extends Conceptual
{
    /**
     * @param callable $func
     * @return Simple
     */
    public function filter(callable $func = null)
    {
        return parent::filter($func ? $func :  function ($value) {
        	// akin to default array_filter callback
        	return (bool) $value;
        });
    }

    /**
     * @param callable $func (mixed $carry, mixed $item)
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $func = null, $initial = null)
    {
    	if ($func) {
    		return parent::reduce($func, $initial);
    	}

    	// default to summation
    	return parent::reduce(function ($a, $b) {
			return $a + $b;
    	}, 0);
    }
}