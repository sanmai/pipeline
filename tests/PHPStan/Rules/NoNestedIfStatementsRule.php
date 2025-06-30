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

namespace Tests\Pipeline\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\If_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function count;

/**
 * @implements Rule<If_>
 */
class NoNestedIfStatementsRule implements Rule
{
    public function getNodeType(): string
    {
        return If_::class;
    }

    /**
     * @param If_ $node
     * @param Scope $scope
     * @return array<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Skip if this if has else or elseif branches (more complex control flow)
        if (null !== $node->else || count($node->elseifs) > 0) {
            return [];
        }

        $statements = $node->stmts;
        if (1 !== count($statements)) {
            return [];
        }

        $onlyStatement = $statements[0];

        // Check if the only statement is another if
        if (!$onlyStatement instanceof If_) {
            return [];
        }

        // Check if the nested if also has no else/elseif
        if (null !== $onlyStatement->else || count($onlyStatement->elseifs) > 0) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Nested if statements should be avoided. Consider using guard clauses, combining conditions with &&, or extracting to a method.'
            )->build(),
        ];
    }
}
