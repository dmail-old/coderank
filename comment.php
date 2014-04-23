<?php

/***************************************************************************
*                        index.php
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

$comment = new DB_Table('comment');
$hidden_fields = '';
$message = false;
$referer = get_referer();
if( !$referer ) $referer = $_SERVER['REQUEST_URI']; // si pas de referer on rechargeras la page
$hidden_fields.= '<input type="hidden" name="referer" value="'.$referer.'" />';

if( isset($_REQUEST['moderate']) || isset($_POST['accept']) || isset($_POST['refuse']) )
{
	$title = $lang['moderate_comment_title'];
	$hidden_fields.= '<input type="hidden" name="moderate" value="1" />';
	
	if( !$session->user->isAdmin() && !$session->user->isModerator() )
	{
		$message = array('type' => 'warning', 'text' => $lang['moderate_comment_forbidden']);
	}
	else
	{
		$data = DB::select(
			'comment JOIN user ON (comment.user = user.id) JOIN code ON (comment.code = code.id) JOIN language ON (code.language = language.id)',
			'comment.*, user.name AS author, code.name AS code, language.extension',
			'WHERE comment.visible = 0 LIMIT 1'
		);
		
		if( !$data )
		{
			$message = array('type' => 'success', 'text' => $lang['moderate_comment_end']);
		}
		else
		{
			$comment->set($data);
			$page->bind('moderate', true);
			$hidden_fields.= '<input type="hidden" name="id" value="'.$comment['id'].'" />';
			
			if( isset($_POST['id']) && $_POST['id'] != $comment['id'] )
			{
				$message = array('type' => 'warning', 'text' => $lang['moderate_comment_conflict']);
			}
		}
	}
}
else if( isset($_REQUEST['id']) )
{
	$title = $lang['comment_edit_title'];
	$hidden_fields.= '<input type="hidden" name="id" value="'.$_REQUEST['id'].'" />';
	$data = DB::select(
		'comment JOIN user ON (comment.user = user.id) JOIN code ON (comment.code = code.id) JOIN language ON (code.language = language.id)',
		'comment.*, user.name AS author, code.name AS code, language.extension',
		'WHERE comment.id = ? LIMIT 1',
		$_REQUEST['id']
	);
	
	if( !$data )
	{
		$message = array('type' => 'error', 'text' => $lang['comment_edit_not_found']);
	}
	else
	{
		$comment->set($data);
		$owner = $comment['user'] == $session->user['id'];
		// si je suis pas l'admin je dois être le propriétaire du code pour pouvoir le modifier
		if( !$owner && !$session->user->isAdmin() )
		{
			$message = array('type' => 'error', 'text' => $lang['comment_edit_forbidden']);
		}
		else if( $owner )
		{
			$fields['mtime'] = time(); // date de modification du commentaire
		}
	}
}

$header->bind('title', $title);
$header->addRow('nav', array('text' => $title));
$page->bind('title', $title);

if( $message)
{
	$page->bind('message', $message);
	include($root_path.'includes/page_footer.php');
}

if( $comment['id'] )
{
	$code_name = $comment['code'].'.'.$comment['extension'];
	$code_href = realurl().'/code/'.$code_name;
	
	$author_name = $comment['author'];
	$author_href = realurl().'/user/'.mb_strtolower($author_name);
	
	$code_link = '<a href="'.$code_href.'">'.$code_name.'</a>';
	$author_link = '<a href="'.$author_href.'">'.$author_name.'</a>';
	$page->bind('info', sprintf($lang['comment_about'], $code_link, humanCtime($comment['ctime']), $author_link));
}

$errors = array();
if( isset($_POST['submit']) || isset($_POST['accept']) || isset($_POST['refuse']) )
{	
	if( isset($_POST['submit']) )
	{
		$text = $_POST['content'];
		
		if( mb_strlen($text) < $config['comment']['min'] )
		{
			$errors['content'] = sprintf($lang['comment_short'], $config['comment']['min']);
		}
		else if( mb_strlen($text) > $config['comment']['max'] )
		{
			$errors['content'] = sprintf($lang['comment_long'], $config['comment']['min']);
		}
		else
		{
			$fields['content'] = $text;
		}
		
		// il s'agit de reproposer le code
		if( $comment['visible'] > 1 ) $fields['visible'] = 0;
		
		$message = $lang['comment_edited'];
	}	
	else if( isset($_POST['accept']) )
	{
		$fields['visible'] = 1;
		$message = $lang['comment_accepted'];
	}
	else if( isset($_POST['refuse']) )
	{
		$fields['visible'] = 2;
		$message = $lang['comment_refused'];
	}
	
	if( $errors )
	{
		$page->bind('error', $errors);
	}
	else
	{
		$comment->update($fields);
		$session->user->update('message', $message);
		if( isset($_REQUEST['moderate']) ) redirect(realurl('comment.php?moderate=true'));
		else redirect($referer);
	}
}

$page->bind('lang', array(
	'send' => $lang['send'],
	'accept' => $lang['accept'],
	'refuse' => $lang['refuse']
));
$page->bind('content', $comment['content']);
$page->bind('hidden', $hidden_fields);

include($root_path.'includes/page_footer.php');

?>