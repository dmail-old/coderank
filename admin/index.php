<?php
/***************************************************************************
 *                        (admin) index.php
 *                        -------------------
 *   begin                : Jeudi 10 Juin 2010
 *   copyright            : (C) Angelblade
 *
 *
 ***************************************************************************/

define('IN', 1);
define('IN_ADMIN', true);
$root_path = '../';
include($root_path.'common.php');
include($root_path.'admin/header.php');

$header->addRow('nav', array('href' => 'index.php?table=code', 'text' => 'Administration'));

$tables = DB::getTables();
$table = $tables[0]['Name'];

function getSelect($table, $id)
{
	$options = DB::selectAll($table,'*');
	$option = &array_find($options, 'id', $id);
	$option['selected'] = true;
	
	return $options;
}

// récupère la table
if( isset($_GET['table']) )
{
	$table = $_GET['table'];
	if( !array_find($tables, 'Name', $table) ) $table = $tables[0]['Name'];
	setcookie('table', $table, time()+60*60*24*30); // 30 jours
}
else if( isset($_COOKIE['table']) )
{
	$table = $_COOKIE['table'];
	if( !array_find($tables, 'Name', $table) ) $table = $tables[0]['Name'];
}

$header->addRow('nav', array('href' => 'index.php?table='.$table, 'text' => $lang['table'][$table]));
$header->set('title', $lang['table'][$table]);

$page->bind('title', $lang['table'][$table]);

$menu = array(
	array('name' => 'item', 'text' => 'Parcourir'),
	array('name' => 'data', 'text' => 'Structure'),
	array('name' => 'list', 'text' => 'Information des codes'),
	array('name' => 'more', 'text' => 'Donnée des codes'),
	array('name' => 'other', 'text' => 'Autre')
);

$items = array('code', 'user');
$lists = array('comment','view','download','vote','favorite','code_category');
$data = array('category','library','language');
$more = array('source','source_colored','demo','demo_colored');

foreach($tables as $tab)
{
	$name = $tab['Name'];
	$item = array(
		'class' => $table == $name ? 'on' : '',
		'href' => 'index.php?table='.$name,
		'text' => $lang['table'][$name],
		'count' => $tab['Rows']
	);
	
	if( in_array($name, $items) ) $menu[0]['list'][] = $item;
	else if( in_array($name, $data) ) $menu[1]['list'][] = $item;
	else if( in_array($name, $lists) ) $menu[2]['list'][] = $item;
	else if( in_array($name, $more) ) $menu[3]['list'][] = $item;
	else $menu[4]['list'][] = $item;
}

$header->set('menu', $menu);

if( isset($_GET['remove']) ) // supprime la ligne
{
	$id = $_GET['remove'];
	if( DB::delete($table, 'WHERE id = ?', $id) )
	{
		$message = 'La ligne '.$id.' de la table '.$lang['table'][$table].' a été supprimé';
		if( AJAX ) ajax_reply(array('type' => 'success', 'text' => $message));
		$header->bind('message', $message);
	}
}
else if( isset($_POST['update']) ) // sauvegarde la ligne
{
	$id = $_POST['id'];
	$fields = $_POST['fields'];
	if( isset($fields['library']) && $fields['library'] == 'null' ) $fields['library'] = null;
	if( isset($fields['version']) && !$fields['version'] ) $fields['version'] = null;  
	
	$by = 'id';
	if( $table == 'source' || $table == 'source_colored' || $table == 'demo_colored' || $table == 'demo' ) $by = 'code';
	
	if( DB::update($table, $fields, 'WHERE '.$by.' = ? ', $id) )
	{
		$header->bind('message', 'La ligne '.$id.' de la table '.$lang['table'][$table].' a été mise à jour');
	}
}
else if( isset($_POST['insert']) ) // insère l'élément
{
	$fields = $_POST['fields'];
	if( isset($fields['library']) && $fields['library'] == 'null' ) $fields['library'] = null;
	if( isset($fields['version']) && !$fields['version'] ) $fields['version'] = null;  
	if( DB::insert($table, $fields) )
	{
		$header->bind('message', 'La nouvelle ligne a été sauvegardé dans la table '.$lang['table'][$table]);
	}
	else
	{
		$header->bind('message', 'Impossible d\'insérer la ligne');
	}
}

