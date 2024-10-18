<?php

use Tanbolt\View\Tag;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    public function testTag()
    {
        $tag = new Tag('tag', null, ['foo' => 'foo'], false);
        static::assertEquals('tag', $tag->name);
        static::assertNull($tag->value);
        static::assertEquals(['foo' => 'foo'], $tag->attributes);
        static::assertFalse($tag->closed);

        $tag = new Tag('tag', 'vvv', ['bar' => 'bar'], true);
        static::assertEquals('tag', $tag->name);
        static::assertEquals('vvv', $tag->value);
        static::assertEquals(['bar' => 'bar'], $tag->attributes);
        static::assertTrue($tag->closed);
    }

    public function testTagAttributes()
    {
        $tag = new Tag('tag', null, ['foo' => 'foo'], false);
        static::assertEquals('foo', $tag->getAttribute('foo'));
        static::assertNull($tag->getAttribute('bar'));
        static::assertEquals('bar', $tag->getAttribute('bar', 'bar'));

        static::assertSame($tag, $tag->setAttribute('foo', 'foo2'));
        static::assertEquals('foo2', $tag->getAttribute('foo'));
        $tag->setAttribute('bar', 'bar2');
        static::assertEquals('bar2', $tag->getAttribute('bar'));

        static::assertEquals(['foo' => 'foo2', 'bar' => 'bar2'], $tag->attributes);
        $tag->setAttribute([
            'foo' => 'foo',
            'baz' => 'baz',
            'que' => 'que'
        ]);
        static::assertEquals(['foo' => 'foo', 'bar' => 'bar2', 'baz' => 'baz', 'que' => 'que'], $tag->attributes);

        static::assertSame($tag, $tag->keepAttribute(['foo', 'bar', 'que']));
        static::assertEquals(['foo' => 'foo', 'bar' => 'bar2', 'que' => 'que'], $tag->attributes);

        static::assertSame($tag, $tag->removeAttribute('foo'));
        static::assertEquals(['bar' => 'bar2', 'que' => 'que'], $tag->attributes);

        static::assertSame($tag, $tag->keepAttribute('bar'));
        static::assertEquals(['bar' => 'bar2'], $tag->attributes);
        $str = <<<'STR'
[
'bar' => 'bar2',
]
STR;
        static::assertEquals($str, $tag->attributeString());

        $tag->setAttribute('foo', 'foo');
        static::assertSame($tag, $tag->clearAttribute());
        static::assertEquals([], $tag->attributes);
    }

    public function testTagValue()
    {
        $tag = new Tag('tag');
        static::assertNull($tag->value);
        static::assertSame($tag, $tag->setValue('foo'));
        static::assertEquals('foo', $tag->value);
        $tag->setValue(null);
        static::assertNull($tag->value);
    }

    public function testTagInner()
    {
        $tag = new Tag('tag');
        static::assertNull($tag->inner);
        static::assertSame($tag, $tag->setInner('foo'));
        static::assertEquals('foo', $tag->inner);
        $tag->setInner(null);
        static::assertNull($tag->inner);
    }

    public function testTagParsed()
    {
        $tag = new Tag('tag');
        static::assertFalse($tag->parsed);
        static::assertSame($tag, $tag->setParsed());
        static::assertTrue($tag->parsed);
        $tag->setParsed(false);
        static::assertFalse($tag->parsed);
    }
}
