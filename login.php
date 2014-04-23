<?php
/***************************************************************************
 *                        login.php
 *                        -------------------
 *   begin                : Mercredi 19 Octobre 2011
 *   copyright            : Angelblade
 *   email                : Angelblade@hotmail.fr
 *
 *
 ***************************************************************************/

define('IN', true);
$root_path = './';
include($root_path.'common.php');

$referer = get_referer();
if( !$referer ) $referer = realurl(); // si pas de referer on renvoit à l'index
$user = $session->user;

if( isset($_POST['submit']) )
{
	$user_name = isset($_REQUEST['user_name']) ? $_REQUEST['user_name'] : '';
	$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
	$persist = isset($_REQUEST['persist']) ? 1 : 0;
	$errors = array();
	
	if( !$user->conform('name', $user_name, true) ) $errors['name'] = $user->error();
	if( !$user->conform('password', $password, true) ) $errors['password'] = $user->error();
	if( !count($errors) )
	{		
		$userdata = $user->selectBy('name', $user_name);
		
		if( !$userdata || !$user->identic('password', $password, $userdata['password']) )
		{
			$errors = $lang['password_mismatch'];
		}
		else
		{
			$user->set($userdata->data);
			if( $user->isLocked() )
			{
				$errors = $lang['user_locked'];
			}
			else if( $session->login($persist, 'login') )
			{
				if( !$user->get('message') )
				{
					$href = realurl('user/'.mb_strtolower($user['name']));
					$user->update('message', sprintf($lang['login_success'], '<a href="'.$href.'">'.$user['name'].'</a>'));
				}
				
				$url = new Url($referer);
				// évite de déconnecter si je vient de ma connecter
				if( $url->getParam('logout') ) $url->unsetParam('logout');
				// n'envoit pas sur le page de register à tort
				if( basename($url->path) == 'register.php' ) $url = realurl();
				redirect($url);
			}
		}
	}
}

include($root_path.'includes/page_header.php');

$header->addRow('nav', array('text' => $lang['connect']));

// dans la page si javascript détecte que l'user accepte pas les cookies
// il le préciseras dans l'action du form par un argument ou chais pas comment

if( $errors )
{
	if( is_array($errors) ) $errors = implode($errors, '<br />');
	$message = array('type' => 'warning', 'text' => $errors);
	$page->set('message', $message);
}
else if( !$session->user->isVisitor() )
{
	// je viens de la page register et je suis connecté -> redirection immédiate vers le referer
	if( pathinfo($_SERVER['HTTP_REFERER'], PATHINFO_BASENAME) == 'register.php' )
	{
		redirect($referer);
	}
	$page->set('message', array('type' => 'warning', 'text' => sprintf($lang['login_exist'], $user['name'], $referer)));
}

$page->set(array(	
	'NOT_REGISTERED' => js_encode($not_registered),
	'INVALID_PASSWORD' => js_encode($invalid_password),
	
	// 'INPUT_PASSWORD' => $session->user->toHTML('password', array('value' => $password)),
	
	'L_PASSWORD' => $lang['password'],
	
	'L_USER_NAME' => $lang['Pseudo'],
	'L_REMEMBER_ME' => $lang['Remember_me'],
	'L_LOGIN' => $lang['Login'],
	'L_REGISTER' => $lang['Register'],
	'L_SEND_PASSWORD' => $lang['Send_password'],
	'L_BACK_TO_INDEX' => $lang['Back_to_index'],
	'EMPTY_LANG' => js_encode(array($lang['Username_empty'], $lang['Password_empty'])),
	
	'L_NOT_REGISTERED' => $lang['Not_registered'],
	'L_INVALID_PASSWORD' => $lang['Invalid_password'],
	'L_SUBMIT' => $lang['submit'],
	
	'AUTO_AUTOLOG' => 'checked',
	'USER_NAME' => $user_name,
	'u_register' => realurl('register.php').'?referer='.$referer,
	'S_ACTION' => realurl('login.php'),
	'S_HIDDEN_FIELDS' => '<input type="hidden" name="referer" value="'.$referer.'" />'
));

include($root_path.'includes/page_footer.php');
 
?>