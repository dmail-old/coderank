<?php
/***************************************************************************
 *                         common.php
 *                         -------------------
 *   begin                : Mercredi, 12 Octobre, 2011
 *   copyright            : (C) Angelblade
 *   email                : Angelblade@hotmail.fr
 *
 *
 ***************************************************************************/
 
if( !defined('IN') ) die('Hacking attempt');
if( !isset($root_path) ) die('$root_path must be set');

$config = array();
$lang = array();
$begin_time = microtime(true);

require($root_path.'includes/constant.php');
require($root_path.'includes/array.php');
require($root_path.'includes/error_handler.php');
require($root_path.'includes/functions.php');
require($root_path.'includes/session.php');
require($root_path.'includes/db.php');
require($root_path.'includes/template.php');
require($root_path.'includes/url.php');
require($root_path.'includes/rss.php');

if( !$content = file_get_contents($root_path.'config.json') )
{
	error('Impossible de récupérer la configuration');
}
$config = js_decode($content, true);

Template::$path = $root_path.'html';
mb_internal_encoding($config['encoding']); // définit le jeu de caractère pour les fonction mb_
header('Content-type: text/html; charset='.$config['encoding']); // définit le charset

DB::connect($config['db']);
unset($config['db']);

$session = new Session($config['session']);
include($root_path.'lang/'.LANG.'/main.php');

// si cette page est une page d'administration et qu'on est pas admin
if( defined('IN_ADMIN') && !$session->user->isAdmin() )
{
	error($lang['Not_admin']);
}
if( !$session->user->isVisitor() )
{
	if( isset($_GET['logout']) )
	{
		$session->logout();
		define('IS_LOGOUT', true);
	}
}
if( $session->user->isVisitor() )
{
	if( PAGE != 'login' )
	{
		if( isset($_GET['login']) || !isset($config['pages'][PAGE]['accessible']) )
		{
			redirect('login.php?referer='.$_SERVER['REQUEST_URI']);
		}
	}
}

?>