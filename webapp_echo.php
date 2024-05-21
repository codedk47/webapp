<?php
declare(strict_types=1);
trait webapp_echo
{
	public readonly webapp $webapp;
	abstract function __construct(webapp $webapp);
	abstract function __toString():string;
}
class webapp_echo_xml extends webapp_implementation
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, string $type = 'webapp', string ...$params)
	{
		$webapp->response_content_type('application/xml');
		parent::__construct($type, ...$params);
	}
	static function mobileconfig(array $values, webapp $webapp = NULL, ?string $basename = NULL):webapp_implementation
	{
		#https://developer.apple.com/documentation/devicemanagement/webclip?changes=latest_beta&language=objc
		$mobileconfig = new webapp_implementation('plist', '-//Apple//DTD PLIST 1.0//EN', 'http://www.apple.com/DTDs/PropertyList-1.0.dtd');
		$mobileconfig->xml['version'] = '1.0';
		if ($webapp)
		{
			$mobileconfig->document->encoding = $webapp['app_charset'];
			if ($basename === NULL)
			{
				$webapp->response_content_type('application/xml');
			}
			else
			{
				$webapp->response_content_type('application/x-apple-aspen-config');
				$webapp->response_content_disposition("{$basename}.mobileconfig");
			}
		}
		else
		{
			$mobileconfig->document->encoding = 'utf-8';
		}
		function config(webapp_xml $xml, array $values)
		{
			$node = $xml->append(array_is_list($values) ? 'array' : 'dict');
			foreach ($values as $key => $value)
			{
				if (is_string($key))
				{
					$nodekey = $node->append('key', $key);
				}
				if (is_array($value))
				{
					config($node, $value);
					continue;
				}
				if (preg_match('/icon/i', (string)$key))
				{
					$node->append('data', base64_encode(file_get_contents($value)));
					continue;
				}
				if ($params = match (get_debug_type($value)) {
					'bool' => [$value ? 'true' : 'false'],
					'int' => ['integer', (string)$value],
					'string' => ['string', $value],
					default => []}) {
					$node->append(...$params);
					continue;
				};
				$nodekey->remove();
			}
		}
		$values += [
			'PayloadDisplayName' => 'Web Application',
			'PayloadDescription' => 'Web Application Description',
			'PayloadOrganization' => 'Web Application Organization',
			//以下四个必要固定字段
			'PayloadUUID' => '00142857-0000-0000-0000-000000801462',
			'PayloadType' => 'Configuration',
			'PayloadIdentifier' => 'WEBAPP.ID',	//唯一相同覆盖
			'PayloadVersion' => 1
		];
		if ($values['PayloadType'] === 'Profile Service')
		{
			//----Profile Service----
			//openssl smime -sign -in unsigned.mobileconfig -out signed.mobileconfig -signer mbaike.crt -inkey mbaike.key -certfile ca-bundle.pem -outform der -nodetach
			//openssl rsa -in mbaike.key -out mbaikenopass.key
			//openssl smime -sign -in unsigned.mobileconfig -out signed.mobileconfig -signer mbaike.crt -inkey mbaikenopass.key -certfile ca-bundle.pem -outform der -nodetach
			$values['PayloadContent'] += [
				'URL' => 'https://localhost/',
				'DeviceAttributes' => ['DEVICE_NAME', 'UDID', 'IMEI', 'ICCID', 'VERSION', 'PRODUCT', 'SERIAL', 'MAC_ADDRESS_EN0']
			];
		}
		else
		{
			//----Web Cilp----
			$values['PayloadContent'][0] += [
				// 'Icon' => 'D:/wmhp/work/asd/icon.png', //桌面图标
				'Label' => 'WebApp', //桌面名称
				'URL' => 'http://localhost/',
				'FullScreen' => TRUE,
				'IsRemovable' => TRUE,
				'IgnoreManifestScope' => FALSE,
				//'PayloadDisplayName' => 'WebApp',
				//以下四个必要固定字段
				'PayloadUUID' => '00142857-0000-0000-0000-000000801462',
				'PayloadType' => 'com.apple.webClip.managed',
				'PayloadIdentifier' => 'Ignored',
				'PayloadVersion' => 1
			];
		}
		config($mobileconfig->xml, $values);
		return $mobileconfig;
	}
}
class webapp_echo_svg extends webapp_implementation
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, array $attributes = [])
	{
		$webapp->response_content_type('image/svg+xml');
		parent::__construct('svg', '-//W3C//DTD SVG 1.1//EN', 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd');
		$this->xml->setattr(['xmlns' => 'http://www.w3.org/2000/svg'] + $attributes);
	}
}
class webapp_echo_json extends ArrayObject implements Stringable
{
	use webapp_echo;
	private int $flags = JSON_UNESCAPED_UNICODE;
	function __construct(public readonly webapp $webapp, array|object $data = [])
	{
		$webapp->response_content_type("application/json; charset={$webapp['app_charset']}");
		parent::__construct($data, ArrayObject::STD_PROP_LIST);
	}

