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

$category_max = 4;

class Code extends DB_Tablerule
{
	function __construct()
	{
		parent::__construct('code');
		
		$this->setRule('name', 'regexp', 'description');
		
		$this->setRule('description', 'min', 10);
		$this->setRule('description', 'max', 150);
		$this->setRule('description', 'regexp', 'description');
		
		$this->setRule('demo', 'min', 10);
		$this->setRule('demo', 'max', 3000);
		
		$this->setRule('source', 'min', 50);
		$this->setRule('source', 'max', 50000);
		
		$this->setRule('version', 'numeric');
		$this->setRule('version', 'min', 0.1);
		$this->setRule('version', 'max', 99.99);
	}
	
	function getErrors(&$fields, $update = false)
	{
		$this->language = $fields['language'];
		return parent::getErrors($fields, $update);
	}
	
	function taken($field, $value)
	{
		return DB::select($this->name, 'id', 'WHERE name = ? AND language = ?', $value, $this->language);
	}
}

$languages = DB::selectAll('language','id, name, name AS text, extension', 'ORDER BY name');
$libraries = DB::selectAll('library','id, name, language, name AS text', 'ORDER BY name');
$categories = DB::selectAll('category','id, name, language, name AS text', 'ORDER BY name');

$languages[0]['checked'] = true;

$library_group = array_groupBy($libraries, 'language');
$library_options = array();
foreach($library_group as $language_id => $option)
{
	$language = array_find($languages, 'id', $language_id);
	$library_options[] = array(
		'name' => $language['name'],
		'group' => $option
	);
}
array_unshift($library_options, array('name' => 'null', 'text' => $lang['library_empty']));

$category_group = array_groupBy($categories, 'language');
$category_options = array();
foreach($category_group as $language_id => $option)
{
	$language = array_find($languages, 'id', $language_id);
	$category_options[] = array(
		'name' => $language['name'],
		'group' => $option
	);
}

$fields = array();
$hidden_fields = '';
$referer = get_referer();
if( !$referer ) $referer = realurl($_SERVER['REQUEST_URI']); // si pas de referer on rechargeras la page
$hidden_fields.= '<input type="hidden" name="referer" value="'.$referer.'" />';

$user = $session->user;
$message = false;
$code = new Code();

if( isset($_REQUEST['moderate']) || isset($_POST['accept']) || isset($_POST['refuse']) )
{
	$title = $lang['code_moderate_title'];
	$hidden_fields.= '<input type="hidden" name="moderate" value="1" />';
	
	if( !$session->user->isAdmin() && !$session->user->isModerator() )
	{
		$message = array('type' => 'warning', 'text' => $lang['code_moderate_forbidden']);
	}
	else
	{
		$subcode = $code->selectBy('visible', 0);
		if( !$subcode )
		{
			$message = array('type' => 'success', 'text' => $lang['code_moderate_end']);
		}
		else
		{
			$code->set($subcode->data);
			$page->bind('moderate', true);
			$hidden_fields.= '<input type="hidden" name="id" value="'.$code->get('id').'" />';
			
			if( isset($_POST['id']) && $_POST['id'] != $code->get('id') )
			{
				$message = array('type' => 'warning', 'text' => $lang['code_moderate_conflict']);
			}
		}
	}
}
else if( isset($_REQUEST['id']) )
{
	$title = $lang['code_edit_title'];
	$hidden_fields.= '<input type="hidden" name="id" value="'.$_REQUEST['id'].'" />';
	$subcode = $code->selectBy('id', $_REQUEST['id']);
	
	if( !$subcode )
	{
		$message = array('type' => 'error', 'text' => $lang['code_edit_not_found']);
	}
	else
	{
		$code->set($subcode->data);
		$owner = $code['user'] == $session->user['id'];
		// si je suis pas l'admin je dois être le propriétaire du code pour pouvoir le modifier
		if( !$owner && !$session->user->isAdmin() )
		{
			$message = array('type' => 'error', 'text' => sprintf($lang['code_edit_forbidden'], $code['name']));
		}
		else if( $owner )
		{
			$fields['mtime'] = time(); // date de modification du commentaire
		}
	}
}
else
{
	$title = $lang['code_add_title'];
	if( ($count = DB::count('code', 'WHERE code.visible <> 1 AND code.user = '.$user['id'])) > $config['code']['queue'] )
	{
		$code_link = '<a href="'.realurl('user/'.$user['name'].'/code').'">'.$count.'</a>';
		$message = array('type' => 'warning', 'text' => sprintf($lang['code_queue'], $code_link));
	}
}

