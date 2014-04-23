<?php

define('IN', true);
$root_path = './';
include($root_path.'common.php');

if( isset($_GET['name']) ) $filename = $_GET['name'];
else $filename = pathinfo(urldecode($_SERVER['REQUEST_URI']), PATHINFO_BASENAME);

$extension = getExtension($filename);
$name = pathinfo($filename, PATHINFO_FILENAME);

function fileContentType($filepath)
{
	switch(pathinfo($filepath, PATHINFO_EXTENSION))
	{
		case 'txt': return 'text/plain';
		case 'htm': case 'html': return 'text/html';	
		case 'php': return 'text/html';
		case 'css': return 'text/css';
		
		case 'js': return 'application/javascript';
		case 'json': return 'application/json';
		case 'xml': return 'application/xml';		
		
		case 'gz': return 'application/x-gzip';
		case 'tgz': return 'application/x-gzip';
		case 'zip': return 'application/zip';
		
		case 'pdf': return 'application/pdf';
		
		case 'png': return 'image/png';
		case 'gif': return 'image/gif';
		case 'jpg': return 'image/jpeg';		
	}
	return 'application/octet-stream';
}

$code = DB::select(
	'code JOIN source ON (source.code = code.id) JOIN language ON (code.language = language.id)',
	'code.id, source.source',
	'WHERE code.name = ? AND language.extension = ? LIMIT 1',
	$name, $extension
);
if( $code )
{
	DB::insert('download', array(
		'code' => $code['id'],
		'user' => $session->user->get('id'),
		'ip' => $session->get('ip'),
		'ctime' => time()
	));
	
	$source = $code['source'];	
	$type = fileContentType($filename);

	header('Content-disposition: attachment; filename="'.$filename.'"');
	header('Content-Type: $type'); // header("Content-Type: application/force-download");
	header('Content-Transfer-Encoding: '.$type."\n"); // Surtout ne pas enlever le \n
	header('Content-Length: '.mb_strlen($source));
	header('Pragma: no-cache');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0, public');
	header('Expires: 0');
	echo $source;
	// readfile($chemin . $nom);
}

?>