<?php
/***************************************************************************
*                        file.php
*                        -------------------
*   begin                : Mardi 25 Octobre 2011
*   copyright            : Angelblade
*   email                : Angelblade@hotmail.fr
*
*
***************************************************************************/

class FileIterator extends FilterIterator
{
	public $file;
	public $regex;
	
	function __construct($file, $regex = null)
	{
		$this->file = $file;
		
		if( $regex )
		{
			$regex = str_replace('.', '\.', $regex);
			$this->regex = '~^' . str_replace('*', '.*', $regex) . '$~';
		}
		
		try
		{
			$iterator = new DirectoryIterator($file->path);
		}
		catch(Exception $e)
		{
			warning('FileIterator: Impossible d\'ouvrir le dossier au chemin '.$file->path);
			$object = new ArrayObject(array()); // en cas d'erreur on fait iterate sur un tableau vide
			$iterator = $object->getIterator();
		}
		
		parent::__construct($iterator);
	}
	
	function current()
	{
		$file = parent::current();
		// retourne un nouvel objet file similaire à l'objet file qu'on parcourt
		// sachant que $file est un splfileinfo ici
		return new $this->file($file->getPathname());
	}
	
	function accept()
	{
		// $file est un objet splfileinfo ici aussi
		$file = $this->getInnerIterator();
		
		if( $file->isDot() ) return false;
		if( $this->regex === null ) return true;
		return preg_match($this->regex, $file->getFilename());
	}
}

class File
{
	public $path;
	private $content = null;
	
	function __construct($path)
	{
		$this->path = $this->cleanPath($path);
	}
	
	function __toString()
	{
		$msg = '';
		if( $this->isFile() ) $msg = 'fichier ';
		else if( $this->isDir() ) $msg = 'dossier ';
		
		return $msg.utf8_encode($this->path);
	}
	
	function cleanPath($path)
	{
		// un chemin genre ..\js/tree devient ../js/tree
		// utile surtout pour le Ftp et puis c'est plus propre
		return preg_replace('#\\\#', '/', $path);
	}
	
	function iterator($regex = '')
	{
		return new FileIterator($this, $regex);
	}
	
	function exist()
	{
		return file_exists($this->path);
	}
		
	function getType()
	{
		return filetype($this->path);
	}
	
	function getBasename()
	{
		return basename($this->path);
	}
	
	function getName()
	{
		return pathinfo($this->path, PATHINFO_FILENAME);
	}
	
	function getExtension()
	{
		return pathinfo($this->path, PATHINFO_EXTENSION);
	}
	
	function getMtime()
	{
		return filemtime($this->path);
	}
	
	function getFreename($name = false)
	{
		if( !$name ) $name = 'Nouveau fichier';
		
		$n = 1;
		$info = pathinfo($name);
		$extension = $info['extension'];
		$filename = $info['filename'];
		if( $extension != '' ) $extension = '.'.$extension;
		$filepath = $this->build_path(dirname($this->path), $filename.$extension);
		
		while( file_exists($filepath) )
		{
			if( $n > 1 ) $filename = substr($filename, 0, strrpos($filename, ' '));
			
			$n++;
			$filename.= " ($n)";
			$filepath = $this->build_path(dirname($this->path), $filename.$extension);
		}
		
		return $filename . $extension;
	}
	
	function getInfo($depth = 0, $regex = null)
	{
		$info = array();
		$info['name'] = $this->getBasename();
		$info['type'] = $this->getType();
		$info['ctime'] = date('d/m/Y H:i:s', filectime($this->path));
		
		if( $this->isDir() )
		{
			if( $depth != 0 )
			{
				$info['files'] = $this->getFiles($depth - 1, $regex);
			}
			else if( $this->isEmpty() )
			{
				$info['files'] = array();
			}
		}
		else
		{
			$info['size'] = $this->getSize();
		}
		
		return $info;
	}
	
	function getFiles($depth = 0, $regex = null)
	{
		$files = array();
		foreach($this->iterator($regex) as $file)
		{
			$files[] = $file->getInfo($depth, $regex);
		}
		return $files;
	}
	
