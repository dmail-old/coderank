<?php

/***************************************************************************
*                        error.php
*                        -------------------
*   begin                : Lundi, 15 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

define('IN', true);
$root_path = './';
include($root_path.'common.php');
include($root_path.'lang/fr/error.php');
include($root_path.'includes/page_header.php');

$status = isset($_REQUEST['status']) ? htmlentities($_REQUEST['status']) : '';
$error_title = isset($lang[$status.'_title']) ? $lang[$status.'_title'] : $lang['unknow_title'];
$error_msg = isset($lang[$status.'_msg']) ? $lang[$status.'_msg'] : $lang['unknow_msg'];

$header->bind('title', $error_title);
$header->addRow('nav', array(
	'text' => $error_title
));
$page->bind(array(
	'ERROR_TITLE' => $error_title,
	'ERROR_MSG' => $error_msg
));

include($root_path.'includes/page_footer.php');

?>