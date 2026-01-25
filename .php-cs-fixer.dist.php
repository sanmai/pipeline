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
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$header = <<<'EOF'
    Copyright 2017, 2018 Alexey Kopytko <alexey@kopytko.com>

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
    EOF;

$config = new Config();
$config
    ->setRiskyAllowed(true)
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        'header_comment' => ['comment_type' => 'PHPDoc', 'header' => $header, 'separate' => 'bottom', 'location' => 'after_open'],
        '@PER-CS' => true,
        '@PER-CS:risky' => true,

        '@PHPUnit100Migration:risky' => true,
        '@PHP82Migration' => true,

        'native_constant_invocation' => [
            'strict' => false,
            'scope' => 'namespaced',
        ],
        'native_function_invocation' => [
            'include' => ['@internal'],
            'scope' => 'namespaced',
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        'ordered_imports' => true,
        'no_unused_imports' => true,

        'strict_comparison' => true,
        'yoda_style' => true,
        'array_indentation' => true,
        'no_unused_imports' => true,
        'operator_linebreak' => ['only_booleans' => true],
        'no_empty_comment' => true,
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__)
            ->append([__FILE__])
    )
;

return $config;
