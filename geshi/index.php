<?php

include_once 'geshi.php';

$result = '';

if( isset($_POST['source']) )
{
	$source = $_POST['source'];
	$geshi = new GeSHi($source);
	$geshi->set_language('php');
	$geshi->enable_classes();
	$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
	$result = $geshi->parse_code();
}

$geshi = new GeSHi();
$geshi->set_language('php');
// note: the false argument is required for stylesheet generators, see API documentation
$css = $geshi->get_stylesheet(false);
echo $css;

?>

<html>

<head>
<style>
<?php
	if( isset($geshi) ) echo $geshi->get_stylesheet();
?>
</style>
</head>

<body>

<form action="index.php" method="post">

Source<br>
<textarea style="width:800px;height:200px;" name="source"><?php if( isset($_POST['source']) ) echo $_POST['source']; ?></textarea>
<br>

Résultat<br>
<textarea style="width:800px;height:200px;"><?php echo $result; ?></textarea>
<br>

HTML<br>
<div style="border:1px solid black;padding:10px;width:800px;">
<?php echo $result;  ?>
</div>
<br><br>
<input type="submit" />

</form>

</body>

</html>