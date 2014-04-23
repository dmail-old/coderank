<?php
/***************************************************************************
*                        error.php
*                        -------------------
*   begin                : Mercredi, 6 Juillet 2011
*   copyright            : Angelblade
*   email                : Angelblade@hotmail.fr
*
*
***************************************************************************/

class Error
{
	static $errors = array();
	static $count = 0;

	function log($type, $message, $file = null, $line = null)
	{		
		self::$errors[self::$count++] = array(
			'type' => $type,
			'message' => $message,
			'file' => $file,
			'line' => $line
		);
	}
	
	function getLast()
	{
		return self::$count ?  self::$errors[self::$count-1] : false;
	}
	
	function getLastmsg()
	{
		$error = self::last();
		return $error ? $error['message'] : '';
	}
	
	function getLastofType($type = E_USER_ERROR)
	{
		$i = self::$count;
		
		while($i)
		{
			$i--;
			$error = self::$errors[$i];
			if( $error['type'] == $type ) return $error;
		}
		return false;
	}
}

function error_handler($code, $message, $file, $line, $context)
{	
	global $config;
	
	$error_trace = $context['error_trace'];
	
	if( $error_trace )
	{
		$file = $error_trace[0]['file'];
		$line = $error_trace[0]['line'];
	}	
	
	Error::log($code, $message, $file, $line);
	
	$error = '';
	
	// Affiche les éventuelles erreurs sql
	if( defined('DEBUG') && DEBUG && class_exists('DB') )
	{
		$sql_error = DB::error();
		$debug_text = '';
		
		if( $sql_error && $sql_error['message'] != '' )
		{
			$debug_text.= '<br />Erreur SQL : '.$sql_error['code'].' '.utf8_encode($sql_error['message']); // messages sql sont passé en iso
			$debug_text.= '<br />Requête passée: <pre>'.DB::lastQuery().'</pre>';
		}		
		
		$message.= $debug_text;
	}
	
	switch($code)
	{
		case E_ERROR: case E_USER_ERROR:
			$error = '<b>Mon ERREUR</b> ['.$code.'] '.$message.'<br />\n';
			$error.= ' Erreur fatale sur la ligne '.$line.' dans le fichier '.$file;
			$error.= ', PHP '. PHP_VERSION .' ('. PHP_OS .')<br />\n';
			$error.= 'Arrêt...<br />\n';
		break;
		case E_USER_NOTICE:
			$error = '<b>Information</b> ['.$code.'] '.$message.'<br />\n';
		break;
		case E_WARNING: case E_USER_WARNING:
			$shortfile = preg_replace("#.*?coderank(?:\\\|/)#",'', $file);			
			if( defined('DEBUG') && DEBUG ) $error.= '<p><b>Avertissement</b> '.$shortfile.' &bull; ligne '.$line;
			$error.= '<p class="warning">'.$message.'</p>';
			// en mode E_WARNING j'ai pas error trace puisque le warning ne dépend pas de moi
			if( $error_trace[1] ) $error.= 'error come from a call at line '.$error_trace[1]['line'].' in file '.$error_trace[1]['file'];
			// debug(debug_backtrace());
		break;
		default:
			$error = 'Type d\'erreur inconnu : ['.$code.'] '.$message.'<br />\n at line '.$line.' in file '.$file;
			if( $error_trace[1] ) $error.= 'error come from a call at line '.$error_trace[1]['line'].' in file '.$error_trace[1]['file'];
		break;
	}
	
	// Si ce code d'erreur existe dans error_reporting(), on l'affiche et on le log si nécéssaire
	if( error_reporting() & $code )
	{
		if( ini_get('display_errors') )
		{
			if( defined('AJAX') && AJAX )
			{
				header('HTTP/1.1 510 Mon statut perso d\'erreur');
				echo $error;
				exit(1);
			}
			
			if( $code == E_USER_ERROR || $code == E_ERROR )
			{
				error_die($message, $file, $line);
			}
			else
			{
				if( defined('REPORTING') )
				{
					report($error);
				}
				else
				{
					echo $error;
				}
			}
		}
		
		if( ini_get('log_errors') ) error_log($error);
    }
	
	if( $code == E_USER_ERROR || $code == E_ERROR ) exit(1);
	
    /* Ne pas exécuter le gestionnaire interne de PHP */
    return true;
}

