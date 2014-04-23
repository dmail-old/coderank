<?php
/***************************************************************************
*                        functions.php
*                        -------------------
*   begin                : Saturday, Feb 13, 2001
*   copyright            : Angelblade
*   email                : Angelblade@hotmail.fr
*
*
***************************************************************************/

/* Accorde la clé de langue $name avec la valeur $value, selon que value est singulier ou pluriel pour le moment */
function agree($key, $value)
{
	global $lang;
	
	if( is_array($value) )
	{
		if( count($value) ) $key.= 's';
	}
	else if( is_numeric($value) )
	{
		if( $value > 0 ) $key.= 's';
	}
	return $lang[$key];
}

define('RAND_LOWER', 1);
define('RAND_UPPER', 2);
define('RAND_NUMERIC', 4);

// Retourne une chaine aléatoire, $options peut contenir une chaine avec les caracs qu'on veut ou une combinaison des option LOWER,UPPER,NUMERIC
function str_rand($length = 16, $options = 7)
{
	$chars = '';
	if( is_int($options) )
	{
		if( $options & RAND_LOWER ) $chars.= implode('',range('a','z'));
		if( $options & RAND_UPPER ) $chars.= implode('',range('A','Z'));
		if( $options & RAND_NUMERIC ) $chars.= implode('',range(0,9));
	}
	else if( is_string($options) ) $chars = $options;
	else if( is_array($options) ) $chars = implode('', $options);
	
	$length = (int)$length;
	$nbr = strlen($chars);
	
	if( $length <= 0 || $nbr <= 0 ) return '';
	
	$str  = '';
	for($i = 0; $i < $length; $i++)
	{
		$str.= $chars[mt_rand(0,($nbr-1))];
	}
	return $str;
}

function debug($value)
{
	echo '<pre>';
	print_r($value);
	echo '</pre>';
}

function sizeName($size)
{
    $names = array(
		'byte' => 1,
		'kb' => 1024,
		'mb' => 1024*1024,
		'gb' => 1024*1024*1024,
		/*" TB", " PB", " EB", " ZB", " YB"*/
	);
	
	while($value = current($names))
	{
		$key = key($names);
		$nextValue = next($names);
		if( $nextValue === false || $size < $nextValue )
		{
			$nb = round($size/$value, 2);
			if( $key == 'byte' && $nb > 1 ) $key.= 's';
			return array($nb, $key);
		}
	}
}

function timeName($time)
{	
	$names = array(
		'second' => 1,
		'minute' => 60,
		'hour' => 60*60,
		'day' => 60*60*24,
		'week' => 60*60*24*7,
		'month' => 60*60*24*30,
		'year' => 60*60*24*365,
		'century' => 60*60*24*365*100,
		'millenary' => 60*60*24*365*100*10
	);
	
	while($value = current($names))
	{
		$key = key($names);
		$nextValue = next($names);
		if( $nextValue === false || $time < $nextValue )
		{
			$nb = floor($time/$value);
			if( $nb > 1 ) $key.= 's';
			return array($nb, $key);
		}
	}
}

function humanCtime($ctime, $now = null)
{
	global $lang;
	
	if( $now === null ) $now = time();
	
	$timename = timeName($now - $ctime);
	$lang_ctime = $lang[$timename[1]] ? $lang[$timename[1]] : $timename[1];
	return sprintf($lang['time_ago'], $timename[0], $lang_ctime);
}

function humanSize($size)
{
	global $lang;
	
	$sizename = sizeName($size);
	$lang_size = $lang[$sizename[1]] ? $lang[$sizename[1]] : $sizename[1];
	if( $sizename[1] != 'byte' && $sizename[1] != 'bytes' ) $lang_size = mb_ucfirst($lang_size);
	return $sizename[0].' '.$lang_size;
}