$page->bind('title', $title);
$header->bind('title', $title);
$header->addRow('nav', array('text' => $title));

if( $message)
{
	$page->bind('message', $message);
	include($root_path.'includes/page_footer.php');
}

if( $code->get('id') )
{
	$category = DB::selectAll(
		'code_category JOIN category ON (code_category.category = category.id)',
		'category.id',
		'WHERE code_category.code = '.$code->get('id')
	);
	$source = DB::select('source', 'source', 'WHERE code = '.$code->get('id'));
	$demo = DB::select('demo', 'demo', 'WHERE code = '.$code->get('id'));
	
	foreach($category as $cat)
	{
		$array[] = $cat['id'];
	}

	$code->set('category', $array);
	$code->set('source', $source['source']);
	$code->set('demo', $demo['demo']);
}

$page_fields = $code->get('name', 'description', 'source', 'demo', 'version', 'language', 'library', 'category');
if( $page_fields['version'] )
{
	$version = explode('.', $page_fields['version']);
	$page_fields['major'] = $version[0];
	$page_fields['minor'] = $version[1];
}
else
{
	$page_fields['major'] = 1;
	$page_fields['minor'] = 0;
}

$errors = array();
if( isset($_POST['submit']) || isset($_POST['accept']) || isset($_POST['refuse']) )
{
	if( isset($_POST['language']) )
	{
		if( $language = array_find($languages, 'name', $_POST['language']) ) $fields['language'] = $language['id'];
		else $errors['language'] = sprintf($lang['language_invalid'], $_POST['language']);
	}
	
	if( isset($_POST['library']) )
	{
		if( $_POST['library'] == 'null' ) $fields['library'] = null;
		else if( $library = array_find($libraries, 'name', $_POST['library']) ) $fields['library'] = $library['id'];
		else $errors['library'] = sprintf($lang['library_invalid'], $_POST['library']);
	}
	
	if( isset($_POST['version']) )
	{
		$fields['version'] = $_POST['major'].'.'.$_POST['minor'];
		$page_fields['major'] = $_POST['major'];
		$page_fields['minor'] = $_POST['minor'];
	}
	
	if( isset($_POST['category']) )
	{
		$category = $_POST['category'];
		foreach($category as $index => $category_name)
		{
			if( $cat = array_find($categories, 'name', $category_name) )
			{
				$category[$index] = $cat['id'];
			}
			else
			{
				if( !$errors['category'] ) $errors['category'] = '';
				$errors['category'].= sprintf($lang['category_invalid'], $category_name);
			}
		}
		$page_fields['category'] = $category;
		
		if( count($category) > $category_max )
		{
			$errors['category'] = sprintf($lang['category_long'], $category_max);
		}
	}
	
	$fields = array_merge($fields, array(
		'name' => $_POST['name'],
		'description' => $_POST['description'],
		'source' => $_POST['source'],
		'demo' => $_POST['demo'],
		'mtime' => time()
	));
	
	// on est en mode update ou modération
	if( isset($_REQUEST['id']) )
	{
		$errors = array_merge($errors, $code->getErrors($fields, true));
		$page_fields = array_merge($page_fields, $fields);
		
		if( isset($_POST['accept']) )
		{
			$fields['visible'] = 1;
		}
		else if( isset($_POST['refuse']) )
		{
			if( isset($_POST['motif']) && $_POST['motif'] > 2 && $_POST['motif'] < 6 )
			{
				if( $_POST['motif'] == 5 )
				{
					if( !$_POST['motif_other'] ) $_POST['motif'] = 2; // non spécifié
					else $fields['message'] = $_POST['motif_other'];
				}
			}
			else
			{
				$_POST['motif'] = 2; // non spécifié
			}
			$fields['visible'] = $_POST['motif'];
		}
		else
		{
			if( $code['visible'] > 1 )
			{
				$fields['visible'] = 0; // l'utilisateur veut reproposer le code qui avait été refusé
			}
		}
		
		if( !$errors )
		{
			$source = $fields['source'];
			unset($fields['source']);
			$demo = $fields['demo'];
			unset($fields['demo']);
			
			if( $code->update($fields) )
			{
				if( $demo && !$code->identic('demo', $demo) ) // la démo a changée
				{
					DB::update('demo', array('demo' => $demo), 'WHERE code = '.$code['id']);
				}
				if( $source && !$code->identic('source', $source) ) // la source a changée
				{
					DB::update('source', array('source' => $source), 'WHERE code = '.$code['id']);
				}
				if( $code['category'] != $category ) // catégories ont changées
				{
					DB::delete('code_category', 'WHERE code = '.$code->get('id')); // on supprime tout les catégories
					if( $category ) // et on réinsère les nouvelles
					{
						foreach($category as $category_id)
						{
							DB::insert('code_category', array('code' => $code->get('id'), 'category' => $category_id));
						}
					}
				}
			}
		}
	}
	else
	{
		$visible = $session->user->isAdmin() || $session->user->isModerator();
		
		$fields = array_merge($fields, array(
			'user' => $session->user->get('id'),
			'visible' => $visible,
			'ctime' => time()
		));
		$errors = array_merge($errors, $code->getErrors($fields));
		$page_fields = array_merge($page_fields, $fields);		
		
		if( !$errors )
		{
			$source = $fields['source'];
			unset($fields['source']);
			$demo = $fields['demo'];
			unset($fields['demo']);
			
			if( $code->insert($fields) )
			{
				DB::insert('source', array('code' => $code['id'], 'source' => $source));
				DB::insert('demo', array('code' => $code['id'], 'demo' => $demo));
				
				if( $category )
				{
					foreach($category as $category_id)
					{
						DB::insert('code_category', array('code' => $code->get('id'), 'category' => $category_id));
					}
				}
			}
		}
	}
	
	if( !$errors )
	{
		$code_lang = 'added';
		
		// lorsque au terme de tout ceci le code est visible
		if( $code['visible'] == 1 )
		{
			$generate = !isset($_REQUEST['id']);
			
			// bien qu'identique demo et source doivent obtenir une version coloré lorsqu'on modère un eocde
			if( isset($_POST['accept']) )
			{			
				$demo = $_POST['demo'];
				$source = $_POST['source'];
				$generate = true;
			}
			
			// si le champ source ou demo existe encore c'est qu'il a changé (il est supprimé si identique par getErrors en mode update)
			if( $demo || $source )
			{		
				include_once($root_path.'geshi/geshi.php');
				$geshi = new GeSHi();
				
				$geshi->set_language(strtolower($language['name']));
				$geshi->enable_classes();
				$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
				
				if( $source )
				{
					$geshi->set_source($source);
					$result = $geshi->parse_code();
					DB::replace('source_colored', array('source' => $result, 'code' => $code['id']));
				}
				if( $demo )
				{
					$geshi->set_source($demo);
					$result = $geshi->parse_code();
					DB::replace('demo_colored', array('demo' => $result, 'code' => $code['id']));
				}
			}
			
			var_dump($generate);
			
			$href = realurl('code/'.$code['name'].'.'.$language['extension']);
			// regénère le flux rss des codes les plus récents si - on vient de l'ajouter et qu'on est modérateur - on vient de l'accepter
			if( $generate )
			{
				$rss = new Rss();
				$rss->addChannel('Coderank - Nouveautés', null, null);
				$codes = DB::selectAll('code JOIN language ON (code.language = language.id)', 'code.*, language.extension', 'WHERE code.visible = 1 ORDER BY code.ctime DESC LIMIT 10');
				foreach($codes as $item)
				{
					$rss->addItem($item['name'], realurl('code/'.$item['name'].'.'.$item['extension']), $item['description'], date('D j F Y G:i:s', $item['ctime']).' -0800');
				}
				file_put_contents($root_path.'rss/latest.xml', $rss);
			}
		}
		else
		{
			$code_lang = 'added_user';
			$owner = $session->user->selectBy('id', $code['user']);
			$href = realurl('user/'.$owner['name']);
		}
		
		if( isset($_POST['accept']) )
		{
			$code_lang = 'accepted';
		}
		else if( isset($_POST['refuse']) )
		{
			$code_lang = 'refused';
		}
		else if( isset($_REQUEST['id']) )
		{
			$code_lang = 'edited';
		}
		
		$message = sprintf($lang['code_'.$code_lang], '<a href="'.$href.'">'.$code['name'].'</a>');
		$session->user->update('message', $message);
		if( isset($_REQUEST['moderate']) ) redirect(realurl('add.php?moderate=true'));
		else{
			if( basename($referer) == 'register.php' ) $url = realurl();
			redirect($referer);
		}
	}
}

