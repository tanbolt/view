<?php
namespace Tanbolt\View;

use Throwable;
use ArrayAccess;
use Tanbolt\View\Exception\ViewException;

/**
 * Class View: 模版视图管理类
 * @package Tanbolt\View
 */
class View implements ViewInterface, ArrayAccess
{
    /**
     * 当前 View 实例
     * @var $this
     */
    private static $instance;

    /**
     * 模板引擎对象
     * @var Engine
     */
    private $engine;

    /**
     * 编译前的监听回调函数
     * @var ?callable
     */
    private $compiledListener = null;

    /**
     * 编译后缓存模版的回调函数
     * @var ?callable
     */
    private $storageListener = null;

    /**
     * 渲染之前的监听函数
     * @var callable[]
     */
    private $beforeRenderListener = [];

    /**
     * 渲染之后的监听函数
     * @var callable[]
     */
    private $afterRenderListener = [];

    /**
     * 模版渲染时 数据标签 的 解析函数
     * @var ?callable
     */
    private $dataProvider = null;

    /**
     * 模版源文件根目录
     * @var ?string
     */
    private $homedir = null;

    /**
     * 解析后模板缓存目录
     * @var ?string
     */
    private $storage = null;

    /**
     * 模板缓存的目录层深
     * @var int
     */
    private $depth = 2;

    /**
     * 检测模板是否变化的间隔时长
     * @var int
     */
    private $validateFreq = 0;

    /**
     * 是否压缩编译内容
     * @var bool
     */
    private $compressCompiled = false;

    /**
     * 是否缓存编译内容
     * @var bool
     */
    private $cachedCompiled = null;

    /**
     * 记录锁定时的 初始化配置
     * @var array
     */
    private $bootConfig = null;

    /**
     * 模板路径(字符串)
     * @var string
     */
    private $template;

    /**
     * 字符串模板虚拟目录
     * @var string|null|false
     */
    private $templateVirtual;

    /**
     * 编译后模板路径
     * @var string
     */
    private $compilePath;

    /**
     * 赋值集合
     * @var array
     */
    private $data = [];

    /**
     * 创建 View 对象
     */
    public function __construct()
    {
        static::$instance = $this;
    }

    /**
     * 获取已创建的 View 静态实例
     * @return static
     */
    public static function instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * 获取模板引擎对象
     * @return Engine
     */
    public function engine()
    {
        if (!$this->engine) {
            $this->engine = (new Engine(__CLASS__.'::instance()->callDataTag'))->setHomedir($this->homedir);
            if ($this->compiledListener) {
                call_user_func($this->compiledListener, $this->engine);
            }
        }
        return $this->engine;
    }

    /**
     * 模版中 tag 标签的回调执行函数，为配合模版而暴漏的接口，一般不应手动调用
     * @param string $tagName tag 名称
     * @param array $attributes tag 参数
     * @param bool $closed 是否为自闭合 tag
     * @return array|mixed|string
     */
    public function callDataTag(string $tagName, array $attributes, bool $closed)
    {
        if ($this->dataProvider) {
            return call_user_func($this->dataProvider, $tagName, $attributes, $closed);
        }
        return $closed ? '' : [];
    }

    /**
     * 设置编译前的监听回调函数, 如有需要, 可对 engine 进行自定义设置。回调:
     * - callback(Engine $engine)
     * @param ?callable $listener
     * @return $this
     */
    public function setCompiledListener(?callable $listener)
    {
        if ($listener !== $this->compiledListener) {
            if ($this->engine) {
                $this->engine = null;
            }
            $this->compiledListener = $listener;
        }
        return $this;
    }

    /**
     * 获取编译前的监听回调函数
     * @return ?callable
     */
    public function getCompiledListener()
    {
        return $this->compiledListener;
    }

    /**
     * 设置编译后缓存模版的回调函数 (如果缓存的话)，回调:
     * - callback(string $filePath, string $fileContent)
     * @param ?callable $listener
     * @return $this
     */
    public function setStorageListener(?callable $listener)
    {
        $this->storageListener = $listener;
        return $this;
    }

    /**
     * 获取编译后缓存模版的回调函数
     * @return ?callable
     */
    public function getStorageListener()
    {
        return $this->storageListener;
    }

    /**
     * 添加一个渲染前的监听函数, 比如可通过 setVar 注入模版变量，回调:
     * - callable(View $view)
     * @param callable $listener
     * @return $this
     */
    public function addBeforeRender(callable $listener)
    {
        if (!in_array($listener, $this->beforeRenderListener)) {
            $this->beforeRenderListener[] = $listener;
        }
        return $this;
    }

