<?php

// ne rajouter \r que au besoin dans selectquery

class DB
{
	static $link = null;
	static $error_code = E_USER_ERROR; // type d'erreur générées: E_USER_ERROR
	static $statement;
	static $counter = 0;
	static $history = array();
	static $version, $table, $columns;
	
	function connect($config)
	{
		self::$link = mysql_connect($config['host'], $config['user'], $config['password']);
		if( self::$link === false )
		{
			self::logError('Impossible de se connecter à la base de données');
			return false;
		}
		if( !mysql_select_db($config['name']) )
		{
			self::logError('Base inexistante');	
			return false;
		}
		
		self::query("SET NAMES 'utf8'");
		
		return self::$link;
	}
	
	function close()
	{
		if( self::$link )
		{
			if( self::$statement ) self::$statement->closeCursor();
			if( $result =  mysql_close(self::$link) ) self::$link = false;
			return $result;
		}
		return false;
	}
	
	function prepare($sql)
	{
		return self::$statement = new DB_Statement($sql);
	}
	
	// éxécute une requête et retourne le jeu de résultats
	function query($sql, $params = null)
	{
		$statement = self::prepare($sql);
		$params = self::autoArguments(func_get_args(), 1);
		
		if( !$statement->execute($params) )
		{
			self::logError('La requête a échoué');
			return false;
		}
		return $statement;
	}
	
	// semblable à query sauf qu'elle ne génère pas d'erreur
	function run($sql, $params = null)
	{
		$statement = self::prepare($sql);
		$params = self::autoArguments(func_get_args(), 1);
		
		if( !$statement->execute($params) )
		{
			return false;
		}
		return $statement;
	}
	
	// éxécute une requête et retourne le nb de ligne affectées
	function exec($sql)
	{
		$statement = self::prepare($sql);
		
		if( !$statement->execute() )
		{
			self::logError('La requête a échoué');
			return false;
		}
		return $statement->rowCount();
	}
	
	function quote($str)
	{
		// mysql_real_escape_string pour str -> bdd
		// addcslashes($str, '%_') pour str -> bdd  avec requête comportant un like
		// intval pour num
		// trim + htmlspecialchars pour str autres
		return mysql_real_escape_string($str);
	}
	
	function setTable($name)
	{
		$name = self::$table = trim($name);
		
		// si ce qu'on retournais était différent de $name on remplace par newname et on rajoute un AS
		if( false )
		{
			$newname = 'autre_nom';
			if( preg_match("/(?:\sAS)?\s+`?(\w+)`?/i", $name, $match) )
			{
				$name = $match[1];
			}
			return "`$newname` AS `$name`";
		}
		
		return "`$name`";
	}
	
	// user JOIN group -> user JOIN group ON (group.user = user.id)
	// user JOIN group,session -> user JOIN group ON (group.user = user.id) JOIN session ON (session.user = user.id)
	// user JOIN group JOIN session -> user JOIN group ON (group.user = user.id) JOIN session ON (session.group = group.id)
	function autoJoin($str)
	{
		if( !preg_match("/,|\sJOIN\s/i", $str) ) // table unique
		{
			return self::setTable($str);
		}
		
		$parts = preg_split("/\s((?:(?:LEFT|RIGHT|FULL)\s+)(?:\s+OUTER\s+)?JOIN|(?:INNER\s+)?JOIN)\s/i", $str, -1, PREG_SPLIT_DELIM_CAPTURE);
		$names = explode(',', $parts[0]);
		
		$i = 0;
		$j = count($names);
		for(;$i<$j;$i++)
		{
			$sql.= self::setTable($names[$i]).', ';
			if( $i == 0 ) $table = self::$table; // la table est la première nommée
		}
		$sql = substr($sql, 0, -2);
		
		$i = 1;
		$j = count($parts);
		for(;$i<$j;)
		{
			$join_type = $parts[$i++];
			$join_expr = $parts[$i++];
			$on = stripos($join_expr, ' ON');
			
			if( $on !== false )
			{	
				$sql.= "\r".$join_type.' '.self::setTable(substr($join_expr, 0, $on)).substr($join_expr, $on);
			}
			else // pas de on après le join
			{
				// recupère les nom des tables après le join (en général une)
				$join_tables = explode(',', $join_expr);
				// la table de référence est la table juste avant celle-ci ou la première nommé
				$ref_table = isset($join_table) ? $join_table : $table;
				
				foreach($join_tables as $join_table)
				{
					$sql.= "\r".$join_type.' '.self::setTable($join_table);
					$sql.= ' ON ('.$join_table.'.id = '.$ref_table.'.'.$join_table.')';
				}
			}
		}
		
		// redéfinit la table sur laquelle on travaille
		self::$table = $table;
		return $sql;
	}
	
