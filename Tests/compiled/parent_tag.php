11
<?php 
$__parent = [];
$parent = [
'att' => 'att',
];
?>
sub template
<?php 
$__parent[] = $parent;
$parent = [];
?>deep template<?php 
$parent = array_pop($__parent);
?>

prue tag

pure:

    this is pure var {$var}


<?php 
unset($parent, $__parent);
?>

22
<?php 
$__parent = [];
$parent = [];
?>parse tag

parse:

    this is parse var <?php echo $var; 
unset($parent, $__parent);
?>

33
<?php 
$__parent = [];
$parent = [
'var' => 'var',
];
?>
inner template

<?php echo $parent['var'];?>__
    template inner
    <?php for ($i = 1; $i > 0; $i -= 1) { ?>
    _<?php echo $i;?>_
    <?php } ?>

    son inner
    <?php 
$__parent[] = $parent;
$parent = [];
?>
son inner template

<?php echo $var; 
$parent = array_pop($__parent);
?>




loop

<?php foreach($x as $y) {  echo $x;?>:<?php echo $y; } ?>



11111


{js xxx /}


<?php for ($i = 0; $i < 5; $i += 1) {  echo $i; } ?>

mmmmm
<?php 
unset($parent, $__parent);
?>

end
