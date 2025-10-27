# Caveats

- Since most callback are [lazily evaluated](https://en.wikipedia.org/wiki/Lazy_evaluation) as more data coming in and out, you must consume the results with a plain `foreach` or use a `reduce()` to make sure processing happens.

    ```php
    foreach ($pipeline as $result) {
        // Processing happens only if you consume the results.
        // Want to stop early after few results? Not a problem here!
    }
    ```

  Almost nothing will happen unless you use the results. That's the point of lazy evaluation after all!
  
- That said, if a non-generator used to seed the pipeline, it will be executed eagerly.

    ```php
    $pipeline = new \Pipeline\Standard();
    $pipeline->map(function () {
        // will be executed immediately on the spot, unless yield is used
        return $this->veryExpensiveMethod();
    })->filter();
    ```
  In the above case the pipeline will store an array internally, with which the pipeline will operate eagerly whenever possible. Ergo, *when in doubt, use a generator.*
  
    ```php
    $pipeline->map(function () {
        // will be executed only as needed, when needed
        yield $this->veryExpensiveMethod();
    })->filter();
    ```  

- Keys for yielded values are being kept as is on a best effort basis, so one must take care when using `iterator_to_array()` on a pipeline: values with duplicate keys will be discarded with only the last value for a given key being returned.
    
    ```php
	$pipeline = \Pipeline\map(function () {
	    yield 'foo' => 'bar';
	    yield 'foo' => 'baz';
	});
	
	var_dump(iterator_to_array($pipeline));
	/* ['foo' => 'baz'] */
    ```
  
  Safer would be to use provided `toList()` method. It will return all values regardless of keys used, making sure to discard all keys in the process.
  
    ```php
    var_dump($pipeline->toList());
    /* ['bar', 'baz'] */
    ```
  If necessary to preserve the keys, there's a sister method `toAssoc()`.

- The resulting pipeline is an iterator and should be assumed not rewindable, just like generators it uses.

	```php
	$pipeline = \Pipeline\map(function () {
	    yield 1;
	});
	
	$sum = $pipeline->reduce();
	
	// Won't work the second time though
	$pipeline->reduce();
	// Exception: Cannot traverse an already closed generator
	```
 
  Although there are some cases where a pipeline can be rewinded and reused just like a regular array, a user should make no assumptions about this behavior as it is not a part of the API compatibility guarantees.   
 
- Pipeline implements `IteratorAggregate` which is not the same as `Iterator`. Where the latter needed, the pipeline can be wrapped with an `IteratorIterator`:

    ```php
    $iterator = new \IteratorIterator($pipeline);
    /** @var $iterator \Iterator */
    ```

- Iterating over a pipeline all over again results in undefined behavior. Best to avoid doing this.
