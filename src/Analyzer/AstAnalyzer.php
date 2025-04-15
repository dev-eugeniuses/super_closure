<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer;

use PhpParser\Error as ParserError;
use PhpParser\Node\Expr\Variable as VariableNode;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser as CodeParser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as NodePrinter;
use ReflectionFunction;
use RuntimeException;
use SuperClosure\Analyzer\Visitor\ClosureLocatorVisitor;
use SuperClosure\Analyzer\Visitor\MagicConstantVisitor;
use SuperClosure\Analyzer\Visitor\ThisDetectorVisitor;
use SuperClosure\Exception\ClosureAnalysisException;

/**
 * AST-based analyzer.
 *
 * Uses reflection and the nikic/php-parser library to analyze a closure and
 * determine its code and context. Although more powerful than a token analyzer,
 * it is slower.
 */
class AstAnalyzer extends ClosureAnalyzer
{
    /**
     * Determines the closure's code by locating it within the AST,
     * performing a second pass to resolve magic constants, and then
     * pretty printing the resulting AST back to PHP code.
     *
     * @param array<string, mixed> $data
     */
    protected function determineCode(array &$data): void
    {
        // Locate the closure node in the AST.
        $this->locateClosure($data);

        // Traverse the closure's AST to resolve magic constants.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new MagicConstantVisitor($data['location']));
        $thisDetector = new ThisDetectorVisitor();
        $traverser->addVisitor($thisDetector);
        $traversed = $traverser->traverse([$data['ast']]);
        $data['ast'] = $traversed[0] ?? null;
        $data['hasThis'] = $thisDetector->detected;

        // Convert the updated AST back to code.
        $printer = new NodePrinter();
        $data['code'] = $printer->prettyPrint([$data['ast']]);
    }

    /**
     * Parses the closure's file and locates the closure node within its AST.
     *
     * @param array<string, mixed> $data
     *
     * @throws ClosureAnalysisException if the closure cannot be found
     */
    private function locateClosure(array &$data): void
    {
        try {
            $locator = new ClosureLocatorVisitor($data['reflection']);
            $fileAst = $this->getFileAst($data['reflection']);

            $fileTraverser = new NodeTraverser();
            $fileTraverser->addVisitor(new NameResolver());
            $fileTraverser->addVisitor($locator);
            $fileTraverser->traverse($fileAst);
        } catch (ParserError $e) {
            // @codeCoverageIgnoreStart
            throw new ClosureAnalysisException(
                'There was an error analyzing the closure code.',
                0,
                $e
            );
            // @codeCoverageIgnoreEnd
        }

        $data['ast'] = $locator->closureNode;
        if (!$data['ast']) {
            // @codeCoverageIgnoreStart
            throw new ClosureAnalysisException(
                'The closure was not found within the abstract syntax tree.'
            );
            // @codeCoverageIgnoreEnd
        }
        $data['location'] = $locator->location;
    }

    /**
     * Retrieves the AST for the file containing the closure.
     *
     * @param ReflectionFunction $reflection
     *
     * @return Stmt[]
     * @throws ClosureAnalysisException if the file does not exist.
     */
    private function getFileAst(ReflectionFunction $reflection): array
    {
        $fileName = $reflection->getFileName();
        if (!file_exists($fileName)) {
            throw new ClosureAnalysisException(
                "The file containing the closure, \"{$fileName}\", did not exist."
            );
        }
        $code = file_get_contents($fileName);

        return $this->getParser()->parse($code);
    }

    /**
     * Returns a PHP-Parser instance.
     *
     * @return CodeParser
     */
    private function getParser(): CodeParser
    {
        if (class_exists(ParserFactory::class)) {
            return new ParserFactory()->createForHostVersion();
        }
        throw new RuntimeException("No parser available. ParserFactory not found.");
    }

    /**
     * Determines the closure context (the variables used in its "use" clause).
     *
     * @param array<string, mixed> $data
     */
    protected function determineContext(array &$data): void
    {
        // Extract variable names from the closure's "use" clause.
        $refs = 0;
        $vars = array_map(static function ($node) use (&$refs): string {
            if ($node->byRef) {
                $refs++;
            }

            return $node->var instanceof VariableNode ? (string)$node->var->name : (string)$node->var;
        }, $data['ast']->uses);
        $data['hasRefs'] = ($refs > 0);

        // Obtain the variables from the closure's static context.
        $values = $data['reflection']->getStaticVariables();

        // Build the context: for each variable in the "use" clause, if a value exists, add it.
        foreach ($vars as $name) {
            if (isset($values[$name])) {
                $data['context'][$name] = $values[$name];
            }
        }
    }
}