	function __toString():string
	{
		return json_encode($this->getArrayCopy(), $this->flags);
		// try
		// {
		// } catch (JsonException $error)
		// {
		// }
	}
	function getFlags():int
	{
		return $this->flags;
	}
	function setFlags(int $flags):void
	{
		$this->flags = $flags;
	}
	function is_list():bool
	{
		return array_is_list($this->getArrayCopy());
	}
	function error(string $error):void
	{
		$this['errors'][] = $error;
	}
	// function dialog(string|array $context, )
	// {
	// 	$this['dialog'] = $context;
	// }
	function goto(string $url = NULL):void
	{
		$this['goto'] = $url;
	}
}
class webapp_echo_html extends webapp_implementation
{
	use webapp_echo;
	public readonly webapp_html $header, $aside, $main, $footer;
	function __construct(public readonly webapp $webapp)
	{
		//https://validator.w3.org/nu/#textarea
		$webapp->response_content_type("text/html; charset={$webapp['app_charset']}");
		parent::__construct();
		$this->xml->setattr(['lang' => 'en'])->append('head');
		$this->meta(['charset' => $webapp['app_charset']]);
		$this->meta(['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1']);
		$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/res/ps/webapp.css', 'media' => 'all']);
		$this->link(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => '?favicon']);
		if ($webapp['manifests'])
		{
			$this->link(['rel' => 'manifest', 'href' => '?manifests']);
			//$this->script(['src' => '?service-workers', 'defer' => NULL]);
		}
		else
		{
			//$mask && $this->script(['src' => '?service-workers', 'defer' => NULL]);
		}
		
		//$head->append('script', ['type' => 'module', 'src' => '/webapp/res/js/webkit.js']);
		//$this->script(['src' => '/webapp/res/js/webapp.js']);
		//$this->script('import $ from "/webapp/res/js/webkit.js";');
		$node = $this->xml->append('body')->append('div', ['class' => 'webapp-grid']);
		[$this->header, $this->aside, $this->main, $this->footer] = [
			&$node->header, &$node->aside, &$node->main,
			$node->append('footer', $webapp['copy_webapp'])];

	}
	function meta(array $attributes):webapp_html
	{
		return $this->xml->head->append('meta', $attributes);
	}
	function link(array $attributes):webapp_html
	{
		return $this->xml->head->append('link', $attributes);
	}
	function script(array|string $context):void
	{
		is_array($context)
			? $this->xml->head->append('script', $context)
			: $this->xml->head->append('script')->cdata($context);
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}
	function meta_open_graph(string $title, string $type, string $image = NULL):void
	{
		//https://ogp.me/
		$this->xml['prefix'] = 'og:https://ogp.me/ns#';
		$this->meta(['name' => 'og:title', 'content' => $title]);
		$this->meta(['name' => 'og:type', 'content' => $type]);
		$this->meta(['name' => 'og:image', 'content' => $image ?? '/?favicon']);
		//$this->meta(['name' => 'og:url', 'content' => ]);
	}

	function link_resources(string|array $origin, string $rel = 'dns-prefetch'):void
	{
		foreach (is_string($origin) ? [$origin] : $origin as $href)
		{
			$this->link($rel === 'preconnect'
				? ['rel' => $rel, 'href' => $href, 'crossorigin' => NULL]
				: ['rel' => $rel, 'href' => $href]);
		}
	}
	function script_variables(array $variables, string $name = 'WEBAPP'):void
	{
		$this->script("const {$name}=" . json_encode($variables, JSON_UNESCAPED_UNICODE));
	}
	// function addstyle(string $rule):DOMText
	// {
	// 	return ($this->style ??= $this->xml->head->append('style', ['media' => 'all']))->text($rule);
	// }
