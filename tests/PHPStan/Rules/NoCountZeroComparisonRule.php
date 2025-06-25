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
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;
use function strtolower;

/**
 * @implements Rule<Node\Expr\BinaryOp>
 */
class NoCountZeroComparisonRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\BinaryOp::class;
    }

    /**
     * @param Node\Expr\BinaryOp $node
     * @param Scope $scope
     * @return array<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isEqualityComparison($node)) {
            return [];
        }

        $left = $node->left;
        $right = $node->right;

        // Check if we have count() on either side
        $countSide = null;
        $valueSide = null;

        if ($this->isCountCall($left) && $this->isZero($right)) {
            $countSide = $left;
            $valueSide = $right;
        } elseif ($this->isCountCall($right) && $this->isZero($left)) {
            $countSide = $right;
            $valueSide = $left;
        } else {
            return [];
        }

        $operator = $this->getOperatorSymbol($node);
        $suggestion = $this->getSuggestion($node);

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Use array comparison instead of count(): Replace "count($array) %s 0" with "$array %s []"',
                    $operator,
                    $suggestion
                )
            )->build(),
        ];
    }

    private function isEqualityComparison(Node\Expr\BinaryOp $node): bool
    {
        return $node instanceof Identical
            || $node instanceof Equal
            || $node instanceof NotIdentical
            || $node instanceof NotEqual;
    }

    private function isCountCall(Node $node): bool
    {
        if (!$node instanceof FuncCall) {
            return false;
        }

        if (!$node->name instanceof Name) {
            return false;
        }

        $functionName = $node->name->toString();
        return 'count' === strtolower($functionName);
    }

    private function isZero(Node $node): bool
    {
        return $node instanceof LNumber && 0 === $node->value;
    }

    private function getOperatorSymbol(Node\Expr\BinaryOp $node): string
    {
        if ($node instanceof Identical) {
            return '===';
        }
        if ($node instanceof Equal) {
            return '==';
        }
        if ($node instanceof NotIdentical) {
            return '!==';
        }
        if ($node instanceof NotEqual) {
            return '!=';
        }

        return '?';
    }

    private function getSuggestion(Node\Expr\BinaryOp $node): string
    {
        if ($node instanceof Identical || $node instanceof Equal) {
            return '===';
        }
        if ($node instanceof NotIdentical || $node instanceof NotEqual) {
            return '!==';
        }

        return '===';
    }
}
