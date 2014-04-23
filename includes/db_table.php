<?php

// en passant name et data en private et avec __set et __get pourquoi ca marcherait pas?

class DB_Table implements ArrayAccess
{
	public $name;
	public $data = array();
		
	function __construct($name)
	{
		$this->name = $name;
	}
	
	function offsetSet($offset, $data)
	{
        if( is_array($data) ) $data = new self($data);
        if( $offset === null ) $this->data[] = $data;
		else $this->data[$offset] = $data;
    }
	function offsetGet($offset){ return $this->data[$offset]; }	
    function offsetExists($offset){ return isset($this->data[$offset]); }	
    function offsetUnset($offset){ unset($this->data); } 
	
	function set($name, $value = null)
	{
		if( is_array($name) )
		{
			foreach($name as $key => $value) $this->set($key, $value);
		}
		else
		{
			$this->data[$name] = $value;
		}
	}
	
	function get()
	{
		$args = func_get_args();
		$j = count($args);
		$data = $this->data;
		
		if( $j == 1 ) return $data[$args[0]];
		$result = array();
		for($i=0;$i<$j;$i++)
		{
			$name = $args[$i];
			$result[$name] = $data[$name];
		}
		return $result;
	}
	
	function insert($fields)
	{
		if( count($fields) )
		{
			DB::insert($this->name, $fields);
			// si on a pas donner d'id pour $fields on suppose que le champ est en auto_increment
			if( !array_key_exists('id', $fields) ) $fields['id'] = Db::lastId();
			$this->set($fields);
		}
		return true;
	}
	
	function update($fields, $value = null)
	{
		$id = $this->get('id');
		if( $id === null )
		{
			warning('Update impossible: Id inconnu pour '.$this->name);
			return false;
		}
		if( is_string($fields) ) $fields = array($fields => $value);
		
		if( $fields )
		{
			DB::update($this->name, $fields, 'WHERE id = ?', $id);
			$this->set($fields);
		}
		return true;
	}
	
	function delete()
	{
		$id = $this->get('id');
		if( $id == null )
		{
			warning('Delete impossible: Id inconnu pour '.$this->name);
			return false;
		}
		
		DB::delete($this->name, 'WHERE id = ? ', $id);
		$this->data = array();
	}
	
	function selectBy($field, $value)
	{
		if( count($this->data) && $this->identic($field, $value) ) return $this;
		
		$data = DB::select($this->name, '*', "WHERE $field = ? LIMIT 1", $value);
		
		if( $data )
		{	
			$table = new $this($this->name);
			$table->set($data);
			return $table;
		}
		return null;
	}
	
	function identic($field, $value, $current = null)
	{
		if( $current == null ) $current = $this->get($field);
		// la comparaison de 1 et true retourne false mais un champ int(1) devrait valoir true sur un boolean faudrait ptet faire (string) $value
		// la recherche est par défaut caseinsensitive, c'est la bdd qui le définit comme ça
		// ce qui signifie que la comparaison entre deux chaine devrait toujours appeler mb_strtolower
		return mb_strtolower($value) == mb_strtolower($current);
	}
	
	// retourne une représentation HTML modifiable de la colonne $column
	function getColumnHTML($column)
	{
		$name = $column[0];
		$type = $column[1];
		$value = $column[4];
		
		$length = '';
		$pos = strpos($type,'(');
		if( $pos )
		{
			$length = preg_replace('/[^0-9]/', '', $type);
			$type = substr($type, 0, $pos);
		}	
		
		switch($type)
		{
			case 'tinyint': case 'boolean':
				// le type hidden sers à envoyer 0 si la checkbox n'est pas cochée
				if( $lenght == 1 ){
					return '
					<input type="hidden" name="fields['.$name.']" value="0" />
					<input id="'.$name.'" type="checkbox" value="1" name="fields['.$name.']" '.($value ? 'checked="checked"' : '').'/>
					';
				}
			case 'date': case 'varchar': case 'char': case 'year': case 'int': case 'mediumint': 
				if( $length < 100 ) return '<input id="'.$name.'" type="text" name="fields['.$name.']" value="'.$value.'" maxlength="'.$length.'" />';
			case 'text': case 'mediumtext': default:
				return '<textarea id="'.$name.'" name="fields['.$name.']">'.$value.'</textarea>';
			break;
		}
	}
}

