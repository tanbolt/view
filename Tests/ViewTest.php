<?php

use Tanbolt\View\Tag;
use Tanbolt\View\View;
use Tanbolt\View\Engine;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    public function testSetCompiledListener()
    {
        $templateString = '
        foo

        {template !blank extension="" /}

        foo

        {@bar key=val hello=world/}

        foo
        ';

        $view = new View();
        $view->setStringTemplate($templateString);
        try {
            $view->getCompiledCode();
            static::fail('It should be throw exception when template is not exist');
        } catch (Exception $e) {
            static::assertTrue(true);
        }

        $view = new View();
        $callback = function(Engine $template){
            $template->addTemplateParser(function(Tag $tag){
                if ('!' === $tag->value[0]) {
                    $tag->setValue('#'.__DIR__.'/Fixtures/'.substr($tag->value, 1));
                }
            });
        };
        static::assertSame($view, $view->setCompiledListener($callback));
        static::assertSame($callback, $view->getCompiledListener());

        $view->setStringTemplate($templateString);
        $compilation = $view->getCompiledCode();
        static::assertFalse(false !== strpos($compilation, '{template !blank'));
        static::assertFalse(false !== strpos($compilation, '{@bar key=val hello=world/}'));
    }

    public function testSetStorageListener()
    {
        $view = new View();
        $callback = function (){};
        static::assertSame($view, $view->setStorageListener($callback));
        static::assertSame($callback, $view->getStorageListener());
    }

    public function testSetDataProvider()
    {
        $templateString = '
        foo

        {@bar key=val hello=world/}

        foo
        ';
        $attr = [];

        $view = new View();
        $callback = function($tag, $attributes, $close) use (&$attr){
            if ('bar' === $tag && $close) {
                $attr = $attributes;
                return '__TagCallbackResult___';
            }
            return false;
        };
        static::assertSame($view, $view->setDataProvider($callback));
        static::assertSame($callback, $view->getDataProvider());
        $view->setStringTemplate($templateString);
        static::assertTrue(false !== strpos($view->render(), '__TagCallbackResult___'));
        static::assertEquals(['key' => 'val', 'hello' => 'world'], $attr);
    }

    public function testSetHomedir()
    {
        $view = new View();
        static::assertInstanceOf(Engine::class, $view->engine());
        static::assertNull($view->getHomedir());
        static::assertNull($view->engine()->getHomeDir());

        $view->setHomedir(__DIR__);
        static::assertNotNull($view->getHomedir());
        static::assertEquals($view->getHomedir(), $view->engine()->getHomeDir());
    }

    public function testSetStorage()
    {
        $view = new View();
        static::assertNull($view->getStorage());
        static::assertSame($view, $view->setStorage(__DIR__.'/Fixtures'));
        static::assertEquals(realpath(__DIR__.'/Fixtures'), $view->getStorage());
    }

    public function testSetDepth()
    {
        $view = new View();
        static::assertTrue(is_int($view->getDepth()));
        static::assertSame($view, $view->setDepth(3));
        static::assertEquals(3, $view->getDepth());
    }

    public function testSetValidateFreq()
    {
        $view = new View();
        static::assertTrue(is_int($view->getValidateFreq()));
        static::assertSame($view, $view->setValidateFreq(300));
        static::assertEquals(300, $view->getValidateFreq());
    }

    public function testSetCompress()
    {
        $view = new View();
        static::assertFalse($view->isCompressed());
        static::assertSame($view, $view->setCompress(true));
        static::assertTrue($view->isCompressed());
        static::assertSame($view, $view->setCompress(false));
        static::assertFalse($view->isCompressed());
    }

    public function testSetCached()
    {
        $view = new View();
        static::assertFalse($view->isCached());
        static::assertSame($view, $view->setCached(true));
        static::assertTrue($view->isCached());
        static::assertSame($view, $view->setCached(false));
        static::assertFalse($view->isCached());

        $view = new View();
        static::assertSame($view, $view->setStorage(__DIR__.'/Fixtures'));
        static::assertTrue($view->isCached());

        $view = new View();
        $view->setCached(false)->setStorage(__DIR__.'/Fixtures');
        static::assertFalse($view->isCached());
    }

    public function testSetTemplate()
    {
        $view = new View();
        static::assertSame($view, $view->setTemplate(__DIR__.'/Fixtures/template.html'));
        static::assertEquals(__DIR__.'/Fixtures/template.html', $view->getTemplate());
        static::assertFalse($view->isStringTemplate());
        static::assertNull($view->getTemplateVirtual());

        static::assertSame($view, $view->setStringTemplate('string'));
        static::assertEquals('string', $view->getTemplate());
        static::assertTrue($view->isStringTemplate());
        static::assertEquals(__DIR__, $view->getTemplateVirtual());

        static::assertSame($view, $view->setStringTemplate('string', 'Dir'));
        static::assertEquals('string', $view->getTemplate());
        static::assertTrue($view->isStringTemplate());
        static::assertEquals('Dir', $view->getTemplateVirtual());
    }

    public function testVars()
    {
        $view = new View();

        static::assertFalse($view->hasVar('foo'));
        static::assertSame($view, $view->putVar('foo', 'bar'));
        static::assertTrue($view->hasVar('foo'));

        static::assertFalse($view->hasVar('key'));
        static::assertSame($view, $view->putVar(['key' => 'val', 'key2' => 'val2']));
        static::assertTrue($view->hasVar('foo'));
        static::assertFalse($view->hasVar('none'));

        static::assertEquals(['foo' => 'bar', 'key' => 'val', 'key2' => 'val2'], $view->allVars());
        static::assertEquals('bar', $view->getVar('foo'));
        static::assertNull($view->getVar('key3'));
        static::assertEquals('val3', $view->getVar('key3', 'val3'));
        static::assertEquals(['foo' => 'bar', 'key' => 'val'], $view->getVar(['foo', 'key']));

        static::assertSame($view, $view->removeVar('foo'));
        static::assertNull($view->getVar('foo'));
        static::assertEquals(['key' => 'val', 'key2' => 'val2'], $view->allVars());

        static::assertSame($view, $view->clearVars());
        static::assertEquals([], $view->allVars());
    }

    public function testDisplay()
    {
        $view = new View();
        $template = 'template';
        $vars = ['foo' => 'foo', 'bar' => 'bar'];
        static::assertSame($view, $view->display($template, $vars));
        static::assertEquals($template, $view->getTemplate());
        static::assertEquals($vars, $view->allVars());
    }

    public function testCompiled()
    {
        $view = new View();
        $view->setStorage(__DIR__.'/Fixtures')
            ->setDepth(0)
            ->setTemplate(__DIR__.'/Fixtures/foo.html');
        $cacheFile = $view->getCompiledPath();

        // 仅编译
        @unlink($cacheFile);
        static::assertStringEndsWith('foo', $view->getCompiledCode());
        static::assertFalse(is_file($cacheFile));

        // 编译并缓存
        static::assertEquals($cacheFile, $view->getCompiled());
        static::assertTrue(is_file($cacheFile));
        static::assertStringEndsWith('foo', file_get_contents($cacheFile));

        // 仅渲染
        @unlink($cacheFile);
        $view->setCached(false);
        static::assertEquals('foo', $view->render());
        static::assertFalse(is_file($cacheFile));

        // 渲染并缓存
        $view->setCached(true);
        static::assertEquals('foo', $view->render());
        static::assertTrue(is_file($cacheFile));
        @unlink($cacheFile);
    }

    public function testCompileExpire()
    {
        $this->compileExpireTest();
    }

    public function testCompileExpireWithHomedir()
    {
        $this->compileExpireTest(true);
    }

    protected function compileExpireTest($withHomeDir = false)
    {
        $view = new View();
        $view->setStorage(__DIR__.'/Fixtures')
            ->setDepth(0)
            ->setTemplate(__DIR__.'/source/parent.html');
        if ($withHomeDir) {
            $view->setHomedir(__DIR__);
        }

        // 缓存过期测试
        $path = $view->getCompiledPath();
        @unlink($path);
        static::assertTrue($view->isExpired());
        static::assertEquals($path, $view->getCompiled());
        static::assertFalse($view->isExpired());

        // 修改模版 再次测试
        $source = __DIR__.'/source/_son_1.html';
        $bak = __DIR__.'/source/_son_1_bak.html';
        copy($source, $bak);
        file_put_contents($source, "append", FILE_APPEND);

        static::assertTrue($view->isExpired());

        $view->setValidateFreq(3000);
        static::assertFalse($view->isExpired());

        $view->setValidateFreq(0);
        static::assertTrue($view->isExpired());

        unlink($source);
        rename($bak, $source);

        static::assertFalse($view->isExpired());
        @unlink($path);
    }

    public function testTemplateParse()
    {
        require __DIR__.'/Fixtures/function.php';

        $view = new View();
        $view->setTemplate(__DIR__.'/Fixtures/template.html');

        // unCompress
        $compilation = $view->getCompiledCode();
        $compiled = explode("\n", $compilation, 2);
        $compiled = $compiled[1];

        $compiled_actual = file_get_contents(__DIR__.'/Fixtures/compiled.php');
        static::assertEquals($compiled, $compiled_actual);

        // compress
        $compilation = $view->setCompress(true)->getCompiledCode();
        $compiled = explode("\n", $compilation, 2);
        $compiled = $compiled[1];

        $compiled_actual = file_get_contents(__DIR__.'/Fixtures/compiledCompress.php');
        static::assertEquals($compiled, $compiled_actual);

        // 测试渲染
        $view->setCompress(false);
        $view->setDataProvider(function($tag, $attributes, $close){
            if ('foobar' === $tag) {
                if($close) {
                    return '__foobar__close__';
                }
                return array_merge([
                    'foo' => 'foo',
                    'bar' => 'bar'
                ], $attributes);
            }
            return false;
        });

        $view->putVar('person', new Person());
        $view->putVar('page',[
            'title' => 'chinese',
            'name' => '汉字',
            'charset' => 'utf-8',
        ]);
        $view->putVar('block', [
            'name' => 'block',
            'arr' => [
                'foo' => 'foo',
                'bar' => 'bar'
            ]
        ]);
        $render_actual = file_get_contents(__DIR__.'/Fixtures/render');

        // 检验渲染结果
        $render = $view->render();
        static::assertEquals($render, $render_actual);

        // 测下 beforeRender 监听
        $temp = $temp2 = null;
        $render_actual2 = str_replace('chinese', 'chinese2', $render_actual);
        $beforeRender = function (View $viewObj) use (&$temp, $view){
            $temp = 'temp';
            static::assertSame($view, $viewObj);
            $viewObj->putVar('page',[
                'title' => 'chinese2',
                'name' => '汉字',
                'charset' => 'utf-8',
            ]);
        };
        $view->addBeforeRender($beforeRender);

        // 测试 afterRender 监听
        $afterRender = function ($content) use (&$temp2, $render_actual2){
            $temp2 = 'temp2';
            static::assertEquals($content, $render_actual2);
            return $content.'____append';
        };
        $view->addAfterRender($afterRender);

        // 校验经过监听函数处理后的 渲染结果
        $render = $view->render();
        static::assertEquals($render, $render_actual2.'____append');
        static::assertEquals('temp', $temp);
        static::assertEquals('temp2', $temp2);

        // 移除 render 监听函数再次测试
        $temp = $temp2 = null;
        $view->putVar('page',[
            'title' => 'chinese',
            'name' => '汉字',
            'charset' => 'utf-8',
        ]);
        $view->offBeforeRender($beforeRender)->offAfterRender($afterRender);
        $render = $view->render();
        static::assertEquals($render, $render_actual);
        static::assertNull($temp);
        static::assertNull($temp2);
    }

    public function testLockConfig()
    {
        $view = new View();
        $temp = [
            'compiled' => 0,
            'storage' => 0,
            'before' => 0,
            'after' => 0
        ];
        $view->setCompiledListener($compiled = function () use (&$temp){
            $temp['compiled']++;
        })->setStorageListener($storage = function () use (&$temp){
            $temp['storage']++;
        })->addBeforeRender(function () use (&$temp){
            $temp['before']++;
        })->addAfterRender(function ($content) use (&$temp){
            $temp['after']++;
            return $content;
        })->setDataProvider($provider = function (){})
            ->setHomedir(__DIR__)
            ->setStorage(__DIR__)
            ->setDepth(2)
            ->setValidateFreq(200)
            ->setCompress()
            ->setCached(false)
            ->lockConfig();

        // 校验初始化配置
        $view->setStringTemplate('foo')->render();
        $this->checkLockConfig($view, $compiled, $storage, $provider, __DIR__, 2, 200);
        static::assertTrue($view->isCompressed());
        static::assertEquals([
            'compiled' => 1,
            'storage' => 0,
            'before' => 1,
            'after' => 1
        ], $temp);

        // 重置配置
        $dir = __DIR__.DIRECTORY_SEPARATOR.'Fixtures';
        $view->setCompiledListener($compiled2 = function () use (&$temp){
            $temp['compiled'] += 2;
        })->setStorageListener($storage2 = function () use (&$temp){
            $temp['storage'] += 2;
        })->addBeforeRender(function () use (&$temp){
            $temp['before'] += 2;
        })->addAfterRender(function ($content) use (&$temp){
            $temp['after'] += 2;
            return $content;
        })->setDataProvider($provider2 = function (){})
            ->setHomedir($dir)
            ->setStorage($dir)
            ->setDepth(1)
            ->setValidateFreq(400)
            ->setCompress(false);
        $view->setStringTemplate('foo')->render();
        $this->checkLockConfig($view, $compiled2, $storage2, $provider2, $dir, 1, 400);
        static::assertFalse($view->isCompressed());
        static::assertEquals([
            'compiled' => 3,
            'storage' => 0,
            'before' => 4,
            'after' => 4
        ], $temp);

        // 还原初始化配置
        $view->__destruct();
        $this->checkLockConfig($view, $compiled, $storage, $provider, __DIR__, 2, 200);
        static::assertTrue($view->isCompressed());
    }

    private function checkLockConfig($view, $compiled, $storage, $provider, $dir, $depth, $freq)
    {
        static::assertSame($compiled, $view->getCompiledListener());
        static::assertSame($storage, $view->getStorageListener());
        static::assertSame($provider, $view->getDataProvider());
        static::assertEquals($dir, $view->getHomedir());
        static::assertEquals($dir, $view->getStorage());
        static::assertEquals($depth, $view->getDepth());
        static::assertEquals($freq, $view->getValidateFreq());
    }
}


class Person
{
    public $name = 'foo';
    public $height = 170;
}
