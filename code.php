<?php

/***************************************************************************
*                        code.php
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

$referer = new Url(get_referer());
$hidden_fields = '';
if( $referer->host == $config['server_name'] ) // je viens d'une page du site
{
	if( basename($referer->path) == 'search.php' ) // je viens de la page de recherche
	{
		$text = $lang['Search'];
		if( $referer->getParam('search') ) $text.= ' "'.urldecode($referer->getParam('search')).'"';
		$header->addRow('nav', array(
			'href' => $_SERVER['HTTP_REFERER'], 'text' => $text
		));
	}
	$hidden_fields.= '<input type="hidden" name="referer" value="'.$referer.'" />';
}

if( isset($_GET['id']) )
{
	$where = 'code.id = ?';
	$values = array($_GET['id']);
}
else
{
	if( isset($_GET['name']) ) $filename = $_GET['name'];
	else $filename = pathinfo(urldecode($_SERVER['REQUEST_URI']), PATHINFO_BASENAME);
	
	if( $filename )
	{
		$extension = getExtension($filename);
		$name = pathinfo($filename, PATHINFO_FILENAME);
		
		$where = 'code.name = ? AND language.extension = ?';
		$values = array($name, $extension);
	}
}

if( $where )
{
	$code = DB::select('
		code JOIN language ON (language.id = code.language)
		LEFT JOIN source ON (source.code = code.id)
		LEFT JOIN source_colored ON (source_colored.code = code.id)
		LEFT JOIN demo_colored ON (demo_colored.code = code.id)',
		'code.*, language.name AS language, language.extension,
		source.source AS source, LENGTH(source.source) AS size,
		source_colored.source AS source_colored, demo_colored.demo AS demo_colored,
		('.DB::selectQuery('user', 'name', 'WHERE user.id = code.user').') AS user,
		('.DB::selectQuery('library', 'name', 'WHERE library.id = code.library').') AS library,
		('.DB::selectQuery('view', 'COUNT(*)', 'WHERE view.code = code.id').') AS view,
		('.DB::selectQuery('vote', 'ROUND(AVG(vote.value))', 'WHERE vote.code = code.id').') AS rank,
		('.DB::selectQuery('code_category JOIN category ON (category.id = code_category.category)', 'GROUP_CONCAT(category.name)', 'WHERE code_category.code = code.id').') AS category',
		'WHERE '.$where.' LIMIT 1', $values
	);
}

/*JOIN language ON (language.id = code.language)
JOIN user ON (user.id = code.user)
LEFT JOIN source ON (source.code = code.id)
LEFT JOIN vote ON (vote.code = code.id)
LEFT JOIN library ON (code.library = library.id)
LEFT JOIN view ON (view.code = code.id)',
'code.*, language.name AS language, language.extension, library.name AS library, user.name AS author, source.source, ROUND(AVG(vote.value)) AS rank, COUNT(view.id) AS view',
'WHERE code.visible = 1 AND code.name = ? AND language.extension = ? GROUP BY code.id',*/

/*SELECT
code.id,
(SELECT COUNT(*) FROM view WHERE view.code = code.id) AS view,
(SELECT language.name FROM language WHERE code.language = language.id) AS language,
(SELECT ROUND(AVG(vote.value)) FROM vote WHERE vote.code = code.id) AS rank
FROM code
WHERE code.id = 1;


SELECT code.id, (SELECT GROUP_CONCAT(code_category.id) FROM code_category WHERE code_category.code = code.id ) AS category
FROM code
WHERE code.id = 1
*/

/*SELECT code.id, COUNT(view.id) AS view, language.name AS language, ROUND(AVG(vote.value)) AS rank
FROM code
LEFT JOIN view ON (view.code = code.id)
LEFT JOIN vote ON (vote.code = code.id)
LEFT JOIN language ON (code.language = language.id)
WHERE code.id = 1 GROUP BY code.id;
*/