	function autoPrefix($sql, $prefix)
	{
		$prefix.= ' ';		
		if( stripos($sql, $prefix) === false ) $sql = $prefix.$sql; // rajoute le prefix s'il n'y est pas
		return "\r".$sql;
	}
	
	// faudrais si on passe un tableau indexé remplir les champs sans spécifié les noms
	function autoFields($fields, &$values)
	{
		$sql = '';
		$values = array();
		foreach($fields as $field => $value)
		{
			$sql.= '`'.$field."` = ?,\r";
			$values[] = $value;
		}
		return substr($sql, 0, -2); // enlève virgule et \r final
	}
		
	// retourne les valeurs passé via un tableau ou comme argument en cherchant dans les argument à partir d'un index
	function autoArguments($args, $index)
	{
		if( array_key_exists($index, $args) )
		{
			$array = $args[$index];
			if( is_array($array) ) return $array;
			return array_slice($args, $index);
		}
		return null;
	}
	
	function selectQuery($table, $fields = '*', $end = '')
	{
		return self::prepare("SELECT \t".preg_replace('/(,\s*)/',",\r\t",$fields)."\rFROM ".self::autoJoin($table)."\r".$end);
	}
	
	/* 
	Retourne le jeu de résultat de la requête select
	
	Manière d'apeller select
	-> select('user', $id);
	-> select('user', 'name,email', $id);
	-> select('user,group', 'user.*', 'WHERE user.id = ? AND group.id = ?', $user_id, $group_id); // ou tableau pour argument
	-> select('user JOIN group', 'user.*')
	*/
	function selectStatement($table, $fields = '*', $end = '', $values = null)
	{
		if( is_numeric($fields) )
		{
			$end = $fields;
			$fields = '*';
		}
		if( is_numeric($end) )
		{
			$values = array($end);
			$end = "\r WHERE id = ?";
		}
		else if( $end != '' )
		{
			$end = "\r".$end;
			$values = self::autoArguments(func_get_args(), 3);
		}
		
		$statement = self::selectQuery($table, $fields, $end);
		if( !$statement->execute($values) )
		{
			self::logError('Impossible de selectionner depuis la table '.self::$table);
			return false;
		}
		return $statement;
	}
	
	// à appeler lorsqu'on attend 0 ou 1 résultat
	// on pourrais peut être rajouter LIMIT 1 à la fin je crois
	function select()
	{
		$statement = call_user_func_array(array(self, selectStatement), func_get_args());
		if( !$statement ) return false;
		$result = $statement->fetch();
		$statement->closeCursor();
		return $result;
	}
	
	// à appeler lorsqu'on attend 0 à n résultats
	function selectAll()
	{
		$statement = call_user_func_array(array(self, selectStatement), func_get_args());
		if( !$statement ) return false;
		return $statement->fetchAll();
	}
	
	// count('code') -> retourne le nb de code count('code', 'visible = 1') -> nb de code visible
	function count($table, $where = '')
	{
		if( $where ) $where = self::autoPrefix($where, 'WHERE');
		$statement = self::selectQuery($table, 'COUNT(*)', $where);
		if( !$statement->execute() )
		{
			self::logError('Impossible de compter le nombre de '.self::$table);
			return false;
		}
		$count = $statement->fetch('num');
		$statement->closeCursor();
		return $count[0];
	}
	
	/*
	-> insert('user', array('name'=>'Angel', 'password'=>'test'));
	*/
	function insert($table, $fields)
	{
		$sql = 'INSERT INTO '.self::setTable($table)." SET\r".self::autoFields($fields, $values);
		if( !self::run($sql, $values) )
		{
			self::logError('Impossible d\'insérer un '.self::$table);
			return false;
		}
		return self::$statement->rowCount(); // on retourne le nb de ligne affectées
	}
	
	/*
	Insère une ligne ou remplace la ligne existante
	*/
	function replace($table, $fields)
	{
		$sql = 'REPLACE INTO '.self::setTable($table)." SET\r".self::autoFields($fields, $values);
		if( !self::run($sql, $values) )
		{
			self::logError('Impossible d\'insérer un '.self::$table);
			return false;
		}
		return self::$statement->rowCount(); // on retourne le nb de ligne affectées
	}
	
