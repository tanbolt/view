<?php
namespace Tanbolt\View;

/**
 * Interface ViewInterface
 * @package Tanbolt\View
 */
interface ViewInterface
{
    /**
     * 设置缓存目录
     * @param ?string $storage
     * @return static
     */
    public function setStorage(?string $storage);

    /**
     * 设置模板文件的路径
     * @param string $template
     * @return static
     */
    public function setTemplate(string $template);

    /**
     * 编译模版并返回编译后的缓存路径
     * @return string
     */
    public function getCompiled();

    /**
     * 设置/替换 当前模版可用变量
     * @param string|array $var 可使用数组同时设置多个
     * @param mixed $val
     * @return static
     */
    public function putVar($var, $val = null);

    /**
     * 判断当前模版是否已设置 $key 变量
     * @param string $key
     * @return bool
     */
    public function hasVar(string $key);

    /**
     * 获取当前模版中 $var 变量的值
     * @param string|array $var 可使用数组作为参数同时获取多个
     * @param mixed $default
     * @return mixed
     */
    public function getVar($var, $default = null);

    /**
     * 移除指定的模版变量
     * @param string|array $var 可使用数组作为参数同时移除多个
     * @return static
     */
    public function removeVar($var);

    /**
     * 获取当前模版所有可用的变量
     * @return array
     */
    public function allVars();

    /**
     * 清空当前模版已设置变量
     * @return static
     */
    public function clearVars();

    /**
     * 同时设置 模版路径 和 模版可用变量
     * @param string $template
     * @param array $vars
     * @return static
     */
    public function display(string $template, array $vars = []);

    /**
     * 根据设置获取渲染结果
     * @return string
     */
    public function render();
}
