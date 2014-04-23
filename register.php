<?php

/***************************************************************************
*                        register.php
*                        -------------------
*   begin                : Lundi, 15 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

define('IN', true);
$root_path = './';
include($root_path.'common.php'); 

$referer = get_referer();
if( !$referer ) $referer = realurl(); // si pas de referer on renvoit à l'index

if( isset($_POST['submit']) )
{
	$terms = isset($_POST['terms']) ? trim(htmlspecialchars($_POST['terms'])) : '';
	$timeid = isset($_POST['timeid']) ? $_POST['timeid'] : '';
	$host = isset($_POST['host']) ? $_POST['host'] : '';
	
	if( $host != '' || $timeid == '' || ($terms && $terms != md5($timeid)) )
	{
		exit('robot');
	}
	
	$user_name = isset($_POST['user_name']) ? $_POST['user_name'] : '';
	$email = isset($_POST['email']) ? $_POST['email'] : '';
	$password = isset($_POST['password']) ? $_POST['password'] : '';
	
	$result = $session->user->register($user_name, $password, $email, $terms);
	if( $result === true )
	{
		// crée une session non pesistante, la personne choisiras à sa prochaine visite si elle veut se connecter tout le temps
		$session->login();
		$href = realurl('user/'.mb_strtolower($user_name));
		$session->user->update('message', sprintf($lang['register_success'], '<a href="'.$href.'">'.$user_name.'</a>'));
		$url = new Url($referer);
		// évite de déconnecter si je vient de m'inscrire
		if( $url->getParam('logout') ) $url->unsetParam('logout');
		if( basename($url->path) == 'login.php' ) $url = realurl();
		redirect($url);
	}
}

include($root_path.'includes/page_header.php');

$header->add('nav', array('text' => $lang['Register']));

if( $result )
{
	if( is_array($result) ) $result = implode($result, '<br />');
	$message = array('type' => 'warning', 'text' => $result);
	$page->set('message', $message);
}
else if( !$session->user->isVisitor() )
{
	$page->set('message', array('type' => 'warning', 'text' => sprintf($lang['login_exist'], $session->user['name'], $referer)));
}

$timeid = time().'_'.rand(50000, 60000);

$s_hidden_fields.= '<input type="hidden" name="timeid" value="'.$timeid.'" />';
$s_hidden_fields.= '<input type="hidden" name="referer" value="'.$referer.'" />';
$s_hidden_fields.= '<input type="checkbox" name="host" style="display:none;" value="'.time().'"/>';

$empty_lang = array($lang['Username_empty'], $lang['Password_empty'], $lang['Email_empty']);
$invalid_lang = array($lang['Username_invalid'], '', $lang['Email_invalid']);

$page->set(array(
	'TERMS' => js_encode($lang['terms']),
	
	'PHP_ERROR' => js_encode($result),
	
	'EMPTY_LANG' => js_encode($empty_lang),
	'INVALID_LANG' => js_encode($invalid_lang),
	'TERMS_NOT_CHECKED' => js_encode($lang['Need_terms']),
	'TERMS_CHECKED' => !empty($_POST['terms']) ? 'checked' : '',
	
	'L_USER_NAME' => $lang['Pseudo'],
	'USER_NAME' => $user_name,
	
	'L_EMAIL' => $lang['email'],
	'EMAIL' => $email,
	
	'L_PASSWORD' => $lang['password'],
	'INPUT_PASSWORD' => $session->user->toHTML('password', array('value' => $password)),
	
	'L_AGREE' => sprintf($lang['agree'], '<a href="'.realurl('terms.php').'">'.mb_strtolower($lang['term_of_use']).'</a>'),
	
	'L_REGISTER' => $lang['Register'],
	'L_SUBMIT' => $lang['submit'],
	
	'TIMEID_ENCODED' => md5($timeid),
	
	'S_ACTION' => PAGE.'.php',
	'S_HIDDEN_FIELDS' => $s_hidden_fields
));

include($root_path.'includes/page_footer.php');

?>
