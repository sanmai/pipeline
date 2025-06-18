# Complex Pipeline Patterns

Advanced patterns and techniques for building sophisticated data processing pipelines.

## Pipeline Composition

### Building Reusable Pipeline Components

```php
use function Pipeline\take;
use function Pipeline\map;

// Create reusable pipeline transformers
class PipelineBuilders {
    public static function normalizeText(): callable {
        return fn($pipeline) => $pipeline
            ->map('trim')
            ->filter()  // Remove empty strings
            ->map('strtolower')
            ->map(fn($s) => preg_replace('/\s+/', ' ', $s));
    }
    
    public static function extractNumbers(): callable {
        return fn($pipeline) => $pipeline
            ->map(fn($text) => preg_match_all('/\d+\.?\d*/', $text, $matches) ? $matches[0] : [])
            ->flatten()
            ->map('floatval');
    }
    
    public static function validateRange($min, $max): callable {
        return fn($pipeline) => $pipeline
            ->filter(fn($x) => $x >= $min && $x <= $max);
    }
}

// Use composed pipelines
$result = take($rawData)
    ->pipe(PipelineBuilders::normalizeText())
    ->pipe(PipelineBuilders::extractNumbers())
    ->pipe(PipelineBuilders::validateRange(0, 100))
    ->toList();
```

### Conditional Pipeline Building

```php
// Dynamic pipeline construction
class DataProcessor {
    private $pipeline;
    
    public function __construct($data) {
        $this->pipeline = take($data);
    }
    
    public function withFiltering(array $criteria): self {
        foreach ($criteria as $field => $value) {
            $this->pipeline = $this->pipeline
                ->filter(fn($item) => ($item[$field] ?? null) === $value);
        }
        return $this;
    }
    
    public function withTransformation(?callable $transformer): self {
        if ($transformer) {
            $this->pipeline = $this->pipeline->map($transformer);
        }
        return $this;
    }
    
    public function withSorting(?string $field, bool $desc = false): self {
        if ($field) {
            $data = $this->pipeline->toList();
            usort($data, fn($a, $b) => $desc 
                ? $b[$field] <=> $a[$field] 
                : $a[$field] <=> $b[$field]
            );
            $this->pipeline = take($data);
        }
        return $this;
    }
    
    public function process(): array {
        return $this->pipeline->toList();
    }
}

// Usage
$processor = new DataProcessor($users);
$results = $processor
    ->withFiltering(['status' => 'active', 'role' => 'admin'])
    ->withTransformation(fn($user) => [
        ...$user,
        'display_name' => strtoupper($user['name'])
    ])
    ->withSorting('created_at', desc: true)
    ->process();
```

## State Management

### Stateful Transformations

```php
// Running calculations with state
class RunningAverage {
    private float $sum = 0;
    private int $count = 0;
    
    public function observe($value): array {
        $this->sum += $value;
        $this->count++;
        
        return [
            'value' => $value,
            'running_avg' => $this->sum / $this->count,
            'count' => $this->count
        ];
    }
}

$avg = new RunningAverage();
$results = take($measurements)
    ->map([$avg, 'observe'])
    ->toList();

// Detecting changes
class ChangeDetector {
    private $previous = null;
    
    public function detect($value): ?array {
        if ($this->previous === null) {
            $this->previous = $value;
            return null;
        }
        
        $change = $value - $this->previous;
        $percentChange = ($change / $this->previous) * 100;
        $this->previous = $value;
        
        return [
            'value' => $value,
            'change' => $change,
            'percent' => $percentChange
        ];
    }
}

$detector = new ChangeDetector();
$changes = take($prices)
    ->map([$detector, 'detect'])
    ->filter()  // Remove first null
    ->toList();
```

### Context-Aware Processing

```php
// Processing with context
class ContextualProcessor {
    public static function processWithContext($items, $contextBuilder) {
        $context = [];
        
        return take($items)
            ->map(function($item) use (&$context, $contextBuilder) {
                $context = $contextBuilder($item, $context);
                return [
                    'item' => $item,
                    'context' => $context
                ];
            })
            ->toList();
    }
}

// Track running statistics
$results = ContextualProcessor::processWithContext(
    $transactions,
    function($transaction, $context) {
        $context['total'] = ($context['total'] ?? 0) + $transaction['amount'];
        $context['count'] = ($context['count'] ?? 0) + 1;
        $context['average'] = $context['total'] / $context['count'];
        
        if (!isset($context['by_type'])) {
            $context['by_type'] = [];
        }
        $type = $transaction['type'];
        $context['by_type'][$type] = ($context['by_type'][$type] ?? 0) + 1;
        
        return $context;
    }
);
```

## Error Handling and Recovery

### Resilient Pipeline Processing

```php
// Safe transformation with error collection
class SafeProcessor {
    private array $errors = [];
    
    public function safeTransform($pipeline, callable $transformer) {
        return $pipeline->map(function($item) use ($transformer) {
            try {
                return [
                    'success' => true,
                    'data' => $transformer($item),
                    'original' => $item
                ];
            } catch (\Exception $e) {
                $this->errors[] = [
                    'item' => $item,
                    'error' => $e->getMessage()
                ];
                return [
                    'success' => false,
                    'data' => null,
                    'original' => $item,
                    'error' => $e->getMessage()
                ];
            }
        });
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
}

$processor = new SafeProcessor();
$results = take($inputs)
    ->pipe(fn($p) => $processor->safeTransform($p, function($item) {
        // Potentially failing transformation
        if (!isset($item['required_field'])) {
            throw new \Exception('Missing required field');
        }
        return processItem($item);
    }))
    ->filter(fn($result) => $result['success'])
    ->map(fn($result) => $result['data'])
    ->toList();

// Check errors
if ($errors = $processor->getErrors()) {
    foreach ($errors as $error) {
        logError($error);
    }
}
```

