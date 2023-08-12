<?php
include 'ffmpeg.php';
return function(string $filename, string $option = '-hide_banner -loglevel error -stats -y -hwaccel dxva2 -threads 4')
{
	return new ffmpeg($filename, $option);
};