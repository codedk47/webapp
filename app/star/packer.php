<?php
class webapp_router_packer
{
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
		$random = $this->webapp->redis_did_save_cid($cid);
		$this->webapp->recordlog($cid, 'dpc_ios');
		$routers = array_map(fn($origin) => "{$origin}/{$random}", $iphone_webcilp['routers']);
		$this->webapp->echo(webapp_echo_xml::mobileconfig([
			'PayloadContent' => [[
				'Icon' => $iphone_webcilp['icon'],
				'Label' => $iphone_webcilp['label'],
				'URL' => $this->webapp->build_test_router(TRUE, $iphone_webcilp['pagefix'], ...$routers),
				//'URL' => 'https://is.hihuli.com/?,did:1234567890123456',
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
		// $this->webapp->response_location('/hhuli.apk');
		// return 302;
		// $android_apk = [
		// 	'prepare_directory' => 'D:/apks',
		// 	'replace_interval' => 0,
		// 	'packer_suffix' => 'apk',
		// 	'download_path' => 'http://hostlocal/pwa'
		// ];
		$android_apk = $this->webapp['android_apk'];
		$currentapk = trim(file_get_contents($file = "{$android_apk['prepare_directory']}/packer.txt"));
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
		if ($this->webapp->cid($cid) && (is_file($packcid = "{$currentdir}/{$cid}.{$android_apk['packer_suffix']}")
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
	//nginx not allow patch
	// function get_recordlog(string $cid, string $log)
	// {
	// 	$this->webapp->recordlog($this->webapp->cid($cid), sprintf('%s%s', $log === 'dpv' ? 'dpv' : 'dpc', match($this->type)
	// 	{
	// 		'android' => '_android',
	// 		'iphone' => '_ios',
	// 		default => ''
	// 	}));
	// }
	function get_dl(string $cid = NULL):int
	{
		switch ($this->type)
		{
			case 'android': return $this->android_apk($cid);
			case 'iphone': return $this->iphone_webcilp($cid);
			default: $this->webapp->recordlog($this->webapp->cid($cid), 'dpc');
		}
		$redirect = $this->webapp->request_entry() . $this->webapp->at([], '?packer/home');
		$svg = new webapp_echo_svg($this->webapp);
		$svg->xml->qrcode(webapp::qrcode($redirect, $this->webapp['qrcode_ecc']), $this->webapp['qrcode_size']);
		$this->webapp->echo($svg);
		return 200;
	}
	function get_home(string $cid = NULL)
	{
		$this->webapp->recordlog($cid = $this->webapp->cid($cid), match ($this->type)
		{
			'android' => 'dpv_android',
			'iphone' => 'dpv_ios',
			default => 'dpv'
		});
		$html = new webapp_echo_html($this->webapp);
		unset($html->xml->head->link);
		$html->title('H狐狸');
		$html->script(['src' => 'https://www.googletagmanager.com/gtag/js?id=G-M24YPC36NJ']);
		$html->script('window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","G-M24YPC36NJ")');
		$html->footer[0] = NULL;
		$html->xml->body->div['class'] = 'packer';
		$html->xml->head->append('style')->cdata(<<<'CSS'
html,body,div.packer,main{
	margin:0;
	padding:0;
	height:100%;
}
div.packer{
	display: flex;
	flex-direction: column;
	background-color: #940000;
}
header{
	position: absolute;
	right: 0;
	bottom: 0;
	z-index: 1;
}
aside:not(:empty){
	position: absolute;
	right: 1rem;
	top: 42%;
	width: 17%;
	max-width: 11rem;
	z-index: 1;
	background-color: white;
	border: .4rem solid white;
}
aside>img{
	display: block;
	width: 100%;
}
main{
	position: relative;
	flex-grow: 1;
}
main>img{
	position: absolute;
	width: 100%;
	height: 100%;
}
footer{
	text-align: center;
}
footer>a{
	display: block;
	text-align: center;
}
footer>div{
	display: flex;
	justify-content: space-between;
	color: white;
	font-size: .8rem;
}
footer>div>a{
	display: block;
	width: 100px;
	flex-shrink: 0;
}
footer>div>a>img{
	width: 100%;
}
CSS);
$html->script(<<<'JS'
async function masker(resource)
{
	const
	response = await fetch(resource),
	reader = response.body.getReader(),
	key = new Uint8Array(8),
	buffer = [];
	for (let read, len = 0, offset = 0;;)
	{
		read = await reader.read();
		if (read.done)
		{
			break;
		}
		if (len < 8)
		{
			let i = 0;
			while (i < read.value.length)
			{
				key[len++] = read.value[i++];
				if (len > 7) break;
			}
			if (len < 8) continue;
			read.value = read.value.slice(i);
		}
		for (let i = 0; i < read.value.length; ++i)
		{
			read.value[i] = read.value[i] ^ key[offset++ % 8];
		}
		buffer[buffer.length] = read.value;
	}
	return URL.createObjectURL(new Blob(buffer));
}

const
device = /android|iphone/i.exec(navigator.userAgent),
mobile = Boolean(device),
type = mobile ? device[0].toLowerCase() : 'desktop',
bg = masker(`/star/packer/${mobile ? 'mobile' : 'desktop'}`);
addEventListener('DOMContentLoaded', () =>
{
	bg.then(blob => document.querySelector('main').appendChild(new Image).src = blob);
	if (mobile)
	{

	}
	else
	{
	}
});
function dl(anchor)
{
	if (type === 'iphone')
	{
		open(anchor);
		location.href = '/webapp/res/embedded.mobileprovision';
		return false;
	}
	return true;
}
JS);
		$dl = $this->webapp->request_entry() . $this->webapp->at(['cid' => $cid], '?packer/dl');
		if ($this->mobile)
		{
			$html->footer->append('a', ['href' => $dl, 'target' => '_blank', 'onclick' => 'return dl(this)'])
				->append('img', ['src' => "/star/packer/dl-{$this->type}.png"]);
			$div = $html->footer->append('div');
			$div->append('span', '安装说明：本APP含有成人内容，容易被杀毒软件误判为恶意软件，H狐狸TV保证无毒和恶意程序，请放心使用');
			$div->append('a', ['href' => $this->webapp['app_business'], 'target' => '_blank'])->append('img', ['src' => '/star/packer/tg.png']);
			$html->xml->body->append('img', ['src' => $this->type === 'android'
				? '/star/packer/tip-android.jpg'
				: '/star/packer/tip-iphone.jpg',
				'style' => 'width:100%']);
		}
		else
		{
			$encrypt = $this->webapp->encrypt($dl);
			$html->aside->append('img', ['src' => "?qrcode/{$encrypt}"]);
			$html->header->append('a', ['href' => $this->webapp['app_business'], 'target' => '_blank'])->append('img', ['src' => '/star/packer/tg.png']);
		}
		$this->webapp->echo($html);
	}
}