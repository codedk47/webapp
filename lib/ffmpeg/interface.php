<?php
include 'ffmpeg.php';
return function(string $filename, string $option = '-hide_banner -loglevel error -stats -y -threads 4')
{
	return new ffmpeg($filename, $option);
};