<?php
declare(strict_types=1);
trait webapp_echo
{
	public readonly webapp $webapp;
	abstract function __construct(webapp $webapp);
	abstract function __toString():string;
	// function __get(string $name):mixed
	// {
	// 	return $this->{$name} = &$this->webapp->{$name};
	// }
	// function __call(string $name, array $params):mixed
	// {
	// 	return $this->webapp->{$name}(...$params);
	// }
}
// class webapp_echo_text implements Stringable
// {
// 	use webapp_echo;
// 	function __construct(public readonly webapp $webapp, private string $content = '')
// 	{
// 		$webapp->response_content_type("text/plain; charset={$webapp['app_charset']}");
// 	}
// 	function __toString():string
// 	{
// 		return $this->content;
// 	}
// }
class webapp_echo_xml extends webapp_implementation
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, string $root = 'webapp')
	{
		$webapp->response_content_type('application/xml');
		parent::__construct($root);
	}
	static function mobileconfig(webapp $webapp, array $values, bool $force = FALSE, bool $download = FALSE):webapp_implementation
	{
		if ($force)
		{
			$webapp->response_content_type($download ? 'application/x-apple-aspen-config' : 'application/xml');
		}
		$mobileconfig = new webapp_implementation('plist', '-//Apple//DTD PLIST 1.0//EN', 'http://www.apple.com/DTDs/PropertyList-1.0.dtd');
		$mobileconfig->document->encoding = $webapp['app_charset'];
		$mobileconfig->xml['version'] = '1.0';
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
		//----Web Cilp----
		// config($mobileconfig->xml, [
		// 	'PayloadContent' => [[
		// 		'Icon' => 'D:/wmhp/work/asd/icon.png', //桌面图标
		// 		'Label' => 'webapp', //桌面名称
		// 		'URL' => 'http://192.168.0.155/a.php',
		// 		'FullScreen' => FALSE,
		// 		'IsRemovable' => TRUE,
		// 		'IgnoreManifestScope' => TRUE,
		// 		//'PayloadDisplayName' => 'WebApp',
		// 		//以下四个必要固定字段
		// 		'PayloadUUID' => 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
		// 		'PayloadType' => 'com.apple.webClip.managed',
		// 		'PayloadIdentifier' => 'Ignored',
		// 		'PayloadVersion' => 1
		// 	]],
		// 	'PayloadDisplayName' => 'Web App',
		// 	'PayloadDescription' => 'Web Application',
		// 	//以下四个必要固定字段
		// 	'PayloadUUID' => 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
		// 	'PayloadType' => 'Configuration',
		// 	'PayloadIdentifier' => 'WEBAPP.ID',
		// 	'PayloadVersion' => 1
		// ]);

		//----Profile Service----
		//openssl smime -sign -in unsigned.mobileconfig -out signed.mobileconfig -signer mbaike.crt -inkey mbaike.key -certfile ca-bundle.pem -outform der -nodetach
		//openssl rsa -in mbaike.key -out mbaikenopass.key
		//openssl smime -sign -in unsigned.mobileconfig -out signed.mobileconfig -signer mbaike.crt -inkey mbaikenopass.key -certfile ca-bundle.pem -outform der -nodetach
		// config($mobileconfig->xml, [
		// 	'PayloadContent' => [
		// 		'URL' => 'https://kenb.cloud/a.php',
		// 		'DeviceAttributes' => ['DEVICE_NAME', 'UDID', 'IMEI', 'ICCID', 'VERSION', 'PRODUCT', 'SERIAL', 'MAC_ADDRESS_EN0'],
		// 	],
		// 	'PayloadDisplayName' => 'Web App',
		// 	'PayloadDescription' => 'Web Application',
		// 	//以下四个必要固定字段
		// 	'PayloadUUID' => 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
		// 	'PayloadType' => 'Profile Service',
		// 	'PayloadIdentifier' => 'WEBAPP.ID',
		// 	'PayloadVersion' => 1
		// ]);
		config($mobileconfig->xml, $values);
		return $mobileconfig;
	}
}
class webapp_echo_svg extends webapp_implementation
{
	use webapp_echo;
	public readonly webapp_svg $svg;
	function __construct(public readonly webapp $webapp, array $attributes = [])
	{
		$webapp->response_content_type('image/svg+xml');
		parent::__construct('svg', '-//W3C//DTD SVG 1.1//EN', 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd');
		$this->xml->setattr(['xmlns' => 'http://www.w3.org/2000/svg'] + $attributes);
	}
	function __invoke(bool $loaded):bool
	{
		return parent::__invoke($loaded) && $this->svg = new webapp_svg($this->xml);
	}
	static function favicon(webapp $webapp):static
	{
		$favicon = new static($webapp, ['width' => 24, 'height' => 24, 'viewBox' => '0 0 24 24']);
		$favicon->xml->append('path', ['d' => 'M12 2c-4.963 0-9 4.038-9 9v8h.051c.245 1.691 1.69 3 3.449 3 1.174 0 2.074-.417 2.672-1.174a3.99 3.99 0 0 0 5.668-.014c.601.762 1.504 1.188 2.66 1.188 1.93 0 3.5-1.57 3.5-3.5V11c0-4.962-4.037-9-9-9zm7 16.5c0 .827-.673 1.5-1.5 1.5-.449 0-1.5 0-1.5-2v-1h-2v1c0 1.103-.897 2-2 2s-2-.897-2-2v-1H8v1c0 1.845-.774 2-1.5 2-.827 0-1.5-.673-1.5-1.5V11c0-3.86 3.141-7 7-7s7 3.14 7 7v7.5z']);
		$favicon->xml->append('circle', ['cx' => 9, 'cy' => 10, 'r' => 2]);
		$favicon->xml->append('circle', ['cx' => 15, 'cy' => 10, 'r' => 2]);
		return $favicon;
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
		//$this->link(['rel' => 'manifest', 'href' => '?webmanifest']);

		// $head->append('meta', ['charset' => $webapp['app_charset']]);
		// $head->append('meta', ['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1']);
		// $head->append('link', ['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => '?favicon']);
		// $head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/res/ps/webapp.css', 'media' => 'all']);
		
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
	static function frame(webapp_echo_html $context)//:webapp_html
	{
		if ($context instanceof webapp_echo_html)
		{
			unset($context->xml->head->link, $context->xml->body->div);
			$context->xml->body['style'] = $context->xml['style'] = 'height:100%;margin:0;overflow:hidden';
			$context = $context->xml->body;
		}
		
		$frame = $context->append('iframe', [
			'frameborder' => 0,
			'loading' => 'lazy',
			'width' => '100%',
			'height' => '100%',
			'src' => 'about:blank'
		]);
		return $frame;
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
			$this->script(['src' => '/webapp/res/js/backer.js']);
			$this->script(['src' => '/webapp/res/js/framer.js']);
			$this->xml->body['style'] = 'margin:0px;padding:0px;overflow:hidden';
			$this->xml->body->append('iframe', [
				'importance' => 'high',
				'width' => '100%',
				'height' => '100%',
				'src' => 'about:blank',
				//'src' => '?asd',
				'style'=> 'position:fixed;border:none',
				'data-query' => '?asd'
			]);
		}
		else
		{
			unset($this->xml->head->link[1]);
			//print_r($this);
		}
	}
	function __toString():string
	{
		return $this->entry ? parent::__toString() : $this->webapp->maskdata(parent::__toString());
		if ($this->entry)
		{
			return parent::__toString();
		}
		$this->webapp->response_content_type('text/plain');
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
	static function webmanifest(webapp $webapp, array $configs):static
	{
		// return new static($webapp, [
		// 	'background_color' => 'white',
		// 	'description' => 'Web Application',
		// 	'display' => 'fullscreen',
		// 	'icons' => [
		// 		['src' => '?favicon', 'sizes' => "192x192", 'type' => 'image/svg+xml']
		// 	],
		// 	'name' => 'WebApp',
		// 	'short_name' => 'webapp',
		// 	'start_url' => '/'
		// ]);
		return new static($webapp, $configs);
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