// 	function wallpaper()
// 	{
// 		$this->script(['src' => '/webapp/res/js/tgwallpaper.min.js']);
// 		$wallpaper = $this->xml->body->insert('div', 'first')->setattr(['style' => 'position:fixed;z-index:-1;top:0;left:0;right:0;bottom:0;']);
// 		$wallpaper->append('canvas', [
// 			'id'=>"wallpaper",
// 			'width' => 50,
// 			'height' => 50,
// 			'data-colors' => 'dbddbb,6ba587,d5d88d,88b884',
// 			'style' => 'position:absolute;width:100%;height:100%'
// 		]);
// 		$wallpaper->append('div', [
// 			'style' => 'position:absolute;width:100%;height:100%;background-image:url(/webapp/res/ps/pattern-telegram.svg);mix-blend-mode: overlay;opacity:.4'
// 		]);
// 		$this->xml->body->append('script', <<<JS
// const wallpaper = document.getElementById('wallpaper');
// if (wallpaper)
// {
// 	TWallpaper.init(wallpaper);
// 	TWallpaper.animate(true);
// 	TWallpaper.update();
// }
// JS);
// 	}
	function nav(array $link):webapp_html
	{
		$node = $this->header->append('nav', ['class' => 'webapp']);
		$node->atree($link, TRUE);
		return $node;
	}
	function search(?string $action = NULL):webapp_form
	{
		$form = $this->header->form($action);
		$form->xml['method'] = 'get';
		$form->field('search', 'search');
		$form->button('Search', 'submit');
		return $form;
	}


	static function form_sign_in(array|webapp|webapp_html $context, ?string $authurl = NULL):webapp_form
	{
		$form = new webapp_form($context, $authurl);
		$form->fieldset('Username');
		$form->field('username', 'text', ['placeholder' => 'Type username', 'required' => NULL, 'autofocus' => NULL]);
		$form->fieldset('Password');
		$form->field('password', 'password', ['placeholder' => 'Type password', 'required' => NULL]);
		$form->captcha('Captcha');
		$form->fieldset();
		$form->button('Sign In', 'submit');
		$form->xml['spellcheck'] = 'false';
		return $form;
	}
	static function form_mobileconfig(array|webapp|webapp_html $context, ?string $authurl = NULL):webapp_form
	{
		$form = new webapp_form($context, $authurl);
		$form->fieldset('Icon / Label');
		$form->field('Icon', 'file', ['accept' => 'image/*', 'required' => NULL]);
		$form->field('Label', 'text', ['placeholder' => 'App Name', 'required' => NULL]);
		
		$form->fieldset('URL');
		$form->field('URL', 'url', ['style' => 'width:24rem', 'placeholder' => 'Startup URL', 'required' => NULL]);

		$boolean = ['No', 'Yes'];
		$format = fn($v, $i) => $i ? boolval($v) : intval($v);
 
		$form->fieldset('Payload Display Name / Full Screen');
		$form->field('PayloadDisplayName', 'text', ['placeholder' => 'Payload Display Name', 'required' => NULL]);
		$form->field('FullScreen', 'select', ['options' => $boolean, 'required' => NULL], $format);

		$form->fieldset('Payload Description / Is Removable');
		$form->field('PayloadDescription', 'text', ['placeholder' => 'Payload Description', 'required' => NULL]);
		$form->field('IsRemovable', 'select', ['options' => $boolean, 'required' => NULL], $format);

		$form->fieldset('Payload Organization / Ignore ManifestScope');
		$form->field('PayloadOrganization', 'text', ['placeholder' => 'Payload Organization', 'required' => NULL]);
		$form->field('IgnoreManifestScope', 'select', ['options' => $boolean, 'required' => NULL], $format);

		$form->fieldset('Payload Identifier');
		$form->field('PayloadIdentifier', 'text', ['style' => 'width:24rem', 'placeholder' => 'com.webapp.example', 'pattern' => '\w+(.\w+)+', 'required' => NULL]);
		if ($form->echo && $form->webapp)
		{
			$form->echo([
				'PayloadDisplayName' => $form->webapp::class,
				'PayloadDescription' => $form->webapp['copy_webapp'],
				'PayloadOrganization' => $form->webapp['copy_webapp'],
				'FullScreen' => 1,
				'IsRemovable' => 1,
				'IgnoreManifestScope' => 1,
				'PayloadIdentifier' => sprintf('com.webapp.id%s', bin2hex($form->webapp->random(8)))
			]);
		}

		$form->fieldset();
		$form->button('Build Mobile Config', 'submit');

		return $form;
	}
}
class webapp_echo_masker extends webapp_echo_html
{
	public readonly bool $initiated;
	public readonly webapp_html $sw;
	public ?webapp_echo_json $json;
	private ?DOMNode $template = NULL;
	protected array $allow = ['get_splashscreen'];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		//$this->template = $this->document;
		/*
		Sets whether a web application runs in full-screen mode.
		If content is set to yes, the web application runs in full-screen mode; otherwise, it does not. The default behavior is to use Safari to display web content.
		You can determine whether a webpage is displayed in full-screen mode using the window.navigator.standalone read-only Boolean JavaScript property.
		*/
		//$this->meta(['name' => 'apple-mobile-web-app-capable', 'content' => 'yes']);
		/*
		Sets the style of the status bar for a web application.
		This meta tag has no effect unless you first specify full-screen mode as described in apple-apple-mobile-web-app-capable.
		If content is set to default, the status bar appears normal.
		If set to black, the status bar has a black background.
		If set to black-translucent, the status bar is black and translucent.
		If set to default or black, the web content is displayed below the status bar.
		If set to black-translucent, the web content is displayed on the entire screen, partially obscured by the status bar. The default value is default.
		*/
		//$this->meta(['name' => 'apple-mobile-web-app-status-bar-style', 'content' => 'black']);
		/*
		Enables or disables automatic detection of possible phone numbers in a webpage in Safari on iOS.
		By default, Safari on iOS detects any string formatted like a phone number and makes it a link that calls the number. Specifying telephone=no disables this feature.
		*/
		//$this->meta(['name' => 'format-detection', 'content' => 'telephone=no']);
		$this->sw = $this->xml->head->append('script', ['fetchpriority' => 'high', 'src' => '?masker']);
		// $webapp->request_header('Sec-Fetch-Dest') === 'document'
		// $webapp->request_header('Sec-Fetch-Mode') === 'navigate'
		// $webapp->request_header('Sec-Fetch-Site') === 'none'
		if ($this->initiated = $webapp->request_header('Service-Worker') !== 'masker')
		{
			unset($this->xml->head->link);
			if (preg_match('/iphone os (\d+)/i', $webapp->request_device(), $iphone) && $iphone[1] < 15)
			{
				unset($this->xml->head->script);
				$webapp->break($this->init(...), FALSE);
			}
			else
			{
				// $this->aside->append('textarea', [join(array_map(fn($k, $v) =>
				// 	in_array($k, ['Accept', 'Cookie', 'User-Agent'], TRUE) ? '' : "{$k}: {$v}\n",
				// 	array_keys($getallheaders = getallheaders()), array_values($getallheaders))), 'rows' => 20, 'cols' => 80]);
				$this->sw['data-reload'] = "?{$webapp['request_query']}";
				if (method_exists($this, 'get_splashscreen'))
				{
					$this->sw['data-splashscreen'] = sprintf('?%s/splashscreen', substr(static::class, strlen($webapp['app_router'])));
				}
				$webapp->break($this->init(...), TRUE);
			}
		}
		else
		{
			if (in_array($webapp->method, $this->allow, TRUE)) return;
			if (method_exists($this, 'authorization'))
			{
				if (empty($webapp->authorization($this->authorization(...))))
				{
					if (is_array($input = json_decode($webapp->request_header('Sign-In') ?? '', TRUE)))
					{
						$webapp($this->json(['signature' => NULL], TRUE));
						if (static::form_sign_in($this->webapp)->fetch($account, $errors, $input))
						{
							if ($user = $this->authorization($account['username'], $account['password'], $webapp->time, 'signature'))
							{
								$this->json['signature'] = $webapp->signature(...$user);
							}
							else
							{
								$this->json['errors'][] = 'Authorization failed';
							}
						}
						$webapp->response_status(200);
					}
					else
					{
						$webapp->break($this->sign_in(...));
					}
				}
			}
		}
	}
	function template():webapp_html
	{
		return webapp_html::from($this->template = $this->document->createElement('template'));
	}
	function __toString():string
	{
		return $this->initiated ? parent::__toString() : $this->webapp->response_maskdata(
			isset($this->json) ? (string)$this->json : ($this->template
				? substr($this->document->saveHTML($this->template), 10, -11)
				: $this->document->saveHTML($this->document)));
	}
	function init(bool $success)
	{
		$this->title('Initializing');
		$this->main->text($success
			? 'Enable JavaScript and cookies to continue'
			: 'Please upgrade system or device to continue');
		return 200;
	}
	function json(array|object $data):webapp_echo_json
	{
		return $this->json = new webapp_echo_json($this->webapp, $data);
	}
	function sign_in()
	{
		static::form_sign_in($this->main)->xml['onsubmit'] = <<<'JS'
if (this.style.pointerEvents !== 'none')
{
	const data = Object.fromEntries(new FormData(this).entries());
	this.style.pointerEvents = 'none';
	this.querySelectorAll('fieldset').forEach(element => element.disabled = true);
	masker(this.action, {headers: {'Sign-In': JSON.stringify(data)}}).then(response => response.json()).then(data =>
	{
		data.errors.length && alert(data.errors.join('\n'));
		data.signature && masker.authorization(data.signature).then(() => location.reload());
	}).finally(() => {
		this.style.pointerEvents = null;
		this.querySelectorAll('fieldset').forEach(element => element.disabled = false);
	});
}
return false;
JS;
		return 401;
	}
}
class webapp_echo_htmlmask extends webapp_echo_html
{
	public readonly bool $entry;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($this->entry = $webapp->method === "{$webapp['request_method']}_{$webapp['app_index']}")
		{
			unset($this->xml->head->link[0], $this->xml->body->div);
	
			$this->script(['src' => '/webapp/res/js/loader.js']);
			$this->script(['src' => '/webapp/res/js/framer.js']);
			//$this->wallpaper();
			$this->xml->body['style'] = 'margin:0px';//;padding:0px;overflow:hidden
			$this->xml->body->append('iframe', [
				'importance' => 'high',
				'width' => '100%',
				'height' => '100%',
				'style'=> 'position:fixed;border:none',
				'data-load' => '?load',
				'src' => 'about:blank'
			]);
		}
		else
		{
			$this->script('history.pushState(null,null,document.URL);window.addEventListener("popstate",()=>history.pushState(null,null,document.URL))');
			unset($this->xml->head->link[1]);
		}
	}
	function __toString():string
	{
		if ($this->entry)
		{
			return parent::__toString();
		}
		$this->webapp->response_content_type('@text/html');
		foreach ($this->xml->xpath('//*[@src]|//*[@href]|//*[@action]') as $node)
		{
			if (strlen($source = (string)$node[$type = match (TRUE)
				{
					isset($node['src']) => 'src',
					isset($node['href']) => 'href',
					default => 'action'
				}])
				&& preg_match('/^\w+\:/', $source) === 0) {
				$node[$type] = match($source[0])
				{
					'/' => "{$this->webapp->request_origin}{$source}",
					'?' => "{$this->webapp->request_entry}{$source}",
					default => "{$this->webapp->request_dir}/{$source}"
				};
			}
		}
		return $this->webapp->maskdata(parent::__toString());
	}
}