// met selected = true aux options choisies
if( $language = &array_find($languages, 'id', $page_fields['language']) )
{
	unset($languages[0]['checked']);
	$language['checked'] = true;
	
	if( $library = array_find($libraries, 'id', $page_fields['library']) )
	{
		$optgroup = &array_find($library_options, 'name', $language['name']);
		if( $optgroup && $option = &array_find($optgroup['group'], 'name', $library['name']) )
		{
			$option['selected'] = true;
		}
	}
	
	$category = $page_fields['category'];
	if( $category && $optgroup = &array_find($category_options, 'name', $language['name']) )
	{
		foreach($category as $category_id)
		{
			if( !($cat = array_find($categories, 'id', $category_id)) ) continue;
			if( !($option = &array_find($optgroup['group'], 'name', $cat['name'])) ) continue;
			$option['selected'] = true;
		}
	}
}

$page->set('lang', array(
	'name' => $lang['code_name'],
	'description' => $lang['code_description'],
	'source' => $lang['code_source'],
	'demo' => $lang['code_demo'],
	'demo_detail' => $lang['code_demo_detail'],
	'language' => $lang['code_language'],
	'category' => $lang['categories'],
	'category_detail' => sprintf($lang['code_category_detail'], $category_max),
	'library' => $lang['library'],
	'version' => $lang['code_version'],
	'version_need' => $lang['version_need'],
	'version_above' => $lang['version_above'],
	'add' => $lang['code_add'],
	'motif' => $lang['motif'],
	'motif_code' => $lang['motif_code'],
	'motif_demo' => $lang['motif_demo'],
	'motif_other' => $lang['motif_other'],
	'accept' => $lang['accept'],
	'refuse' => $lang['refuse'],
));

$page->set(array(
	'name' => $page_fields['name'],
	'description' => $page_fields['description'],
	'source' => $page_fields['source'],
	'demo' => $page_fields['demo'],
	'version' => $page_fields['version'],
	'major' => $page_fields['major'],
	'minor' => $page_fields['minor']
));
$page->set('hidden', $hidden_fields);
$page->set('error', $errors);
$page->set('language', $languages);
$page->set('library', $library_options);
$page->set('category', $category_options);

include($root_path.'includes/page_footer.php');

?>