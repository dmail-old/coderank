<?php

// ptet rajouté un truc de langue automatique genre l'attribut name est remplacé par son équivalent dans la langue dans un attribut text
// s'il n'est pas trouvé text vautdras name

// TODO toujours utilisé set au lieu de bind pour respecté le caseinsensitive
// TODO metas, css, js devrait être sous forme de tableau jusqu'à ce qu'on parse comme ça dans les pages on peut faire $template->setMeta('description', 'une description')

class Template
{
	// tout est caseinsensitive, le nom des blocs et des propriétés dans le .html et ici
	
	static $path; // seras définit juste après: chemin menant au dossier contenant les templates
	
	const LIMIT_IMBRICATE_BEGIN = 15;
	
	public $autoindex = false; // à true la propriété index est ajouté aux lignes des blocs indiquant ainsi le numéro de ligne
	public $name; // nom de ce template
	public $vars = array(); // toutes les variables du template
	public $content = ''; // html de ce template
	public $tpl; // version finale du template
	
	function __construct($name = null)
	{
		global $session, $lang;		
		
		if( isset($session) )
		{
			$data = $session->user->data;
			$data['logged'] = $session->logged;
			$data['locked'] = $session->user->isLocked();
			$data['visitor'] = $session->user->isVisitor();
			$data['member'] = $session->user->isMember();
			$data['admin'] = $session->user->isAdmin();
			$data['moderator'] = $session->user->isModerator();
			
			$this->bind('me', $data);
		}		
		
		if( isset($lang) ) $this->bind('ranking', $lang['rank']);
		$this->bind('root', server_path());
		
		if( $name ) $this->setName($name);
	}
	
	function setName($name)
	{
		$this->name = setExtension($name, 'html');
	}
	
	// cette fonction feras la même chose que set, mais par référence
	function bind($name, $value = null)
	{
		if( is_array($name) )
		{
			foreach($name as $key => $value)
			{
				$this->bind($key, $value);
			}
		}
		else
		{
			$this->vars[$name] = $value;
		}
	}
	
	function bindLang()
	{
		global $lang;
		$args = func_get_args();
		$i = count($args);
		while($i--) $this->bind($args[$i], $lang[$args[$i]]);
	}
	
	static function run($name, $vars = null)
	{
		$template = new Template($name);
		$template->execute($vars);
	}
	
	function getContent()
	{
		$content = $this->content;
		
		if( !$content )
		{
			if( !$this->name )
			{
				warning('Le nom du template n\'a pas été défini');
				return false;
			}
			$file = self::$path .'/'. $this->name;
		
			if( !is_file($file) )
			{
				warning('Le template '.$file.' n\'existe pas');
				return false;
			}
			
			$content = file_get_contents($file);
			$this->content = $content;
		}
		
		return $content;
	}
	
	function setVars($vars)
	{
		$this->vars = $vars;
	}
	
	// retourne la valeur trouvé au chemin $path
	function get($path)
	{
		return array_getAt($this->vars, $path, true);
	}
	
	// définit une valeur au chemin $path
	function set($path, $value = null)
	{
		if( is_array($path) ) foreach($path as $key => $value) $this->set($key, $value);
		else return array_setAt($this->vars, $path, $value, true);
	}
	
	function add($path, $value)
	{
		$parts = explode('.', $path);
		$i = 0;
		$j = count($parts);
		$vars = &$this->vars;
		for(;$i<$j;$i++)
		{
			$name = array_key_exists_nc($parts[$i], $vars); // ceci permet de récupérer partie du chemin sans tenir compte de la casse
			if( $name === false ) $vars[$name = $parts[$i]] = array();	
			if( $i < $j-1 ) $vars = &$vars[$name][count($vars[$name])-1];
			else $vars[$name][] = $value;
		}
	}
	
	// ajoute une ligne de résultat au tableau situé situé au chemin $path
	// DEPRECATED
	function addRow($path, $row)
	{
		return call_user_func_array(array($this, 'add'), func_get_args());
	}
	
	function execute($vars = null)
	{
		if( $string = $this->parse($vars) )
		{
			// debug(htmlspecialchars($string));
			eval(' ?>' . $string . ' <?php ');
		}
	}
	
	function parseInclude($tpl)
	{
		// remplace INCLUDE AS par le template correspondant en remplacant le nom du template par AS
		// on pourrait plutot avoir un commande genre INCLUDE code REPLACE code BY section.code
		// nan c'est dans codelist.html qu'en fait on préciserais le mot clé: context et on ferais INCLUDE codelist CONTEXT code
		return preg_replace_callback('#<!-- INCLUDE (.*?) AS (.*?) -->#', array($this, 'replaceInclude'), $tpl);
	}
	