### Retry Logic

```php
// Pipeline with retry mechanism
function withRetry(callable $operation, int $maxAttempts = 3) {
    return function($item) use ($operation, $maxAttempts) {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $operation($item);
            } catch (\Exception $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    usleep(100000 * $attempt); // Exponential backoff
                }
            }
        }
        
        throw $lastException;
    };
}

$results = take($urls)
    ->map(withRetry(fn($url) => fetchData($url), 3))
    ->filter()  // Remove failed fetches
    ->toList();
```

## Parallel-Like Processing

### Batch Processing with Concurrency Simulation

```php
// Simulate concurrent processing using batches
class BatchProcessor {
    public static function processConcurrently(
        $items, 
        callable $processor, 
        int $batchSize = 10
    ) {
        return take($items)
            ->chunk($batchSize)
            ->map(function($batch) use ($processor) {
                // Simulate parallel processing of batch
                $results = [];
                $startTime = microtime(true);
                
                foreach ($batch as $key => $item) {
                    $results[$key] = $processor($item);
                }
                
                $duration = microtime(true) - $startTime;
                return [
                    'results' => $results,
                    'batch_size' => count($batch),
                    'duration' => $duration,
                    'rate' => count($batch) / $duration
                ];
            })
            ->map(fn($batch) => $batch['results'])
            ->flatten()
            ->toList();
    }
}

// Process API calls in batches
$responses = BatchProcessor::processConcurrently(
    $apiRequests,
    fn($request) => makeApiCall($request),
    20  // Process 20 at a time
);
```

## Complex Data Transformations

### Hierarchical Data Processing

```php
// Process nested structures
class TreeProcessor {
    public static function traverseTree($node, callable $processor) {
        $result = $processor($node);
        
        if (isset($node['children']) && is_array($node['children'])) {
            $result['children'] = take($node['children'])
                ->map(fn($child) => self::traverseTree($child, $processor))
                ->toList();
        }
        
        return $result;
    }
    
    public static function flattenTree($node, $level = 0) {
        yield [...$node, 'level' => $level];
        
        if (isset($node['children'])) {
            foreach ($node['children'] as $child) {
                yield from self::flattenTree($child, $level + 1);
            }
        }
    }
}

// Process hierarchical data
$tree = [
    'id' => 1,
    'name' => 'Root',
    'children' => [
        ['id' => 2, 'name' => 'Child 1'],
        ['id' => 3, 'name' => 'Child 2', 'children' => [
            ['id' => 4, 'name' => 'Grandchild']
        ]]
    ]
];

// Transform tree
$transformed = TreeProcessor::traverseTree($tree, fn($node) => [
    ...$node,
    'processed' => true,
    'path' => strtolower(str_replace(' ', '-', $node['name']))
]);

// Flatten to list
$flat = take([TreeProcessor::flattenTree($tree)])
    ->flatten()
    ->toList();
```

### Multi-Stage Data Pipeline

```php
// Complex ETL pipeline
class ETLPipeline {
    private $extractors = [];
    private $transformers = [];
    private $loaders = [];
    
    public function addExtractor(callable $extractor): self {
        $this->extractors[] = $extractor;
        return $this;
    }
    
    public function addTransformer(callable $transformer): self {
        $this->transformers[] = $transformer;
        return $this;
    }
    
    public function addLoader(callable $loader): self {
        $this->loaders[] = $loader;
        return $this;
    }
    
    public function run($sources) {
        // Extract
        $extracted = take($sources)
            ->map(function($source) {
                $data = [];
                foreach ($this->extractors as $extractor) {
                    $data = array_merge($data, $extractor($source));
                }
                return $data;
            })
            ->flatten();
        
        // Transform
        $transformed = array_reduce(
            $this->transformers,
            fn($pipeline, $transformer) => $pipeline->map($transformer),
            $extracted
        );
        
        // Load
        $transformed->each(function($item) {
            foreach ($this->loaders as $loader) {
                $loader($item);
            }
        });
    }
}

// Configure and run ETL
$etl = new ETLPipeline();
$etl->addExtractor(fn($file) => parseCsv($file))
    ->addExtractor(fn($file) => parseJson($file))
    ->addTransformer(fn($row) => normalizeData($row))
    ->addTransformer(fn($row) => validateData($row))
    ->addTransformer(fn($row) => enrichData($row))
    ->addLoader(fn($row) => saveToDatabase($row))
    ->addLoader(fn($row) => indexInElasticsearch($row))
    ->run($dataFiles);
```

## Performance Optimization Patterns

### Lazy Evaluation with Caching

```php
// Memoized pipeline operations
class MemoizedPipeline {
    private array $cache = [];
    
    public function memoize(callable $expensive): callable {
        return function($input) use ($expensive) {
            $key = serialize($input);
            if (!isset($this->cache[$key])) {
                $this->cache[$key] = $expensive($input);
            }
            return $this->cache[$key];
        };
    }
    
    public function process($data, $expensiveOperation) {
        return take($data)
            ->map($this->memoize($expensiveOperation))
            ->toList();
    }
}

// Short-circuit evaluation
function earlyTermination($items, $predicate, $limit = null) {
    $found = 0;
    $results = [];
    
    take($items)
        ->filter($predicate)
        ->each(function($item) use (&$found, &$results, $limit) {
            $results[] = $item;
            $found++;
            
            if ($limit !== null && $found >= $limit) {
                throw new \Exception('Limit reached'); // Hack to break
            }
        });
    
    return $results;
}
```

## Next Steps

- [Performance Tips](performance.md) - Optimization strategies
- [Best Practices](best-practices.md) - Guidelines and recommendations