if( isset($_GET['edit']) ) // edition d'une ligne de la bdd
{
	$id = $_GET['edit'];
	$db_table = new DB_Table($table);
	
	$by = 'id';
	if( $table == 'source' || $table == 'source_colored' || $table == 'demo_colored' || $table == 'demo' ) $by = 'code';
	
	$row = DB::select($table, '*', 'WHERE '.$by.' = ? ', $id);
	
	$columns = DB::getColumns($table);
	$i = 0;
	$fields = array();
	
	foreach($columns as $column)
	{
		$name = $column[0];	
		$column[4] = $row[$name]; // on met la valeur de la colonne comme valeur pour le champ
		
		$html = $db_table->getColumnHTML($column);
		
		if( $name == 'language' || $name == 'user' || $name == 'code' || $name == 'library' || $name == 'category' )
		{
			$html = getSelect($name, $column[4]);
			if( $name == 'library' ) array_unshift($html, array('name' => 'Aucune', 'id' => 'null'));
		}
		$fields[] = array('name' => $name, 'text' => $lang['field'][$name], 'html' => $html);
	}
	
	$title = 'Modification de '.$table;
	$page->setName('admin/edit');
	$page->set(array(
		'title' => $title,
		'name' => 'update',
		'submit' => 'Sauvegarder',
		'hidden' => '<input type="hidden" name="id" value="'.$id.'" />',
		'field' => $fields
	));
	$header->set('title', $title);
	$header->addRow('nav', array('text' => $title));
}
else if( isset($_REQUEST['add']) ) // ajout d'une ligne à la bdd
{
	$columns = DB::getColumns($table);
	$fields = array();
	$db_table = new DB_Table($table);
	
	foreach($columns as $column)
	{
		$name = $column[0];	
		$html = $db_table->getColumnHTML($column);
		
		if( $name == 'id' ) continue;
		
		if( $name == 'language' || $name == 'user' || $name == 'code' || $name == 'library' || $name == 'category' )
		{
			$html = getSelect($name, $column[4]);
			if( $name == 'library' ) array_unshift($html, array('name' => 'Aucune', 'id' => 'null'));
		}
		$fields[] = array('name' => $name, 'text' => $lang['field'][$name], 'html' => $html);
	}
	
	$title = 'Ajouter un '.$table;
	$page->setName('admin/edit');
	$page->set(array(
		'title' => $title,
		'name' => 'insert',
		'submit' => 'Ajouter',
		'hidden' => '',
		'field' => $fields
	));
	$header->set('title', $title);
	$header->addRow('nav', array('text' => $title));
} 
else // Affichage de la liste des lignes
{
	$item_per_page = $config['pagination']['count'];
	$current_page = intval($_GET['page']);
	if( $current_page )
	{
		$limit_start = ($current_page -1) * $item_per_page;
	}
	else
	{
		$limit_start = 0;
		$current_page = 1;
	}
	
	$columns = array();
	$result = DB::getColumns($table);
	foreach($result as $column)
	{
		$name = $column[0];
		$columns[] = array('text' => $lang['field'][$name]);
	}
	
	$rows = DB::selectAll($table, 'SQL_CALC_FOUND_ROWS *', 'LIMIT '.$limit_start.','.$item_per_page);
	$items = array();
	
	if( count($rows) )
	{		
		$statement = DB::query('SELECT FOUND_ROWS()');
		$result = $statement->fetch('num');
		$count = $result[0];
		
		if( $count > $config['pagination']['count'] )
		{
			$url = new Url(realurl('admin/index.php'));
			$url->query = $_GET;
			
			$pagination = array();
			$page_last = ceil($count / $config['pagination']['count']);
			
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
			if( $page_end > $page_last ){ $page_start-= $page_end - $page_last; $page_end = $page_last; }
			if( $page_start < 1 ) $page_start = 1;

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
		
		$columns[] = array('text' => 'Editer');
		$columns[] = array('text' => 'Supprimer');
		
		foreach($rows as $row)
		{
			$fields = array();
			foreach($row as $key => $value)
			{
				if( $key == 'language' || $key == 'user' || $key == 'code' || $key == 'library' || $key == 'category' )
				{
					$item = DB::select($key, 'name', 'WHERE id = ?', $value);
					
					if( $key == 'code' ) $value = '<a href="'.realurl('code.php?id='.$value).'">'.$item['name'].'</a>';
					else if( $key == 'user' ) $value = '<a href="'.realurl('user/'.$item['name']).'">'.$item['name'].'</a>';
					else $value = $item['name'];
				}
				if( $key == 'ctime' || $key == 'mtime' )
				{
					$value = date('j/h/Y H:m:s', $value);
				}
				if( $key == 'demo' || $key == 'source' )
				{
					$value = '<div class="code">'.htmlspecialchars($value).'</div>';
				}
				
				$fields[] = array('key' => $key, 'value' => $value);
			}
			
			if( !isset($row['id']) ) $row['id'] = $row['code'];
			
			$fields[] = array('name' => 'edit', 'href' => 'index.php?edit='.$row['id']);
			$fields[] = array('name' => 'remove', 'href' => 'index.php?remove='.$row['id']);
			
			$items[] = array('field' => $fields);
		}
	}
	else
	{
		$page->set('column_count', count($columns));
		$page->set('no_row', 'Pas de '.$table.' à afficher');
	}
	
	$header->set('nav.1.href', false);
	$page->setName('admin/list');
	$page->set('count', $count);
	$page->set('column', $columns);	
	$page->set('item', $items);
}

include($root_path.'admin/footer.php');

?>