if( $code['id'] && $code['visible'] != 1 && !$session->user->isAdmin() && !$session->user->isModerator() )
{
	$title = $code['name'].'.'.$code['extension'];
	$page->bind('message', array('type' => 'warning', 'text' => $lang['code_hidden']));
}
else if( $code['id'] )
{
	$user = $session->user;
	$user_id = $user->get('id');
	$user_ip = $session->get('ip');
	
	$code['author'] = array(
		'legend' => $lang['author'],
		'name' => $code['user'],
		'href' => realurl().'/user/'.mb_strtolower($code['user'])
	);
	$size = humanSize($code['size']);
	$code['download'] = array(
		'size' => $size,
		'href' => realurl().'/download/'.mb_strtolower($code['name']).'.'.$code['extension'],
		'text' => sprintf($lang['download'], $size)
	);
	
	if( $user->isVisitor() )
	{
		$login_link = '<a href="'.realurl().'/login.php">'.$lang['login'].'</a>';
		$register_link = '<a href="'.realurl().'/register.php">'.mb_strtolower($lang['register']).'</a>';	
		$textarea['requirement'] = sprintf($lang['comment_login'], $login_link, $register_link);
		
		$vote['requirement'][] = array('text' => $lang['rank_member']);
		$vote['requirement'][] = array('text' => $lang['rank_hascode']);
		$vote['requirement'][] = array('text' => $lang['rank_hasfive']);
		$page->bind('vote', $vote);
	}
	else
	{
		$time = time();
		$stock = $config['view']['stock'];
		$expire = $time - $config['view']['time'];
		$views = DB::selectAll('view', '*', 'WHERE user = ? AND ctime > ? LIMIT '.$stock, $user_id, $expire);
		
		if( count($views) < $stock ) // si ce membre n'a pas éqpuisé son nombre de vues
		{
			$view_codes = array_groupBy($views, 'code');
			if( count($view_codes[$code['id']]) < $config['view']['code'] ) // ni épuisé son nb de vues pour ce code
			{
				$max_ip = $config['view']['ip'];
				$views = DB::selectAll('view', 'id', 'WHERE ip = ? AND ctime > ? LIMIT '.$max_ip, $user_ip, $expire);
				if( count($views) < $max_ip ) // et que cette ip n'a pas plus de $max_ip
				{
					DB::insert('view', array('code' => $code['id'], 'user' => $user_id, 'ip' => $session['ip'], 'ctime' => $time));
					$code['view']++;
				}
			}
		}
				
		if( $code['author']['name'] != $user->get('name') ) // si je ne suis pas l'auteur du code
		{
			$vote = DB::select('vote', '*', 'WHERE user = '.$user_id.' AND code = '.$code['id'].' LIMIT 1');
			if( !$vote )
			{
				if( $user->isAdmin() || $user->isModerator() ) $hascode = $hasfive = true;
				else
				{
					$hascode = DB::select('code JOIN user ON (code.user = '.$user_id.')','code.id','WHERE code.visible = 1 LIMIT 1');
					$hasfive = $hascode && DB::select('code JOIN vote ON (vote.code = code.id) ','code.id','WHERE code.user = '.$user_id.' AND vote.value > 4 LIMIT 1');
				}			
				if( !$hascode ) $vote['requirement'][] = array('text' => $lang['rank_hascode']);
				if( !$hasfive ) $vote['requirement'][] = array('text' => $lang['rank_hasfive']);
				
				if( !$requirement ) // rien de tout ça: propose les 5 notes
				{
					for($i=0;$i<5;$i++)	$vote['rate'][] = array('value' => $i+1, 'title' => sprintf($lang['vote_title'], $i+1));
					$vote['text'] = $lang['vote'];
				} 
			}
			if( isset($_REQUEST['vote']) )
			{
				if( $vote['value'] )
				{
					$vote['message'] = array('type' => 'warning', 'text' => $lang['vote_done']);
				}		
				else
				{
					if( $requirement )
					{
						$vote['message'] = array('type' => 'warning', 'text' => $lang['vote_forbidden']);
					}
					else
					{
						$rate = $_REQUEST['rate'];
						if( !is_numeric($rate) || $rate < 1 || $rate > 5 )
						{
							$vote['message'] = array('type' => 'error', 'text' => sprintf($lang['vote_invalid'], $rate));
						}
						else
						{
							DB::insert('vote', array('user' => $user_id, 'code' => $code['id'], 'value' => $rate));
							$vote = DB::select('vote', 'ROUND(AVG(value)) AS rank', 'WHERE code = '.$code['id']);
							$code['rank'] = $vote['rank']; // recalcule la note
							
							$vote['message'] = array('type' => 'success', 'text' => $lang['vote_success']);
						}
					}
				}
			}
			$page->bind('vote', $vote);
			
			$favory = DB::select('favorite', 'id', 'WHERE code = '.$code['id'].' AND user = '.$user_id);
			if( isset($_REQUEST['favory']) )
			{
				$favory_link = '<a href="'.realurl('user/'.$user['name'].'/favory').'">'.$lang['favorite'].'</a>';
				if( $favory )
				{
					$favory['message'] = array('type' => 'warning', 'text' => sprintf($lang['favory_exist'], $favory_link));
				}
				else
				{
					$favory = array('user' => $user_id, 'code' => $code['id']);
					DB::insert('favorite', $favory);				
					$favory['message'] = array('type' => 'success', 'text' => sprintf($lang['favory_added'], $favory_link));
				}
			}
			else if( !$favory )
			{
				$favory['text'] = $lang['favory_add'];
			}		
			$page->bind('favory', $favory);
		}
		
		$textarea['text'] = $_REQUEST['text'];
		$textarea['placeholder'] = $lang['comment_add'];
		$comment = DB::select('comment', 'id', 'WHERE visible = 0 AND user = '.$user_id.' AND code = '.$code['id'].' LIMIT 1');
		if( $comment )
		{
			$comment_link = '<a href="'.realurl('user/'.$user['name'].'/comment').'">'.$lang['a'].'</a>';
			$edit_link = '<a href="'.realurl('commment.php?id='.$comment['id']).'">'.mb_strtolower($lang['edit']).'</a>';
			$delete_link = '<a href="'.realurl('delete.php?id='.$comment['id'].'&comment=true').'">'.mb_strtolower($lang['delete']).'</a>';
			$textarea['requirement'] = sprintf($lang['comment_exist'], $comment_link, $edit_link, $delete_link);
		}
		else if( ($count = DB::count('comment', 'visible <> 1 AND user = '.$user_id)) >= $config['comment']['queue'] )
		{
			$comment_link = '<a href="'.realurl('user/'.$user['name'].'/comment').'">'.$count.'</a>';
			$textarea['requirement'] = sprintf($lang['comment_queue'], $comment_link);
		}
		else
		{
			if( isset($_REQUEST['comment']) ) // si je souhaite commenter
			{
				$text = $_REQUEST['text'];
				if( mb_strlen($text) < $config['comment']['min'] )
				{
					$textarea['message'] = array('type' => 'warning', 'text' => sprintf($lang['comment_short'], $config['comment']['max']));
				}
				else if( mb_strlen($text) > $config['comment']['max'] )
				{
					$textarea['message'] = array('type' => 'warning', 'text' => sprintf($lang['comment_long'], $config['comment']['max']));
				}
				else
				{
					$visible = $user->isAdmin() || $user->isModerator();
					DB::insert('comment', array(
						'user' => $user_id,
						'code' => $code['id'],
						'content' => htmlspecialchars($text),
						'ctime' => time(),
						'visible' => $visible
					));
					
					$textarea['message'] = array('type' => 'success', 'text' => $visible ? $lang['comment_added'] : $lang['comment_user_added']);
				}
			}
		}
	}
	
	$header->set('meta.description', $code['description']);
	
	if( $code['category'] )
	{
		$header->set('meta.keywords', $code['category']);
		
		$categories = explode(',', $code['category']);
		$i = 0;
		$j = count($categories);
		for(;$i<$j;$i++)
		{
			$temp[$i]['href'] = server_path().'/search.php?category='.$categories[$i];
			$temp[$i]['name'] = $categories[$i];
		}
		$code['category'] = $temp;
	}
	
	$comment = DB::selectAll(
		'comment JOIN user ON (comment.user = user.id)',
		'comment.*, user.name AS author',
		'WHERE comment.code = '.$code['id'].' AND comment.visible = 1 ORDER BY comment.ctime'
	);
	humanData($comment, $code['author']['name']);
	
	$code['date'] = date('j',$code['ctime']) .' '. $lang[date('F',$code['ctime'])] .' '. date('Y',$code['ctime']);
	$code['comment'] = $comment;
	$code['comment_count'] = count($comment);
	
	$title = $code['name'].'.'.$code['extension'];
	
	$page->bind('codelang', array(
		'demo' => $lang['code_demo'],
		'source' => $lang['code_source'],
		'code_comment' => $lang['code_comment'],
		'comment_empty' => $lang['code_comment_empty'],
		'comment' => $lang['post'],
		'vote' => $lang['code_vote'],
		'voteneed' => $lang['code_voteneed'],
	));
	$page->bindLang('info', 'category', 'language', 'size', 'view', 'ctime', 'library', 'category_empty');
}
else
{
	$title = $lang['code_not_found_title'];
	$page->bind('message', array('type' => 'error', 'text' => $lang['code_not_found']));
}

$header->addRow('nav', array('text' => $title));	
$header->bind('title', $title);

$page->bind('textarea', $textarea);
$page->bind('rating', $rating);
$page->bind('code', $code);
$page->bind('hidden', $hidden_fields);

include($root_path.'includes/page_footer.php');

?>