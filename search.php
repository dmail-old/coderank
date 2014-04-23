<?php
/***************************************************************************
*                        game.php
*                        -------------------
*   begin                : Lundi, 15 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

/*
NOTE: compter le nombre de vote pourrais donner plus de poids à la note

- trier par change de place genre au dessus des résultats
- une options chercher dans: titre et description, titre, description, contenu, auteur
*/

/*
Intéréssant

SELECT id_livre,
sum(CASE format WHEN 'papier' THEN 1 ELSE 0 END) AS papier,
sum(CASE format WHEN 'carton' THEN 1 ELSE 0 END) AS carton
FROM  achats
GROUP BY id_livre
*/

/*
important: http://www.ranks.nl/stopwords/french.html

Les stopwords anglais seront toujours supprimés, si une autre langue est choisies on supprime cetet langue aussi
on ne supprimer les stopwords que si d'autre mots sont présents
A voir je me prendrais pas le tete avec ca
*/

/*
SELECT code.*,AVG(vote.value) FROM code JOIN vote ON (vote.code = code.id) WHERE code.id = 4
*/

/*
SELECT code.id, COUNT(view.id) AS view FROM code LEFT JOIN view ON (view.code = code.id) GROUP BY code.id;
SELECT code.id, ROUND(AVG(vote.value)) AS rank FROM code LEFT JOIN vote ON (vote.code = code.id) GROUP BY code.id;
*/

define('IN', true);
$root_path = './';
include($root_path.'common.php');
include($root_path.'includes/page_header.php');

$code_per_page = $config['pagination']['count'];

function emphasize_terms($str, $search)
{
	if( !$search ) return $str;
	
	$terms = explode(' ', $search);
	$i = count($terms);
	while($i--)
	{
		$str = preg_replace('/('.$terms[$i].')+/i', '<em>$1</em>', $str);
	}
	$str = str_replace('</em> <em>', ' ', $str); // évite les doublons de em
	return $str;
}

$languages = DB::selectAll(
	'code JOIN language ON (code.language = language.id)',
	'COUNT(code.id) AS count, language.*, language.name AS text',
	'WHERE code.visible = 1 GROUP BY language.id ORDER BY count DESC, language.name DESC'
);
$libraries = DB::selectAll(
	'code JOIN library ON (code.library = library.id)',
	'COUNT(code.id) AS count, library.*, library.name AS text',
	'WHERE code.visible = 1 GROUP BY library.name ORDER BY count DESC, library.name DESC'
);
$categories = DB::selectAll(
	'code JOIN code_category ON (code_category.code = code.id) JOIN category ON (category.id = code_category.category)',
	'COUNT(code.id) AS count, category.*, category.name AS text',
	'WHERE code.visible = 1 GROUP BY category.id ORDER BY count DESC, category.name DESC'
);

$versions = DB::selectAll(
	'code',
	'COUNT(*) AS count, version AS name, version AS text, language, library',
	'WHERE code.visible = 1 AND version IS NOT NULL GROUP BY version,language,library ORDER BY count DESC'
);

$orders = array(
	array('name' => 'match', 'text' => $lang['order_match']),
	array('name' => 'rank', 'text' => $lang['order_rank']),
	array('name' => 'view', 'text' => $lang['order_view']),
	array('name' => 'date', 'text' => $lang['order_date'])
);

$i = 0;
foreach($languages as &$__lang)
{
	$language_indexes[$__lang['id']] = $i++;
}

$filters = array(
	array('name' => 'language', 'option' => $languages),
	array('name' => 'category', 'option' => $categories, 'group' => true),
	array('name' => 'library', 'option' => $libraries, 'group' => true),
	array('name' => 'version', 'option' => $versions, 'group' => true),
	array('name' => 'order', 'option' => $orders)
);

