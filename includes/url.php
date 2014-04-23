<?php
// http://www.display-inline.fr/2009/09/classe-de-gestion-url/
class Url
{
	public $parts;
	
	function __construct($url = null)
	{
		global $config;
		
		$this->parts = array(
			'scheme' => 'http',				// Protocole
			'host' => $config['host'],		// Domaine ou IP
			'user' => null,					// Nom d'utilisateur
			'pass' => null,					// Mot de passe
			'path' => null,					// Chemin après le domaine
			'query' => array(),				// Paramètres de la requête
			'fragment' => null,				// Ancre nommée (#ancre)
			'subdomain' => null,			// Sous-domain (si existant)
			'domain' => null,				// Domaine sans le sous-domaine ni l'extension
			'tld' => null,					// Extension (Top Level Domain)
			'ip' => null					// Adresse ip si host est une ip
		);
		
		if( $url ) $this->parse($url);
	}
	
	/**
	 * Renvoie l'url avec tous ses composants
	 * @return	string		l'url finale
	 */
	function __toString()
	{
		$url = '';

		if( !is_null($this->parts['scheme']) ) $url.= $this->parts['scheme'].'://';
		if( !is_null($this->parts['user']) ) $url.= $this->parts['user'];
		if( !is_null($this->parts['pass'] ) && !is_null($this->parts['user'])) $url.= ':'.$this->parts['pass'];
		if( !is_null($this->parts['host']) ) $url.= (!is_null($this->parts['user']) ? '@' : '').$this->parts['host'];
		if( !is_null($this->parts['path']) ) $url.= $this->parts['path'];
		if( count($this->parts['query']) > 0 ) $url.= '?'.http_build_query($this->parts['query']);
		if( !is_null($this->parts['fragment']) && strlen($this->parts['fragment']) > 0 ) $url.= '#'.$this->parts['fragment'];
		
		return $url;
	}
	
	function __isset($name)
	{
		return isset($this->parts[$name]);
	}
	
	function __unset($name)
	{
		unset($this->parts[$name]);
	}

	function __get($name)
	{
		return isset($this->parts[$name]) ? $this->parts[$name] : null;
	}
	
	function __set($name, $value)
	{
		$this->setPart($name, $value);
	}
	
	function parse($url)
	{
		$this->parts = array_merge($this->parts, parse_url($url));

		// Si domaine IP
		if( !is_null($this->parts['host']) && preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $this->parts['host'], $matches) )
		{
			$this->parts['ip'] = $matches[0];
		}
		
		// Si paramètres
		if( is_null($this->parts['query']) ) $this->parts['query'] = array();
		else if( is_string($this->parts['query']) )
		{
			if( strlen($this->parts['query']) > 0 ) parse_str($this->parts['query'], $this->parts['query']);
			else $this->parts['query'] = array();
		}

		// Analyse du domaine
		$this->parseHost();
	}

	/**
	 * Découpe des composantes de host en : subdomain, domain et tld
	 * @return	void
	 */
	function parseHost()
	{
		$this->parts['subdomain'] = null;
		$this->parts['domain'] = null;
		$this->parts['tld'] = null;

		// Si domaine défini
		if( !is_null($this->parts['host']) && is_null($this->parts['ip']))
		{
			if( strpos($this->parts['host'], '.') === false ) $this->parts['domain'] = $this->parts['host'];
			else
			{
				$host = explode('.', $this->parts['host']);
				$this->parts['tld'] = array_pop($host);
				$this->parts['domain'] = array_pop($host);
				if( count($host) > 0 ) $this->parts['subdomain'] = implode('.', $host);
			}
		}
	}
	
	/**
	 * Modifie une des données analysées de l'url fournie
	 * @param	string		$name		le nom de la donnée à modifier
	 * @param	mixed		$value		la valeur à affecter
	 * @return	void
	 */
	function setPart($name, $value)
	{
		if( $name == 'query' && is_string($value) ) $value = strlen($value) > 0 ? parse_str($value, $value) : array();
		else if( $name == 'path' && $value[0] != '/' ) $value = '/'.$value;
	
		$this->parts[$name] = $value;
		
		// Si on modifie une des composantes de host, réassemblage
		if( $name == 'subdomain' || $name == 'domain' || $name == 'tld' )
		{
			$host = array();
			if( !is_null($this->parts['subdomain']) ) $host[] = $this->parts['subdomain'];
			if( !is_null($this->parts['domain']) ) $host[] = $this->parts['domain'];
			if( !is_null($this->parts['tld']) ) $host[] = $this->parts['tld'];

			// Si au moins une section  est définie
			$this->parts['host'] = count($host) > 0 ? implode('.', $host) : null;
		}
		else if( $name == 'host' )
		{
			$this->parseHost();
		}
	}

	/**
	 * Indique si l'url fournie est basée sur une IP
	 * @return	boolean		la confirmation que l'url est une ip ou non
	 */
	function isIP()
	{
		return !is_null($this->parts['ip']);
	}

	/**
	 * Renvoie le domaine
	 * @return	string		le domaine (nom + extension) ou NULL si inexistant
	 */
	function getDomain()
	{
		if( is_null($this->parts['domain']) ) return null; // Si non défini
		if( is_null($this->parts['tld']) ) return $this->parts['domain']; // Si pas d'extension
		return $this->parts['domain'].'.'.$this->parts['tld'];
	}

	/**
	 * Lit la valeur d'un paramètre de la requête
	 * @param	string		$name		le nom du paramètre
	 * @return	mixed		la valeur si définie, NULL sinon
	 */
	function getParam($name)
	{
		return isset($this->parts['query'][$name]) ? $this->parts['query'][$name] : null;
	}

	/**
	 * Ajoute un paramètre à la requête. Si il existe déjà, il est modifié
	 * @param	string		$name		le nom du paramètre
	 * @param	string|int	$value		la valeur
	 * @return	Url			l'objet pour chaînage
	 */
	function setParam($name, $value)
	{
		$this->parts['query'][$name] = $value;
		return $this;
	}
	
	function unsetParam($name)
	{
		unset($this->parts['query'][$name]);
		return $this;
	}
}
