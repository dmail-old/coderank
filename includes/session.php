<?php
/***************************************************************************
 *                        sessions.php
 *                        -------------------
 *   begin                : Dimanche 16 Octobre 2011
 *   copyright            : (C) Angelblade
 *   email                : Angelblade@hotmail.fr
 *
 *
 ***************************************************************************/

require_once('db_table.php');
 
// que user ait une méthode isOnline() qui retourne depuis cb de temps ou false
// se référer à db_table par rapport aux règles que doivent respecter les tables, User y compris

class User extends DB_Tablerule
{
	function __construct()
	{
		parent::__construct('user');
		
		$this->setRule('password', 'min', 5);
		$this->setRule('password', 'max', 25);
		$this->setRule('password', 'regexp', 'alphanumeric');
		
		$this->setRule('email', 'regexp', 'email');
		$this->setRule('email', 'taken');
	}
	
	function identic($field, $value, $current = null)
	{
		if( $current == null ) $current = $this->get($field);
		switch($field)
		{
			case 'password': return crypt($value, $current) == $current;
		}
		return parent::identic($field, $value, $current);
	}
	
	function banned($field, $value)
	{
		$statement = DB::query("SELECT value FROM ban WHERE field = ?", $field);
		while($row = $statement->fetch('num'))
		{
			$match = str_replace('\*', '.*?', preg_quote($row[0], '/'));
			if( preg_match('/^'.$match.'$/i', $value) )
			{
				$statement->closeCursor();
				return true;
			}
		}
		return false;
	}
	
	function transgress($field, $value, $baserule)
	{
		if( $error = parent::transgress($field, $value, $baserule) ) return $error;
		if( !$baserule && in_array($field, array('name','email','password')) )
		{
			if( $this->banned($field, $this->value) ) return 'banned';
			if( $field == 'password' ) $this->value = crypt($this->value);
		}
		return false;
	}
	
	function getHTML($field, $data)
	{
		// if( $field != 'password' )
			return parent::getHTML($field, $data);
		
		$name = $data['name'];
		$lang = $this->lang;
		
		$template = '
			<div class="field reveal">
				<label for="{NAME}"><span>{L_NAME}</span></label>
				<input type="password" name="{NAME}" value="{VALUE}" id="{NAME}" maxlength="25" />
				<script>window.addEvent("load", function(){ reveal($("{REVEAL}")); });</script>
				<label>
					<input type="checkbox" id="{REVEAL}" onchange="reveal(this)" />
					<span>{L_REVEAL}</span>
				</label>
			</div>
		';
		
		$str = str_replace(
			array('{NAME}','{L_NAME}','{VALUE}','{REVEAL}','{L_REVEAL}'),
			array($name, $lang[$data['lang']], $data['value'], $name.'_reveal', $lang['password_reveal']),
			$template
		);
			
		return $str;	
	}
	
	function register($name, $password, $email, $terms)
	{
		$fields = array('name' => $name, 'password' => $password, 'email' => $email);
		$errors = array();
		
		if( !$terms ) $errors['terms'] = $this->lang['terms_empty'];
		foreach($fields as $field => $value)
		{
			if( !$this->conform($field, $value) ) $errors[$field] = $this->error();
			else $fields[$field] = $this->value;
		}
		
		if( count($errors) ) return $errors;
		$fields['ctime'] = $fields['mtime'] = time();
		return DB_Table::insert($fields);
	}
	
	function isLocked()
	{
		return $this->get('statut') == USER_LOCKED;
	}
	
	function isVisitor()
	{
		return $this->get('id') == USER_VISITOR;
	}
	
	function isMember()
	{
		return $this->get('level') == USER_MEMBER;
	}
	
	function isModerator()
	{
		return $this->get('level') == USER_MODERATOR;
	}
	
	function isAdmin()
	{
		return $this->get('level') == USER_ADMIN;
	}
}
 
// que session devienne statique est ce possible avec extends? non
 
/*
si le cookie ovalia n'existe pas au chargement de la page c'est un problème on dit au visiteur que sans les cookies
il ne pourras pas se connecter de page en page: à insérer dans login.html
*/
class Session extends DB_Table
{
	public $user; // utilisateur de la session
	public $logged = false; // l'utilisateur a-t-il une session?
	private $options;
	
