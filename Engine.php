<?php
namespace Tanbolt\View;

use Tanbolt\View\Exception\EngineException;

/**
 * Class Engine: View 模版引擎
 *
 * # 一、变量输出
 *   1. 常量: {CONSTANTS}
 *   2. 基本: {$var}  {$var.key} = {$var[key]}  {$var:key} = {$var->key}  {$var:method()} = {$var->method()}
 *   3. 计算: {$var + 1}  {($var + 1) * 2}
 *   4. 有缺省值: {$var or str}  {$var or $var2}， 若 $var 不存在, 输出缺省值
 *   5. 函数: {method($var)}  {method($var, method2($var2))}
 *
 * 解析规则:
 * 以 "{}" 包裹作为标志, 以 "$" 或 "($" 或 "method(" 开头, 将解析为 php 输出; 不符合此规则将原样输出。
 * 符合此规则，且希望以原样输出，使用 "{{}}" 包裹,  如 "{{$var}}" 将输出为 "{$var}"
 *
 *
 * # 二、模版标签
 *   1. 正则提取  {tagName value attrs} inner {/tagName}  和  {tagName value attrs /} 两种类型
 *   2. tagName 内核支持
 *      php 代码: {php} php code {/php}  或  {php echo $a * 2 /}
 *      保持原样: {literal} any string {/literal}
 *      嵌套模版: {template path attrs/} 或 {template path attrs} inner {/template}
 *      条件判断: {if $a} foo {elseif $b} bar {else} biz {/if}
 *      循环输出: {loop $array $item} {$item} {/loop}
 *              {loop $array $key $item} {$key}:{$item} {/loop}
 *              {loop start=2 end=10} {$i} {/loop} 或 {loop start=2 end=10 key=index} {$index} {/loop}
 *   3. 可自行通过接口扩展更多标签
 *
 *
 * # 三、数据标签
 *   1. 任意 {@taglib value attrs} inner {/@taglib}  或  {@taglib value attrs /}
 *   2. 参数 @taglib value attrs 传递给 setDataProvider 设置的回调函数, 处理后返回 array 或 string
 *      a. 返回 string 用于自封口标签, 直接输出 string
 *      b. 返回 array 标签, 用于 成对 标签, inner 中可使用 array 单条变量, 如
 *      {@taglib value attrs key=key field=field}   (key, field 为默认变量名, 可重置)
 *          {$key}
 *          {$field.name}
 *      {/@taglib}
 *
 *
 * # 四、关于 模版标签 和 数据标签 中的 value attrs
 *   1. value 为紧跟着 tag 后的字符串, 允许空格, 直到碰到第一个 attr 值为止, value 总会解析为字符串
 *   2. attrs 与 html 标签的 attributes 类似
 *      key=value 不使用引号, 值在空格处结束
 *      key="value more" 也可使用单引号/双引号括起来, 值可以有空格
 *      key 仅单独一个 key, 值解析为 true, 此类型 attr 必须出现在 key=value 形式之后, 否则会被当做 tag value 处理
 *   3. attr 的 value 若符合 变量 规则, 则解析为 php 变量, 否则解析为普通字符串
 *
 * @package Tanbolt\View
 */
class Engine
{
    /**
     * 模版根目录
     * @var ?string
     */
    private $homedir = null;

    /**
     * @var int
     */
    private $homedirLen = 0;

    /**
     * 数据标签 的 数据提供函数
     * @var string
     */
    private $dataProvider = null;

    /**
     * 解析模板路径的函数容器
     * @var array
     */
    private $templateParser = [];

    /**
     * 支持的模版标签
     * @var array
     */
    private $tagCompilers = [];

    /**
     * 嵌套模板缓存
     * @var array
     */
    private $layerCache = [];

    /**
     * 当前正在处理的 嵌套模版
     * @var Layer
     */
    private $layer = null;

    /**
     * php 标签 innerText 缓存
     * @var array
     */
    private $phpCode = [];

    /**
     * 原样输出 innerText 缓存
     * @var array
     */
    private $literal = [];

    /**
     * taglib 模版标签缓存
     * @var Tag[]
     */
    private $tagString = [];

    /**
     * Template constructor.
     * @param ?string $commonTagMethod 数据标签的解析函数
     */
    public function __construct(string $commonTagMethod = null)
    {
        if (null !== $commonTagMethod) {
            $this->setDataProvider($commonTagMethod);
        }
    }

    /**
     * 设置模版源文件根目录
     * @param ?string $dir
     * @return $this
     */
    public function setHomedir(?string $dir)
    {
        if ($dir !== $this->homedir) {
            if ($dir && false === ($dir = realpath($dir))) {
                throw new EngineException('Home dir not exist');
            }
            $this->homedir = $dir;
            $this->homedirLen = $dir ? strlen($dir) : 0;
        }
        return $this;
    }

    /**
     * 获取当前设置的源文件根目录
     * @return string
     */
    public function getHomedir()
    {
        return $this->homedir;
    }

    /**
     * 设置 数据标签 的 解析函数名
     * @param string $provider
     * @return $this
     */
    public function setDataProvider(string $provider)
    {
        $this->dataProvider = $provider;
        return $this;
    }