	// sachant qu'en ligne le contenu de js,css etc seras les fichiers en version compréssé
	function parseFiles($names, $extension)
	{
		global $root_path;
		
		if( is_string($names) ) $names = array($names);
		$i = 0;
		$j = count($names);
		$result = '';
		$server_path = server_path();
		$str = $extension == 'js' ? '<script src="#" type="text/javascript"></script>' : '<link href="#" type="text/css" rel="stylesheet">';
		
		for(;$i<$j;$i++)
		{
			$name = $names[$i];
			$path = $root_path. $extension . '/' . setExtension($name, $extension);
			
			if( !is_file($path) )
			{
				warning('Le fichier '.$path.' n\'existe pas');
				array_splice($names, $i, 1);
				$i--;
				$j--;
			}
			else
			{
				$path = str_replace($root_path, $server_path.'/', $path); // pas de chemin relatif pour les fichier css et js
				$result.= str_replace('#', $path, $str);
			}
		}
		return $result;
	}
	
	function parseMetas($metas)
	{
		// alias bien pratique
		$alias = array('charset' => 'type', 'style' => 'style-type', 'script' => 'script-type');
		// metas utilisant l'attribut "http-equiv"
		$http_equiv = array('language','type','refresh','pragma','expires','style-type','script-type','cache-control');
		// metas commencant par "Content-"
		$content_prefix = array('language','type','style-type','script-type');
		// le prefix content à mettre devant
		$prefix = 'content-';
		
		$output = '';
		foreach($metas as $name => $value)
		{
			$attr = 'name';
			$name = strtolower($name);
			
			if( strpos($name, $prefix) === 0 ) $name = substr($name, strlen($prefix));
			if( isset($alias[$name]) ) $name = $alias[$name];
			if( in_array($name, $http_equiv) )
			{
				$attr = 'http-equiv';
				if( in_array($name, $content_prefix) )
				{
					if( $name == 'type' ) $value = 'text/html; charset='.$value;
					$name = $prefix.$name;
				}
				$name = uchyphenate($name);
			}
			
			$output.= "<meta $attr=\"$name\" content=\"$value\" />\n";
 		}
		return $output;
	}
	
	function parse($vars = null)
	{
		if( $vars )
		{
			$this->setVars($vars); // ceci supprime toutes les variables qui aurait été ajoutées avant
		}
		if( !$this->tpl = $this->getContent() )
		{
			return false;
		}
		
		$this->tpl = $this->parseInclude($this->tpl);
		// prepare les blocks BEGIN END
		$this->begin_imbrique($this->tpl, 0);
		$this->tpl = preg_replace('#<!-- (BEGIN|END) (.*?) -->#', '', $this->tpl);
		// remplace les variables
		$this->replaceVars($this->vars);
		
		$preg_replace = array(
			'<!-- IF (!)?([a-zA-Z0-9_\\.]+)([a-zA-Z0-9=><_!"\'\\.{}& ]+)? -->'  	=> 'if( \\1$this->get("\\2")\\3 ){',
			'<!-- ELSEIF (!)?([a-zA-Z0-9_\\.]+)([a-zA-Z0-9=><_!"\'\\.{}& ]+)? -->'	=>	'}else if( \\1$this->get("\\2")\\3 ){',
			'<!-- ELSE -->'															=>	'}else{',
			'<!-- ENDIF -->'														=>	'}',
			'<!-- INCLUDE (.*?) -->'												=> 'Template::run("\\1", $this->vars);'
		);
		foreach($preg_replace as $key => $val)
		{
			$this->tpl = preg_replace('#'.$key.'#', '<?php ' . $val . ' ?>', $this->tpl);
		}
		
		return $this->tpl;
	}
	
	function replaceInclude($matches)
	{
		$path = $matches[1];
		$as = $matches[2];
		$name = pathinfo($matches[1], PATHINFO_FILENAME);
		$template = new Template($path);
		
		$content = $template->getContent();
		$content = preg_replace('#\\{'.$name.'#', '{'.$as, $content);
		$content = preg_replace('#<!-- (BEGIN|END|IF|ELSEIF|INCLUDE .*? AS) '.$name.'(.*?) -->#', '<!-- $1 '.$as.'$2 -->', $content);
		$content = $this->parseInclude($content);
		
		return $content;
	}
	