/*
class webapp_echo_xls extends webapp_echo_xml
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		#$webapp->response_content_type('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$this->loadXML("<?xml version='1.0' encoding='{$webapp['app_charset']}'?><?mso-application progid='Excel.Sheet'?><Workbook/>");
		$this->xml->setattr([
			'xmlns' => 'urn:schemas-microsoft-com:office:spreadsheet',
			// 'xmlns:o' => 'urn:schemas-microsoft-com:office:office',
			'xmlns:x' => 'urn:schemas-microsoft-com:office:excel',
			'xmlns:ss' => 'urn:schemas-microsoft-com:office:spreadsheet',
			// 'xmlns:html' = 'http://www.w3.org/TR/REC-html40'
		]);
		$style = $this->xml->append('Styles')->append('Style', [
			// 'ss:Name' => 'Normal',
			'ss:ID' => 'sc0'
		]);
		$style->append('Alignment', [
			// 'ss:Horizontal' => 'Center',
			// 'ss:Horizontal' => 'Fill',
			'ss:Vertical' => 'Center'
		]);
		$borders = $style->append('Borders');
		$borders->append('Border', ['ss:Position' => 'Left', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Top', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Right', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Bottom', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$this->worksheet = $this->xml->append('Worksheet', ['ss:Name' => 'webapp']);
		$this->table = $this->worksheet->append('Table');
	}
	function appendrow(...$values):webapp_xml
	{
		$row = $this->table->append('Row');
		foreach ($values as $value)
		{
			$row->append('Cell', ['ss:StyleID' => 'sc0'])->append('Data', [$value, 'ss:Type' => 'String']);
		}
		return $row;
	}
	function import(iterable $data):static
	{
		foreach ($data as $values)
		{
			$this->appendrow(...$values);
		}
		return $this;
	}
}
*/