    /**
     * 获取 数据标签 的 数据提供函数
     * @return string
     */
    public function getDataProvider()
    {
        return $this->dataProvider;
    }

    /**
     * 针对 template 标签，添加模板路径解析器，用于解析 {template path} 中的 path 路径。
     * 可重置 path 的值, 方便使用别名嵌套一些特殊目录下的模板。
     * @param callable|callable[] $callback  回调: callable(Tag $tag, Engine $engine), 使用 $tag->setValue() 重置路径
     *                                       若所设置 value 已 # 开头为绝对路径, 否则为当前父模版的相对路径
     * @return $this
     */
    public function addTemplateParser($callback)
    {
        if (!is_array($callback)) {
            $callback = [$callback];
        }
        foreach ($callback as $call) {
            if (is_callable($call)) {
                $this->templateParser[] = $call;
            }
        }
        return $this;
    }

    /**
     * 新增一个自定义 模版标签 处理器
     * @param Compiler $compiler
     * @return $this
     */
    public function addTagCompiler(Compiler $compiler)
    {
        $tags = $compiler->getTags();
        if (!is_array($tags)) {
            $tags = array_unique(array_filter(explode(',', $tags)));
        }
        if (!($tag = array_shift($tags))) {
            return $this;
        }
        $tag = strtolower($tag);
        $this->tagCompilers[$tag] = $compiler;
        foreach ($tags as $key) {
            $this->tagCompilers[strtolower($key)] = $tag;
        }
        return $this;
    }

    /**
     * 获取 指定模版标签 的 处理器
     * @param string $tag
     * @return Compiler|bool
     */
    public function getTagCompiler(string $tag)
    {
        if (!isset($this->tagCompilers[$tag])) {
            return false;
        }
        $tag = $this->tagCompilers[$tag];
        if ($tag instanceof Compiler) {
            return $tag;
        } elseif (is_string($tag)) {
            return $this->getTagCompiler($tag);
        }
        return false;
    }

    /**
     * 获取所有支持的 模版标签
     * @return array
     */
    public function allCompilerTags()
    {
        return array_keys($this->tagCompilers);
    }

    /**
     * 获取当前模版的嵌套层级, 可在自定义模版标签的回调函数中使用
     * @return Layer
     */
    public function currentLayer()
    {
        return $this->layer;
    }

    /**
     * 通过 模板路径 获得编译结果
     * @param string $template
     * @param bool $compress
     * @return string
     */
    public function compile(string $template, bool $compress = false)
    {
        if (false === ($template = realpath($template))) {
            throw new EngineException('Template ['.$template.'] not found');
        }
        return $this->compileTemplate($template, false, $compress);
    }

    /**
     * 通过 字符串 获得编译结果
     * @param string $code 模版字符串
     * @param ?string $virtual 假设当前模版所在的目录路径，以便模版字符串中使用 template 标签
     * @param bool $compress
     * @return string
     */
    public function compileCode(string $code, string $virtual = null, bool $compress = false)
    {
        if ($virtual && false === ($virtual = realpath($virtual))) {
            throw new EngineException('Virtual path ['.$virtual.'] not found');
        }
        return $this->compileTemplate($code, $virtual, $compress);
    }

    /**
     * 编译模版
     * @param string $template
     * @param ?string|false $virtual
     * @param bool $compress
     * @return string
     */
    private function compileTemplate(string $template, $virtual, bool $compress = false)
    {
        $this->layer = null;
        $this->layerCache = [];
        $compilers = [];
        $layer = $this->pushLayer($template, $virtual);
        foreach ($this->tagCompilers as $compiler) {
            if ($compiler instanceof Compiler) {
                $compilers[] = $compiler;
                $compiler->onStart($layer, $this);
            }
        }
        $code = $this->compressContent($this->mergeCompilerTags($this->collectCompilerTags($layer->source)), $compress);
        foreach ($compilers as $compiler) {
            $compiler->onEnd($code, $this);
        }
        $this->layer = null;
        $this->layerCache = [];
        return $code;
    }

    /**
     * 加一级当前的嵌套模版
     * @param string $template
     * @param string|null|false $virtual
     * @return Layer
     */
    private function pushLayer(string $template, $virtual = false)
    {
        $layer = new Layer($template, $virtual, $this->layer);
        if (!$layer->isString && !in_array($layer->path, $this->layerCache)) {
            $this->layerCache[] = $layer->path;
        }
        return $this->layer = $layer;
    }

    /**
     * 退回到上级嵌套模版
     * @return $this
     */
    private function popLayer()
    {
        $this->layer = $this->layer->parent;
        return $this;
    }

    /**
     * 当前 layer 的 template path
     * @return string
     */
    private function atLayerPath()
    {
        return ' @['.($this->layer->isString ? 'StringTemplate' : $this->layer->path).']';
    }

