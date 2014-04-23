<?php

/***************************************************************************
*                        page_footer.php
*                        -------------------
*   begin                : Dimanche, 21 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

$l_footer = '';//'&copy; Tous droits r&eacute;serv&eacute;s - Angelblade ';

$is_admin = false;
if( isset($session) ) $is_admin = $session->user->isAdmin();

if( LANG == 'fr' ) $country = array('lang' => 'en', 'text' => 'Coderank in english');
else $country = array('lang' => 'fr', 'text' => 'Coderank en français');
$map = array(
	array('href' => realurl('index.php?lang='.$country['lang']), 'text' => $country['text'], 'lang' => $country['lang']),
	array('href' => realurl('privacy.php'), 'text' => $lang['privacy']),
	array('href' => realurl('terms.php'), 'text' => $lang['term_of_use'])
);

if( $is_admin )
{
	$l_footer.= 'PHP: '.phpversion();
	if( class_exists('DB') )
	{
		$l_footer.= ' - MYSQL: '.DB::getVersion();
		$l_footer.= ' - Requêtes: <a href="javascript:logsql()">'.DB::$counter.'</a>';
		$l_footer.= ' - Temps d\'execution : '.round(microtime(true) - $begin_time, 2).' secondes';
		$l_footer.= '<script>function logsql(){ Array.each(req, function(part){ console.log(part);}); }; req = '.js_encode(DB::$history).';</script>';
	}
	
	$map[] = array('href' => realurl('admin/index.php'), 'text' => 'Administration');
	// if( $config['local'] ) $map[] = array('href'=> realurl('ftp/index.php'), 'text' => 'Mettre à jour');
}

$footer = new Template('footer');
$footer->bind('l_footer', $l_footer);
$footer->bind('map', $map);
$footer->bind('links', $header->get('links'));
$footer->bind('moderate', $header->get('moderate'));

if( isset($header) )
{
	if( !$header->tpl ) $header->execute();
}
if( isset($page) )
{
	if( !$page->name ) $page->setName(PAGE);
	$page->execute();
}
$footer->execute();

if( class_exists('DB') )
{
	DB::close();
}

exit;

?>
