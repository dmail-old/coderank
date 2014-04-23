<?php
/***************************************************************************
 *                             constant.php
 *                             -------------------
 *   begin					: Dimanche 18 Janvier 2009
 *   copyright				: (C) Angelblade
 *   email					: Angelblade@hotmail.fr
 *
 *
 ***************************************************************************/

if( !defined('IN') ) die('Hacking attempt');

// défini le nom de la page courante
$traces = debug_backtrace();
array_shift($traces); // sinon on obtiendrais common.php tout le temps
foreach($traces as $trace)
{
	if( $trace['function'] == 'include' )
	{
		define('PAGE', pathinfo($trace['file'], PATHINFO_FILENAME)); // page qui à appelé common.php
		break;
	}
}

// défini si la page est appelé via ajax ou non
define('AJAX', array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

 // Debugging
define('DEBUG', 1);

// Sessions
define('SESSION_METHOD_COOKIE', 100);
define('SESSION_METHOD_GET', 101);

// Utilisateurs
define('USER_VISITOR', -1);
define('USER_MEMBER', 0);
define('USER_ADMIN', 1);
define('USER_MODERATOR', 2);

// statut utilisateurs
define('USER_OFF', 0);
define('USER_ON', 1);
define('USER_LOCKED', 2);

// Groupes
define('DESIGNER', 1);

?>