    /**
     * 第一步：提取模板中 compiledTags 标签
     * - {tagName __value__  attr=value attr2=value2 /}
     * - {tagName __value__  attr=value attr2=value2} inner {/tagName}
     * @param string $content
     * @return array
     */
    protected function collectCompilerTags(string $content)
    {
        // 先将 {php} {literal} 替换为占位符
        $content = $this->holderPhpTag($this->holderLiteral(static::clearNativePhp($content)));
        // 再提取 tags 并编译
        $compiledTags = $this->allCompilerTags();
        array_unshift($compiledTags, 'template', 'loop');
        // 对 tags 按照长度排序, 长的在前 比如 {tag attrs} {tag-alias attrs}
        // 若不排序, 后面那个标签预期是 tag-alias 标签名, 但实际获取到的为 tag
        usort($compiledTags, [__CLASS__, 'sortByLength']);
        $compiledTags = join('|', $compiledTags);
        $pattern = sprintf( '/\{(\/?)((%s)\b(?>[^\}]*))\}/is', $compiledTags);
        if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return [$content];
        }
        $tagLibs = [];
        $tagChildren = [];

        $valid = [];
        $startIndex = 0;
        foreach ($matches[0] as $key => $match) {
            $tagName = $matches[3][$key][0];
            $preContent = (string) substr($content, $startIndex, $match[1] - $startIndex);
            $startIndex = $match[1] + strlen($match[0]);

            if ('/' === $matches[1][$key][0]) {
                // end tag
                if (!($last = array_pop($valid)) || $last[0] !== $tagName) {
                    throw new EngineException(($last
                            ? 'Tag "{'.$last[1].'}" not closed'
                            : '"'.$match[0].'" start tag not found'
                    ) . $this->atLayerPath());
                }
                $tag = static::makeCompiledTag($tagName, static::parseAttribute(substr($last[1], strlen($tagName))));
                $tagChildren[] = $preContent;
                $tag->__setChildren($tagChildren);

                $tagChildren = [];
                $tagChildren[] = $last[2];
                $tagChildren[] = $tag;
            } else {
                $tagInner = trim(static::clearComment($matches[2][$key][0]));
                if ('/' === substr($tagInner, -1)) {
                    // self-close tag
                    $tagChildren[] = $preContent;
                    $tag = static::makeCompiledTag(
                        $tagName,
                        static::parseAttribute(substr($tagInner, strlen($tagName), -1)),
                        true
                    );
                    $tagChildren[] = $tag->__setChildren(false);
                } else {
                    // start tag
                    if (!count($valid)) {
                        if (count($tagChildren)) {
                            $tagLibs = array_merge($tagLibs, $tagChildren);
                            $tagChildren = [];
                        }
                    }
                    $valid[] = [
                        $tagName,  // tag
                        $tagInner, // tag foo=bar ...
                        $preContent
                    ];
                }
            }
        }
        if (count($valid)) {
            $last = array_pop($valid);
            throw new EngineException('Tag "{'.$last[1].'}" not closed' . $this->atLayerPath());
        }
        $tagChildren[] = substr($content, $startIndex);
        return array_merge($tagLibs, $tagChildren);
    }

    /**
     * 将 literal 标签替换为字符串  会替换: {literal} inner {/literal}
     * @param string $content
     * @return string
     */
    protected function holderLiteral(string $content)
    {
        $pattern = static::makeTagRegex('literal');
        $count = count($this->literal);
        return preg_replace_callback($pattern, function ($matches) use (&$count) {
            $this->literal[] = substr($matches[0], 9 , -10);
            return '<!--###__BOLT_LITERAL__' . $count++ . '###-->';
        }, $content);
    }

    /**
     * 将 php 标签替换为字符串, 会替换: {php} code {/php}  {php code /}
     * @param string $content
     * @return string
     */
    protected function holderPhpTag(string $content)
    {
        // php 成对标签
        $pattern = static::makeTagRegex('php');
        $count = count($this->phpCode);
        $content = preg_replace_callback($pattern, function ($match) use (&$count) {
            $this->phpCode[] = substr($match[0], 5 , -6);
            return '<!--###__BOLT_PHP_CODE__' . $count++ . '###-->';
        }, $content);

        // php 自封口标签
        $pattern = '/{php\b(?>[^\/}]*)\/}/i';
        $count = count($this->phpCode);
        return preg_replace_callback($pattern, function ($match) use (&$count) {
            $this->phpCode[] = trim(substr($match[0], 4, -2));
            return '<!--###__BOLT_PHP_CODE__' . $count++ . '###-->';
        }, $content);
    }

    /**
     * 第二步：转换所有提取的 compiledTags 标签转为 php code
     * @param array $tags
     * @return string
     */
    protected function mergeCompilerTags(array $tags)
    {
        $content = '';
        foreach ($tags as $tag) {
            if ($tag instanceof Tag) {
                $content .= $this->parseCompilerTag($tag);
            } else {
                $content .= $tag;
            }
        }
        $content = $this->compileConditional($content);
        return $this->compileDataTag($content);
    }

    /**
     * 转换 Tag 对象 为 php code
     * @param Tag $tag
     * @return string
     */
    protected function parseCompilerTag(Tag $tag)
    {
        if ('template' === $tag->name) {
            return $this->parseTemplateTag($tag);
        }
        if ('loop' === $tag->name) {
            return $this->parseLoopTag($tag);
        }
        // 找不到 Tag 自定义扩展, 返回空
        if (!($callback = $this->getTagCompiler($tag->name))) {
            return '';
        }
        if (false === $tag->children) {
            $tag->setInner(false);
        } else {
            $tag->setInner($this->mergeCompilerTags($tag->children));
        }
        $callback->compile($tag, $this);
        // 若 Tag 的 inner 已自行处理过, 转为占位符, 后续不再编译标量标签
        if ($tag->parsed) {
            $this->tagString[] = $tag;
            return '<!--###__BOLT_TAGLIB__'.(count($this->tagString) - 1).'###-->';
        }
        return (string) $tag->inner;
    }

    /**
     * 内置标签: 转 {template} 系统标签为 php code
     * @param Tag $tag
     * @return string
     */
    protected function parseTemplateTag(Tag $tag)
    {
        if (!$tag->value) {
            return '';
        }
        // 获取模版路径
        $template = $this->getTemplatePath($tag);
        // 校验模版是否循环嵌套
        $layer = $this->layer;
        $layerPath = $this->atLayerPath();
        $subLayer = $this->pushLayer($template);
        while ($layer) {
            if (!$layer->isString && $layer->path === $subLayer->path) {
                throw new EngineException('Circular template ['.$template.']' . $layerPath);
            }
            $layer = $layer->parent;
        }
        // 对子模版进行编译
        $layerTags = $this->collectCompilerTags($subLayer->source);
        if ($tag->children) {
            $newTags = [];
            while ($layerTag = array_shift($layerTags)) {
                if (is_string($layerTag)) {
                    if (false !== strpos($layerTag, '{$inner}')) {
                        $inners = explode('{$inner}', $layerTag);
                        $newTags[] = array_shift($inners);
                        foreach ($inners as $inner) {
                            $newTags = array_merge($newTags, $tag->children);
                            $newTags[] = $inner;
                        }
                    } else {
                        $newTags[] = $layerTag;
                    }
                } else {
                    $newTags[] = $layerTag;
                }
            }
            $layerTags = $newTags;
        }
        $code = $this->mergeCompilerTags($layerTags);

        // 添加开始/结束代码, 在子模版中可调用 父模版 调用标签中的 attrs
        $attr = $tag->attributes;
        $start = $end = '<?php ' . "\n";
        if ($this->layer->deep > 1) {
            $start .= '$__parent[] = $parent;' . "\n";
            $end .= '$parent = array_pop($__parent);';
        } else {
            $start .= '$__parent = [];' . "\n";
            $end .= 'unset($parent, $__parent);';
        }
        $start .= '$parent = '.static::stringifyAttrs($attr).';'. "\n" . '?>';
        $end .= "\n" . '?>';
        $code = $start . $code  . $end;
        $this->popLayer();
        return $code;
    }

    /**
     * 经 templateParser 回调函数处理后，返回模板最终路径
     * @param Tag $tag
     * @return string
     */
    protected function getTemplatePath(Tag $tag)
    {
        foreach ($this->templateParser as $parser) {
            call_user_func($parser, $tag, $this);
        }
        $path = $tag->value;
        $layer = $this->layer;
        if ('#' === $path[0]) {
            // 以 # 开头, 意味着已是绝对路径
            $path = substr($path, 1);
        } elseif ($layer && ($dir = $layer->dirname)) {
            // 否则为相对于当前 layer 的相对路径
            $path = $dir . '/' . $path;
        }
        // 是否有 extension attr
        $extension = $tag->getAttribute('extension');
        // extension 为空, path 中可能已包含 extension, 尝试一下
        if (empty($extension) && false !== ($template = realpath($path))) {
            return $template;
        }
        // 未指定 extension，使用当前 layer 的 extension (若已经明确使用 attr 指定 extension 为空, 不会进行该步骤)
        $extension = null === $extension ? $layer->extension : $extension;
        if (!empty($extension) && false !== ($template = realpath($path.'.'.$extension))) {
            return $template;
        }
        throw new EngineException('Template ['.$path.'] not found' . $this->atLayerPath());
    }

    /**
     * 内置标签: 转 {loop} 系统标签为 php code
     * @param Tag $tag
     * @return string
     */
    protected function parseLoopTag(Tag $tag)
    {
        if (false === $tag->children) {
            return '';
        }
        $start = $tag->getAttribute('start', 0);
        $end = $tag->getAttribute('end', 0);
        $inner = $this->mergeCompilerTags($tag->children);
        if ($start || $end) {
            if ($start === $end) {
                return '';
            }
            $step = $tag->getAttribute('step', 1);
            $key = $tag->getAttribute('key', 'i');
            $code = '<?php for (%1$s = '.$start.'; %1$s' . ($start < $end ? ' < ' : ' > ') .$end.'; %1$s'.
                ($start < $end ? ' +' : ' -').'= ' . $step . ') { ?>';
            return sprintf($code, '$'.$key) . $inner . '<?php } ?>';
        }
        if (!$tag->value) {
            return '';
        }
        $exception = 'Compile tag "{loop '.$tag->value.'}" failed' . $this->atLayerPath();
        $attr = explode(' ', trim(preg_replace("/[ \t\r\n]+/", ' ', $tag->value)));
        if (count($attr) > 2) {
            if (!static::isVar($attr[1]) || !static::isVar($attr[2])) {
                throw new EngineException($exception);
            }
            return sprintf(
                '<?php foreach(%s as %s => %s) { ?>',
                static::compileDotVars($attr[0]),
                $attr[1],
                $attr[2]
            ) . $inner . '<?php } ?>';
        } elseif (count($attr) > 1) {
            if (!static::isVar($attr[1])) {
                throw new EngineException($exception);
            }
            return sprintf(
                '<?php foreach(%s as %s) { ?>',
                static::compileDotVars($attr[0]),
                $attr[1]
            ) . $inner . '<?php } ?>';
        }
        return '';
    }

    /**
     * 内置标签: 转判断语句标签为 php code  {if *}  {elseif *}  {else} {/if}
     * @param string $content
     * @return string
     */
    protected function compileConditional(string $content)
    {
        $valid = 0;
        $pattern = '/{(?:(if|elseif)\b(?>[^}]*)|(else|\/if))}/i';
        $content = preg_replace_callback($pattern, function ($match) use (&$valid) {
            if (0 === $valid && 'if' != $match[1]) {
                throw new EngineException('Conditional statement is not closed' . $this->atLayerPath());
            }
            if ('if' == $match[1] || 'elseif' == $match[1]) {
                if ('if' == $match[1]) {
                    $valid++;
                }
                $statement = trim(substr(static::clearComment($match[0]), strlen($match[1])+1, -1));
                $statement = empty($statement) ? '1==1' : static::compileDotVars($statement);
                return sprintf('<?php %s (%s) { ?>', ('if' === $match[1] ? $match[1] : '} '.$match[1]), $statement);
            } elseif (isset($match[2])) {
                if ('/if' == $match[2]) {
                    $valid--;
                    return '<?php }?>';
                } elseif ('else' == $match[2]) {
                    return '<?php } else { ?>';
                }
            }
            return '';
        }, $content);
        if ($valid) {
            throw new EngineException('Conditional statement is not closed' . $this->atLayerPath());
        }
        return $content;
    }

    /**
     * 内置标签: 转任意 {tag} 数据标签为 php code，由 $dataProvider 提供数据源
     * @param string $content
     * @return string
     */
    protected function compileDataTag(string $content)
    {
        if (!$this->dataProvider) {
            return $content;
        }
        $valid = [];
        $pattern = sprintf('/\{(\/?)@((%s)\b(?>[^\}]*))\}/is',  '[a-zA-Z_\x7f-\xff][a-zA-Z0-9\._\x7f-\xff]*');
        $content = preg_replace_callback($pattern, function ($matches) use (&$valid) {
            $matches[2] = static::clearComment($matches[2]);
            //闭合标签
            if ('/' === $matches[1]) {
                //不合法的闭合标签
                if (trim($matches[2]) !== $matches[3]) {
                    return $matches[0];
                }
                $matches[3] = strtolower($matches[3]);
                if (!($last = array_pop($valid)) || $last[3] !== $matches[3]) {
                    throw new EngineException(($last
                            ? 'Tag "'.$last[0].'" not closed'
                            : '"'.$matches[0].'" start tag not found'
                    ) . $this->atLayerPath());
                }
                return $last[4] ?? '<?php } ?>';
            }
            $matches[2] = substr($matches[2], strlen($matches[3]));
            $matches[3] = strtolower($matches[3]);
            // 自闭合标签
            if ('' != $matches[2] && '/' === substr($matches[2], -1)) {
                return static::makeDataTag(
                    $this->dataProvider,
                    $matches[3], 
                    static::parseAttribute(substr($matches[2], 0, -1)), 
                    true
                );
            }
            // 开始标签
            $taglib = static::makeDataTag(
                $this->dataProvider,
                $matches[3], 
                static::parseAttribute($matches[2])
            );
            if (is_array($taglib)) {
                $matches[4] = $taglib[1];
                $taglib = $taglib[0];
            }
            $valid[] = $matches;
            return $taglib;
        }, $content);
        if (count($valid)) {
            throw new EngineException('Tag "'.$valid[0][0].'" not closed' . $this->atLayerPath());
        }
        return $content;
    }

    /**
     * 第三步：还原 {php} {literal}, 编译结果的最终处理
     * @param string $content
     * @param bool $compress
     * @return string
     */
    protected function compressContent(string $content, bool $compress = true)
    {
        // 转换标量标签为 php code
        $content = static::compileScalarTag($content);
        if ($compress) {
            // 不压缩 js 代码块, 防止 js 使用不写大括号的风格, 压缩后无法运行
            $js = [];
            $count = 0;
            $content = preg_replace_callback('#<script(.*?)>(.*?)</script>#is', function($match) use (&$js, &$count) {
                $js[] = $match[0];
                return '<!--###__BOLT_JAVASCRIPT__' . $count++ . '###-->';
            }, $content);
            unset($count);

            // 压缩多余空白
            $content = str_replace(["\r\n", "\t", "\n"], '  ', $content);
            $content = preg_replace('/\s{3,}/','  ',$content);

            // 还原 js 代码块
            $content = preg_replace_callback('/<!--###__BOLT_JAVASCRIPT__(\d+)###-->/i', function ($match) use ($js) {
                return $js[$match[1]];
            }, $content);
            unset($js);
        }
        // 还原注释
        $content = $this->revertLiteral($content);
        // 还原PHP代码块
        $content = $this->revertPhpTag($content);
        // 还原Taglib
        $content = $this->revertTaglib($content);
        $content = preg_replace('/\?' . ">[ \r\n\t]*<" . '\?php/' , "" , $content);
        return $this->expireHeader($compress) . $content;
    }

    /**
     * 将所有牵涉模版的 md5 保存到头部
     * @param ?bool $compress
     * @return string
     */
    protected function expireHeader(bool $compress = null)
    {
        $cache = [];
        foreach ($this->layerCache as $file) {
            $name = $file;
            if ($this->homedir && 0 === strpos($file, $this->homedir)) {
                $name = substr($name, $this->homedirLen);
            }
            $cache[$name] = md5_file($file);
        }
        if (null !== $compress) {
            $cache['__compress__'] = $compress;
        }
        return sprintf('<?php /*%s*/ ?>', serialize($cache)) . "\n";
    }

    /**
     * 还原 php code
     * @param string $content
     * @return string
     */
    protected function revertPhpTag(string $content)
    {
        $content = preg_replace_callback('/<!--###__BOLT_PHP_CODE__(\d+)###-->/i', function ($match) {
            return isset($this->phpCode[$match[1]]) ? "<?php\n" . $this->phpCode[$match[1]] . "\n?>" : '';
        }, $content);
        $this->phpCode = [];
        return $content;
    }

    /**
     * 还原 Literal
     * @param string $content
     * @return string
     */
    protected function revertLiteral(string $content)
    {
        $content = preg_replace_callback('/<!--###__BOLT_LITERAL__(\d+)###-->/i', function ($match) {
            return $this->literal[$match[1]] ?? '';
        }, $content);
        $this->literal = [];
        return $content;
    }

    /**
     * 还原 taglib
     * @param string $content
     * @return string
     */
    protected function revertTaglib(string $content)
    {
        // <!--###__BOLT_TAGLIB__'.count($this->tagString).'###-->
        $content = preg_replace_callback('/<!--###__BOLT_TAGLIB__(\d+)###-->/i', function ($match) {
            return isset($this->tagString[$match[1]]) ? $this->tagString[$match[1]]->inner : '';
        }, $content);
        $this->tagString = [];
        return $content;
    }

    /**
     * 去除 php 注释
     * @param string $content
     * @return string
     */
    public static function clearComment(string $content)
    {
        return preg_replace('/(\/\*[\s\S]*?\*\/)/', '',$content);
    }

    /**
     * 去除 $content 中的 php 代码
     * @param string $content
     * @return string
     */
    public static function clearNativePhp(string $content)
    {
        return preg_replace('/<!--{(.+?)}-->/s', "{\\1}", implode('', array_map(function($token) {
            return is_array($token) && $token[0] == T_INLINE_HTML ? $token[1] : '';
        }, token_get_all($content))));
    }

    /**
     * 创建 提取 {tag} inner {/tag} 标签的正则表达式
     * @param string $tag
     * @return string
     */
    public static function makeTagRegex(string $tag)
    {
        return sprintf(
            '/(\{%1$s\b(?>[^\}]*)\})(?:(?>[^\{]*)(?>(?!\{(?>%1$s\b[^\}]*|\/%1$s)\})\{[^\{]*)*)(\{\/%1$s\})/is',
            $tag
        );
    }

    /**
     * 解析 标量标签 输出, $content 中可能包含 php 原生代码
     * @param string $content
     * @return string
     */
    public static function compileScalarTag(string $content)
    {
        $result = '';
        foreach (token_get_all($content) as $token) {
            if (!is_array($token)) {
                $result .= $token;
            } else {
                $result .= $token[0] == T_INLINE_HTML ? static::compileEchoScalar($token[1]) : $token[1];
            }
        }
        return $result;
    }

    /**
     * 解析 $content 中的 标量标签, $content 中不包含原生 php 代码
     * - `{$var} => <?php echo $var;?>`
     * - `{CONSTANTS} => <?php echo CONSTANTS;?>`
     * @param string $content
     * @return string
     */
    private static function compileEchoScalar(string $content)
    {
        $pattern = '/({?){([a-zA-Z_\$(\x7f-\xff](.+?))}(}?)/';
        return preg_replace_callback($pattern, function($matches) {
            if ('{' === $matches[1] && '}' === $matches[4]) {
                return '{' . $matches[2] . '}';
            }
            $expression = trim(static::clearComment($matches[2]));
            $code = static::preparedScalarConstants($expression);
            if (!$code) {
                $code = static::preparedScalarIsset($expression);
            }
            if (!$code) {
                $code = static::preparedScalarDefault($expression);
            }
            $code = $code ?: static::preparedScalarFailed($matches[2]);
            return $matches[1].$code.$matches[4];
        }, $content);
    }

    /**
     * echo 常量 {CONSTANTS}
     * @param string $expression
     * @return ?string
     */
    private static function preparedScalarConstants(string $expression)
    {
        return preg_match('/^[a-zA-Z0-9_\x7f-\xff]*$/', $expression) ? static::preparedScalarSuccess($expression) : null;
    }

    /**
     * isset 三元表达式
     * - `{$var or default} -> <echo isset($var) ? $var : default>`
     * @param string $expression
     * @return ?string
     */
    private static function preparedScalarIsset(string $expression)
    {
        $use = false;
        $pattern = '/^(.+?)\s+or\s+(.+?)$/i';
        $code = preg_replace_callback($pattern, function($matches) use (&$use) {
            $use = true;
            if ('$' !== $matches[1][0]) {
                return $matches[0];
            }
            return static::preparedScalarSuccess(sprintf(
                'isset(%1$s) ? %1$s : %2$s',
                static::compileDotVars($matches[1]),
                static::compileVariable($matches[2])
            ));
        }, $expression);
        if (!$use) {
            return null;
        }
        if ($code === $expression) {
            return static::preparedScalarFailed($code);
        }
        return $code;
    }

    /**
     * 缺省 echo 解析
     * @param string $expression
     * @return ?string
     */
    private static function preparedScalarDefault(string $expression)
    {
        if (!static::isExpression($expression)) {
            return null;
        }
        return static::preparedScalarSuccess(static::compileDotVars($expression));
    }

    /**
     * 解析 scalar 成功
     * @param string $expression
     * @return string
     */
    private static function preparedScalarSuccess(string $expression)
    {
        return '<?php echo '.$expression.';?>';
    }

    /**
     * 解析 scalar 失败
     * @param string $expression
     * @return string
     */
    private static function preparedScalarFailed(string $expression)
    {
        return '{'.$expression.'}';
    }

    /**
     * 获取 {tag} 数据标签编译后的 php code
     * @param string $dataProvider
     * @param string $tag
     * @param array $attr
     * @param bool $close
     * @return array|string
     */
    public static function makeDataTag(string $dataProvider, string $tag, array $attr, bool $close = false)
    {
        $code = sprintf(
            '<?php $__template__tag__block__ = %s(\'%s\', %s, %s); ',
            $dataProvider,
            $tag,
            static::stringifyAttrs($attr),
            $close ? 'true' : 'false'
        );
        if ($close) {
            return $code . 'echo is_array($__template__tag__block__) ? \'Array\' : '.
                '(string) $__template__tag__block__; unset($__template__tag__block__);?>';
        }
        $code .= sprintf(
                'foreach((array) $__template__tag__block__ as $%s=>$%s) { ',
                isset($attr['key']) ? (string) $attr['key'] : 'key',
                isset($attr['field']) ? (string) $attr['field'] : 'field'
            ) . ' ?>';
        return [$code, '<?php } unset($__template__tag__block__);?>'];
    }

    /**
     * 转换模版标签为 Tag 对象
     * @param string $tag
     * @param array $attr
     * @param bool $closed
     * @return Tag
     */
    public static function makeCompiledTag(string $tag, array $attr, bool $closed = false)
    {
        if (isset($attr['__value__'])) {
            $value = $attr['__value__'];
            unset($attr['__value__']);
        } else {
            $value = null;
        }
        return new Tag($tag, $value, $attr, $closed);
    }

    /**
     * 解析 tag attributes 属性，返回数组
     * @param string $str
     * @return array
     */
    public static function parseAttribute(string $str)
    {
        $i = 0;
        $status = 0;
        $quote = null;
        $name = $value = '';
        $len = strlen($str);
        $spaceChar = " \0\t\r\n\x0B";
        $defaults = [];
        $attributes = [];
        while ($i < $len) {
            $whitespace = strspn($str, $spaceChar, $i);
            if ($whitespace) {
                $i += $whitespace;
                // 跳过空白后, 已到结尾
                if ($i >= $len) {
                    break;
                }
            }
            // 还有字符, 循环继续
            $ended = false;
            switch ($status) {
                // name 开始
                case 0:
                    $status = 1;
                    $name = $str[$i++];
                    break;
                // 收集 name
                case 1:
                    $next = $str[$i++];
                    if ('=' === $next) {
                        // attr name 获取成功, 准备获取 value
                        $status = 2;
                        // 先去除 = 后的空白符, 可能跳过空白后, 已到结尾 {name =   }
                        if ($space = strspn($str, $spaceChar, $i)) {
                            $i += $space;
                        }
                        // = 字符后结束了
                        if ($i >= $len) {
                            $ended = true;
                            break;
                        }
                        // 获取 = 号后第一个非空白字符 {name = "sss" next =  value  }, 看 value 是否被引号包裹
                        $next = $str[$i++];
                        if ('"' === $next || "'" === $next) {
                            $value = '';
                            $quote = $next;
                        } else {
                            $value = $next;
                            $quote = null;
                        }
                    } else if($whitespace) {
                        // 无属性值 attr, 直接缓存, 开始下一个 name {name next=}
                        if ($attributes) {
                            $attributes[$name] = true;
                        } else {
                            $defaults[] = $name;
                        }
                        $name = $next;
                    } else {
                        $name .= $next;
                    }
                    break;
                // 收集 value
                case 2:
                    if ($whitespace) {
                        // value 没有引号包裹, 碰到空白就认为 value 结束 {name = xx }
                        if (null === $quote) {
                            $status = 0;
                            $attributes[$name] = $value;
                            $name = $value = '';
                            break;
                        }
                        // value 被引号包裹, 出现空白符, {name = "   }
                        $value .= substr($str, $i - $whitespace, $whitespace);
                    }
                    $next = $str[$i++];
                    if ($quote && $next === $quote) {
                        $status = 0;
                        $attributes[$name] = $value;
                        $name = $value = '';
                    } else {
                        $value .= $next;
                    }
                    break;
            }
            if ($ended) {
                break;
            }
        }
        // 最后一个属性
        if ('' !== $name) {
            if (2 === $status) {
                $attributes[$name] = $quote .$value;
            } elseif ($attributes) {
                $attributes[$name] = true;
            } else {
                $defaults[] = $name;
            }
        }
        // value 属性
        if ($defaults) {
            $attributes = array_merge(['__value__' => implode(' ', $defaults)], $attributes);
        }
        return $attributes;
    }

    /**
     * 数组转字符串 类似于 `var_export($arr, true)` 功能
     * @param array $attrs
     * @return string
     */
    public static function stringifyAttrs(array $attrs)
    {
        $str = '';
        foreach ($attrs as $key => $val) {
            $val = static::compileVariable($val);
            $str .= "\n'$key' => $val,";
        }
        return '' === $str ? '[]' : '['.$str."\n".']';
    }

    /**
     * 将表达式转为合法的 php 变量形式
     * @param string $expression
     * @return string
     */
    public static function compileVariable(string $expression)
    {
        // 数字
        if (is_numeric($expression)) {
            return $expression;
        }
        // 特意使用引号包裹 认为是字符串
        if (static::hasQuote($expression)) {
            return "'".static::addsLashes(substr($expression, 1, -1))."'";
        }
        // php 表达式
        if (static::isExpression($expression)) {
            return static::compileDotVars($expression);
        }
        // 字符串
        return "'".static::addsLashes($expression)."'";
    }

    /**
     * 格式化变量标签  提取 $code 中的 arr[key] arr.key 格式转为 php 语法
     * 1. 修正数组格式: $arr[key] -> $arr['key']
     * 2. 新增数组格式: $arr.key  -> $arr['key']
     * 3. 新增属性格式: $obj:var  -> $obj->var
     * 4. 仍支持原格式: $arr['key'] / $obj->var
     * @param string $code
     * @return string
     */
    public static function compileDotVars(string $code)
    {
        $pattern = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\$.\[\]:\x7f-\xff]*)/';
        $code = preg_replace_callback($pattern, function ($matches) {
            // $arr[key] -> $arr['key']
            $vars = preg_replace_callback('/\[([a-zA-Z0-9_\x7f-\xff]+)]/', function($m) {
                if (!preg_match('/^\d*$/', $m[1])) {
                    $m[1] = "'".$m[1]."'";
                }
                return '['.$m[1].']';
            }, $matches[1]);

            // $arr.key -> $arr['key']
            $vars = explode('.', $vars);
            $var = '$'.str_replace(':', '->', array_shift($vars));
            foreach ($vars as $k) {
                $obj = strpos($k, ':') ? explode(':', $k) : null;
                $key = null === $obj ? $k : array_shift($obj);
                $originalArr = null;

                // $arr.key[4] -> $arr['key'][4]
                if ($len = strpos($key, '[')) {
                    $originalArr = substr($key, $len);
                    $key = substr($key, 0, $len);
                }
                $var .= '[' . ('$' === $key[0] || preg_match('/^\d*$/', $key) ? $key : "'$key'") .']';
                if ($originalArr) {
                    $var .= $originalArr;
                }

                // $arr.key:var -> $arr['key']->var
                if ($obj) {
                    foreach ($obj as $o) {
                        $var .= '->'.$o;
                    }
                }
            }
            return $var;
        }, $code);
        return trim($code);
    }

    /**
     * 是否为合法的 php 变量名称
     * @param string $var
     * @return int
     */
    public static function isVar(string $var)
    {
        return preg_match('/^\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $var);
    }

    /**
     * 是否为 php 表达式, 并不是特别严谨, 但足以应对大部分情况了
     * @param string $code
     * @return bool
     */
    public static function isExpression(string $code)
    {
        $len = strlen($code);
        // "$" 开头， 如 $a
        if ($len > 1 && '$' === $code[0]) {
            return true;
        }
        // "($" 开头, 如 ($a + 1)
        if ($len > 3 && '($' === substr($code, 0, 2)) {
            return true;
        }
        // 函数格式
        return preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\((.+?)$/', $code) && ')' === substr($code, -1);
    }

    /**
     * 转义字符串
     * @param string $str
     * @return string
     */
    public static function addsLashes(string $str)
    {
        return str_replace('\'', '\\\'', str_replace('\\', '\\\\', $str));
    }

    /**
     * 是否是引号包裹的字符串
     * @param string $val
     * @return bool
     */
    public static function hasQuote(string $val)
    {
        return strlen($val) > 1 && (
                ('"' === substr($val, 0, 1) && '"' === substr($val, -1)) ||
                ("'" === substr($val, 0, 1) && "'" === substr($val, -1))
            );
    }

    /**
     * 数组按照 value 长度排序
     * @param string $a
     * @param string $b
     * @return int
     */
    public static function sortByLength(string $a, string $b)
    {
        return strlen($b) - strlen($a);
    }
}
