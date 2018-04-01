<?php

namespace Phpactor\Completion\Tests\Integration\Completor;

use PHPUnit\Framework\TestCase;
use Phpactor\WorseReflection\Core\Logger\ArrayLogger;
use Phpactor\WorseReflection\Core\SourceCodeLocator\StringSourceLocator;
use Phpactor\WorseReflection\Core\SourceCode;
use Phpactor\WorseReflection\ReflectorBuilder;
use Phpactor\Completion\Completor;
use Phpactor\Completion\Response;
use Phpactor\Completion\Completor\ClassMemberCompletor;
use Phpactor\Completion\Tests\Integration\CouldCompleteTestCase;
use Generator;
use Phpactor\Completion\CouldComplete;

class ClassMemberCompletorTest extends CouldCompleteTestCase
{
    protected function createCompletor(string $source): CouldComplete
    {
        $reflector = ReflectorBuilder::create()->addSource($source)->build();
        return new ClassMemberCompletor($reflector);
    }

    public function provideComplete(): Generator
    {
        yield 'Public property' => [
            <<<'EOT'
<?php

class Foobar
{
    public $foo;
}

$foobar = new Foobar();
$foobar-><>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'foo',
                'info' => 'pub $foo',
            ]
        ]
    ];

        yield 'Private property' => [
            <<<'EOT'
<?php

class Foobar
{
    private $foo;
}

$foobar = new Foobar();
$foobar-><>

EOT
        ,
            [ ]
        ];

        yield 'Public property access' => [
            <<<'EOT'
<?php

class Barar
{
    public $bar;
}

class Foobar
{
    /**
     * @var Barar
     */
    public $foo;
}

$foobar = new Foobar();
$foobar->foo-><>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'bar',
                'info' => 'pub $bar',
            ]
        ]
    ];

        yield 'Public method with parameters' => [
            <<<'EOT'
<?php

class Foobar
{
    public function foo(string $zzzbar = 'bar', $def): Barbar
    {
    }
}

$foobar = new Foobar();
$foobar-><>

EOT
        , [
            [
                'type' => 'f',
                'name' => 'foo',
                'info' => 'pub foo(string $zzzbar = \'bar\', $def): Barbar',
            ]
        ]
    ];

        yield 'Public method multiple return types' => [
            <<<'EOT'
<?php

class Foobar
{
    /**
     * @return Foobar|Barbar
     */
    public function foo()
    {
    }
}

$foobar = new Foobar();
$foobar-><>

EOT
        , [
            [
                'type' => 'f',
                'name' => 'foo',
                'info' => 'pub foo(): Foobar|Barbar',
            ]
        ]
    ];

        yield 'Private method' => [
            <<<'EOT'
<?php

class Foobar
{
    private function foo(): Barbar
    {
    }
}

$foobar = new Foobar();
$foobar-><>

EOT
        , [
        ]
    ];

        yield 'Static method' => [
            <<<'EOT'
<?php

class Foobar
{
    public static $foo;
}

$foobar = new Foobar();
$foobar::<>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'foo',
                'info' => 'pub static $foo',
            ]
        ]
    ];

        yield 'Static method with previous arrow accessor' => [
            <<<'EOT'
<?php

class Foobar
{
    public static $foo;

    /**
     * @var Foobar
     */
    public $me;
}

$foobar = new Foobar();
$foobar->me::<>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'foo',
                'info' => 'pub static $foo',
            ],
            [
                'type' => 'm',
                'name' => 'me',
                'info' => 'pub $me: Foobar',
            ]
        ]
    ];

        yield 'Partially completed' => [
            <<<'EOT'
<?php

class Foobar
{
    public static $foobar;
    public static $barfoo;
}

$foobar = new Foobar();
$foobar::f<>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'foobar',
                'info' => 'pub static $foobar',
            ]
        ]
    ];

        yield 'Partially completed' => [
            <<<'EOT'
<?php

class Foobar
{
    const FOOBAR = 'foobar';
    const BARFOO = 'barfoo';
}

$foobar = new Foobar();
$foobar::<>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'FOOBAR',
                'info' => 'const FOOBAR',
            ],
            [
                'type' => 'm',
                'name' => 'BARFOO',
                'info' => 'const BARFOO',
            ],
        ],
    ];

        yield 'Accessor on new line' => [
            <<<'EOT'
<?php

class Foobar
{
    public $foobar;
}

$foobar = new Foobar();
$foobar
    -><>

EOT
        , [
            [
                'type' => 'm',
                'name' => 'foobar',
                'info' => 'pub $foobar',
            ],
        ],
    ];
    }

    private function complete(string $source, $offset): Response
    {
        $reflector = ReflectorBuilder::create()->addSource($source)->build();
        $complete = new ClassMemberCompletor($reflector);

        $complete->couldComplete($source, $offset);
        $result = $complete->complete($source, $offset);

        return $result;
    }

    /**
     * @dataProvider provideErrors
     */
    public function testErrors(string $source, int $offset, array $expected)
    {
        $results = $this->complete($source, $offset);
        $this->assertEquals($expected, $results->issues()->toArray());
    }

    public function provideErrors()
    {
        return [
            [
                <<<'EOT'
<?php

$asd = 'asd';
$asd->
EOT
        ,27,
            [
                'Cannot complete members on scalar value (string)',
            ]
        ],
        [
            <<<'EOT'
<?php

$asd->
EOT
        ,13,
            [
                'Variable "asd" is undefined',
            ]
        ],
        [
            <<<'EOT'
<?php

$asd = new BooBar();
$asd->
EOT
        ,34,
            [
                'Could not find class "BooBar"',
            ]
        ],
        [
            <<<'EOT'
<?php

class Foobar
{
    public $foobar;
}

$foobar = new Foobar();
$foobar->barbar->;
EOT
        ,86,
            [
                'Class "Foobar" has no properties named "barbar"',
            ]
        ]
    ];
    }

    public function provideCouldComplete(): Generator
    {
        yield 'instance member' => [ '$hello-><>' ];
        yield 'static access' => [ 'Hello::<>' ];
    }

    public function provideCouldNotComplete(): Generator
    {
        yield 'non member access' => [ '$hello<>' ];
    }
}