function humanData(&$array, $owner = null)
{
	global $session, $lang;
	
	$user = $session->user;
	$i = count($array);
	$time = time();
	$server_path = server_path();
	
	while($i--)
	{
		$item = &$array[$i];
		
		$item['ctime'] = humanCtime($item['ctime'], $time);
		if( isset($item['size']) ) $item['size'] = humanSize($item['size']);
		if( isset($item['author']) )
		{
			$text = $item['author'];
			if( $text == $user->get('name') )
			{
				$text = $lang['me'];
				$item['mine'] = true;
			}
			else if( $owner && $text == $owner )
			{
				$text = sprintf($lang['from_author'], $text);
				$item['owner'] = true;
			}
			
			$item['author'] = array(
				'href' => $server_path.'/user/'.mb_strtolower($item['author']),
				'name' => $item['author'],
				'text' => $text
			);
		}
		if( isset($item['extension']) )
		{
			$item['ranking'] = $lang['ranking'];
			$item['filename'] = mb_strtolower($item['name']).'.'.$item['extension'];
			$item['href'] = $server_path.'/code/'.$item['filename'];
			$item['title'] = sprintf($lang['code_title'], $item['name'].'.'.$item['extension']);
		}
		if( isset($item['language']) )
		{
			$href = $server_path.'/search.php?';
			
			if( $item['library'] )
			{
				$href.= 'library='.$item['library'];
				$text = $item['library'];
			}
			else
			{
				$href.= 'language='.$item['language'];
				$text = $item['language'];
			}
			
			if( isset($item['version']) )
			{
				$href.= '&version='.$item['version'];
				$text.= ' '.$item['version'];
			}
			
			$item['language'] = array(
				'href' => $href,
				'name' => $item['language'],
				'text' => $text
			);
		}
		if( isset($item['code']) && isset($item['extension']) ) // commentaire sur un code
		{
			$item['code'] = array(
				'href' => $server_path.'/code/'.mb_strtolower($item['code']).'.'.$item['extension'],
				'name' => $item['code'],
				'text' => $lang['about'],
				'extension' => $item['extension']
			);
		}
		if( $owner === true )
		{
			$item['mine'] = true;
		}
		
		if( $item['mine'] || $item['favory'] || $user->isAdmin() )
		{
			$item['control'] = array(
				array('name' => 'edit', 'href' => $server_path.'/add.php?id='.$item['id'], 'text' => $lang['edit']),
				array('name' => 'remove', 'href' => $server_path.'/delete.php?id='.$item['id'], 'text' => $lang['delete'])
			);
			
			if( !isset($item['language']) ) // commentaire
			{
				$item['control'][0]['href'] = $server_path.'/comment.php?id='.$item['id'];
				$item['control'][1]['href'] = $server_path.'/delete.php?id='.$item['id'].'&comment=true';
			}
			else if( $item['favory'] )
			{
				array_shift($item['control']); // supprime l'action modifier
				$item['control'][0]['text'] = $lang['delete_favory'];
				$item['control'][0]['href'] = $server_path.'/delete.php?id='.$item['favory'].'&favorite=true';
			}
			
			if( isset($item['visible']) )
			{
				if( $item['visible'] == 0 )
				{
					$item['control'][0]['text'] = $lang['edit_waiting'];
					$item['description'] = $lang['code_waiting'];
				}
				else if( $item['visible'] > 1 ) // code refusé
				{
					$item['control'][0]['text'] = $lang['edit_refused'];
					
					if( isset($item['description']) )
					{
						switch($item['visible'])
						{
							case 2: $why = $lang['motif_unknown']; break;
							case 3: $why = $lang['motif_code']; break;
							case 4: $why = $lang['motif_demo']; break;
							// case 4: $why = $item['message'] ? 'Ce code ressemble à '.$item['message'] : 'Ce code ressemble à un autre'; break;
							case 5: default: $why = $item['message']; break;
						}
						$item['description'] = $lang['motif_reason'].': '.$why;
					}
				}
			}
		}
	}
	return $array;
}

