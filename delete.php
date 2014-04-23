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

$user = $session->user;
$hidden_fields = '';
$referer = get_referer();
if( !$referer ) $referer = realurl(); // si pas de referer on renvoit à l'index

if( isset($_REQUEST['id']) )
{
	$id = $_REQUEST['id'];
	$what = 'code';
	$field = 'id';
	
	$title = sprintf($lang['delete_a'], $lang['a'.$what]);
	
	if( isset($_REQUEST['comment']) ) $what = 'comment';
	else if( isset($_REQUEST['favorite']) ) $what = 'favorite';
	
	$item = DB::select($what, '*', 'WHERE id = ?', $id);
	
	if( !$item )
	{
		$message = array('type' => 'error', 'text' => sprintf($lang['delete_item_not_found'], ucfirst($lang['the_'.$what])));
	}
	else
	{
		if( !$user->isAdmin() && $user['id'] != $item['user'] )
		{
			$message = array('type' => 'warning', 'text' => sprintf($lang['delete_forbidden'], $lang['that_'.$what]));
		}
		else
		{
			// récup le nom du code pour le favori
			if( $what == 'favorite' ) $item = array_merge($item, DB::select('code', 'name', 'WHERE id = '.$item['code']));
			
			// je suis le propriétaire ou l'admin je peux donc supprimer cet objet
			DB::delete($what, $id);
			
			$the = ucfirst($lang['the_'.$what]); 
			if( isset($item['name']) ) $the.= ' "'.$item['name'].'"';
			$message = sprintf($lang['delete_item'], $the);
			
			if( AJAX ) ajax_reply(array('type' => 'success', 'text' => $message));
			
			$session->user->update('message', $message);
			redirect($referer);
		}
	}
}
else
{
	$message = array('type' => 'warning', 'text' => $lang['delete_not_found']);
	$title = $lang['delete_not_found'];
}

if( AJAX ) ajax_reply($message);

$page->bind('title', $title);
$header->bind('title', $title);
$header->addRow('nav', array('text' => $title));
$page->bind('message', $message);

include($root_path.'includes/page_footer.php');

?>