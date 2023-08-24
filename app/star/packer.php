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


		$this->android_apk('0000');
	}


	function channels():array
	{
		return ['0000'];
	}
	private function iphone_webcilp(string $cid = NULL)
	{

	}
	private function android_apk(string $cid = NULL)
	{
		$android_apk = [
			'prepare_directory' => 'D:/sharefiles',
			'replace_interval' => 0,
			'packer_suffix' => 'txt',
			'download_path' => 'http://hostlocal'
		];

		$currentapk = file_get_contents($file = __DIR__ . '/packer.txt');
		$currentdir = "{$android_apk['prepare_directory']}/" . basename($currentapk, ".{$android_apk['packer_suffix']}");
		if ($this->webapp->time(-$android_apk['replace_interval']) > filemtime($file)
			&& is_resource($index = fopen($file, 'r+'))
			&& flock($index, LOCK_EX | LOCK_NB)) {
			$files = glob("{$android_apk['prepare_directory']}/*.{$android_apk['packer_suffix']}");
			$files = array_combine(array_map(basename(...), $files), array_map(filemtime(...), $files));
			asort($files);
			$names = array_keys($files);
			$count = count($names);
			for ($i = 0; $i < $count; ++$i)
			{
				if ($currentapk === $names[$i])
				{
					if ($count > ++$i)
					{
						$currentapk = $names[$i];
					}
					break;
				}
			}
			(is_dir($currentdir = "{$android_apk['prepare_directory']}/" . basename($currentapk, ".{$android_apk['packer_suffix']}")) 
				|| mkdir($currentdir))
				&& rewind($index)
				&& ftruncate($index, 0)
				&& fwrite($index, $currentapk);
			flock($index, LOCK_UN);
			fclose($index);
		}



		if (in_array($cid, $this->channels(), TRUE))
		{
			is_file($packcid = "{$currentdir}/{$cid}.{$android_apk['packer_suffix']}");

			var_dump($packcid);
			// webapp::lib('apkpacker/apkpacker.php')("{$apkdir}/{$apkname}", $cid, $packcid);
			//var_dump("{$currentdir}/{$cid}.{$android_apk['packer_suffix']}");
		}
		else
		{
			$redirect = "{$android_apk['download_path']}/{$currentapk}";
		}

		var_dump($redirect);
		//$currentdir

		


		// is_file($apk = __DIR__."/../pwa/apk/{$unit}{$filetime}.apk") 
		// || webapp::lib('apkpacker/apkpacker.php')("{$apkdir}/{$apkname}",$unit,$apk);



		// if (in_array($cid, $this->channels(), TRUE))
		// {
		// 	is_file($apk = __DIR__."/../pwa/apk/{$unit}{$filetime}.apk") 
        //             || webapp::lib('apkpacker/apkpacker.php')("{$apkdir}/{$apkname}",$unit,$apk);
		// }





		var_dump($currentapk, $currentdir);
	}
}