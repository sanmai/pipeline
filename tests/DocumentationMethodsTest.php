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

use Pipeline\Standard;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

use function array_diff;
use function array_keys;
use function dirname;
use function file_get_contents;
use function implode;
use function in_array;
use function Pipeline\take;
use function preg_match_all;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;
use function substr_count;

use const PREG_OFFSET_CAPTURE;

/**
 * @coversNothing
 */
final class DocumentationMethodsTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $publicMethods;

    protected function setUp(): void
    {
        // Get all public methods from the Pipeline\Standard class
        $reflection = new ReflectionClass(Standard::class);
        $this->publicMethods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip magic methods and constructors
            if (!str_starts_with($method->getName(), '__')) {
                $this->publicMethods[] = $method->getName();
            }
        }
    }

    /**
     * Test that all methods mentioned in documentation actually exist in the code.
     */
    public function testDocumentationMethodsExist(): void
    {
        $docsPath = dirname(__DIR__) . '/docs';
        $this->assertDirectoryExists($docsPath, 'Documentation directory not found');

        $documentedMethods = $this->scanDocumentationForMethods($docsPath);
        $nonExistentMethods = [];

        foreach ($documentedMethods as $method => $locations) {
            if (!in_array($method, $this->publicMethods, true)) {
                $nonExistentMethods[$method] = $locations;
            }
        }

        if (!empty($nonExistentMethods)) {
            $message = "The following methods are documented but do not exist in Pipeline\\Standard:\n\n";
            foreach ($nonExistentMethods as $method => $locations) {
                $message .= "- {$method}() found in:\n";
                foreach ($locations as $location) {
                    $message .= "  * {$location}\n";
                }
                $message .= "\n";
            }
            $this->fail($message);
        }

        $this->assertTrue(true, 'All documented methods exist in the code');
    }

    /**
     * Scan documentation files for method references.
     *
     * @return array<string, array<string>>
     */
    private function scanDocumentationForMethods(string $directory): array
    {
        $skipMethods = [
            // PHP built-in functions
            'count', 'array_sum', 'array_filter', 'array_map', 'array_reduce', 'array_slice', 'array_chunk',
            'json_decode', 'explode', 'implode', 'trim', 'sort',
            'fn', 'function', 'use', 'new', 'echo', 'print',
            'isset', 'empty', 'is_array', 'is_numeric',
            'str_getcsv', 'str_contains', 'str_starts_with',
            'file_get_contents', 'file', 'range', 'sqrt',
            'round', 'take', 'map', 'zip', 'fromArray', 'fromValues',
            'iterator_to_array',

            // RunningVariance methods (different class)
            'getMean', 'getStandardDeviation', 'getCount', 'getVariance',
            'getMin', 'getMax', 'observe', 'merge',

            // PDO/Database methods
            'prepare', 'execute', 'fetch', 'closeCursor', 'beginTransaction',
            'commit', 'rollBack', 'query',

            // Iterator methods
            'rewind', 'valid', 'current', 'next',

            // Other example methods
            'isActive', 'getMessage', 'bulkInsert', 'measure', 'getReport',
            'withFiltering', 'withTransformation', 'withSorting', 'process',
            'safeTransform', 'getErrors', 'addExtractor', 'addTransformer',
            'addLoader', 'run', 'memoize', 'isValid', 'assertEquals',
            'buildFilter', 'buildTransformer',

            // Constructor
            '__construct',
        ];

        $patterns = [
            '/->(\w+)\s*\(/m',           // Method calls like ->method(
            '/`(\w+)\s*\(/m',            // Backtick method references
            '/###\s+`(\w+)\s*\(/m',      // Header method references
            '/\|\s*`(\w+)\([^)]*\)`/m', // Table method references
        ];

        return take(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)))
            ->filter(fn($file) => $file->isFile() && 'md' === $file->getExtension())
            ->filter(fn($file) => 'BUNDLED_DOCUMENTATION.md' !== $file->getBasename())
            ->map(function ($file) use ($directory, $patterns, $skipMethods) {
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace($directory . '/', '', $file->getPathname());

                return take($patterns)
                    ->map(function ($pattern) use ($content) {
                        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
                        return $matches[1] ?? [];
                    })
                    ->flatten()
                    ->filter(fn($match) => !in_array($match[0], $skipMethods, true))
                    ->map(function ($match) use ($content, $relativePath) {
                        $method = $match[0];
                        $offset = $match[1];
                        $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                        return ['method' => $method, 'location' => "{$relativePath}:{$lineNumber}"];
                    })
                    ->toList();
            })
            ->flatten()
            ->fold([], function ($methods, $item) {
                if (!isset($methods[$item['method']])) {
                    $methods[$item['method']] = [];
                }
                $methods[$item['method']][] = $item['location'];
                return $methods;
            });
    }

    /**
     * Test that pipe() method is not used in code examples.
     */
    public function testPipeMethodNotUsedInExamples(): void
    {
        $docsPath = dirname(__DIR__) . '/docs';
        $pipeUsages = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($docsPath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || 'md' !== $file->getExtension()) {
                continue;
            }

            // Skip the bundled documentation file
            if ('BUNDLED_DOCUMENTATION.md' === $file->getBasename()) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace($docsPath . '/', '', $file->getPathname());

            // Look for actual usage of ->pipe(
            if (preg_match_all('/->pipe\s*\(/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    $pipeUsages[] = "{$relativePath}:{$lineNumber}";
                }
            }
        }

        if (!empty($pipeUsages)) {
            $this->fail(
                "The non-existent pipe() method is being used in examples:\n" .
                implode("\n", $pipeUsages)
            );
        }

        $this->assertTrue(true, 'No usage of pipe() method found in documentation');
    }

    /**
     * Test that all public methods are documented somewhere.
     */
    public function testAllPublicMethodsDocumented(): void
    {
        $docsPath = dirname(__DIR__) . '/docs';
        $documentedMethods = array_keys($this->scanDocumentationForMethods($docsPath));

        $undocumentedMethods = [];
        foreach ($this->publicMethods as $method) {
            if (!in_array($method, $documentedMethods, true)) {
                $undocumentedMethods[] = $method;
            }
        }

        // Some methods might be intentionally undocumented (like internal ones)
        $allowedUndocumented = ['getIterator', '__construct'];
        $undocumentedMethods = array_diff($undocumentedMethods, $allowedUndocumented);

        if (!empty($undocumentedMethods)) {
            $this->markTestIncomplete(
                "The following public methods are not documented: " .
                implode(', ', $undocumentedMethods)
            );
        }

        $this->assertTrue(true, 'All public methods are documented');
    }
}
