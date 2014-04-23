<?php

/***************************************************************************
*                        index.php
*                        -------------------
*   begin                : Lundi, 15 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

/*

- apparence de div class="code" lorsque le code dépasse le background change de couleur

- coloration syntaxique
- flux rss
- options du profil
- backoffice
- passer la suppression d'un commentaire et d'un code en ajax
- multilingue

- pages règles & conditions => 70%
- mise en ligne

*/

// un truc cool: une fonction à la sprintf qui lit la langue et si j'écris %{config.comment.length} %link{favory} il remplace par l'url ou la variable
// au lieu d'un define IN, true, un define('ROOT','./'); et le test se ferais sur is_defined(ROOT)
define('IN', true);
$root_path = './';
include($root_path.'common.php');
include($root_path.'includes/page_header.php');

$latest = DB::selectAll('code JOIN language ON (language.id = code.language)',
	'code.*, language.name AS language, language.extension,
	('.DB::selectQuery('user', 'name', 'WHERE user.id = code.user').') AS author,
	('.DB::selectQuery('library', 'name', 'WHERE library.id = code.library').') AS library,
	('.DB::selectQuery('view', 'COUNT(*)', 'WHERE view.code = code.id').') AS view,
	('.DB::selectQuery('vote', 'ROUND(AVG(vote.value))', 'WHERE vote.code = code.id').') AS rank',
	'WHERE code.visible = 1 ORDER BY code.ctime DESC, rank DESC, view DESC LIMIT 5'
);
humanData($latest);

$popular = DB::selectAll('code JOIN language ON (language.id = code.language)',
	'code.*, language.name AS language, language.extension,
	('.DB::selectQuery('user', 'name', 'WHERE user.id = code.user').') AS author,
	('.DB::selectQuery('library', 'name', 'WHERE library.id = code.library').') AS library,
	('.DB::selectQuery('view', 'COUNT(*)', 'WHERE view.code = code.id').') AS view,
	('.DB::selectQuery('vote', 'ROUND(AVG(vote.value))', 'WHERE vote.code = code.id').') AS rank',
	'WHERE code.visible = 1 ORDER BY view DESC, rank DESC, ctime DESC LIMIT 5'
);
humanData($popular);

/* Languages */
$languages = DB::selectAll(
	'code JOIN language ON (code.language = language.id)',
	'COUNT(code.id) AS count, language.*, language.name AS text',
	'WHERE code.visible = 1 GROUP BY language.id ORDER BY count DESC, language.name DESC LIMIT 5'
);
array_unshift($languages, array(
	'id' => 0,
	'name' => 'all',
	'text' => $lang['all'],
	'count' => DB::count('code','visible=1')
));

/* Librairies */
$libraries = DB::selectAll(
	//'library JOIN code ON (code.library = library.id)',
	//'library.*, COUNT(code.id) AS count',
	//'WHERE code.visible = 1 GROUP BY library.id ORDER BY count DESC, name DESC LIMIT 5'
	'code JOIN library ON (code.library = library.id)',
	'COUNT(code.id) AS count, library.*, library.name AS text',
	'WHERE code.visible = 1 GROUP BY library.name ORDER BY count DESC, library.name DESC LIMIT 5'
);

/* Catégories */
$categories = DB::selectAll(
	//'code_category JOIN code ON (code_category.code = code.id) JOIN category ON (category.id = code_category.category)',
	//'category.*, COUNT(code.id) AS count',
	//'WHERE code.visible = 1 GROUP BY category.id ORDER BY count DESC, name DESC LIMIT 5'
	'code JOIN code_category ON (code_category.code = code.id) JOIN category ON (category.id = code_category.category)',
	'COUNT(code.id) AS count, category.*, category.name AS text',
	'WHERE code.visible = 1 GROUP BY category.id ORDER BY count DESC, category.name DESC LIMIT 5'
);

$page->addRow('section', array(
	'title' => $lang['latest'],
	'rss' => array('href' => realurl('rss/latest.xml'), 'text' => $lang['follow_latest']),
	'code' => $latest
));
$page->addRow('section', array(
	'title' => $lang['popular'],
	'code' => $popular
));

$page->add('menu', array(
	'name' => 'language',
	'text' => $lang['browse'],
	'list' => $languages
));
$page->add('menu', array(
	'name' => 'library',
	'text' => $lang['libraries'],
	'list' => $libraries
));
$page->add('menu', array(
	'name' => 'category',
	'text' => $lang['categories'],
	'list' => $categories
));

$page->bind(array(
	'root' => realurl(),
	'PROFIL' => $profil
));

include($root_path.'includes/page_footer.php');

?>