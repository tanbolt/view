<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="<?php echo $page['charset'];?>">
    <title><?php echo $page['title'];?></title>
</head>
<body>
<h1><?php 
$__parent = [];
$parent = [
'name' => 'head',
'title' => $page['title'],
];
 echo $parent['name']; echo $parent['title'];?>-<?php echo $page['title']; 
unset($parent, $__parent);
?></h1>

<?php echo $page['title']; echo $page['title']; echo $page['name'];?>{$page.name}

<?php echo $person->name;?> - <?php echo $person->height;?>

<br/>
<?php echo add($person->height,10); echo sub(add($person->height,20),10);?>

<pre>
    <?php if ($person->height > 180) { ?>
    tall
    <?php } elseif ($person->height > 170) { ?>
    normal
    <?php } else { ?>
    low
    <?php }?>
</pre>

<pre>
    <?php foreach($page as $k => $v) {  echo $k;?> : <?php echo $v; } ?>
</pre>

<pre>
    <?php for ($j = 0; $j < 10; $j += 1) {  echo $j; } ?>
</pre>

<pre>
    <?php for ($i = 2; $i < 5; $i += 1) {  echo $i; for ($j = 2; $j < 5; $j += 2) {  echo $j; }  } ?>
</pre>


<?php
echo 'foo';
?>

{$}

<?php

$foo = 'bar';
echo $foo;

 
$__parent = [];
$parent = [
'attr' => 'att',
];
 echo $block['name']; echo $block['name']; echo $block['arr']['foo'];?>
-
<?php echo $block['arr']['bar'];?>

<br/>

<?php $__template__tag__block__ = Tanbolt\View\View::instance()->callDataTag('foobar', [
'foo' => 'foo1',
'attr' => $parent['attr'],
], false); foreach((array) $__template__tag__block__ as $key=>$field) {   echo $key;?> : <?php echo $field; } unset($__template__tag__block__);?>

<br/>

<?php $__template__tag__block__ = Tanbolt\View\View::instance()->callDataTag('foobar', [
'key' => 'k',
'field' => 'f',
], false); foreach((array) $__template__tag__block__ as $k=>$f) {   echo $k;?> : <?php echo $f; } unset($__template__tag__block__);?>

<br/>


<?php $__template__tag__block__ = Tanbolt\View\View::instance()->callDataTag('foobar', [
'foo' => 'foo1',
], false); foreach((array) $__template__tag__block__ as $key=>$field) {  ?>
    _ <?php echo $key; $__template__tag__block__ = Tanbolt\View\View::instance()->callDataTag('foobar', [
'key' => 'k',
'field' => 'f',
], false); foreach((array) $__template__tag__block__ as $k=>$f) {   echo $k;?> : <?php echo $f; } unset($__template__tag__block__);?>
    _ <?php echo $field; } unset($__template__tag__block__);?>

<br/>
<?php $__template__tag__block__ = Tanbolt\View\View::instance()->callDataTag('foobar', [
'foo' => 'foo1',
'bar' => 'bar1',
], true); echo is_array($__template__tag__block__) ? 'Array' : (string) $__template__tag__block__; unset($__template__tag__block__); $__template__tag__block__ = Tanbolt\View\View::instance()->callDataTag('nothing', [
'foo' => 'foo',
], true); echo is_array($__template__tag__block__) ? 'Array' : (string) $__template__tag__block__; unset($__template__tag__block__); 
unset($parent, $__parent);
?>



<script type="text/JavaScript">
    var js='{$js}';
</script>


</body>
</html>