    /**
     * 移除一个渲染前监听函数
     * @param callable $listener
     * @return $this
     */
    public function offBeforeRender(callable $listener)
    {
        $key = array_search($listener, $this->beforeRenderListener);
        if (false !== $key) {
            unset($this->beforeRenderListener[$key]);
            $this->beforeRenderListener = array_values($this->beforeRenderListener);
        }
        return $this;
    }

    /**
     * 添加一个渲染后的监听函数, 可对最终渲染结果进行再次修改，回调:
     * - callable(String $content): string, 需返回处理后的 $content
     * @param callable $listener
     * @return $this
     */
    public function addAfterRender(callable $listener)
    {
        if (!in_array($listener, $this->afterRenderListener)) {
            $this->afterRenderListener[] = $listener;
        }
        return $this;
    }

    /**
     * 移除一个渲染后监听函数
     * @param callable $listener
     * @return $this
     */
    public function offAfterRender(callable $listener)
    {
        $key = array_search($listener, $this->afterRenderListener);
        if (false !== $key) {
            unset($this->afterRenderListener[$key]);
            $this->afterRenderListener = array_values($this->afterRenderListener);
        }
        return $this;
    }

    /**
     * 设置模版渲染时 数据标签 的 解析函数，回调:
     * - callback(string $tagName, array $attributes, bool $closed)
     * @param ?callable $provider
     * @return $this
     */
    public function setDataProvider(?callable $provider)
    {
        $this->dataProvider = $provider;
        return $this;
    }

    /**
     * 获取模版渲染时 数据标签 的 解析函数
     * @return ?callable
     */
    public function getDataProvider()
    {
        return $this->dataProvider;
    }

    /**
     * 设置模版源文件根目录
     * @param ?string $dir
     * @return $this
     */
    public function setHomedir(?string $dir)
    {
        if ($dir === $this->homedir) {
            return $this;
        }
        if ($dir && false === ($dir = realpath($dir))) {
            throw new ViewException('Home dir not exist');
        }
        $this->homedir = $dir;
        if ($this->engine) {
            $this->engine->setHomedir($dir);
        }
        return $this;
    }

    /**
     * 获取模版源文件根目录
     * @return string
     */
    public function getHomedir()
    {
        return $this->homedir;
    }

    /**
     * 设置编译后模版的缓存目录
     * @param ?string $storage
     * @return $this
     */
    public function setStorage(?string $storage)
    {
        if ($storage === $this->storage) {
            return $this;
        }
        if ($storage) {
            if(!is_dir($storage) && false === @mkdir($storage, 0777, true)) {
                throw new ViewException(sprintf('Unable to create template cache directory [%s]', $storage));
            }
            $storage = realpath($storage);
            if (null === $this->cachedCompiled) {
                $this->cachedCompiled = true;
            }
        }
        $this->storage = $storage;
        return $this;
    }

    /**
     * 获取编译后模版的缓存目录
     * @return ?string
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * 设置编译后模版保存的目录层深
     * @param int $depth
     * @return $this
     */
    public function setDepth(int $depth)
    {
        $this->depth = $depth;
        return $this;
    }

    /**
     * 获取编译后模版保存目录层深
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * 设置模版源文件变动检测间隔秒数。
     * - -2: 永不检查且总是更新,
     * - -1: 永不检查且不更新,
     * -  0: 则每次都检查,
     * - >0: 间隔秒数,
     * @param int $second
     * @return $this
     */
    public function setValidateFreq(int $second)
    {
        $this->validateFreq = $second;
        return $this;
    }

    /**
     * 获取模版源文件变动检测间隔秒数
     * @return int
     */
    public function getValidateFreq()
    {
        return $this->validateFreq;
    }

    /**
     * 设置是否对编译后模版进行压缩
     * @param bool $compress
     * @return $this
     */
    public function setCompress(bool $compress = true)
    {
        $this->compressCompiled = $compress;
        return $this;
    }

    /**
     * 当前是否会压编译后的模版
     * @return bool
     */
    public function isCompressed()
    {
        return $this->compressCompiled;
    }

    /**
     * 设置是否缓存编译后模版
     * @param bool $cache
     * @return $this
     */
    public function setCached(bool $cache = true)
    {
        $this->cachedCompiled = $cache;
        return $this;
    }

    /**
     * 当前是否缓存编译后模版
     * @return bool
     */
    public function isCached()
    {
        return (bool) $this->cachedCompiled;
    }

