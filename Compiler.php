<?php
namespace Tanbolt\View;

abstract class Compiler
{
    /**
     * 支持的 Tag 标签名, 数组 或逗号隔开的(如: 'foo,bar') 字符串
     * @var array|string
     */
    protected $tags = [];

    /**
     * 支持的 Tag 标签名
     * @return array|string
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * 编译开始前
     * @param Layer $layer
     * @param Engine $engine
     */
    public function onStart(Layer $layer, Engine $engine)
    {
    }

    /**
     * 编译结束后
     * @param string $code
     * @param Engine $engine
     */
    public function onEnd(string $code, Engine $engine)
    {
    }

    /**
     * 解析标签
     * @param Tag $tag
     * @param Engine $engine
     */
    abstract public function compile(Tag $tag, Engine $engine);
}