function int($int)
{
    if( is_numeric($int) === TRUE )
	{
		if( (int)$int == $int )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	else
	{
		return FALSE;
	}
}

function sizeformat($val)
{
	$end = array('octets','Ko','Mo');
	if( $val < 999 ){ $val = round($val/1024,1).' '.$end[0]; }
	else if( $val < 999999 ){ $val = round($val/1024,1).' '.$end[1]; }
	else{ $val = round($val/(1024*1024),1).' '.$end[2]; }
	if( int($val) ){ $val.= '.0'; }
	
	return str_replace('.', ',', $val);
}

function member_of($group)
{
	global $userdata;
	$user_group = explode('-', $userdata['user_group']);
	
	return in_array($group, $user_group) || $userdata['user_level'] == ADMIN;
}

// Include language files 
function language_include($category) 
{ 
	global $root_path, $config;
	
	$dirname = $root_path.'language/lang_'.$config['default_lang']; 
	$dir = opendir($dirname); 

	while($file = readdir($dir)) 
	{ 
		if( ereg("^lang_" . $category, $file) && is_file($dirname . "/" . $file) && !is_link($dirname . "/" . $file) ) 
		{ 
			$incname = str_replace("lang_" . $category, "", $file); 
			include($dirname . '/lang_' . $category . $incname); 
		} 
	} 
	closedir($dir); 
}

// retourne soit l'url de la racine du site soit l'url de la racine + l'url qu'on passe en paramètre
function realurl($url = null)
{
	global $config;
	
	$scheme = ($config['session']['cookie']['secure']) ? 'https' : 'http';
	$host = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($config['host']));
	$script_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($config['script_path']));
	$script_name = $script_name ? '/'.$script_name : $script_name;
	
	$root = $scheme .'://'. $host . $script_name;
	
	if( $url )
	{
		// remplace tous les ../ ou .\ par rien, on considère que c'est qu'on veut remonter à la racine dans ce cas		
		// $url = preg_replace('#^(?:\/?\.*(\/)?)+#', '$1', trim($url));
		$url = preg_replace('#^(\/?\.*\/)#', '/', $url);
		if( $url[0] !== '/' ) $url = '/'.$url;
		
		$url = new Url($url);
		$url->scheme = $scheme;
		$url->host = $host;
		// met le chemin vers la racine s'il n'est pas dans l'url
		if( $script_name && strpos($url->path, $script_name) !== 0 )
		{
			$url->path = $script_name . ($url->path[0] == '/' ? $url->path : '/'.$url->path);
		}
		
		$root = $url->__toString();
	}
	
	return $root;
}

// passeras dans realurl puisque c'est exactement ce que fait la fonction
// transforme ../img en un chemin absolu
function root_url($url)
{
	global $config;
	
	$url = preg_replace('#^(\/?\.*\/?)*#', '/', trim($url));
	$url = preg_replace('#'.$config['script_path'].'#', '', $url);
	return server_path() . $url;
}

// passeras dans la fonction realurl()
function server_path($script = true)
{
	global $config;
	
	$server_protocol = ($config['session']['cookie']['secure']) ? 'https://' : 'http://';
	$server_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($config['server_name']));
	
	if( $script )
	{
		$script_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($config['script_path']));
		$script_name = ($script_name == '') ? $script_name : '/'.$script_name;
		return $server_protocol.$server_name.$script_name;
	}
	return $server_protocol.$server_name;
}