    /**
     * 锁定当前初始化配置, 以便后期一键还原
     * @return $this
     */
    public function lockConfig()
    {
        $config = [];
        $fields = [
            'compiledListener', 'storageListener', 'beforeRenderListener', 'afterRenderListener', 'dataProvider',
            'homedir', 'storage', 'depth', 'validateFreq', 'compressCompiled', 'cachedCompiled',
        ];
        foreach ($fields as $field) {
            $config[$field] = $this->{$field};
        }
        $this->bootConfig = $config;
        return $this;
    }

    /**
     * 一键还原 lockConfig() 锁定的初始化配置
     * @return $this
     */
    public function revertConfig()
    {
        if (!$this->bootConfig) {
            return $this;
        }
        $config = $this->bootConfig;
        // storage 需要在 cachedCompiled 重置后设置
        $storage = $config['storage'];
        // compiledListener/homedir 跟 engine 有关联, 不能直接重置
        $this->setCompiledListener($config['compiledListener'])
            ->setHomedir($config['homedir']);
        unset($config['storage'], $config['compiledListener'], $config['homedir']);
        foreach ($config as $field => $value) {
            $this->{$field} = $value;
        }
        return $this->setStorage($storage);
    }

    /**
     * @inheritdoc
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;
        $this->compilePath = null;
        // false 为路径模版,  null|string 意味着字符串模版
        $this->templateVirtual = false;
        return $this;
    }

    /**
     * 设置字符串模板
     * @param string $string
     * @param ?string $virtual 可为当前模板指定一个虚拟目录, 方便解析其包含的子模板
     * @return $this
     */
    public function setStringTemplate(string $string, string $virtual = null)
    {
        $this->compilePath = null;
        $this->template = $string;
        if (empty($virtual)) {
            $this->templateVirtual = null;
            if (count($virtual = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))) {
                $this->templateVirtual = dirname($virtual[0]['file']);
            }
        } else {
            $this->templateVirtual = $virtual;
        }
        return $this;
    }

    /**
     * 当前是否为 字符串模版
     * @return bool
     */
    public function isStringTemplate()
    {
        return false !== $this->templateVirtual;
    }

    /**
     * 获取 模板路径 或 字符串模板
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * 如果是字符串模版，获取字符串模板 虚拟目录
     * @return string
     */
    public function getTemplateVirtual()
    {
        return $this->templateVirtual ?: null;
    }

    /**
     * 检测当前所设置模板(或字符串模版)缓存是否过期
     * @param bool $checkCompress
     * @return bool
     */
    public function isExpired(bool $checkCompress = true)
    {
        $freq = $this->getValidateFreq();
        if (-2 === $freq) {
            return true;
        }
        $cacheFile = $this->getCompiledPath();
        if (null === $cacheFile || !is_file($cacheFile)) {
            return true;
        }
        if (-1 === $freq) {
            return false;
        }
        if ($freq && filemtime($cacheFile) + $freq > time()) {
            return false;
        }
        if (!$handle = @fopen($cacheFile, "r")) {
            return true;
        }
        if (!preg_match('/\/\*(.+?)\*\//', fgets($handle), $matches) || !is_array($cache = @unserialize($matches[1]))) {
            fclose($handle);
            return true;
        }
        if ($checkCompress && (!isset($cache['__compress__']) || (bool) $cache['__compress__'] !== $this->isCompressed())) {
            fclose($handle);
            return true;
        }
        unset($cache['__compress__']);
        foreach ($cache as $path => $hash) {
            $path = ($this->homedir ?: '') . $path;
            if (!is_file($path) || md5_file($path) !== $hash) {
                fclose($handle);
                return true;
            }
        }
        if ($freq) {
            touch($cacheFile);
        }
        fclose($handle);
        return false;
    }

    /**
     * 计算当前设置模板(字符串)的编译后的缓存路径, 此时不会进行编译
     * @return string
     */
    public function getCompiledPath()
    {
        $storage = $this->getStorage() ?: sys_get_temp_dir();
        if (!$storage || !$this->template) {
            return null;
        }
        if (null === $this->compilePath) {
            $md5 = md5($this->template);
            $path = $this->storage . DIRECTORY_SEPARATOR;
            if ($this->depth > 0) {
                for ($i = 0; $i < $this->depth; $i++) {
                    $path .= substr($md5, $i * 2 ,2) . DIRECTORY_SEPARATOR;
                }
            }
            $this->compilePath = $path . $md5;
        }
        return $this->compilePath;
    }

    /**
     * 获取当前设置模版编译后的代码
     * @return string
     */
    public function getCompiledCode()
    {
        $template = $this->getTemplate();
        if (!$template) {
            throw new ViewException('Template is not set');
        }
        if ($this->isStringTemplate()) {
            $compile = $this->engine()->compileCode($template,  $this->getTemplateVirtual(), $this->isCompressed());
        } else {
            $compile = $this->engine()->compile($template, $this->isCompressed());
        }
        return $compile;
    }

    /**
     * @inheritdoc
     */
    public function getCompiled()
    {
        $path = $this->getCompiledPath();
        if ($path && $this->isExpired()) {
            $this->writeCache($path, $this->getCompiledCode());
        }
        return $path;
    }

    /**
     * 将编译后模版内容写入缓存文件
     * @param string $file
     * @param string $content
     * @return int
     */
    private function writeCache(string $file, string $content)
    {
        if ($this->depth && !is_dir($dir = dirname($file)) && false === @mkdir($dir, 0777, true)) {
            throw new ViewException(sprintf('Unable to create template directory [%s]', $dir));
        }
        if (false === ($size = file_put_contents($file, $content))) {
            throw new ViewException(sprintf('Failed to write template cache [%s]', $file));
        }
        if ($this->storageListener) {
            call_user_func($this->storageListener, $file, $content);
        }
        return $size;
    }

    /**
     * @inheritdoc
     */
    public function putVar($var, $val = null)
    {
        if (is_array($var)) {
            $this->data = array_merge($this->data, $var);
        } else {
            $this->data[(string) $var] = $val;
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasVar(string $key)
    {
        return isset($this->data[$key]);
    }

    /**
     * @inheritdoc
     */
    public function getVar($var, $default = null)
    {
        if (is_array($var)) {
            $data = [];
            foreach ($var as $key) {
                $data[$key] = $this->data[$key] ?? $default;
            }
            return $data;
        }
        return $this->data[$var] ?? $default;
    }

    /**
     * @inheritdoc
     */
    public function removeVar($var)
    {
        if (is_array($var)) {
            foreach ($this->data as $key) {
                unset($this->data[$key]);
            }
        } else {
            unset($this->data[$var]);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function allVars()
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function clearVars()
    {
        $this->data = [];
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function display(string $template, array $vars = [])
    {
        return $this->setTemplate($template)->clearVars()->putVar($vars);
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $this->triggerBeforeRender();
        if ($this->isCached()) {
            $render = static::renderContent($this->getCompiled(), $this->allVars());
        } else {
            $render = static::renderContent($this->getCompiledCode(), $this->allVars(), true);
        }
        return $this->getRender($render);
    }

    /**
     * 触发渲染前回调
     * @return $this
     */
    private function triggerBeforeRender()
    {
        foreach ($this->beforeRenderListener as $listener) {
            call_user_func($listener, $this);
        }
        return $this;
    }

    /**
     * 触发渲染后回调
     * @param string $content
     * @return string
     */
    private function getRender(string $content)
    {
        foreach ($this->afterRenderListener as $listener) {
            $content = (string) call_user_func($listener, $content);
        }
        return $content;
    }

    /**
     * 通过 php 模版 和 变量设置 组合，得到渲染结果
     * @param string $template
     * @param array $vars
     * @param bool $stringTemplate
     * @return string
     */
    public static function renderContent(string $template, array $vars = [], bool $stringTemplate = false)
    {
        extract($vars);
        $obLevel = ob_get_level();
        ob_start();
        try {
            if ($stringTemplate) {
                unset($stringTemplate);
                eval('?>' . $template);
            } else {
                unset($stringTemplate);
                include $template;
            }
        } catch (Throwable $e) {
            static::handleException($e, $obLevel);
        }
        return ltrim(ob_get_clean());
    }

    /**
     * 模版中抛出的错误 此处不能 handle 到语法错误
     * 就是说一旦这个函数被调用 肯定是发生了致命错误 可以直接 throw
     * @param $e
     * @param $obLevel
     * @throws
     */
    private static function handleException($e, $obLevel)
    {
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }
        throw $e;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->hasVar($offset);
    }

    /**
     * @param mixed $offset
     * @return array|null
     */
    public function offsetGet($offset)
    {
        if (!$this->hasVar($offset)) {
            $trace = debug_backtrace();
            trigger_error('Undefined index: '.$offset.' in '.$trace[0]['file'].' on line '.$trace[0]['line']);
        }
        return $this->getVar($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->putVar($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->removeVar($offset);
    }

    /**
     * toString
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * 清除本次模版渲染相关配置
     */
    public function __destruct()
    {
        $this->data = [];
        $this->template = $this->compilePath = $this->templateVirtual = null;
        $this->revertConfig();
    }
}
