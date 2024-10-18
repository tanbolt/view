<?php

use Tanbolt\View\Tag;
use Tanbolt\View\Engine;
use Tanbolt\View\Compiler;
use PHPUnit\Framework\TestCase;

class EngineTest extends TestCase
{
    public function testSetHomedir()
    {
        $engine = new Engine();
        static::assertSame($engine, $engine->setHomedir(__DIR__));
        static::assertEquals(__DIR__, $engine->getHomedir());
        $engine->setHomedir(null);
        static::assertNull($engine->getHomedir());
        try {
            $engine->setHomedir(__DIR__.'/none');
            static::fail('It should throw exception when homedir not exist');
        } catch (Exception $e) {
            static::assertTrue(true);
        }
    }

    public function testDataProvider()
    {
        $engine = new Engine();
        static::assertNull($engine->getDataProvider());
        $engine->setDataProvider('foo');
        static::assertEquals('foo', $engine->getDataProvider());

        $engine = new Engine('bar');
        static::assertEquals('bar', $engine->getDataProvider());
        $engine->setDataProvider('foo');
        static::assertEquals('foo', $engine->getDataProvider());
    }

    public function testCompileTag()
    {
        $engine = new Engine();
        $compiler = new class extends Compiler {
            protected $tags = ['phpunit', 'phpunit-alias'];

            public function compile(Tag $tag, Engine $engine)
            {
                // nothing
            }
        };
        static::assertSame($engine, $engine->addTagCompiler($compiler));
        static::assertSame($compiler, $engine->getTagCompiler('phpunit'));
        static::assertSame($compiler, $engine->getTagCompiler('phpunit-alias'));
        static::assertEquals($compiler->getTags(), $engine->allCompilerTags());
    }

    protected function getCompiled(Engine $engine, $source)
    {
        list($header, $compilation) = explode("\n", $engine->compileCode($source), 2);
        return $compilation;
    }

    /**
     * @dataProvider getVariable
     * @param $source
     * @param $compiled
     */
    public function testVariableParser($source, $compiled)
    {
        $engine = new Engine();
        $line = $this->getCompiled($engine, $source);
        if ($compiled) {
            static::assertEquals($compiled, $line);
        } else {
            static::assertEquals($source, $line);
        }
    }

    public function getVariable()
    {
        return [
            ['{__FILE__}', '<?php echo __FILE__;?>'],
            ['{{__FILE__}}', '{__FILE__}'],

            ['{$var}', '<?php echo $var;?>'],
            ['{{$var}}', '{$var}'],

            ['{$arr[key]}', '<?php echo $arr[\'key\'];?>'],
            ['{$arr[\'key\']}', '<?php echo $arr[\'key\'];?>'],
            ['{$arr.key}', '<?php echo $arr[\'key\'];?>'],
            ['{$arr[4]}', '<?php echo $arr[4];?>'],
            ['{$arr.4}', '<?php echo $arr[4];?>'],

            ['{$obj->key}', '<?php echo $obj->key;?>'],
            ['{$obj:key}', '<?php echo $obj->key;?>'],

            ['{$obj->method()}', '<?php echo $obj->method();?>'],
            ['{$obj:method()}', '<?php echo $obj->method();?>'],


            ['{$arr.key[4]}', '<?php echo $arr[\'key\'][4];?>'],
            ['{$arr.key[4]:var}', '<?php echo $arr[\'key\'][4]->var;?>'],
            ['{$arr:var.key}', '<?php echo $arr->var[\'key\'];?>'],


            ['{$var--}', '<?php echo $var--;?>'],
            ['{$var++}', '<?php echo $var++;?>'],
            ['{$var+1}', '<?php echo $var+1;?>'],
            ['{$var + 1}', '<?php echo $var + 1;?>'],
            ['{$var - 1}', '<?php echo $var - 1;?>'],
            ['{$var* 1}', '<?php echo $var* 1;?>'],
            ['{$var/ 3}', '<?php echo $var/ 3;?>'],
            ['{$var % 3}', '<?php echo $var % 3;?>'],
            ['{$var ** 3}', '<?php echo $var ** 3;?>'],
            ['{$var.key * $var2.4}', '<?php echo $var[\'key\'] * $var2[4];?>'],
            ['{($var + 1) * 3}', '<?php echo ($var + 1) * 3;?>'],

            ['{function($var)}', '<?php echo function($var);?>'],
            ['{function(sub($var), "str", 5)}', '<?php echo function(sub($var), "str", 5);?>'],
            ['{function(sub(add($var+2)), "str", 5)}', '<?php echo function(sub(add($var+2)), "str", 5);?>'],

            ['{$var /*输出变量*/}', '<?php echo $var;?>'],
            ['{$var or default}', '<?php echo isset($var) ? $var : \'default\';?>'],
            ['{$var or $default}', '<?php echo isset($var) ? $var : $default;?>'],
            ['{$var.key or $default + 2}', '<?php echo isset($var[\'key\']) ? $var[\'key\'] : $default + 2;?>'],

            ['{$}', null],
            ['{-$var}', null],
            ['{ $var }', null],
            ['{method(}', null],
        ];
    }

