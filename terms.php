<?php

/***************************************************************************
*                        terms.php
*                        -------------------
*   begin                : Lundi, 15 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

define('IN', true);
$root_path = './';
include($root_path.'common.php');
include($root_path.'includes/page_header.php');

$title = $lang['term_of_use'];

$header->add('nav', array('text' => $title));
$header->set('title', $title);
$page->set('title', $title);
$page->set('terms', $lang['terms']);

include($root_path.'includes/page_footer.php');

?>