foreach($filters as &$filter)
{
	$name = $filter['name'];
	$filter['text'] = $lang[$name];
	$filter['value'] = $_GET[$name];
	$options = $filter['option'];
	
	if( $filter['group'] )
	{
		$group = array();
		foreach($options as $option)
		{
			if( $option['name'] == $filter['value'] ) $option['selected'] = true;
			if( $filter['name'] == 'version' && $option['library'] )
			{
				$collection = $libraries;
				$value = $option['library'];
			}
			else
			{
				$collection = $languages;
				$value = $option['language'];
			}
			$item = array_find($collection, 'id', $value);	
			$subgroup = &array_find($group, 'name', $item['name']);
			if( !$subgroup )
			{
				$group[] = $subgroup = array('name' => $item['name'], 'group' => array($option));
			}
			else
			{
				$subgroup['group'][] = $option;
			}
		}
		$filter['option'] = $group;
	}
	else
	{
		foreach($options as $index => $option)
		{
			if( $option['name'] == $filter['value'] ) $filter['option'][$index]['selected'] = true;
		}
	}
}

array_unshift($filters[0]['option'], array('name' => 'all', 'text' => $lang['language_all']));
// catégories, toutes en prems
array_unshift($filters[1]['option'], array('name' => 'all', 'text' => $lang['category_all']));
// libraries: Peu importe, Sans librarie
array_unshift($filters[2]['option'], array('name' => 'null', 'text' => $lang['library_without']));
array_unshift($filters[2]['option'], array('name' => 'all', 'text' => $lang['library_any']));
// Version: toutes
array_unshift($filters[3]['option'], array('name' => 'all', 'text' => $lang['version_all']));

$page->bind('filter', $filters);

$table = 'code JOIN language ON (language.id = code.language)';
$fields = 'code.*, language.name AS language, language.extension,
	('.DB::selectQuery('library', 'name', 'WHERE library.id = code.library').') AS library,
	('.DB::selectQuery('user', 'name', 'WHERE user.id = code.user').') AS author,
	('.DB::selectQuery('view', 'COUNT(*)', 'WHERE view.code = code.id').') AS view,
	('.DB::selectQuery('vote', 'ROUND(AVG(vote.value))', 'WHERE vote.code = code.id').') AS rank';
$where = 'code.visible = 1';
$like = '';
$match_order = '';

if( $language = array_find($languages, 'name', $_GET['language']) )
{
	$where.= ' AND code.language = '.$language['id'];
}

$library = $_GET['library'];
if( $library == 'null' ) $where.= ' AND code.library IS NULL';
else if( $library = array_find($libraries, 'name', $library) ) $where.= ' AND code.library = '.$library['id'];

if( $category = array_collect($categories, 'name', $_GET['category']) )
{
	// toutes les catégories portant ce nom son concernés
	$cat_ids = array();
	$i = count($category);
	while($i--) $cat_ids[] = $category[$i]['id'];
	$table.= ' JOIN code_category ON (code_category.code = code.id)';
	$where.= ' AND code_category.category IN ('.implode($cat_ids, ',').')';
}
$subnav = $lang['query'];
if( $search = $_GET['search'] )
{
	$search = trim($search); // enlève espace début et fin
	$search = preg_replace('#\s+#', ' ', $search); // enlève les doubles espaces entre les mots
	$parts = explode(' ', $search); // sépare tous les mots
	
	// si on trouve pas d'espace on sépare les majuscules et minuscules, les points aussi
	
	if( $parts )
	{
		$title = $lang['query'].' "'.$search.'"';
		$subnav = $title;
		
		$header->bind('title', $title);
		$i = 0;
		$j = count($parts);
		
		for(;$i<$j;$i++)
		{
			if( $i )
			{
				$like.= ' OR ';
				$match_order.= ' + ';
			}
			
			$value = '\'%'.DB::quote($parts[$i]).'%\'';
			$condition = 'code.name LIKE '.$value.' OR code.description LIKE '.$value;
			
			$like.= $condition;
			if( $j > 1 ) $match_order.= '(CASE WHEN '.$condition.' THEN '.($j-$i).' ELSE 0 END)';
		}
		if( $j > 1 ) $match_order.= ' DESC, ';
	}
}
$header->addRow('nav', array('text' => $subnav));