	/*
	-> update('user', 'SET name = ? WHERE id = ?', array($name, $id));
	-> update('user', 'name = ? WHERE id = ?', $name, $id);
	-> update('user', array('name'=> $name), 'WHERE name = ?', $name);
	-> update('user', array('name'=> $name), $id);
	*/	
	function update($table, $fields, $values = null)
	{
		$sql = 'UPDATE '.self::setTable($table);
		
		if( is_array($fields) )
		{
			$where = $values; // $values est en fait le where, les valeurs sont passé après dans les arguments
			$sql.= 'SET '.self::autoFields($fields, $values);
			
			if( is_numeric($where) )
			{
				$sql.= "\r WHERE id = ?";
				$values[] = $where; // met ce int dans les arguments à remplacer par leur valeur
			}
			else
			{
				$sql.= self::autoPrefix($where, 'WHERE');
				$args = self::autoArguments(func_get_args(), 3);	
				if( $args ) $values = array_merge($values, $args);
			}
		}
		else if( is_string($fields) )
		{
			$sql.= self::autoPrefix($fields, 'SET');
			$values = self::autoArguments(func_get_args(), 2);
		}
		
		if( !self::run($sql, $values) )
		{
			self::logError('Impossible de mettre à jour '.self::$table);
			return false;
		}
		return self::$statement->rowCount();
	}
		
	/*
	-> delete('user', $id);
	-> delete('user', 'WHERE id = ?', $id);
	-> delete('user', 'id = ?', array($id));
	*/
	function delete($table, $where, $values = null)
	{
		$sql = 'DELETE FROM '.self::setTable($table);
		
		if( is_numeric($where) )
		{
			$sql.= "\rWHERE id = ?";
			$values = array($where);
		}
		else
		{
			$sql.= self::autoPrefix($where, 'WHERE');
			$values = self::autoArguments(func_get_args(), 2);
		}
		
		if( !self::run($sql, $values) )
		{
			self::logError('Impossible de supprimer '.self::$table);
			return false;
		}
		return self::$statement->rowCount();
	}
	
	// retourne la version de mysql
	function getVersion()
	{
		$version = self::$version;
		if( !$version )
		{
			$statement = self::query('SELECT VERSION()');
			$select = $statement->fetch('num');
			$version = preg_replace('/[^0-9\.]/', '', $select[0]);
			$statement->closeCursor();
		}
		return $version;
	}
	
	// retourne le nom des tables de la bdd
	function getTables()
	{
		// $tables = array();
		$statement = self::query('SHOW TABLE STATUS');			
		return $statement->fetchAll();
		
		// while($row = $statement->fetch('num')) $tables[] = $row[0];
		// $statement->closeCursor();
		// return $tables;
	}
	
	// retourne les colonnes de $table au format array(0=>name, 1=>type+taille, 2=>not null, 3=>index, 4=>default value, 5=>extra)
	function getColumns($table)
	{
		$columns = self::$columns[$table];
		if( !$columns )
		{
			$statement = self::query('SHOW COLUMNS FROM '.self::setTable($table));
			$columns = self::$columns[$table] = $statement->fetchAll('num');
			// $statement->closeCursor();
		}
		return $columns;
	}
	
	function lastId()
	{
		return self::$link ? mysql_insert_id(self::$link) : false;
	}
	
	function lastQuery()
	{
		return self::$counter ? self::$history[self::$counter-1] : false;
	}
	
	function logError($msg)
	{
		return fire_error($msg, self::$error_code);
	}
	
	function error()
	{
		$link = self::$link;
		if( $link ) return array('message' => mysql_error($link), 'code' => mysql_errno($link));
		return false;
	}
}

// à renommer en DB_Query?
class DB_Statement
{
	public $template, $query, $cursor;
	public $fetchmode = 'assoc';
	public $params = array();
	public $meta; // sers de cache pour les metas de fetch('table')
	
	function __construct($template)
	{
		$this->template = $template;
	}
	
	function __toString()
	{
		return $this->template;
	}
	