	function replaceVars($array, $path = '')
	{
		foreach($array as $key => $value)
		{
			if( $path === '' )
			{
				switch(strtolower($key)) // au premier tour de boucle les clés meta, css et js sont spéciales 
				{
					case 'meta': $value = $this->parseMetas($value); break;
					case 'css': case 'js': $value = $this->parseFiles($value, $key); break;
				}
			}
			
			$object = is_object($value);
			if( $object && method_exists($value, '__toString') )
			{
				$value = $value->__toString();
				$object = false;
			}
			if( is_array($value) || $object )
			{
				$this->replaceVars($value, $path.$key.'.');
			}
			else
			{
				$this->tpl = preg_replace('#\\{' . $path.$key . '\\}#i', $value, $this->tpl);
			}
		}
	}
	
	/*
	<!-- BEGIN foo -->
		{foo.name}
		<!-- BEGIN foo.bar -->
			{foo.bar.name}
		<!-- END foo.bar -->
	<!-- END foo -->
	
	devient
	
	{foo.0.name}
		{foo.0.bar.0.name}
	*/
	function begin_imbrique($text_begin, $imbrique)
	{
		if( $imbrique > self::LIMIT_IMBRICATE_BEGIN ) return;
		$regex_begin = '<!-- BEGIN (.*?) -->(.*?)(<!-- BEGINELSE -->(.*?))?<!-- END \\1 -->';
		if( !preg_match_all('#'.$regex_begin.'#s', $text_begin, $matches) ) return;
		
		$j = count($matches[0]);
		for($i=0;$i<$j;$i++)
		{
			$block_path = $matches[1][$i];
			$text = $matches[2][$i];
			$new_text = '';
			$block = $this->get($block_path);
			$l = count($block);
			
			for($k=0;$k<$l;$k++)
			{
				$tmp_text = $text;
				$tmp_text = preg_replace('#(\\{' . $block_path . ')(.*?\\})#is', '\\1.' . $k . '\\2', $tmp_text);
				$tmp_text = preg_replace('#(<!-- (BEGIN|END|IF|ELSEIF) !?' . $block_path . ')(.*? -->)#is', '\\1.' . $k . '\\3', $tmp_text);
				$new_text.= $tmp_text;
				
				/*if( isset($block[$k]['name']) ){
					echo $block_path.'->'.$block[$k]['name'].'<br>';
				}*/				
				if( $this->autoindex ) $this->set($block_path.'.'.$k.'.index', $k);
			}
			$this->tpl = str_replace($text, $new_text, $this->tpl);
			if( preg_match('#' . $regex_begin . '#s', $new_text) ) $this->begin_imbrique($new_text, $imbrique+1);
		}
	}
}

// force $path à finir par $ext
function setExtension($path, $ext)
{
	return getExtension($path) == $ext ? $path : $path.'.'.$ext;
}

function getExtension($path)
{
	return pathinfo($path, PATHINFO_EXTENSION);
}

// transforme hello-world en Hello-World
function uchyphenate($str, $delimiter = '-')
{
	$delimiter_space = $delimiter.' ';
	return str_replace($delimiter_space, $delimiter, ucwords(str_replace($delimiter, $delimiter_space, $str)));
}

// version caseinsensitive de array_key_exists, retourne la clé trouvée ou false
function array_key_exists_nc($key, $search)
{
    if( array_key_exists($key, $search) ) return $key;
    if( !(is_string($key) && is_array($search) && count($search)) ) return false;
    
	$key = strtolower($key);
    foreach($search as $k => $v)
	{
        if( strtolower($k) == $key ) return $k;
    }
    return false;
}

// retourne la valeur trouvé dans $array en suivant les clés contenu dans $parts, on peut activer ou non la case des clés avec $nocase
function array_getAt($array, $parts, $nocase = false)
{
	if( is_string($parts) ) $parts = explode('.', $parts);
	$i = 0;
	$j = count($parts);
	for(;$i<$j;$i++)
	{
		$key = $parts[$i];
		if( !is_array($array) ) return null;
		$key = $nocase ? array_key_exists_nc($key, $array) : array_key_exists($key, $array);
		if( $key === false ) return null;
		$array = $array[$key];
	}
	return $array;
}

// définit une valeur dans $array en suivant les clés contenu dans $parts
// lorsque la clé n'existe pas un tableau est créé
function array_setAt(&$array, $parts, $value, $nocase = false)
{
	if( is_string($parts) ) $parts = explode('.', $parts);
	$i = 0;
	$j = count($parts);
	for(;$i<$j;$i++)
	{
		$key = $parts[$i];
		if( $i < $j -1 )
		{
			if( !is_array($array) ) return false;
			if( false === ($nocase ? array_key_exists_nc($key, $array) : array_key_exists($key, $array)) ) $array[$key] = array();
			$array = &$array[$key];
		}
		else
		{
			$array[$key] = $value;
		}
	}
	return true;	
}

?>