/*
le mieux serait surement d'avoir un endroit ou je définit toutes les règles que doivent respecter les tables
ensuite j'ai plus qu'à faire $comment = new DB_Table('comment');
sans me poser de question et je la manipule avec quelques routines de base
à la manière de PDO on pourras préciser le type de valeur qu'on attend: chaine, nombre, boolean etc
*/

class DB_Tablerule extends DB_Table
{
	public $regexp = array(
		'numeric' => '/^[0-9]+$/',
		'alphanumeric' => '/^[\w]+$/u', // u pou runicode pour les accents
		'name' => '/^[\w ]+$/',
		'description' => '/^[\w :\.,\'()"]+$/u',
		'email' => '/^[a-z0-9&\'\.\-_\+]+@[a-z0-9\-]+\.([a-z0-9\-]+\.)*?[a-z]+$/is',
	);
	public $rules = array();
	public $rule_identic = false;
	public $violated; // dernière règle violé
	public $field; // dernier champ traité
	public $value; // dernière valeur traitée
	public $lang;
	
	function __construct($name)
	{
		global $lang;
		
		parent::__construct($name);
		
		$this->lang = $lang;
		$this->setRule('name', 'min', 2);
		$this->setRule('name', 'max', 25);
		$this->setRule('name', 'regexp', 'name');
		$this->setRule(array('id','ctime','mtime','atime'), 'regexp', 'numeric');
		$this->setRule(array('id','name'), 'taken');
	}
	
	function getErrors(&$fields, $update = false)
	{
		$errors	= array();
		$this->rule_identic = $update;
		foreach($fields as $field => $value)
		{
			if( !$this->conform($field, $value) )
			{
				if( $this->violated == 'identic' ) unset($fields[$field]);
				else $errors[$field] = $this->error();
			}
			else $fields[$field] = $this->value;
		}
		$this->rule_identic = false;
		return $errors;
	}
	
	// pendant de insert
	function add($fields)
	{
		if( $errors = $this->getErrors($fields) ) return $errors;
		return $this->insert($fields);
	}
	
	// pendant de update
	function setFields($fields, $value = null)
	{
		if( is_string($fields) ) $fields = array($fields => $value);	
		if( $errors = $this->getErrors($fields, true) ) return $errors;
		return parent::update($fields);
		
		/*$errors = array();
		$this->rule_identic = true;
		foreach($fields as $field => $value)
		{
			if( !$this->conform($field, $value) )
			{
				if( $this->violated == 'identic' ) unset($fields[$field]);
				else $errors[$field] = $this->error();
			}
			else $fields[$field] = $this->value;
		}
		$this->rule_identic = false;
		
		if( count($errors) ) return $errors;
		return parent::update($fields);*/
	}
		
	function setRule($field, $rule, $rulevalue = true)
	{
		if( is_array($field) ) foreach($field as $name) $this->setRule($name, $rule, $rulevalue);
		else $this->rules[$field][$rule] = $rulevalue;
	}
	
	function hasRule($field, $rule)
	{
		return isset($this->rules[$field][$rule]);
	}
	
	function getRules($field)
	{
		return isset($this->rules[$field]) ? $this->rules[$field] : false;
	}
	
	function removeRule($field, $rule = null)
	{
		if( $rule ) unset($this->rules[$field][$rule]);
		else unset($this->rules[$field]);
	}
	
