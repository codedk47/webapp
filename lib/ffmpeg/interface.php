<?php
include 'ffmpeg.php';
return function(string $filename)
{
	return new ffmpeg($filename);
};