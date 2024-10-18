<?php
namespace Tanbolt\View;

/**
 * Class Tag: 模版中的标签对象
 * @package Tanbolt\View
 *
 * 以下为只读属性
 * @property-read string $name Tag 名称
 * @property-read string $value Tag 值
 * @property-read array $attributes Tag 属性集合
 * @property-read bool $closed  是否为自封口 Tag
 * @property-read array|false $children  Tag 包裹的子结构, 自封口 Tag 为 false
 * @property-read ?string $inner 非自封口标签, 标签包裹的字符串
 * @property-read bool $parsed 包裹字符串 $inner 是否已解析为 php code
 */
class Tag
{
    /**
     * 标签名
     * @var string
     */
    private $name = null;

    /**
     * 标签值
     * @var ?string
     */
    private $value = null;

    /**
     * 标签属性
     * @var array
     */
    private $attributes = [];

    /**
     * 是否为自封口标签
     * @var bool
     */
    private $closed = false;

    /**
     * Tag包裹的子结构, [string, Tag, ...], 默认情况下已编译 children 并将结果赋值给 inner
     * 若对 inner 有特殊需求, 可自行利用 children 处理, 一般很难用的到
     * @var array|false
     */
    private $children = [];

    /**
     * 非自封口标签, 标签包裹的字符串
     * @var ?string
     */
    private $inner = null;

    /**
     * 包裹字符串 $inner 是否已解析为 php code
     * @var bool
     */
    private $parsed = false;

    /**
     * Tag constructor.
     * @param string $name
     * @param ?string $value
     * @param array $attributes
     * @param bool $closed
     */
    public function __construct(string $name, string $value = null, array $attributes = [], bool $closed = false)
    {
        $this->name = $name;
        $this->value = $value;
        $this->attributes = $attributes;
        $this->closed = $closed;
        return $this;
    }

    /**
     * 设置一个或多个 attrs 属性
     * @param string|array $key 可使用数组同时设置多个
     * @param mixed $val
     * @return $this
     */
    public function setAttribute($key, $val = null)
    {
        if (is_array($key)) {
            $this->attributes = array_merge($this->attributes, $key);
        } else {
            $this->attributes[$key] = $val;
        }
        return $this;
    }

    /**
     * 获取指定 attrs 属性
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $key, $default = null)
    {
        if (!array_key_exists($key, $this->attributes)) {
            return $default;
        }
        return $this->attributes[$key];
    }

    /**
     * 移除一个或多个 attrs 属性
     * @param string|array $key 可使用数组同时移除多个
     * @return $this
     */
    public function removeAttribute($key)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            unset($this->attributes[$k]);
        }
        return $this;
    }

    /**
     * 仅保留指定的一个或多个 attrs 属性
     * @param string|array $key
     * @return $this
     */
    public function keepAttribute($key)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($this->attributes as $k => $v) {
            if (!in_array($k, $key)) {
                unset($this->attributes[$k]);
            }
        }
        return $this;
    }

    /**
     * 清空 attrs 属性
     * @return $this
     */
    public function clearAttribute()
    {
        $this->attributes = [];
        return $this;
    }

    /**
     * 获得所有属性的字符串表示方式
     * @return string
     */
    public function attributeString()
    {
        return Engine::stringifyAttrs($this->attributes);
    }

    /**
     * 重置 value
     * @param ?string $value
     * @return $this
     */
    public function setValue(?string $value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * 重置包裹字符串
     * @param ?string $code
     * @return $this
     */
    public function setInner(?string $code)
    {
        $this->inner = $code;
        return $this;
    }

    /**
     * 设置包裹字符串 是否已解析，若设置为 true, 则后续就不会处理包裹的 标签（如 {$foo} 这样的标量输出标签）
     * @param bool $parsed
     * @return $this
     */
    public function setParsed(bool $parsed = true)
    {
        $this->parsed = $parsed;
        return $this;
    }

    /**
     * 设置 $children, 用在引擎内部，不建议外部使用
     * @param array|false $children
     * @return $this
     */
    public function __setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * 获取 Tag 属性值
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->{$name};
    }
}