function redirect($url)
{
	global $db, $config;
	
	if( is_a($url, Url) ) $url = $url->__toString();
	
	$url = str_replace('&amp;', '&', $url); // Make sure no &amp;'s are in, this will break the redirect
	if( strstr(urldecode($url), "\n") || strstr(urldecode($url), "\r") || strstr(urldecode($url), ';url') )
	{
		error('Tried to redirect to potentially insecure url.');
	}
	//$url = realurl($url);
	//$url = new Url($url);
	
	/*if( basename($url->path) == basename($_SERVER['PHP_SELF']) )
	{
		error('L\'url de destination '.$url.' et l\'url courante '.$_SERVER['PHP_SELF'].' sont identique');
	}*/
	
	/*if( $url->host != $config['host'] )
	{
		error('L\'url de destination ne fait pas partie de ce domaine');
	}*/
	
	if( !empty($db) ) $db = null;
	
	// Redirect via an HTML form for PITA webservers
	if( preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE')) )
	{
		header('Refresh: 0; URL='.$url);
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head>';
		echo '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">';
		echo '<meta http-equiv="refresh" content="0; url='.$url.'">';
		echo '<title>Redirect</title></head><body><div align="center">';
		echo 'If your browser does not support meta redirection please click ';
		echo '<a href="'.$url.'">HERE</a> to be redirected</div></body></html>';
		exit;
	}

	// Behave as per HTTP/1.1 spec for others
	header('Location: '.$url);
	exit;
}

function get_referer()
{
	$referer = '';
	
	if( isset($_POST['referer']) ) $referer = $_POST['referer'];
	else if( isset($_GET['referer']) ) $referer = $_GET['referer'];
	else if( isset($_COOKIE['referer']) ) $referer = $_COOKIE['referer'];
	else if( isset($_SERVER['HTTP_REFERER']) ) $referer = $_SERVER['HTTP_REFERER'];
	
	return $referer;
}

function redirect_referer($referer = null)
{	
	if( !$redirect ) $redirect = get_referer();
	redirect($redirect);
}

function ajax_encode($data = '', $enc = TRUE)
{
	if( $enc && $data != '' )
	{
		$data = js_encode($data);
	}
	return 'ok'.$data;
}

function ajax_reply($data, $enc = true)
{
	echo ajax_encode($data, $enc);
	exit;
}

// http://fr.php.net/manual/fr/function.json-encode.php#105749 pour supporter les fonctions comme valeur
function js_encode($data)
{
	// JSON_NUMERIC_CHECK encode array('id'=>"1") en {"id":1} et pas {"id":"1"} par défaut, utile pour que javascript récup un nb et pas une chaine
	// pas supporté actuellement apparement php5.4	
	
	// ceci encoderas les caractères spéciaux non encodé par flemme (é en &eacute; par ex)
	$data = convert_all($data, 'html_encode');
	return json_encode($data);
}

function js_decode($data, $comment = false)
{
	if( $comment )
	{
		// c'est pas parfait échoueras sur "ok": "//ok"
		// mais ça me convient pour le moment
		$data = preg_replace(array('#/\*.*?\*/#s','#(?<!\:)//.*?[\r\n]#s'), '', $data);
	}
	
	$json = json_decode($data, true);
	
	if( version_compare(PHP_VERSION, '5.3.0') < 0 ) return $json;
	
	switch(json_last_error())
	{
		case JSON_ERROR_DEPTH:
			$error = ' - Profondeur maximale atteinte';
		break;
		case JSON_ERROR_CTRL_CHAR:
			$error = ' - Erreur lors du contrôle des caractères';
		break;
		case JSON_ERROR_SYNTAX:
			$error = ' - Erreur de syntaxe ; JSON malformé, retour:'.$json.' depuis les données '.$data;
		break;
		case JSON_ERROR_NONE: default:
			return $json;
	}
	
	error($error);
}

function convert_all($data, $callback)
{
	if( is_string($data) ) return call_user_func($callback, $data);
	if( !is_array($data) && !is_object($data) ) return $data;
	
	$encoded = array();
	foreach($data as $key => $val)
	{
		$encoded[call_user_func($callback, $key)] = convert_all($val, $callback);
	}
	return $encoded;
}

function html_encode($str)
{
	global $config;
	
	return $str;
	return htmlentities($str, ENT_QUOTES, $config['encoding']);
}

// prépare le buffer de sortie pour les message de progression
function init_report()
{
	define('REPORTING', 1); // sers à dire qu'on est actuellement en mode reporting
	
	@apache_setenv('no-gzip', 1);
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    for($i=0;$i<ob_get_level();$i++) ob_end_flush();
    ob_implicit_flush(1);
	ob_start();
	
	echo str_repeat(" ", 256);
	echo '<div id="report" style="padding:10px;overflow:auto;border:2px solid grey;height:300px;"></div>';
	echo '<script>
	function report(msg)
	{
		var div = document.getElementById("report");
		div.innerHTML+= msg+"<br>";
		div.scrollTop = div.scrollHeight;
	}
	</script>';
	
	ob_flush();
	flush();
}

function report($message)
{
	echo str_repeat(" ", 256);
	echo '<script>report('.json_encode($message).')</script>';
	
	ob_flush();
	flush();
}

// revient à rightpath sauf qu'on peut préciser la racine à remplacer
function setRoot($path, $root = '')
{
	return $root.preg_replace('#\.+[/\\\]#', '', $path);
}

// retourne un chemin sans la partie relative
function rightpath($path)
{
	return preg_replace('#\.+[/\\\]#', '', $path);
}

function array_prefix($array, $prefix)
{
	$i = count($array);
	while($i--) $array[$i] = $prefix.$array[$i];
	return $array;
}

function array_suffix($array, $suffix)
{
	$i = count($array);
	while($i--) $array[$i].= $suffix;
	return $array;
}

function array_type($array)
{
    $last_key = -1;
    $type = 'index';
    foreach( $array as $key => $val )
	{
        if( !is_int($key) ) return 'assoc';
        if( $key !== $last_key + 1 ) $type = 'sparse';
		$last_key = $key;
    }
    return $type;
}

// array_remove(array(0,1,2), 1,2);
function array_remove(&$array)
{
	$args = func_get_args();
	if( count($args) ) $array = array_merge(array_diff($array, $args));
	return $array;
}

function json_readable_encode($data, $indent = 0)
{
	if( is_array($data) ) $indexed = array_type($data) == 'index';
	
	if( isset($indexed) || is_object($data) )
	{
		$json = '';
		foreach($data as $key=>$value)
		{
			$json.= str_repeat("\t", $indent + 1);
			if( !$indexed ) $json.= json_encode((string)$key).':';
			$json.= json_readable_encode($value, $indent+1).",\n";
		}
		
		$braclet = $indexed ? '[]' : '{}';
		
		if( empty($json) ) return $braclet;
		
		$out = "\n".str_repeat("\t", $indent).$braclet[0];
		$out.= "\n".substr($json, 0, -2);
		$out.= "\n".str_repeat("\t", $indent).$braclet[1];
		
		return $out;
	}
	return json_encode($data);
}

function mb_ucfirst($str)
{
    $str[0] = mb_strtoupper($str[0]);
    return $str;
} 

function str_cut($str, $delimiter = ' ')
{
	$pos = strpos($str, $delimiter);
	return $pos === false ? $str : substr($str, 0, $pos);
}

function explode_trim($str, $delimiter = ',')
{
    return array_map('trim', explode(',', $str));
	if( is_string($delimiter) )
	{
        $str = trim(preg_replace('|\\s*(?:' . preg_quote($delimiter) . ')\\s*|', $delimiter, $str));
        return explode($delimiter, $str);
    }
    return $str;
}

// crée un alias pour une fonction ou une méthode d'un objet
function alias($callback, $name)
{
	if( function_exists($name) )
	{
		warning('Impossible de crée un alias appelé '.$name.'. Une fonction utilise déjà ce nom');
		return false;
	}
	if( !is_callable($callback, false, $call_name) ) // $call_name recoit le nom tel qu'il doit être utiliser pour appeler la fonction
	{
		if( is_string($callback) ) $message = 'La fonction '.$callback.' ne peut pas être appelé';
		else $message = 'La méthode '.$callback[1].' de l\'objet '.get_class($callback[0]).' ne peut pas être appelée';
		
		warning($message);
		return false;
	}
	$bodyFunc = 'function '.$name.'(){ $args = func_get_args(); return call_user_func_array("'.$call_name.'", $args);}';
	eval($bodyFunc);
	return true;
}

function read_map($name)
{
	global $root_path;
	
	if( !strpos($name, '.map') ) $name.= '.map';
	
	$path = $root_path.'map/'.$name;
	if( !file_exists($path) )
	{
		return false;
	}
	
	return fread(fopen($path, 'r'), filesize($path));
}
 
function data_are_corrupt($data)
{
	if( !isset($data) || $data == '' )
	{
		error('Aucune donnée pour cette carte');
	}
	
	$data = js_decode($data);
	
	if( !is_numeric($data['w']) || !is_numeric($data['h']) )
	{
		error('Les dimensions de la carte ne sont pas des valeurs numériques');
	}
	
	$tiles = $data['tiles'];
	$sprites = $data['sprites'];
	$cells = $data['cells'];
	$events = $data['events'];
		
	for($i=0;$i<count($tiles);$i++)
	{
		if( !preg_match('/^[a-zA-Zéèàê0-9\-_ ]{1,32}$/', $tiles[$i]) )
		{
			error('Les tiles sont corrompus');
		}
	}
	for($i=0;$i<count($sprites);$i++)
	{
		if( !preg_match('/^[a-zA-Zéèàê0-9\-_ ]{1,32}$/', $sprites[$i]) )
		{
			error('Les sprites sont corrompus');
		}
	}	
	for($i=0;$i<count($cells);$i++)
	{
		// les cellules ne peuvent pas comporter autre chose que chiffre, virgule, #, : et x
		if( !preg_match('/^[0-9x:,#]/', $cells[$i]) )
		{
			error('Les données des cellules sont corrompues');
		}
	}
	
	return false;
}

function get_tile_config()
{
	global $root_path;
	
	$filepath = $root_path.'includes/tiles_config.json';
	
	return js_decode(file_get_contents($filepath));
}

function save_tile_config($config)
{
	global $root_path;
	
	$file_path = $root_path.'includes/tiles_config.json';
	
	$data = js_encode($config);
	
	$handle = fopen($file_path,'w');
	flock($handle, LOCK_EX);
	fwrite($handle, $data);
	flock($handle, LOCK_UN);
	fclose($handle);
	umask(0000);
	
	return chmod($file_path, 0666);
}

?>