$version = $_GET['version'];
if( $version == 'null' ) $where.= ' AND code.version IS NULL';
else if( is_numeric($version) ) $where.= ' AND code.version IS NOT NULL AND code.version >= '.$version;

$order = $_GET['order'];
if( $order == 'rank' ) $orderby = 'rank DESC, '.$match_order.' view DESC, ctime DESC';
else if( $order == 'date' ) $orderby = 'ctime DESC, '.$match_order.' rank DESC, view DESC';
else if( $order == 'view' ) $orderby = 'view DESC, '.$match_order.' rank DESC, ctime DESC';
else $orderby = $match_order.' rank DESC, view DESC, ctime DESC';

$current_page = intval($_GET['page']);
if( $current_page )
{
	$limit_start = ($current_page -1) * $code_per_page;
}
else
{
	$limit_start = 0;
	$current_page = 1;
}

$end = $like == '' ? $where : $where.' AND ('.$like.')';
$codes = DB::selectAll($table, 'SQL_CALC_FOUND_ROWS '.$fields, 'WHERE '.$end.' ORDER BY '.$orderby.' LIMIT '.$limit_start.','.$code_per_page);
$count = count($codes);

// debug(DB::lastQuery());
// debug($codes);

if( $count )
{
	$statement = DB::query('SELECT FOUND_ROWS()');
	$result = $statement->fetch('num');
	$count = $result[0];
	
	if( $count > $code_per_page )
	{
		$url = new Url();
		$url->path = $config['script_path'].PAGE.'.php';
		$url->query = $_GET;
		
		$pagination = array();
		// numéro de la dernière page
		$page_last = ceil($count / $code_per_page);
		
		if( $current_page > 1 )
		{
			$url->setParam('page', $current_page-1);
			$pagination[] = array('href' => $url->__toString(), 'text' => $lang['previous']);
			
			$middle = floor($config['pagination']['page']/2);
			$page_start = $current_page - $middle;
			$page_end = $current_page + $middle;
		}
		else
		{
			$page_start = 1;
			$page_end = $config['pagination']['page'];
		}
		
		if( $page_start < 1 ){ $page_end-= $page_start-1; $page_start = 1; }
		if( $page_end > $page_last ) $page_end = $page_last;
		
		for(;$page_start<=$page_end;$page_start++)
		{
			$url->setParam('page', $page_start);
			$pagination[] = array('text' => $page_start, 'href' => $url->__toString(), 'current' => $page_start == $current_page);
		}
		
		if( $current_page < $page_last )
		{
			$url->setParam('page', $current_page+1);
			$pagination[] = array('href' => $url->__toString(), 'text' => $lang['next']);
		}
		
		$page->bind('page', $current_page);
		$page->bind('pagination', $pagination);
	}
	
	humanData($codes);
	
	foreach($codes as &$code)
	{
		if( $search )
		{
			$code['name'] = emphasize_terms($code['name'], $search);
			$code['description'] = emphasize_terms($code['description'], $search);
		}
		if( $language || $library )
		{
			$code['language']['text'] = emphasize_terms($code['language']['text'], $library ? $library['name'] : $language['name']);
		}
		if( $version )
		{
			$code['language']['text'] = emphasize_terms($code['language']['text'], $version);
		}
	}
	
	$page->set('codelist', $codes);
}
else
{	
	if( $search )
	{
		$nomatch = sprintf($lang['no_match_search'], $search);
		$proposal = $lang['you_can'];
		
		$suggest = array(
			array('text' => $lang['suggest_spell']),
			array('text' => $lang['suggest_keyword']),
			array('text' => $lang['suggest_change'])
		);
		
		$page->bind('suggest', $suggest);
	}
	else
	{
		$nomatch = $lang['no_match'];
		$proposal = $lang['change_search_option'];
	}
	
	$page->bind('nomatch', $nomatch);
	$page->bind('proposal', $proposal);
}

$page->bind('lang', array(
	'advanced' => $lang['advanced'],
	'search' => $lang['search']
));
$page->bind(array(
	'title' => $subnav,
	'search' => $search,
	'count' => $count
));

include($root_path.'includes/page_footer.php');

?>
