<?php

define('HEADER_INC', TRUE);
$do_gzip_compress = FALSE;

if( $config['gzip_compress'] )
{
	$useragent = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : getenv('HTTP_USER_AGENT');
	
	if( strstr($useragent,'compatible') || strstr($useragent,'Gecko') )
	{
		if( extension_loaded('zlib') )
		{
			ob_start('ob_gzhandler');
		}
	}
	else
	{
		if( strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') )
		{
			if( extension_loaded('zlib') )
			{			
				$do_gzip_compress = TRUE;
				ob_start();
				ob_implicit_flush(0);
				header('Content-Encoding: gzip');
			}
		}
	}
}

?>