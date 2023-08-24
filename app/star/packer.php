<?php
class webapp_router_packer
{
	const prepare_dir = '';
	private readonly bool $mobile;
	private readonly string $type;
	function __construct(private readonly webapp $webapp)
	{
		if (preg_match('/android|iphone/i', $webapp->request_device(), $device))
		{
			$this->mobile = TRUE;
			$this->type = strtolower($device[0]);
		}
		else
		{
			$this->mobile = FALSE;
			$this->type = 'desktop';
		}
		
	}
	function get_home()
	{


		$this->android_apk();
	}


	function iphone_webcilp(string $cid = NULL)
	{

	}
	function android_apk(string $cid = NULL)
	{
		$android_apk = [
			'prepare_directory' => 'D:/sharefiles',
			'replace_interval' => 0,
			'replace_suffix' => 'txt'
		];

		$currentapk = file_get_contents($file = __DIR__ . '/packer.txt');
		if ($this->webapp->time(-$android_apk['replace_interval']) > filemtime($file)
			&& is_resource($index = fopen($file, 'r+'))
			&& flock($index, LOCK_EX | LOCK_NB)) {
			$files = glob("{$android_apk['prepare_directory']}/*.{$android_apk['replace_suffix']}");
			$files = array_combine(array_map(basename(...), $files), array_map(filemtime(...), $files));
			asort($files);
			$names = array_keys($files);
			$count = count($names);
			$nextapk = $currentapk;
			for ($i = 0; $i < $count; ++$i)
			{
				if ($nextapk === $names[$i])
				{
					if ($count > ++$i && rewind($index) && ftruncate($index, 0))
					{
						fwrite($index, $apkname = $names[$i]);
					}
					break;
				}
			}
			flock($index,LOCK_UN);
			fclose($index);
		}
	}
}