<?php
include 'ffmpeg.php';
return function(string $filename, string $option = '-hide_banner -loglevel error -stats -y')
{
	return new ffmpeg($filename, $option);
};