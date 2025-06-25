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
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function count;

/**
 * @implements Rule<Node>
 */
class NoNestedLoopsRule implements Rule
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

        $nodeFinder = new NodeFinder();
        $nestedLoops = $nodeFinder->find($node, function (Node $innerNode) use ($node): bool {
            return $innerNode !== $node && $this->isLoopNode($innerNode);
        });

        if (count($nestedLoops) > 0) {
            return [
                RuleErrorBuilder::message(
                    'Nested loops are not allowed. Use functional approaches like map(), filter(), or extract to a separate method.'
                )->build(),
            ];
        }

        return [];
    }

    private function isLoopNode(Node $node): bool
    {
        return $node instanceof For_
            || $node instanceof Foreach_
            || $node instanceof While_
            || $node instanceof Do_;
    }
}
