<?php
/***************************************************************************
*                        page_header.php
*                        -------------------
*   begin                : Dimanche, 21 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

if( !defined('IN') ) die('Hacking attempt');
if( !class_exists('Template') ) return;

include($root_path.'includes/head.php');

$visitor = true;
if( isset($session) ) $visitor = $session->user->isVisitor();
if( !defined('PAGE') ) define('PAGE', pathinfo($_SERVER['REQUEST_URI'], PATHINFO_FILENAME));
$header = new Template('header');

$links = array(
	array('name' => 'add', 'href' => realurl('add.php'), 'icon' => 'plus', 'lang' => $lang['add_code']),
	array('name' => 'register', 'href' => realurl('register.php'), 'icon' => 'gear', 'lang' => $lang['register']),
	array('name' => 'login', 'href' => realurl('login.php'), 'icon' => 'forward', 'lang' => $lang['login'])
);

if( $visitor )
{
	
}
else
{
	$username = $session->user->get('name');
	$links[1] = array('name' => 'profil', 'icon' => 'gear', 'href' => realurl('user/'.mb_strtolower($username)), 'lang' => $username);
	$links[2] = array('name' => 'logout', 'icon' => 'delete', 'href' => realurl('index.php?logout=1'), 'lang' => $lang['logout']);
	
	if( $session->user->isAdmin() || $session->user->isModerator() )
	{
		$count = DB::count('comment', 'WHERE visible = 0');
		$header->addRow('moderate', array('name' => 'commentaire', 'href' => realurl('comment.php?moderate=true'), 'text' => ucfirst(agree('comment', $count)), 'count' => $count));
		$count = DB::count('code', 'WHERE visible = 0');
		$header->addRow('moderate', array('name' => 'code', 'href' => realurl('add.php?moderate=true'), 'text' => ucfirst(agree('code', $count)), 'count' => $count));	
	}
}

$head_js = str_replace(
	array('{LANG}','{COOKIE}'),
	array(js_encode($lang['js']), js_encode($config['cookie'])),
	"<script type=\"text/javascript\">lang = {LANG};\ncookie = {COOKIE};</script>"
);
if( $config['analytics'] )
{
	$head_js.= '<script type="text/javascript">
		var _gaq = _gaq || [];
		_gaq.push(["_setAccount", "'.$config['analytics'].'"]);
		_gaq.push(["_trackPageview"]);
		(function(){
			var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true;
			ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js";
			var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);
		})()
	</script>';
}

$metas = array(
	'charset' => $config['encoding'],
	// 'language' => 'fr', // page en plusieurs langues: fr,en page qui utilise plusieurs langues: fr-en
	'description' => $lang['description'],
	'keywords' => 'code, source, php, javascript, ressource, script',
	'robots' => isset($config['pages'][PAGE]['robot']) ? $config['pages'][PAGE]['robot'] : 'all',
	'viewport' => 'width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=1;' // pour les portables
);

// si on est en local ceci évite la mise en cache qui est pénible
if( $config['local'] )
{
	$metas['cache-control'] = 'no-cache, must-revalidate';
	$metas['expires'] = 0;
	$metas['pragma'] = 'no-cache';
}

if( isset($session) )
{
	if( defined('IS_LOGOUT') )
	{
		$message = $lang['logout_success'];
	}
	else if( $message = $session->user->get('message') )
	{
		DB::update('user','SET message = null WHERE id = ?', $session->user->get('id'));
	}
}

if( PAGE == 'index' )
{
	$header->set('nav.0', array('text' => $lang['description']));
}
else
{
	$header->set('nav.0', array('href' => server_path(), 'text' => $lang['home']));
	$header->set('title', $lang[ucfirst(PAGE)]);
}
if( $message ) $header->set('message', $message);

$header->set(array(
	'links' => $links,
	'meta' => $metas,
	'css' => $config['css'],
	'js' => $config['js'],
	
	'sitename' => $lang['Site'],
	'HEAD_JS' => $head_js,
	
	'LANG' => LANG, // language de la page
	'ROOT_PATH' => $root_path,
	
	'L_HELLO' => $lang['Hello'],
	'HELLO_WHO' => $hello_who,
	'HELLO_WHAT' => $hello_what,
	
	'L_NOSCRIPT' => $lang['Noscript'],
	'L_NOT_SUPPORTED' => $lang['Not_supported'],
	'L_OLDVERSION' => $lang['Old_version'],
	'L_UNDER_CONSTRUCT' => $config['under_construct'] ? $lang['Under_construct'] : '',
	'L_OR' => $lang['Or'],
	
	'SEARCH' => $_REQUEST['search'],
	'L_SEARCH' => $lang['search'],
	'SEARCH_ACTION' => realurl('search.php')
));

$page = new Template();

?>