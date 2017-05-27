<?php
namespace Pipeline;

/**
 * Principal abstract pipeline with no defaults.
 *
 * You're not expected to use this class directly, but you may subclass it as you see fit.
 */
abstract class Principal implements PipelineInterface
{
	/**
	 * Pre-primed pipeline
	 * @var \Generator
	 */
	private $pipeline;

	public function map(callable $func)
	{
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
		$this->map(function ($value) use ($func) {
			if ($func($value)) {
				yield $value;
			}
		});

		return $this;
	}

	/**
	 * @return \ArrayIterator|Generator
	 */
	public function getIterator()
	{
		// with non-primed pipeline just return empty iterator
		if (!$this->pipeline) {
			return new \ArrayIterator([]);
		}

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