	// nettois la valeur d'un champ avant vérification
	function clean($field, $value)
	{
		$value = stripslashes(trim($value));
		switch($field)
		{
			case 'name': return preg_replace('#\s+#', ' ', $value); // supprime les doubles espaces dans le nom
		}
		return $value;
	}
	
	function taken($field, $value)
	{
		return $this->selectBy($field, $value) ? true : false;
	}
	
	// $value respecte-t-elle les règles propres à $field? $baserule permet de ne tester que les règles de bases
	function conform($field, &$value, $baserule = false)
	{
		if( $this->violated = $this->transgress($field, $value, $baserule) ) return false;
		return true;
	}
	
	// retourne la règle qui est trangréssée ou false
	function transgress($field, &$value, $baserule = false)
	{
		$this->field = $field;
		$this->value = $value;
		
		if( is_null($value) ) return false;
		if( is_bool($value) ) $value = (int) $value;
		// triple égal pour éviter que 0 soit prit pour vide
		if( $value === '' ) return 'empty';
		$value = $this->value = $this->clean($field, $value);
		// encore triple égal pour '0' par exemple
		if( $value === '' ) return 'empty';
		
		$rules = $this->getRules($field);
		if( $rules )
		{
			if( isset($rules['numeric']) )
			{
				if( !is_numeric($value) ) return 'invalid';
				$length = $value;
			}
			else
			{
				$length = mb_strlen($value);
			}
			if( isset($rules['min']) && $length < $rules['min'] ) return 'small';
			if( isset($rules['max']) && $length > $rules['max'] ) return 'large';			
			if( isset($rules['regexp']) && !preg_match($this->regexp[$rules['regexp']], $value) ) return 'invalid';
			if( isset($rules['call']) && is_callable($rules['call']) && !call_user_func($rules['call'], $value) ) return 'invalid';
			if( isset($rules['apply']) && is_callable($rules['apply'][0]) )
			{
				$arguments = array_slice($rules['apply'], 1);
				$arguments[] = $value;
				if( !call_user_func_array($rules['apply'][0], array_reference($arguments)) ) return 'invalid';
			}
		}
		if( $this->rule_identic && $this->identic($field, $value) ) return 'identic';
		if( !$baserule && $rules && isset($rules['taken']) && $this->taken($field, $value) ) return 'taken';
		return false;
	}
		
	function error()
	{
		global $lang;
		
		if( !$violated = $this->violated ) return false;
		$field = $this->field;
		$value = $this->value;
		
		$message = $lang[$this->name.'_'.$field.'_'.$violated];
		if( !$message )
		{
			$message = $lang['field_'.$violated];
			if( !$message ) $message = 'Valeur incorrecte: %s ne peut être utilisé pour le champ %s';
		}
		
		if( $violated == 'small') return sprintf($message, $this->rules[$field]['min'], $field);
		if( $violated == 'large') return sprintf($message, $this->rules[$field]['max'], $field);
		return sprintf($message, $value, $field);
	}
	
	function toHTML($field, $options = null)
	{
		$data = array('name' => $field, 'lang' => $field);
		if( $options ) $data = array_merge($data, $options);
		
		return $this->getHTML($field, $data);
	}
	
	function getHTML($field, $data)
	{
		$lang = $this->lang;
		if( !isset($data['value']) ) $data['value'] = $this->get($field);
		
		$template = '
			<div class="field">
				<label for="{NAME}">{L_NAME} :</label>
				<input type="text" name="{NAME}" id="{NAME}" value="{VALUE}" maxlength="{MAXLENGTH}"  />
			</div>
		';
		
		$str = str_replace(
			array('{NAME}','{L_NAME}','{VALUE}','{MAXLENGTH}'),
			array($data['name'], $lang[$data['lang']], $data['value'], $this->rules[$field]['max']),
			$template
		);
		
		return $str;
	}
}

function array_reference(&$array)
{ 
    $refs = array(); 
    foreach($array as $key => $value) $refs[$key] = &$array[$key]; 
    return $refs; 
}


?>