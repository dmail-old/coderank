<?php

if( !defined('IN') ) die('Hacking attempt');
if( !class_exists('Template') ) return;
if( !defined('PAGE') ) define('PAGE', pathinfo($_SERVER['REQUEST_URI'], PATHINFO_FILENAME));

include($root_path.'lang/fr/admin.php');

$header = new Template('admin/header');

$header->set(array(
	'css' => $config['css'],
	'js' => $config['js'],
	'site' => $lang['admin'],
	'meta' => array(
		'charset' => $config['encoding'],
		'language' => 'fr'
	),
	'head_js' => str_replace(
		array('{LANG}','{COOKIE}'),
		array(js_encode($lang['js']), js_encode($config['cookie'])),
		"<script type=\"text/javascript\">lang = {LANG};\ncookie = {COOKIE};</script>"
	)
));
$header->add('css', 'admin');

$page = new Template();

?>