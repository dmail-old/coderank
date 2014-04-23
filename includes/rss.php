<?php

class Rss
{
	public $rss;
	public $channel;
	
	const desc_length = 100;
	
	function __construct()
	{
		$this->rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0"></rss>');
	}
	
	/*
	on peut ajouter une image au cannal
	
	<image> is an optional sub-element of <channel>, which contains three required and three optional sub-elements.

	<url> is the URL of a GIF, JPEG or PNG image that represents the channel.

	<title> describes the image, it's used in the ALT attribute of the HTML <img> tag when the channel is rendered in HTML.

	<link> is the URL of the site, when the channel is rendered, the image is a link to the site. (Note, in practice the image <title> and <link> should have the same value as the channel's <title> and <link>.

	Optional elements include <width> and <height>, numbers, indicating the width and height of the image in pixels. <description> contains text that is included in the TITLE attribute of the link formed around the image in the HTML rendering.

	Maximum value for width is 144, default value is 88.

	Maximum value for height is 400, default value is 31.
	*/
	
	function addChilds($element, $childs)
	{
		foreach($childs as $name => $value) $element->addChild($name, $value);
		return $element;
	}
	
	function addChannel($title, $link, $desc, $date = null, $other = null)
	{
		$this->channel = $this->rss->addChild('channel');	
		
		$childs['lastBuildDate'] = date('D j F Y G:i:s').' -0800';
		$childs['title'] = $title;
		$childs['link'] = $link;
		$childs['description'] = $desc;
		
		if( $other ) $childs = array_merge($childs, $other);
		
		return $this->addChilds($this->channel, $childs);
	}
	
	function addItem($title, $link, $desc, $date= null, $other = null)
	{
		if( strlen($desc) > self::desc_length ) $desc = substr($desc, 0, self::desc_length).'...';
		
		$childs['title'] = $title;
		$childs['link'] = $link;
		$childs['description'] = $desc;

		if( $date ) $childs['pubDate'] = $date;
		if( $other ) $childs = array_merge($childs, $other);
		
		return $this->addChilds($this->channel->addChild('item'), $childs);
	}
	
	function __toString()
	{
		return $this->rss->asXML();
	}
	
	function toFile($path)
	{
		return $this->rss->asXML($path);
	}
}

?>