	function getContent()
	{
		if( $this->content !== null ) return $this->content;
		return file_get_contents($this->path);
	}
	
	function getSize()
	{
		$size = filesize($this->path);
		
		if( $this->isDir() )
		{
			foreach($this->iterator() as $file) $size+= $file->getSize();
		}
		
		// if( $size === false ) return 'error'.last_error_msg();
		
		return $size;
	}
	
	function setPath($path)
	{
		return $this->path = $this->cleanPath($path);
	}
	
	function setMod($mod)
	{
		if( !chmod($this->path, $mod) )
		{
			warning('Echec de l\'écriture des permissions '.$mod.' sur le fichier '.$this->path);
			return false;
		}
	}
	
	function setMtime($time = null)
	{
		return touch($this->path, $time);
	}
	
	function setContent($content = '', $flags = LOCK_EX)
	{
		if( false !== file_put_contents($this->path, $content, $flags) )
		{
			$this->content = $content;
			return true;
		}
		return false;
	}
		
	function isDir()
	{
		return is_dir($this->path);
	}
	
	function isFile()
	{
		return is_file($this->path);
	}
	
	function isEmpty()
	{
		foreach($this->iterator() as $file) return false;
		return true;
	}
	
	function create_dir($name, $mod = false)
	{
		$path = $this->build_path($this->path, $name);
		
		if( !$mod ) $mod = 0777;
		if( !mkdir($path, $mod) )
		{
			warning('Echec de la création du dossier '.$path);
			return false;
		}
		return new $this($path);
	}
	
	// utiliser fopen et ne pas mettre le contenu comme ça on contrôle ce qui échoue ou pas non.?
	function create_file($name, $content = false, $mod = false)
	{
		$path = $this->build_path($this->path, $name);
		
		if( !file_put_contents($path, $content) )
		{
			warning('Impossible de crée le fichier '.$path);
			return false;
		}
		$file = new $this($path);
		
		return $mod ? $file->setMod($mod) : $file;
	}
	
	function each($callback) // apelle une fonction sur chaque fichier de ce dossier (non récursif)
	{
		foreach($this->iterator() as $file)
		{
			if( !call_user_func($callback, $file) ) return false;
		}
	}
	
	function invoke($method) // appel une méthode sur chaque fichier de ce dossier (non récursif)
	{
		$args = array_slice(func_get_args(), 1);
		foreach($this->iterator() as $file)
		{
			if( call_user_func_array(array($file, $method), $args) === false ) return false;
		}
		return true;
	}
	
	function build_path($dir, $name = false)
	{
		if( !$name ) $name = $this->getBasename();
		
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
	
	function remove()
	{
		if( $this->isDir() )
		{
			if( !$this->invoke('remove') ) return false;
			if( !rmdir($this->path) )
			{
				// inutile les warnings de php font l'affaire
				// warning('Impossible de supprimer le dossier '.$this->path);
				return false;
			}
			return true;
		}
		
		if( !unlink($this->path) )
		{
			// warning('Impossible de supprimer le fichier '.$this->path);
			return false;
		}
		return true;
	}
	
	function rename($name)
	{
		$path = $this->build_path(dirname($this->path), $name);
		
		if( !rename($this->path, $path) )
		{
			warning('Impossible de renommer le fichier');
			return false;
		}
		$this->setPath($path);
		return true;
	}
		
	function move($dest, $name = false)
	{
		$path = $this->build_path($dest, $name);
		
		if( !rename($this->path, $path) )
		{
			warning('Impossible de déplacer le fichier');
			return false;
		}
		$this->setPath($path);
		return true;
	}
	
	function copy($dest, $name = false)
	{
		$path = $this->build_path($dest, $name);
		
		if( $this->type == 'dir' )
		{
			$dest = new $this($dest); // récupère le dossier de destination
			if( !$dest->create_dir(basename($path)) ) return false; // crée un dossier vide portant le nom récup dans $path
			if( !$this->invoke('copy', $path) ) return false; // copie les fichiers de ce dossier dans le nouveau dossier
		}
		else
		{
			copy($this->path, $path);
		}
		
		return new $this($path); // retourne la copie créé
	}
}

?>