	function __construct($options)
	{
		global $config;
		
		$this->options = $options;
		$this->user = new User();
		
		$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
		$page = isset($config['pages'][PAGE]['id']) ? $config['pages'][PAGE]['id'] : 0;
		
		$this->set('ip', encode_ip($ip));
		$this->set('page', $page);
		$this->set('mtime', time());
		
		parent::__construct('session');
		
		$this->start();
		$this->setLang($this->getLang());
	}
	
	function getLang()
	{
		if( isset($_GET['lang']) ) return $_GET['lang'];
		if( isset($this->user['lang']) ) return $this->user['lang'];
		if( isset($_COOKIE['lang']) ) return $_COOKIE['lang'];
		
		$lang = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		return strtolower(substr(trim($lang[0]),0,2));
	}
	
	function setLang($lang)
	{
		if( $lang != 'fr' && $lang != 'en' ) $lang = 'fr';
		if( $_COOKIE['lang'] != $lang ) setcookie('lang', $lang, time()+60*60*24*30*12); // un an
		if( $this->user['lang'] != $lang && !$this->user->isVisitor() ) $this->user->update('lang', $lang); // l'utilisateur change de langue
		define('LANG', $lang);
	}
	
	function start()
	{
		$data = $this->recover();
		
		// on a pu récupérer une session depuis la bdd
		if( $data )
		{	
			$this->logged = true;
			// garde mtime et page avant qu'on les restaure depuis la bdd
			$mtime = $this->get('mtime');
			$page = $this->get('page');
			
			$this->user->set($data['user']); // défini l'utilisateur de cette session (qui peut très bien être un invité)
			$this->set($data['session']);
			
			// si la page à changer ou si la session peut être update (toutes les 1min)
			if( $page != $this->get('page') || $mtime - $this->get('mtime') > $this->options['update'] )
			{
				$this->update(array('mtime' => $mtime, 'page' => $page));
				$this->purge();
			}
			
			// l'utilisateur est bloqué, on supprime la session
			if( $this->user->isLocked() ) $this->delete();
			else return true;
		}
		
		// l'utilisateur est forcément un invité
		$this->setAsVisitor();
		
		// on lui crée une session si la config le veut
		if( $this->options['visitor'] )
		{
			//$this->set('persist', 1); // la session invité est persistante
			$this->set('persist', 0); // la session invité n'est pas persistante....
			$this->insert();
			return true;
		}
		
		// sinon on met le cookie session pour tester si le client accepte les cookies
		$this->cookie('ok');
		return false;
	}
	
	function recover()
	{
		$cookiename = $this->options['cookie']['name'];
		
		if( isset($_COOKIE[$cookiename]) ) $id = $_COOKIE[$cookiename];
		else if( isset($_REQUEST['sid']) ) $id = $_REQUEST['sid'];
		else return false;
		
		if( !$id || $id == 'ok' || !preg_match('/^[A-Za-z0-9]+$/', $id) ) return false;
		
		// on a récup un id de session apparement valide
		$select = DB::selectStatement('session JOIN user', '*', 'WHERE session.id = ?', $id);
		$session = $select->fetch('table');
		
		return count($session) ? $session : false;
	}
	
	function insert()
	{		
		$fields = array(
			'id' => $this->generate_id(),
			'user' => $this->user->get('id'),
			'ip' => $this->get('ip'),
			'ctime' => $this->get('mtime'),
			'mtime' => $this->get('mtime'),
			'page' => $this->get('page'),
			'persist' => $this->get('persist')
		);
		
		parent::insert($fields);
		$this->cookie($fields['id']);
		if( !$this->user->isVisitor() ) $this->user->update('mtime', $this->get('mtime'));
	}
	
	function update($fields)
	{
		parent::update($fields);
		if( $this->get('persist') ) $this->cookie($this->get('id')); // on remet le cookie lorsque la session persiste
		if( !$this->user->isVisitor() ) $this->user->update('mtime', $this->get('mtime'));
	}
	
