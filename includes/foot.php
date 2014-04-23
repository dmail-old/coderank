<?php

if( class_exists('DB') )
{
	DB::close();
}

if( $do_gzip_compress )
{
	$gzip_contents = ob_get_contents();
	ob_end_clean();
	$gzip_size = mb_strlen($gzip_contents);
	$gzip_crc = crc32($gzip_contents);

	$gzip_contents = gzcompress($gzip_contents, 9);
	$gzip_contents = substr($gzip_contents, 0, mb_strlen($gzip_contents) - 4);

	echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
	echo $gzip_contents;
	echo pack('V', $gzip_crc);
	echo pack('V', $gzip_size);
}

exit;

?>