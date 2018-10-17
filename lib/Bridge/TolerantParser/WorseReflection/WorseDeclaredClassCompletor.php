<?php

namespace Phpactor\Completion\Bridge\TolerantParser\WorseReflection;

use Generator;
use Microsoft\PhpParser\Node;
use Phpactor\Completion\Bridge\TolerantParser\Qualifier\ClassQualifier;
use Phpactor\Completion\Bridge\TolerantParser\TolerantCompletor;
use Phpactor\Completion\Bridge\TolerantParser\TolerantQualifiable;
use Phpactor\Completion\Bridge\TolerantParser\TolerantQualifier;
use Phpactor\Completion\Core\Completor;
use Phpactor\Completion\Core\Formatter\ObjectFormatter;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\WorseReflection\Core\Exception\NotFound;
use Phpactor\WorseReflection\Core\Reflector\ClassReflector;

class WorseDeclaredClassCompletor implements TolerantCompletor, TolerantQualifiable
{
    /**
     * @var ClassReflector
     */
    private $reflector;

    /**
     * @var ObjectFormatter
     */
    private $formatter;

    public function __construct(ClassReflector $reflector, ObjectFormatter $formatter)
    {
        $this->reflector = $reflector;
        $this->formatter = $formatter;
    }

    /**
     * {@inheritDoc}
     */
    public function complete(Node $node, string $source, int $offset): Generator
    {
        $classes = get_declared_classes();
        $classes = array_filter($classes, function ($class) use ($node) {
            return 0 === strpos($class, $node->getText());
        });

        foreach ($classes as $class) {
            try {
                $reflectionClass = $this->reflector->reflectClass($class);
            } catch (NotFound $e) {
                continue;
            }

            yield Suggestion::createWithOptions(
                $class,
                [
                    'type' => Suggestion::TYPE_CLASS,
                    'short_description' => $this->formatter->format($reflectionClass),
                ]
            );
        }
    }

    public function qualifier(): TolerantQualifier
    {
        return new ClassQualifier();
    }
}
