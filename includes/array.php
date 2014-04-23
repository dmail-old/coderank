<?php

function &array_find(&$array, $key, $value)
{
	foreach($array as &$item)
	{
		if( $item[$key] == $value ) return $item;
	}
	return null;
}

function array_collect(&$array, $key, $value)
{
	$result = array();
	foreach($array as &$item)
	{
		if( $item[$key] == $value ) $result[] = $item;
	}
	return $result;
	
	// mais a priori $array seras toujours une collection indexé numériquement de 0 à x
	/*$i = 0;
	$j = count($array);
	for(;$i<$j;$i++)
	{
		if( $array[$i][$key] == $value ) 
	}
	return $result;*/
}

function array_groupBy($array, $key)
{
	$result = array();
	$group = array();
	$i = 0;
	foreach($array as $item)
	{
		$value = $item[$key];
		if( isset($group[$value]) )
		{
			$index = $group[$value];
		}
		else
		{
			$group[$value] = $index = $i++;
		}
		$result[$value][] = $item;
	}
	return $result;
}

class Collection
{
	public $collection;
	
	function __construct($array)
	{
		$this->collection = $array;
	}
	
	function find($key, $value)
	{
		$array = $this->collection;
		foreach($array as $item)
		{
			if( $item[$key] == $value ) return $item;
		}
		return null;
	}
}

?>