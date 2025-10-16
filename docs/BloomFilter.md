# BloomFilter Helper

The `BloomFilter` helper provides probabilistic duplicate detection with configurable false positive rates.

## Key Characteristics

- **No false negatives**: If the filter says an item is new, it's definitely new
- **Possible false positives**: If the filter says an item is a duplicate, it *might* actually be new

**In practice:** When filtering duplicates, false positives mean some unique items get incorrectly filtered out, but you'll never let a duplicate through.
- **Fixed memory usage**: Memory size is determined upfront based on expected items and desired false positive rate
- **Cannot remove items**: Once added, items cannot be removed from the filter

## Constructor Parameters

### expectedItems (int)
The anticipated number of unique items you'll add to the filter. This helps calculate optimal filter size.
- Underestimating: Filter will work but false positive rate will be higher than desired
- Overestimating: Wastes memory but maintains desired false positive rate

### falsePositiveRate (float)
The acceptable probability (0.0 to 1.0) that the filter incorrectly reports a **new** item as "already seen".

**What this means:**
- With `falsePositiveRate=0.1`, about 10% of new items will be incorrectly flagged as duplicates
- Items that were actually added are ALWAYS correctly identified (0% false negatives)
- Lower rates = more memory but fewer false duplicates

**Common values:**
- 0.01 (1%) - Good default, 1 in 100 new items incorrectly flagged as duplicates
- 0.001 (0.1%) - When accuracy matters more
- 0.1 (10%) - When memory is very constrained

### keyFunc (callable, optional)
Function to convert values to hashable strings. Defaults handle common cases.

## Implementation Details

### Hash Functions
- Uses SHA-1 with different seeds for multiple hash functions
- Number of hash functions is calculated optimally based on filter size and expected items

### Key Function
- Default for objects: `spl_object_hash()` (identity-based)
- Default for non-objects: `hash('xxh64', serialize($value))` (value-based)
- Can be customized via constructor parameter

### No Artificial Bounds
The implementation calculates mathematically optimal parameters without imposing limits:
- If you request extreme parameters (e.g., 0.0000001% false positive rate), it will attempt to allocate the required memory
- PHP's memory limits provide natural constraints
- Performance degrades naturally with excessive hash function counts

### Example Parameter Calculations

**Typical usage:**
- `new BloomFilter(10000, 0.01)` → ~95,851 bits (12KB), 7 hash functions
- `new BloomFilter(1000000, 0.001)` → ~14.4M bits (1.8MB), 10 hash functions

**Extreme parameters (avoid unless necessary):**
- `new BloomFilter(1, 0.00001)` → ~166,000 bits (20KB), 115 hash functions
- `new BloomFilter(1000, 1e-50)` → ~166 billion bits (20GB), 34,000 hash functions

The filter size grows with the log of the false positive rate, so extremely low rates require exponentially more memory.

## Usage Considerations

- Best for high-volume streams where exact deduplication would be memory-prohibitive
- Consider the trade-off between memory usage and false positive rate
- Test with realistic data volumes before production use
- Monitor `currentFalsePositiveRate()` if tracking approximate item count

## When Not to Use

- When false positives are unacceptable (e.g., financial transactions)
- For small datasets where a regular Set would suffice
- When you need to remove items from the filter
- When you need exact duplicate counts