	function delete() // met fin à la session
	{
		DB::delete('session', 'WHERE id = ? AND user = ?', $this->get('id'), $this->get('user')); // supprime la session
		$this->cookie(''); // supprime le cookie
		$this->data = array();
		$this->setAsVisitor();
	}
	
	function cookie($id) // enregistre ou efface le cookie de session
	{
		$cookie = $this->options['cookie'];
		$duration = 0; // par défaut le cookie expire à la fermeture du navigateur (y compris pour les sessions invités?)
		
		if( $id == '' ) $duration = time() - 5000; // supprime le cookie
		else if( $this->get('persist') ) $duration = $this->get('mtime') + $cookie['length'];
		
		return setcookie($cookie['name'], $id, $duration, $cookie['path'], $cookie['domain'], $cookie['secure']);
	}
	
	function login($persist = 0)
	{
		if( $this->logged ) // update si une session existe déjà
		{
			$this->update(array(
				'user' => $this->user->get('id'),
				'page' => $this->get('page'),
				'mtime' => time(),
				'persist' => $persist
			));
		}
		else // sinon on crée une session
		{
			$this->set('mtime', time());
			$this->set('persist', $persist);
			$this->insert();
		}
		
		return true;
	}
	
	function logout()
	{
		return $this->logged ? $this->delete() : false;
	}
	
	function setAsVisitor()
	{
		$this->user->set(array(
			'id' => USER_VISITOR,
			'name' => 'Visitor',
			'level' => 0
		));
		$this->logged = false;
	}
	
	function purge() // supprime les sessions expirées
	{
		$length = (int) $this->options['length'];
		$persistance = (int) $this->options['cookie']['length'];
		
		$sql = 'WHERE id <> ?';
		$values = array($this->get('id'));
		
		if( $length > 0 ) // la session expire au bout de x secondes d'inactivité
		{
			$sql.= ' AND (persist = 0 AND mtime < ?)';
			$values[] = time() - $length;
		}
		if( $persistance > 0 ) // la session ne peut plus persister pour permettre l'autologin
		{
			$sql.= ' '.($length > 0 ? 'OR' : 'AND').' (persist = 1 AND mtime < ?)';
			$values[] = time() - $persistance;
		}
		
		if( count($values) != 1 )
		{
			DB::delete('session', $sql, $values);
		}
	}
	
	function generate_id() // retourne une chaine unique servant d'id de session
	{
		return md5(str_rand(16) . microtime() . str_rand(16)); // gènère la chaîne à laquelle on ajoute microtime() pour la rendre unique
	}
}

function encode_ip($ip)
{
	$parts = explode('.', $ip);
	if( count($parts) == 4 ) return sprintf('%02x%02x%02x%02x', $parts[0], $parts[1], $parts[2], $parts[3]);
 
    $parts = explode(':', preg_replace('/(^:)|(:$)/', '', $ip));
    $result = '';
    foreach($parts as $x)
	{
		$result.= sprintf('%0'. ($x == '' ? (9 - count($parts)) * 4 : 4) .'s', $x);
	}
    return $result;
}

// pas besoin de ça normalement
function decode_ip($int_ip)
{
    function hexhex($value){ return dechex(hexdec($value)); };
 
    if( strlen($int_ip) == 32 )
	{
        $int_ip = substr(chunk_split($int_ip, 4, ':'), 0, 39);
        $int_ip = ':'. implode(':', array_map("hexhex", explode(':',$int_ip))) .':';
        preg_match_all("/(:0)+/", $int_ip, $zeros);
        if( count($zeros[0]) > 0 )
		{
            $match = '';
            foreach($zeros[0] as $zero)
			{
                if( strlen($zero) > strlen($match) ) $match = $zero;
			}
            $int_ip = preg_replace('/'. $match .'/', ':', $int_ip, 1);
        }
        return preg_replace('/(^:([^:]))|(([^:]):$)/', '$2$4', $int_ip);
    }
	
    $hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
    return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}

function compare_ip($a, $b)
{
	// Do not check IP assuming equivalence, if IPv4 we'll check only first 24 bits ... 
	// I've been told (by vHiker) this should alleviate problems with 
	// load balanced et al proxies while retaining some reliance on IP security.
	return substr($a, 0, 6) == substr($b, 0, 6);
}

?>