    public function testClearPHP()
    {
        $engine = new Engine();
        $source = 'a_<?php echo $foo;?>_b';
        static::assertEquals(
            'a__b',
            $this->getCompiled($engine, $source)
        );

        $source = 'a_<?php echo $foo;?>_b<??>';
        static::assertEquals(
            'a__b<??>',
            $this->getCompiled($engine, $source)
        );
    }

    public function testLiteral()
    {
        $engine = new Engine();
        $source = '{literal}_{$var}_{/literal}';
        static::assertEquals(
            '_{$var}_',
            $this->getCompiled($engine, $source)
        );
    }

    public function testPphCode()
    {
        $engine = new Engine();
        $source = '{php $foo="foo"; /}';
        static::assertEquals(
            '<?php'."\n" .
            '$foo="foo";' .
            "\n" .'?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{php}' .
            ' $foo = "foo"; '. "\n" .
            ' $bar = "bar"; '.
            '{/php}';
        static::assertEquals(
            '<?php'."\n" .
            ' $foo = "foo"; '. "\n" .
            ' $bar = "bar"; '.
            "\n" .'?>',
            $this->getCompiled($engine, $source)
        );
    }

    public function testConditional()
    {
        $engine = new Engine();
        $source = '{if true}{/if}';
        static::assertEquals(
            '<?php if (true) {  }?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{if true}{else}{/if}';
        static::assertEquals(
            '<?php if (true) {  } else {  }?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{if true}{elseif false}{/if}';
        static::assertEquals(
            '<?php if (true) {  } elseif (false) {  }?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{if true}{elseif false}{else}{/if}';
        static::assertEquals(
            '<?php if (true) {  } elseif (false) {  } else {  }?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{if $foo > 0}_{$foo-1}_{elseif $foo < 0}_{$foo+1}_{else}_{$foo}_{/if}';
        static::assertEquals(
            '<?php if ($foo > 0) { ?>'.
            '_<?php echo $foo-1;?>_'.
            '<?php } elseif ($foo < 0) { ?>'.
            '_<?php echo $foo+1;?>_'.
            '<?php } else { ?>'.
            '_<?php echo $foo;?>_'.
            '<?php }?>',
            $this->getCompiled($engine, $source)
        );
    }

    public function testLoopTag()
    {
        $engine = new Engine();
        $source = '{loop start=1}{/loop}';
        static::assertEquals(
            '<?php for ($i = 1; $i > 0; $i -= 1) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop end=1}{/loop}';
        static::assertEquals(
            '<?php for ($i = 0; $i < 1; $i += 1) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop start=1 end=10}{/loop}';
        static::assertEquals(
            '<?php for ($i = 1; $i < 10; $i += 1) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop start=10 end=1}{/loop}';
        static::assertEquals(
            '<?php for ($i = 10; $i > 1; $i -= 1) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop start=10 end=1 key=k}{/loop}';
        static::assertEquals(
            '<?php for ($k = 10; $k > 1; $k -= 1) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop start=10 end=1 key=k step=2}{/loop}';
        static::assertEquals(
            '<?php for ($k = 10; $k > 1; $k -= 2) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop start=10 end=1 key=k step=2}_{$k}_{/loop}';
        static::assertEquals(
            '<?php for ($k = 10; $k > 1; $k -= 2) { ?>_<?php echo $k;?>_<?php } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop start=10 end=1 key=k step=2}_{$k}_{loop start=1}_{$i}_{/loop}{/loop}';
        static::assertEquals(
            '<?php for ($k = 10; $k > 1; $k -= 2) { ?>'.
            '_<?php echo $k;?>_'.
            '<?php for ($i = 1; $i > 0; $i -= 1) { ?>'.
            '_<?php echo $i;?>_'.
            '<?php }  } ?>',
            $this->getCompiled($engine, $source)
        );
    }

    public function testLoopArray()
    {
        $engine = new Engine();
        $source = '{loop $items $item}{/loop}';
        static::assertEquals(
            '<?php foreach($items as $item) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop $items $key $item}{/loop}';
        static::assertEquals(
            '<?php foreach($items as $key => $item) {  } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop $items $key $item}_{$key}:{$item}_{/loop}';
        static::assertEquals(
            '<?php foreach($items as $key => $item) { ?>_<?php echo $key;?>:<?php echo $item;?>_<?php } ?>',
            $this->getCompiled($engine, $source)
        );

        $source = '{loop $items $key $item}_{$key}:{$item}_{loop $item $k $v}_{$k}:{$v}_{/loop}{/loop}';
        static::assertEquals(
            '<?php foreach($items as $key => $item) { ?>'.
            '_<?php echo $key;?>:<?php echo $item;?>_'.
            '<?php foreach($item as $k => $v) { ?>'.
            '_<?php echo $k;?>:<?php echo $v;?>_'.
            '<?php }  } ?>',
            $this->getCompiled($engine, $source)
        );
    }

    public function testTemplateTag()
    {
        $engine = new Engine();
        $code = $engine->compile(__DIR__.'/source/parent.html');
        list($header, $compilation) = explode("\n", $code, 2);
        static::assertEquals($compilation, file_get_contents(__DIR__.'/compiled/parent.php'));
    }

    public function testDataTag()
    {
        $engine = new Engine();

        $source = '{@data attr=attr /}';
        static::assertEquals(
            '{@data attr=attr /}',
            $this->getCompiled($engine, $source)
        );

        $source = '{@data attr=attr}_{$filed.name}_{/@data}';
        static::assertEquals(
            '{@data attr=attr}_<?php echo $filed[\'name\'];?>_{/@data}',
            $this->getCompiled($engine, $source)
        );

        $engine->setDataProvider('Function');

        $source = '{@data attr=attr /}';
        static::assertEquals(
            '<?php $__template__tag__block__ = Function(\'data\', [' . "\n" .
            '\'attr\' => \'attr\',' . "\n" .
            '], true); echo is_array($__template__tag__block__) ? \'Array\' : (string) $__template__tag__block__; unset($__template__tag__block__);?>'
            ,
            $this->getCompiled($engine, $source)
        );

        $source = '{@data attr=attr}_{$filed.name}_{/@data}';
        static::assertEquals(
            '<?php $__template__tag__block__ = Function(\'data\', [' . "\n" .
            '\'attr\' => \'attr\',' . "\n" .
            '], false); foreach((array) $__template__tag__block__ as $key=>$field) {  ?>'.
            '_<?php echo $filed[\'name\'];?>_'.
            '<?php } unset($__template__tag__block__);?>'
            ,
            $this->getCompiled($engine, $source)
        );
    }

    public function testTemplateParser()
    {
        $code = <<<'CODE'
11 
{template @blank /}
22 
{template @foo extension="html"/} 
33
{template %blank /} 
44
{template %foo.html /} 
CODE;
        $engine = new Engine();
        try {
            $engine->compileCode($code, __DIR__);
            static::fail('It should throw exception when template not exist');
        } catch (Exception $e) {
            static::assertTrue(true);
        }

        $engine->addTemplateParser(function (Tag $tag) {
            if ('@' === $tag->value[0]) {
                $tag->setValue('#'.__DIR__.'/Fixtures/'.substr($tag->value, 1));
            }
        })->addTemplateParser(function (Tag $tag) {
            if ('%' === $tag->value[0]) {
                $tag->setValue('/Fixtures/'.substr($tag->value, 1));
            }
        });
        static::assertTrue(false !== strpos($engine->compileCode($code, __DIR__), 'foo'));
    }

    public function testCustomTagCompiler()
    {
        $engine = new Engine();
        $engine->addTagCompiler(new class extends Compiler {
            protected $tags = ['phpunit', 'phpunit-alias'];

            public function compile(Tag $tag, Engine $engine)
            {
                if ($tag->closed) {
                    return $tag->setInner($tag->name.'_'.$tag->getAttribute('foo'));
                }
                return $tag->setInner($tag->name.'_'.$tag->inner.'_'.$tag->getAttribute('foo'));
            }
        });
        $source = '{phpunit foo=foo/}';
        static::assertEquals(
            'phpunit_foo',
            $this->getCompiled($engine, $source)
        );
        $source = '{phpunit foo=foo}inner{/phpunit}';
        static::assertEquals(
            'phpunit_inner_foo',
            $this->getCompiled($engine, $source)
        );

        $source = '{phpunit-alias foo=foo/}';
        static::assertEquals(
            'phpunit-alias_foo',
            $this->getCompiled($engine, $source)
        );
        $source = '{phpunit-alias foo=foo}inner{/phpunit-alias}';
        static::assertEquals(
            'phpunit-alias_inner_foo',
            $this->getCompiled($engine, $source)
        );

        $engine = new Engine();
        $engine->addTagCompiler(new class extends Compiler {
            protected $tags = 'pure';

            public function compile(Tag $tag, Engine $engine)
            {
                $tag->setInner('pure:'.$tag->inner)->setParsed();
            }
        });
        $engine->addTagCompiler(new class extends Compiler {
            protected $tags = 'parse';

            public function compile(Tag $tag, Engine $engine)
            {
                $tag->setInner('parse:'.$tag->inner);
            }
        });
        $code = $engine->compile(__DIR__.'/source/parent.html');
        list($header, $compilation) = explode("\n", $code, 2);
        static::assertEquals($compilation, file_get_contents(__DIR__.'/compiled/parent_tag.php'));
    }

    /**
     * @dataProvider getAttrTest
     * @param $source
     * @param $compiled
     * @param $str
     */
    public function testAttrParser($source, $compiled, $str)
    {
        $arr = Engine::parseAttribute($source);
        static::assertEquals($compiled, $arr);
        static::assertEquals($str, Engine::stringifyAttrs($arr));
    }

    public function getAttrTest()
    {
        return [
            ['foo=foo bar="bar" baz=\'baz\'', [
                'foo'=>'foo',
                'bar'=>'bar',
                'baz'=>'baz'
            ], "[
'foo' => 'foo',
'bar' => 'bar',
'baz' => 'baz',
]"],

            ['foo   
             bar=  bar baz="  \'baz\'"', [
                '__value__'=>'foo',
                'bar'=>'bar',
                'baz'=>'  \'baz\''
            ], "[
'__value__' => 'foo',
'bar' => 'bar',
'baz' => '  \'baz\'',
]"],

            ['foo  bar  
            biz bar =  bar baz = "  baz=\'baz\'" que    qux= qux', [
                '__value__'=>'foo bar biz',
                'bar'=>'bar',
                'baz'=>'  baz=\'baz\'',
                'que' => true,
                'qux' => 'qux'
            ], "[
'__value__' => 'foo bar biz',
'bar' => 'bar',
'baz' => '  baz=\'baz\'',
'que' => 1,
'qux' => 'qux',
]"],

            ['foo   $foo 
             bar=$bar baz=method($a) que=2 qux=$arr.key $lst=$obj:key', [
                '__value__' => 'foo $foo',
                'bar' => '$bar',
                'baz' => 'method($a)',
                'que' => '2',
                'qux' => '$arr.key',
                '$lst' => '$obj:key',
            ], <<<'STR'
[
'__value__' => 'foo $foo',
'bar' => $bar,
'baz' => method($a),
'que' => 2,
'qux' => $arr['key'],
'$lst' => $obj->key,
]
STR
            ],

        ];
    }
}