set_error_handler('error_handler');
// reporte erreurs php natives (ERROR, PARSE), les erreurs dans les fonctions (WARNING)
// les erreurs perso fatales (USER_ERROR) et les avertissement perso (USER_WARNING), les infos perso (USER_NOTICE)
// error_reporting(E_ERROR | E_PARSE | E_WARNING | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE);
error_reporting(E_ALL ^ E_NOTICE);

function exeption_handler($exeption)
{
	$code = $exeption->getCode();
	$message = $exeption->getMessage();
	$error_trace = $exeption->getTrace();
	
	error($message);
}

set_exception_handler('exeption_handler');

function error_die($error_message, $error_file, $error_line)
{
	global $root_path;
	global $config, $lang;
	global $header, $page, $footer;
	
	$error_title = '';
	
	if( defined('HAS_DIED') )
	{
		// error_die was called multiple times, should not happen
		printr(Error::$errors);
		die();
	}
	define('HAS_DIED', 1);
	
	$error_message.= ' '.$error_file;
	$error_message.= ' ligne '.$error_line;
	
	// si la config n'est pas accessible on est en mode critical_error, on affiche l'erreur au mieux mais le site on peux pas
	if( empty($config) || !class_exists('Template') )
	{
		if( empty($lang) )
		{
			include($root_path.'lang/fr/main.php');
		}
		
		$error_title = $lang['Critical_Error'];
		
		echo '<html><head>';
		echo '<title>'.$lang['Site'].'</title>';
		echo '<link href="'.$root_path.'/favicon.png" type="image/x-icon" rel="shortcut icon"/>';
		echo '<link rel="stylesheet" type="text/css" href="'.$root_path.'css/site.css" /></head>';
		echo '<body><div class="error"><h1>'.$error_title.'</h1><p>'.$error_message.'</p></body></html>';
		return true;
	}
	
	// ajoute le fichier de langue de l'utilisateur
	if( empty($lang) )
	{
		if( !defined(LANG) ) define('LANG', 'fr');
		include($root_path.'lang/'.LANG.'/main.php');
	}
	
	// on affiche le site, le header, le footer etc
	// ATTENTION, cette action peut changer mes variables et tout...
	if( !isset($header) )
	{
		if( !defined('IN_ADMIN') )
		{
			include($root_path.'includes/page_header.php');
		}
		else
		{
			include($root_path.'admin/header.php');
		}
	}
	
	$error_title = $lang['An_error_occured'];
	if( !empty($lang[$error_message]) )
	{
		$error_message = $lang[$error_message];
	}
	
	if( isset($header) )
	{
		$header->addRow('nav', array('text' => $error_title));
		$header->execute();
	}
	
	Template::run('error', array(
		'ERROR_TITLE' => $error_title,
		'ERROR_MSG' => $error_message
	));
	
	if( !isset($footer) )
	{
		if( !defined('IN_ADMIN') )
		{
			include($root_path.'includes/page_footer.php');
		}
		else
		{
			include($root_path.'admin/footer.php');
		}
	}
	
	return true;
}

function fire_error($message, $code)
{
	$error_trace = debug_backtrace();
	
	// la première trace de l'erreur doit contenir l'endroit où elle a été appelé
	// plus profondément on peut remonter à une éventuelle fonction qui a déclenché l'erreur
	if( in_array($error_trace[0]['function'], array('error','warning','notice')) )
	{
		// fire_error a été appelée par une fonction raccourci, pas besoin de garder cette trace en mémoire
		array_splice($error_trace, 0, 2);
	}
	else
	{
		array_splice($error_trace, 0, 1);
	}
	
	return trigger_error($message, $code);
}

function error($message = '')
{
	return fire_error($message, E_USER_ERROR);
}

function warning($message = '')
{
	return fire_error($message, E_USER_WARNING);
}

function notice($message = '')
{
	return fire_error($message, E_USER_NOTICE);
}

?>