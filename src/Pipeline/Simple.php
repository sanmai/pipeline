<?php
namespace Pipeline;

/**
 * Concrete pipeline with sane default callbacks.
 */
class Simple extends Principal
{
    /**
     * With no callback drops all null and false values (not unlike array_filter defaults).
     */
    public function filter(callable $func = null)
    {
        if ($func) {
            return parent::filter($func);
        }

        return parent::filter(function ($value) {
        	return (bool) $value;
        });
    }

    /**
     * Defaults to summation.
     */
    public function reduce(callable $func = null, $initial = null)
    {
    	if ($func) {
    		return parent::reduce($func, $initial);
    	}

    	return parent::reduce(function ($a, $b) {
			return $a + $b;
    	}, 0);
    }
}