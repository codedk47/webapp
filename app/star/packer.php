<?php
class webapp_router_packer
{
	const cid = '0000';
	private readonly bool $mobile;
	private readonly string $type, $cid;
	function __construct(private readonly webapp $webapp)
	{
		//$this->cid = trim($webapp->query['cid'] ?? self::cid, "/ \t\n\r\0\x0B");
		if (preg_match('/android|iphone/i', $webapp->request_device, $device))
		{
			$this->mobile = TRUE;
			$this->type = strtolower($device[0]);
		}
		else
		{
			$this->mobile = FALSE;
			//$this->type = preg_match('/i?pad/i', $webapp->request_device) ? 'pad' : 'desktop';
			$this->type = 'desktop';
		}
	}
	function channel(?string $id):bool
	{
		return $id && ($id === self::cid || $this->webapp->mysql->channels('WHERE hash=?s LIMIT 1', $id)->fetch());
	}
	function iphone_webcilp(?string $cid):int
	{
		// $iphone_webcilp = [
		// 	'icon' => 'D:/logo512x512.png',
		// 	'label' => 'app name',
		// 	'displayname' => 'full app name',
		// 	'description' => 'app description',
		// 	'organization' => 'app organization',
		// 	'identifier' => 'com.webapp.test',
		// 	'pagefix' => 'https://github.com/',
		// 	'routers' => [
		// 		'wss://hostlocal'
		// 	]
		// ];
		$iphone_webcilp = $this->webapp['iphone_webcilp'];
		$random = $this->webapp->random_hash(FALSE);
		if ($this->channel($cid))
		{
			//这里也许需要在 REDIS 保存渠道码
			$this->webapp->recordlog($cid, 'dpc_ios');
		}
		$routers = array_map(fn($origin) => "{$origin}/CID/{$random}", $iphone_webcilp['routers']);
		$this->webapp->echo(webapp_echo_xml::mobileconfig([
			'PayloadContent' => [[
				'Icon' => $iphone_webcilp['icon'],
				'Label' => $iphone_webcilp['label'],
				'URL' => $this->webapp->build_test_router(TRUE, $iphone_webcilp['pagefix'], ...$routers),
				'FullScreen' => TRUE,
				'IsRemovable' => TRUE,
				'IgnoreManifestScope' => TRUE
			]],
			'PayloadDisplayName' => $iphone_webcilp['displayname'],
			'PayloadDescription' => $iphone_webcilp['description'],
			'PayloadOrganization' => $iphone_webcilp['organization'],
			'PayloadIdentifier' => $iphone_webcilp['identifier']
		], $this->webapp, $iphone_webcilp['label']));
		return 200;
	}
	function android_apk(?string $cid):int
	{
		// $android_apk = [
		// 	'prepare_directory' => 'D:/apks',
		// 	'replace_interval' => 0,
		// 	'packer_suffix' => 'apk',
		// 	'download_path' => 'http://hostlocal/pwa'
		// ];
		$android_apk = $this->webapp['android_apk'];
		$currentapk = trim(file_get_contents($file = __DIR__ . '/packer.txt'));
		$currentfix = basename($currentapk, ".{$android_apk['packer_suffix']}");
		$currentdir = "{$android_apk['prepare_directory']}/{$currentfix}";
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
						$currentfix = basename($currentapk = $names[$i], ".{$android_apk['packer_suffix']}");
					}
					break;
				}
				else
				{
					is_dir($removedir = "{$android_apk['prepare_directory']}/" . basename($names[$i], ".{$android_apk['packer_suffix']}"))
						&& (array_filter(glob("{$removedir}/*"), fn($file) => unlink($file) === FALSE)
							|| (rmdir($removedir) && unlink("{$android_apk['prepare_directory']}/{$names[$i]}")));
				}
			}
			(is_dir($currentdir = "{$android_apk['prepare_directory']}/{$currentfix}")
				|| mkdir($currentdir))
				&& rewind($index)
				&& ftruncate($index, 0)
				&& fwrite($index, $currentapk);
			flock($index, LOCK_UN);
			fclose($index);
		}
		if ($this->channel($cid) && (is_file($packcid = "{$currentdir}/{$cid}.{$android_apk['packer_suffix']}")
			|| webapp::lib('apkpacker/apkpacker.php')("{$android_apk['prepare_directory']}/{$currentapk}", $cid, $packcid))) {
			$this->webapp->recordlog($cid, 'dpc_android');
			$currentapk ="{$currentfix}/{$cid}.{$android_apk['packer_suffix']}";
		}
		$this->webapp->response_location("{$android_apk['download_path']}/{$currentapk}");
		return 302;
	}
	function get_build_apk()
	{
		if (PHP_SAPI !== 'cli') return 404;
		$android_apk = $this->webapp['android_apk'];
		if (count(glob("{$android_apk['prepare_directory']}/*.{$android_apk['packer_suffix']}")) > 1)
		{
			return;
		}
		// for ($i = 0; $i < 10; $i++)
		// {
		// 	$build = file_get_contents('build.gradle');
		// 	$random = 'aw' . bin2hex(random_bytes(7));
		// 	$build = preg_replace('/group\s*=\s*\'[^\']+/', "group = 'org.chromium.{$random}", $build);
		// 	if (file_put_contents('build.gradle', $build) === strlen($build))
		// 	{
		// 		exec('gradlew --no-build-cache assembleDebug', $output, $result_code);
		// 		$result_code === 0
		// 			&& rename('build/outputs/apk/debug/android_webview-debug.apk', "build/outputs/apk/debug/{$random}.apk");
		// 	}
		// }
	}
	function get_home(string $cid = NULL)
	{
		if (is_string($cid))
		{
			$cid = trim($cid, "/ \t\n\r\0\x0B");
		}
		$this->webapp->recordlog($this->channel($cid) ? $cid : self::cid, match ($this->type)
		{
			'android' => 'dpv_android',
			'iphone' => 'dpv_ios',
			default => 'dpv'
		});
		//var_dump($this->mobile, $this->type);
		$dl = $this->webapp->request_entry() . '' . $this->webapp->at(['cid' => $cid], '?packer/dl');
		$html = new webapp_echo_html($this->webapp);
		$html->loadHTMLFile("{$this->webapp['android_apk']['prepare_directory']}/../rstar.html");
		if ($this->mobile)
		{
			$base64bg = base64_encode(file_get_contents("{$this->webapp['android_apk']['prepare_directory']}/../mobile.png"));
			$html->xml->body['style'] = "background-position: center 6rem;background-color: #1f1d1f;background-image: url(data:image/png;base64,{$base64bg})";
			$html->xml->body->header['class'] = 'mobile';
			$html->xml->body->a['style'] = 'position:fixed;top:1.3rem;right:1rem';
			$html->xml->body->div[0]['style'] = 'display:block';
			$html->xml->body->div[0]->main->a->setattr($this->type === 'iphone'
				? ['iOS 下载', 'href' => $dl, 'class' => 'iphone', 'onclick' => 'return iphone(this)']
				: ['Android 下载', 'href' => $dl, 'class' => 'android']);
		}
		else
		{
			$base64bg = base64_encode(file_get_contents("{$this->webapp['android_apk']['prepare_directory']}/../desktop.png"));
			$html->xml->body['style'] = "background-image: url(data:image/png;base64,{$base64bg})";
			$html->xml->body->header['class'] = 'desktop';
			$html->xml->body->div[1]['style'] = 'display:block';
			$html->xml->xpath('//div[@class="qrcode"]')[0]['style'] = "background-image:url({$dl})";
			$html->xml->xpath('//div[@data-tg]')[0]->dom()->appendChild($html->xml->body->a->dom());
		}
		$this->webapp->echo($html);
	}
	function get_dl(string $cid = NULL):int
	{
		switch ($this->type)
		{
			case 'android': return $this->android_apk($cid);
			case 'iphone': return $this->iphone_webcilp($cid);
			default: $this->webapp->recordlog($this->channel($cid) ? $cid : self::cid, 'dpc');
		}
		$redirect = $this->webapp->request_entry() . '' . $this->webapp->at([]);
		$svg = new webapp_echo_svg($this->webapp);
		$svg->xml->qrcode(webapp::qrcode($redirect, $this->webapp['qrcode_ecc']), $this->webapp['qrcode_size']);
		$this->webapp->echo($svg);
		return 200;
	}
	static function dl(webapp $webapp, ?string $cid):int
	{
		return (new static($webapp))->get_dl($cid);
	}
}