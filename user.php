<?php

/***************************************************************************
*                        user.php
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

if( !isset($_GET['name']) )
{
	$_GET['name'] = preg_replace('#(.*?)user/([^/]*)/?(.*?)$#', '$2', urldecode($_SERVER['REQUEST_URI']));
 	$_GET['part'] = preg_replace('#^(.*?)user/([^/]*)/([^/]*)/?(.*?)$#', '$3', urldecode($_SERVER['REQUEST_URI']));
}

if( isset($_GET['id']) )
{
	$user = $session->user->selectBy('id', $_REQUEST['id']);
}
else
{
	if( isset($_GET['name']) ) $name = $_GET['name'];
	$user = $session->user->selectBy('name', $name);
}
if( !$user ) $user = $session->user;

$title = $user['name'];
$menu_path = realurl().'/user/'.mb_strtolower($user['name']);
$part = isset($_GET['part']) ? $_GET['part'] : 'code';
$owner = $user['name'] == $session->user['name'];
$lang_owner = $owner ? $lang['my'] : $lang['its'];

if( $owner && $session->user->isVisitor() )
{
	$message = array('type' => 'info', 'text' => $lang['guest_profil']);
	$page->bind('message', $message);
	include($root_path.'includes/page_footer.php');
}

$menu = array(
	array('href' => $menu_path.'/code', 'text' => sprintf($lang_owner, $lang['codes']), 'count' => 0),
	array('href' => $menu_path.'/comment', 'text' => sprintf($lang_owner, $lang['comments']), 'count' => 0),
	array('href' => $menu_path.'/favory', 'text' => sprintf($lang_owner, $lang['favorite']), 'count' => 0)
);

$codes = DB::selectAll(
	'code JOIN language ON (language.id = code.language)',
	'code.*, language.name AS language, language.extension,
	('.DB::selectQuery('library', 'name', 'WHERE library.id = code.library').') AS library,
	('.DB::selectQuery('view', 'COUNT(*)', 'WHERE view.code = code.id').') AS view,
	('.DB::selectQuery('vote', 'ROUND(AVG(vote.value))', 'WHERE vote.code = code.id').') AS rank',
	'WHERE code.user = '.$user['id'].' GROUP BY code.id ORDER BY code.ctime DESC'
);

$comments = DB::selectAll(
	'comment JOIN code ON (code.id = comment.code) JOIN language ON (code.language = language.id)',
	'comment.*, code.name AS code, language.extension',
	'WHERE comment.user = '.$user['id'].' ORDER BY comment.ctime DESC'
);

$favorite = DB::selectAll(
	'favorite JOIN code ON (favorite.code = code.id) JOIN language ON (language.id = code.language)',
	'code.*, favorite.id AS favory, language.name AS language, language.extension,
	('.DB::selectQuery('library', 'name', 'WHERE library.id = code.library').') AS library,
	('.DB::selectQuery('view', 'COUNT(*)', 'WHERE view.code = code.id').') AS view',
	'WHERE favorite.user = '.$user['id'].' AND code.visible = 1 GROUP BY code.id ORDER BY code.ctime DESC'
);

$menu[2]['count'] = count($favorite);
$control = $owner || $session->user->isAdmin();

$codes_group = array(
	array('class' => 'r', 'title' => $lang['refused']),
	array('class' => 'd', 'title' => $lang['waiting']),
	array('class' => 'b')
);
$comments_group = array(
	array('class' => 'r', 'title' => $lang['refused']),
	array('class' => 'd', 'title' => $lang['waiting']),
	array('class' => 'b')
);

if( $control )
{
	$options = array(
		array('name' => 'name', 'href' => $menu_path.'/name', 'text' => $lang['name']),
		array('name' => 'email', 'href' => $menu_path.'/email', 'text' => $lang['email']),
		array('name' => 'password', 'href' => $menu_path.'/password', 'text' => $lang['password']),
		array('name' => 'unsuscribe', 'href' => $menu_path.'/unsuscribe', 'text' => $lang['change_unsuscribe']),
	);
}

if( $part == 'comment' )
{	
	$page->setName('user_comment');
	$title = $owner ? $menu[1]['text'] : sprintf($lang['user_'.$part], $user['name']);
	$menu[1]['current'] = true;
	$comments_group[2]['title'] = $title;
}
else if( $part == 'favory' )
{	
	humanData($favorite, $session->user['name']);
	
	$page->setName('user_favory');
	$title = $owner ? $menu[2]['text'] : sprintf($lang['user_'.$part], $user['name']);
	$menu[2]['current'] = true;
	
	$page->bind('favory', array(
		'title' => $title,
		'list' => $favorite,
		'count' => count($favorite)
	));
}
else if( $part == 'name' || $part == 'email' || $part == 'password' || $part == 'unsuscribe' )
{
	$title = $lang['change_'.$part];
	$option = &array_find($options, 'name', $part);
	$option['current'] = true;
	$page->setName('user_change');
	
	$field = array();
	$errors = array();
	
	$lang_password = $lang['password'];
	if( $part != 'unsuscribe' )
	{
		$field['name'] = $lang[$part];
		if( $part != 'password' ) $field['value'] = $user[$part];
		else $lang_password = $lang['password_current'];
	}
	else if( $codes || $comments )
	{
		$info = '';
		if( $codes )
		{
			$amount = count($codes);		
			$info.= $amount.' '.agree('code', $amount);
		}
		if( $comments )
		{
			$amount = count($comments);
			if( $info ) $info.= ' '.$lang['and'].' ';
			$info.= $amount.' '.agree('comment', $amount);
		}
		$page->bind('info', sprintf($lang['leave_info'], $info));
	}
	
	if( !$owner && !$session->user->isAdmin() )
	{
		$message = array('type' => 'warning', 'text' => $lang['forbidden']);
	}
	else
	{
		if( isset($_POST['submit']) )
		{
			$value = isset($_POST['field']) ? $_POST['field'] : '';
			$password = isset($_POST['password']) ? $_POST['password'] : '';
			$field['value'] = $value;
			
			if( $part != 'unsuscribe' )
			{
				if( $user->identic($part, $value)) $identic = true;
				else if( !$user->conform($part, $value) ) $errors['field'] = $user->error();
			}
			if( !$user->conform('password', $password) ) $errors['password'] = $user->error();
			if( !$errors )
			{
				if( !$user->identic('password', $password) ) $errors['password'] = $lang['user_password_mismatch'];
				else
				{
					if( $part == 'unsuscribe' )
					{
						$user->delete();
					}
					else
					{
						if( !$identic ) $user->update($part, $value);
						$field['value'] = $value;
					}
					
					$success = sprintf($lang[$part.'_changed'], $value);
				}
			}
		}
	}
	
	if( $part == 'password' ) $field['value'] = '';
	
	$page->bind('password', $lang_password);
	$page->bind('success', $success);
	$page->bind('error', $errors);
	$page->bind('message', $message);
	$page->bind('field', $field);
	$page->bind('error', $errors);
}
else
{
	$part = 'code';
	$page->setName('user_code');
	$title = $owner ? $menu[0]['text'] : sprintf($lang['user_'.$part], $user['name']);
	$menu[0]['current'] = true;
	$codes_group[2]['title'] = $title;
}

$header->addRow('nav', array('text' => $title));
$header->bind('title', $title);

$i = 0;
$j = count($codes);
for(;$i<$j;$i++)
{
	$code = $codes[$i];
	if( $code['visible'] == 0 ) $codes_group[1]['list'][] = $code;
	else if( $code['visible'] == 1 ) $codes_group[2]['list'][] = $code;
	else if( $code['visible'] > 1 ) $codes_group[0]['list'][] = $code;
}
$i = 0;
$j = count($comments);
for(;$i<$j;$i++)
{
	$comment = $comments[$i];
	if( $comment['visible'] == 0 ) $comments_group[1]['list'][] = $comment;
	else if( $comment['visible'] == 1 ) $comments_group[2]['list'][] = $comment;
	else if( $comment['visible'] > 1 ) $comments_group[0]['list'][] = $comment;
}

if( !$control ) // on ne voit pas ce qui est réfusés et en attente
{
	array_shift($codes_group);
	array_shift($codes_group);
	array_shift($comments_group);
	array_shift($comments_group);
}

$i = count($codes_group);
while($i--)
{
	$count = count($codes_group[$i]['list']);
	$menu[0]['count']+= $count;
	if( $part == 'code' )
	{
		$codes_group[$i]['count'] = $count;
		humanData($codes_group[$i]['list'], $control);
	}
}
$i = count($comments_group);
while($i--)
{
	$count = count($comments_group[$i]['list']);
	$menu[1]['count']+= $count;
	if( $part == 'comment' )
	{
		$comments_group[$i]['count'] = $count;
		humanData($comments_group[$i]['list'], $control);
	}
}

// $user['date'] = date('j',$user['ctime']) .' '. $lang[date('F',$user['ctime'])] .' '. date('Y',$user['ctime']);
// $user['ctime'] = ucfirst(humanCtime($user['ctime']));

if( $part == 'code' || $part == 'comment' || $part == 'favory' )
{
	if( $part == 'code' )
	{
		$collection = $codes_group;
		$count = $menu[0]['count'];
	}
	else if( $part == 'comment' )
	{
		$collection = $comments_group;
		$count = $menu[1]['count'];
	}
	
	if( $count ) $page->bind($part, $collection);
	else $page->bind('empty', $owner ? sprintf($lang['user_'.$part.'_empty'], $user['name']) : $lang[$part.'_empty']);
}

$page->bind('lang', array(
	'menu' => $lang['look'],
	'options' => $lang['options']
));
$page->bind('menu', $menu);
$page->bind('options', $options);
$page->bind('title', $title);

include($root_path.'includes/page_footer.php');

?>
