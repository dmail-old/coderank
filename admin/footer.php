<?php

$footer = new Template('admin/footer');

if( isset($session) && $session->user->isAdmin() )
{
	$l_footer = 'PHP: '.phpversion();
	if( class_exists('DB') )
	{
		$l_footer.= ' - MYSQL: '.DB::getVersion();
		$l_footer.= ' - Requêtes: <a href="javascript:logsql()">'.DB::$counter.'</a>';
		$l_footer.= ' - Temps d\'execution : '.round(microtime(true) - $begin_time, 2).' secondes';
		$l_footer.= '<script>function logsql(){ Array.each(req, function(part){ console.log(part);}); }; req = '.js_encode(DB::$history).';</script>';
		$footer->set('footer', $l_footer);
	}
}

if( isset($header) )
{
	if( !$header->tpl ) $header->execute();
}
if( isset($page) )
{
	if( !$page->name ) $page->setName('admin/'.PAGE);
	$page->execute();
}
$footer->execute();

if( class_exists('DB') )
{
	DB::close();
}

exit;

?>