<?php
namespace Tanbolt\View;

/**
 * Class Layer
 * @package Tanbolt\View
 *
 * 以下为只读属性
 * @property-read bool $isString 是否为字符串模板
 * @property-read ?string $path 模板路径 (真实文件路径 或 虚拟目录路径)
 * @property-read ?string $dirname 模板所在目录
 * @property-read ?string $basename 模板文件名(包含后缀)
 * @property-read ?string $filename 模板文件名(不包含后缀)
 * @property-read ?string $extension 模板文件名后缀
 * @property-read string $source 模板源代码
 * @property-read ?Layer $parent 父级模板
 * @property-read int $deep 模板嵌套层深
 */
class Layer
{
    /**
     * 是否为字符串模板
     * @var bool
     */
    private $isString = false;

    /**
     * 模板路径 (真实文件路径 或 虚拟目录路径)
     * @var ?string
     */
    private $path = null;

    /**
     * 模板所在目录
     * @var ?string
     */
    private $dirname = null;

    /**
     * 模板文件名(包含后缀)
     * @var ?string
     */
    private $basename = null;

    /**
     * 模板文件名(不包含后缀)
     * @var ?string
     */
    private $filename = null;

    /**
     * 模板文件名后缀
     * @var ?string
     */
    private $extension = null;

    /**
     * 模板源代码
     * @var string
     */
    private $source = null;

    /**
     * 父级模板
     * @var ?Layer
     */
    private $parent = null;

    /**
     * 模板嵌套层深
     * @var int
     */
    private $deep = 0;

    /**
     * Layer constructor.
     * @param string $template
     * @param string|null|false $virtual false:模版路径, string|null:字符模版所在的虚拟目录
     * @param Layer|null $parent
     */
    public function __construct(string $template, $virtual = null, Layer $parent = null)
    {
        if (false === $virtual) {
            $this->isString = false;
            $this->source = file_get_contents($template);
            $this->path = $template;
            $paths = pathinfo($template);
            if (isset($paths['dirname'])) {
                $this->dirname = $paths['dirname'];
            }
            if (isset($paths['basename'])) {
                $this->basename = $paths['basename'];
            }
            if (isset($paths['filename'])) {
                $this->filename = $paths['filename'];
            }
            if (isset($paths['extension'])) {
                $this->extension = $paths['extension'];
            }
        } else {
            $this->isString = true;
            $this->source = $template;
            $this->path = $virtual;
            $this->dirname = $virtual;
        }
        $this->parent = $parent;
        $this->deep = $parent ? $parent->deep + 1 : 0;
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