	function bind($name, $value = null, $type = null)
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
			$this->params[$key] = $value;
		}
	}
	
	// transforme le template en une requête
	function parse($params = null)
	{
		if( !$params && count($this->params) ) $params = $this->params;
		$query = $this->template;
		
		if( $params )
		{
			$values = array();
			foreach($params as $key => $value)
			{
				if( is_string($key) )
				{
					if( $key[0] != ':' ) $key = ':'.$key;
					$query = str_replace_once($key, '?', $query);
				}
				
				if( is_numeric($value) )
				{
					$values[] = "'".strval($value)."'"; // on garde les '' parce sinon les valeur des ips 4E par ex ça va pas
				}
				else if( is_null($value) )
				{
					$values[] = 'null';
				}
				else
				{
					$values[] = "'".DB::quote($value)."'";
				}
			}

			if( !$final = vsprintf(str_replace('?','%s',$query), $values) )
			{
				DB::logError('sql parse error, requête:<pre style="overflow:auto">'.$query.'</pre> valeurs: ');
				return false;
			}
			$query = $final;
		}
		
		return $this->query = $query;
	}
	
	function execute($params = null)
	{
		unset($this->meta); // supprime les métas en cache
		
		$this->parse($params);
		
		DB::$history[DB::$counter++] = $this->query; // garde en mémoire qu'on a éxécuté cette requête
		$result = mysql_query($this->query, DB::$link);
		
		unset($this->cursor);
		// seul les requêtes select renvoie un curseur de résultat
		if( !is_bool($result) )
		{
			$this->cursor = $result;
		}
		return $result;
	}
	
	function rowCount()
	{
		if( $this->cursor ) return mysql_num_rows($this->cursor); // requête de type select
		if( DB::$link ) return mysql_affected_rows(DB::$link); // les autres requêtes
		return false;
	}
	
	function columnCount()
	{
		return $this->cursor ? mysql_num_fields($this->cursor) : false;
	}
	
	function getColumnMeta($column = 0)
	{
		return $this->cursor ? mysql_fetch_field($this->cursor, $column) : false;
	}
	
	function getMeta()
	{
		$meta = $this->meta;
		if( !$meta )
		{
			$columns = $this->columnCount();
			$meta = array();
			for($i=0;$i<$columns;$i++) $meta[$i] = $this->getColumnMeta($i);
			$this->meta = $meta;
		}
		return $meta;
	}
	
	function setFetchmode($mode)
	{
		$this->fetchmode = $mode;
	}
	
	function nextRow()
	{
		if( !$this->cursor ) return false;
		return mysql_fetch_row($this->cursor);
	}
	
	function fetch($mode = null)
	{
		if( !$this->cursor ) return false;
		if( !$mode ) $mode = $this->fetchmode;
		
		switch($mode)
		{
			case 'assoc': case 'num': case 'both':
				$fetch = mysql_fetch_array($this->cursor, constant('MYSQL_'.strtoupper($mode)));
			break;
			case 'table':
				$row = $this->nextRow();
				if( !$row ) return false;
				
				$meta = $this->getMeta();	
				$i = 0;
				$n = 0;
				$j = count($row);
				$fetch = array();
				
				for(;$i<$j;$i++)
				{
					$column_meta = $meta[$i];
					$table = $column_meta->table;
					if( !isset($fetch[$table]) ){ $n++; $fetch[$table] = array(); }
					$fetch[$table][$column_meta->name] = $row[$i];
				}		
				if( $n == 1 ) $fetch = $fetch[$table];
			break;
			default:
				return false;
		}
		
		if( STRIP ) return strip_magic($fetch);
		return $fetch;
	}
	
	function fetchAll($mode = null)
	{
		$result = array();
		while($row = $this->fetch($mode)) $result[] = $row;
		$this->closeCursor();
		return $result;
	}
	
	function closeCursor()
	{
		if( $this->cursor )
		{
			mysql_free_result($this->cursor);
			unset($this->cursor);
			return true;
		}
		return false;
	}
}

function str_replace_once($search, $replace, $str)
{
	$pos = strpos($str, $search);
    if( $pos !== false )
	{
        $before = substr($str, 0, $pos);
        $after = substr($str, $pos + strlen($search));
        return $before.$replace.$after;
    }
	else
	{
        return $str;
    }
}

function strip_magic($data)
{
	if( is_array($data) )
	{
		foreach($data as $key => $value) $data[$key] = strip_magic($value);
	}
	else
	{
		$data = cancel_magic_quote($data);
	}
	return $data;		
}

function cancel_magic_quote($value)
{
	if( isset($value) )
	{
		if( STRIP === true ) $value = stripslashes($value);
		else $value = str_replace("''", "'", $value);
	}
	return $value;
}

if( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() )
{
	$mqs = strtolower(ini_get('magic_quotes_sybase'));
	
	if( empty($mqs) || 'off' == $mqs )
	{
		define('STRIP', true); // si magic_quote_gpc est à true faut faire stripslashes sur les données de la bdd
	}
	else
	{
		define('STRIP', 1); // si sybase est on on fait str_replace sur les doubles quotes
	}
	
	$_REQUEST = strip_magic($_REQUEST);
	$_POST = strip_magic($_POST);
	$_GET = strip_magic($_GET);
	$_COOKIE = strip_magic($_COOKIE);
}
else
{
	define('STRIP', false);
}

?>