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
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\While_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function count;

/**
 * @implements Rule<Node>
 */
class RequireGuardClausesInLoopsRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @param Node $node
     * @param Scope $scope
     * @return array<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isLoopNode($node)) {
            return [];
        }

        $statements = $this->getLoopStatements($node);
        if (null === $statements || [] === $statements) {
            return [];
        }

        $errors = [];

        foreach ($statements as $index => $statement) {
            // Skip if it's not an if statement
            if (!$statement instanceof If_) {
                continue;
            }

            // Check if this if statement has else/elseif branches
            if (null !== $statement->else || count($statement->elseifs) > 0) {
                continue;
            }

            // Check if the if statement body contains only early returns
            if ($this->containsOnlyEarlyReturns($statement->stmts)) {
                continue;
            }

            // Check if there are statements after this if in the loop
            $hasStatementsAfter = $index < count($statements) - 1;

            // If the if body doesn't contain early returns and there are statements after it,
            // this should be a guard clause
            if ($hasStatementsAfter || count($statement->stmts) > 1) {
                $errors[] = RuleErrorBuilder::message(
                    'Use guard clauses instead of wrapping code in if statements. Consider using: if (!condition) { continue; }'
                )->build();
            }
        }

        return $errors;
    }

    private function isLoopNode(Node $node): bool
    {
        return $node instanceof For_
            || $node instanceof Foreach_
            || $node instanceof While_
            || $node instanceof Do_;
    }

    /**
     * @param Node $node
     * @return array<Stmt>|null
     */
    private function getLoopStatements(Node $node): ?array
    {
        if ($node instanceof For_ || $node instanceof Foreach_ || $node instanceof While_) {
            return $node->stmts;
        }

        if ($node instanceof Do_) {
            return $node->stmts;
        }

        return null;
    }

    /**
     * @param array<Stmt> $statements
     * @return bool
     */
    private function containsOnlyEarlyReturns(array $statements): bool
    {
        if ([] === $statements) {
            return false;
        }

        foreach ($statements as $statement) {
            // Check for continue, return, break, throw
            if ($statement instanceof Continue_ || $statement instanceof Return_) {
                continue;
            }

            if ($statement instanceof Stmt\Break_ || $statement instanceof Stmt\Throw_) {
                continue;
            }

            // If it's an expression that might be a function call (like exit/die)
            if ($statement instanceof Expression) {
                continue;
            }

            // Any other statement means it's not just early returns
            return false;
        }

        return true;
    }
}
