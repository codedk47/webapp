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
	static function mobileconfig(array $values, webapp $webapp = NULL, bool $config = FALSE):webapp_implementation
	{
		$mobileconfig = new webapp_implementation('plist', '-//Apple//DTD PLIST 1.0//EN', 'http://www.apple.com/DTDs/PropertyList-1.0.dtd');
		$mobileconfig->xml['version'] = '1.0';
		if ($webapp)
		{
			$webapp->response_content_type($config ? 'application/x-apple-aspen-config' : 'application/xml');
			$mobileconfig->document->encoding = $webapp['app_charset'];
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
			'PayloadUUID' => '00801462-0000-0000-0000-000000000000',
			'PayloadType' => 'Configuration',
			'PayloadIdentifier' => 'WEBAPP.ID',
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
				'IgnoreManifestScope' => TRUE,
				//'PayloadDisplayName' => 'WebApp',
				//以下四个必要固定字段
				'PayloadUUID' => '00801462-0000-0000-0000-000000000000',
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
			//$this->script(['src' => '/webapp/res/js/sw.js', 'defer' => NULL]);
			$this->link(['rel' => 'manifest', 'href' => '?manifests']);

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
	// function addstyle(string $rule):DOMText
	// {
	// 	return ($this->style ??= $this->xml->head->append('style', ['media' => 'all']))->text($rule);
	// }
	function wallpaper()
	{
		$this->script(['src' => '/webapp/res/js/tgwallpaper.min.js']);
		$wallpaper = $this->xml->body->insert('div', 'first')->setattr(['style' => 'position:fixed;z-index:-1;top:0;left:0;right:0;bottom:0;']);
		$wallpaper->append('canvas', [
			'id'=>"wallpaper",
			'width' => 50,
			'height' => 50,
			'data-colors' => 'dbddbb,6ba587,d5d88d,88b884',
			'style' => 'position:absolute;width:100%;height:100%'
		]);
		$wallpaper->append('div', [
			'style' => 'position:absolute;width:100%;height:100%;background-image:url(/webapp/res/ps/pattern-telegram.svg);mix-blend-mode: overlay;opacity:.4'
		]);
		$this->xml->body->append('script', <<<JS
const wallpaper = document.getElementById('wallpaper');
if (wallpaper)
{
	TWallpaper.init(wallpaper);
	TWallpaper.animate(true);
	TWallpaper.update();
}
JS);
	}
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
		return $form;
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
			$this->wallpaper();
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
class webapp_echo_json extends ArrayObject implements Stringable
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, array|object $data = [])
	{
		$webapp->response_content_type("application/json; charset={$webapp['app_charset']}");
		parent::__construct($data, ArrayObject::STD_PROP_LIST);
	}

	function __toString():string
	{
		return json_encode($this->getArrayCopy(), JSON_UNESCAPED_UNICODE);
		// try
		// {
		// } catch (JsonException $error)
		// {
		// }
	}
	// function dialog(string|array $context, )
	// {
	// 	$this['dialog'] = $context;
	// }
	function goto(string $url = NULL)
	{
		$this['goto'] = $url;
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
