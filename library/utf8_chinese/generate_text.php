<?php
return function(int $count, int $start = 0x4e00, int $end = 0x9fa5):string
{
	$random = unpack('V*', random_bytes($count * 4));
	$mod = $end - $start;
	foreach ($random as &$unicode)
	{
		$unicode = iconv('UCS-4LE', $this['app_charset'], pack('V', $unicode % $mod + $start));
	}
	return join($random);
};