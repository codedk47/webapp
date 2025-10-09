<?php
declare(strict_types=1);
trait webapp_echo
{
	public $locale;
	public array $errors = [];
	public readonly webapp $webapp;
	abstract function __construct(webapp $webapp);
	abstract function __toString():string;
	function locale(string $prefix, string ...$locales):string
	{
		return $this->webapp->locale($this->locale, $prefix, ...$locales);
	}
	function input(string $type = NULL):webapp_xml|array|string
	{
		return $this->webapp->request_content(match ($type)
		{
			'xml' => 'application/xml',
			'json' => 'application/json',
			default => 'text/plain'
		});
	}
}
class webapp_echo_xml extends webapp_implementation
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, string $type = 'webapp', string ...$params)
	{
		$webapp->response_content_type('application/xml');
		parent::__construct($type, ...$params);
	}
	static function apple_mobile_config(array $values, webapp $webapp = NULL, ?string $basename = NULL):webapp_implementation
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
	function error(Error|string|array $message, string $field = NULL):void
	{
		$this['error'] = match (TRUE)
		{
			$message instanceof Error =>
			[
				'message' => $this['errors'][] = $message->getMessage(),
				'code' => $message->getCode(),
				'file' => $message->getFile(),
				'line' => $message->getLine(),
				'trace' => $message->getTraceAsString()],
			is_string($message) => $field ? ['message' => $this['errors'][] = $message, 'field' => $field] : ['message' => $this['errors'][] = $message],
			default => isset($message['message']) ? ['message' => $this['errors'][] = $message] : $message
		};
	}
	function message(string|array $context):void
	{
		$this['message'] = $context;
	}
	function confirm(string|array $context):void
	{
		$this['confirm'] = $context;
	}
	function prompt(string|webapp_form $context):void
	{
		$this['prompt'] = $context instanceof webapp_form ? $context->xml->html() : $context;
	}
	function continue(string $action, string $method = NULL, string|array $body = NULL):void
	{
		$this['continue'] = ['action' => $action, 'method' => $method, 'body' => $body];
	}
	function redirect(string $url):void
	{
		//$this->webapp->response_location($url);
		$this['redirect'] = $url;
	}
	function refresh(?string $url = NULL):void
	{
		//$this->webapp->response_refresh(0, $url);
		$this['refresh'] = $url;
	}
}
class webapp_echo_html extends webapp_implementation implements ArrayAccess
{
	use webapp_echo {locale as private echo_locale;}
	public readonly array $auth;
	public readonly string $routename;
	protected string|webapp_html|webapp_echo_json $echo;
	public readonly webapp_html $header, $aside, $main, $footer;
	function __construct(public readonly webapp $webapp, webapp|string $authenticate = NULL, string ...$allow)
	{
		//https://validator.w3.org/nu/#textarea
		$webapp->response_content_type("text/html; charset={$webapp['app_charset']}");
		parent::__construct();
		$this->routename = $webapp->routename;
		$this->xml->setattr(['lang' => $webapp['app_locale']])->append('head');
		$this->meta(['charset' => $webapp['app_charset']]);
		$this->meta(['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1']);
		$this->link(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => '?favicon']);
		$this->stylesheet('/webapp/static/webapp.css');
		$this->script(['type' => 'module', 'src' => '/webapp/static/modules/webapp.mjs']);
		$webapp['manifests'] && $this->link(['rel' => 'manifest', 'href' => '?manifests']);
		$node = $this->xml->append('body')->append('div', ['class' => 'webapp-grid']);
		[$this->header, $this->aside, $this->main, $this->footer] = [
			&$node->header, &$node->aside, &$node->main,
			$node->append('footer', $webapp['copy_webapp'])];
		$authenticate && $this->initauth($authenticate, ...$allow);
	}
	function __toString():string
	{
		return isset($this->echo) ? match (get_debug_type($this->echo))
		{
			'webapp_html' => substr($this->document->saveHTML($this->echo->dom()), 10, -11),
			'webapp_echo_json' => (string)$this->echo,
			default => $this->echo
		} : parent::__toString();
	}
	final function offsetExists(mixed $offset):bool
	{
		return array_key_exists($offset, $this->locale);
	}
	final function offsetGet(mixed $offset):mixed
	{
		return $this->locale[$offset] ?? NULL;
	}
	final function offsetSet(mixed $offset, mixed $value):void
	{
		$this->locale[$offset] = $value;
	}
	final function offsetUnset(mixed $offset):void
	{
		unset($this->locale[$offset]);
	}
	final function locale(string $prefix, string ...$locales):string
	{
		return $this->xml['lang'] = $this->echo_locale($prefix, ...$locales);
	}
	final function initauth(webapp|string $context, string ...$allow):void
	{
		[$this->auth, $authenticate, $storage] = is_string($context)
			? [$this->webapp->auth($this->authenticate(...), $context), $this->authenticate(...), $context]
			: [$this->webapp->auth, $this->webapp->authenticate(...), $this->webapp['admin_cookie']];
		if (in_array($this->webapp->method, $allow, TRUE) === FALSE && empty($this->auth))
		{
			$this->webapp->response_status($this->auth(json_decode(rawurldecode(
				$this->webapp->request_header('Sign-In') ?? ''), TRUE), $authenticate, $storage));
		}
	}
	function auth(?array $data, callable $authenticate, string $storage):int
	{
		if (is_array($data))
		{
			$this->json();
			if (static::form_sign_in($this->webapp)->fetch($account, input: $data))
			{
				if ($auth = $authenticate($account['username'], $account['password'], $this->webapp->time))
				{
					$this->echo['signature'] = $this->webapp->signature(...$auth);
				}
				else
				{
					$this->echo->error('Authorization failed', 'username');
				}
			}
			return 200;
		}
		$this->title('Authenticate');
		$form = static::form_sign_in($this->main);
		$form->xml['onsubmit'] = 'return $.authsignin(this,authorize=>authorize.signature?location.reload(document.cookie=`${this.dataset.storage}=${authorize.signature};path=/`):(this[authorize.error.field].setCustomValidity(authorize.error.message),requestAnimationFrame(()=>this.reportValidity())))';
		$form->xml['data-storage'] = $storage;
		// $form->xml['onsubmit'] = <<<'JS'
		// if (this.style.pointerEvents !== 'none')
		// {
		// 	const
		// 	account = Object.fromEntries(new FormData(this).entries()),
		// 	fieldset = this.querySelectorAll('fieldset');
		// 	this.style.pointerEvents = 'none';
		// 	fieldset.forEach(field => field.disabled = true);
		// 	this.oninput = event => Object.keys(account).forEach(field => this[field].setCustomValidity(''));
		// 	fetch(this.action, {headers: {'Sign-In': encodeURI(JSON.stringify(account))}}).then(response => response.json())
		// 	.then(authorize => authorize.signature
		// 		? location.reload(document.cookie = `${this.dataset.storage}=${authorize.signature};path=/`)
		// 		: (this[authorize.error.field].setCustomValidity(authorize.error.message),
		// 			requestAnimationFrame(() => this.reportValidity())))
		// 	.finally(() => fieldset.forEach(field => field.disabled = false), this.style.pointerEvents = null);
		// }
		// return false;
		// JS;
		return 401;
	}
	function clear(bool $all = FALSE):void
	{
		if ($all)
		{
			unset($this->xml->head->link,
				$this->xml->head->script,
				$this->xml->head->title);
		}
		unset($this->xml->body->div);
	}
	function echo(string $data = ''):void
	{
		$this->webapp->response_content_type("text/plain; charset={$this->webapp['app_charset']}");
		$this->echo = $data;
	}
	function json(array|object $data = []):webapp_echo_json
	{
		return $this->echo = ($this->webapp)(new webapp_echo_json($this->webapp, $data));
	}
	function frag():webapp_html
	{
		return $this->echo = webapp_html::from($this->document->createElement('template'));
	}
	function template(string $id):webapp_html
	{
		return $this->xml->body->template($id);
	}
	// function echo_no_content():int
	// {
	// 	$this->echo('');
	// 	return 204;
	// }
	function meta(array $attributes):webapp_html
	{
		return $this->xml->head->append('meta', $attributes);
	}
	function link(array $attributes):webapp_html
	{
		return $this->xml->head->append('link', $attributes);
	}
	function stylesheet(string $filename, string $media = 'all'):webapp_html
	{
		return $this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $filename, 'media' => $media]);
	}
	function style(string $stylesheet, string $media = 'all'):webapp_html
	{
		$style = $this->xml->head->append('style', ['media' => $media]);
		$style->text($stylesheet);
		return $style;
	}
	function script(array|string $context):webapp_html
	{
		$script = $this->xml->head->append('script');
		$script->{is_array($context) ? 'setattr' : 'cdata'}($context);
		return $script;
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}
	function meta_pageinfo(string $keywords, string $description = NULL, string $author = NULL):void
	{
		$this->meta(['name' => 'keywords', 'content' => $keywords]);
		$this->meta(['name' => 'description', 'content' => $description]);
		$author && $this->meta(['name' => 'author', 'content' => $author]);
	}
	function meta_opengraph(string $title, string $type = 'website', string $image = NULL):void
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
	// function script_variables(array $variables, string $name = 'WEBAPP'):void
	// {
	// 	$this->script("const {$name}=" . json_encode($variables, JSON_UNESCAPED_UNICODE));
	// }


	// function wallpaper()
	// {
	// 	$this->script(['src' => '/webapp/static/js/tgwallpaper.min.js']);
	// 	$wallpaper = $this->xml->body->insert('div', 'first')->setattr(['style' => 'position:fixed;z-index:-1;top:0;left:0;right:0;bottom:0;']);
	// 	$wallpaper->append('canvas', [
	// 		'id'=>"wallpaper",
	// 		'width' => 50,
	// 		'height' => 50,
	// 		'data-colors' => 'dbddbb,6ba587,d5d88d,88b884',
	// 		'style' => 'position:absolute;width:100%;height:100%'
	// 	]);
	// 	$wallpaper->append('div', [
	// 		'style' => 'position:absolute;width:100%;height:100%;background-image:url(/webapp/static/ps/pattern-telegram.svg);mix-blend-mode: overlay;opacity:.4'
	// 	]);
	// 	$this->xml->body->append('script', <<<JS
	// 	const wallpaper = document.getElementById('wallpaper');
	// 	if (wallpaper)
	// 	{
	// 		TWallpaper.init(wallpaper);
	// 		TWallpaper.animate(true);
	// 		TWallpaper.update();
	// 	}
	// 	JS);
	// }

	function nav(array $anchors):webapp_html
	{
		return $this->header->append('nav')->listmenu($anchors, TRUE, TRUE, TRUE);
	}
	function submenu(array $anchors, bool $fold = FALSE):webapp_html
	{
		return $this->aside->listmenu($anchors, $fold);
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
}
class webapp_echo_masker extends webapp_echo_html
{
	public readonly bool $init;
	public readonly array $auth;
	public readonly webapp_html $sw;
	public array $initallow = [];
	function __construct(webapp $webapp, webapp|string $authenticate = NULL, string ...$allow)
	{
		parent::__construct($webapp);
		//$this->template = $this->document;

		/* $this->meta(['name' => 'apple-mobile-web-app-capable', 'content' => 'yes']);
		Sets whether a web application runs in full-screen mode.
		If content is set to yes, the web application runs in full-screen mode; otherwise, it does not. The default behavior is to use Safari to display web content.
		You can determine whether a webpage is displayed in full-screen mode using the window.navigator.standalone read-only Boolean JavaScript property.
		*/

		/* $this->meta(['name' => 'apple-mobile-web-app-status-bar-style', 'content' => 'black']);
		Sets the style of the status bar for a web application.
		This meta tag has no effect unless you first specify full-screen mode as described in apple-apple-mobile-web-app-capable.
		If content is set to default, the status bar appears normal.
		If set to black, the status bar has a black background.
		If set to black-translucent, the status bar is black and translucent.
		If set to default or black, the web content is displayed below the status bar.
		If set to black-translucent, the web content is displayed on the entire screen, partially obscured by the status bar. The default value is default.
		*/
		
		/* $this->meta(['name' => 'format-detection', 'content' => 'telephone=no']);
		Enables or disables automatic detection of possible phone numbers in a webpage in Safari on iOS.
		By default, Safari on iOS detects any string formatted like a phone number and makes it a link that calls the number. Specifying telephone=no disables this feature.
		*/
		$this->sw = $this->script(['fetchpriority' => 'high', 'src' => "?masker{$webapp->into}"]);
		if ($this->init = $webapp->request_header('Service-Worker') !== 'masker')
		{
			$this->auth = [];
			if (in_array($webapp->method, $this->initallow, TRUE)) return;
			$this->sw['data-reload'] = "?{$webapp['request_query']}";
			//$this->sw['data-router'] = $this->routename;
			$this->sw['data-init'] = "?{$this->routename}/init";
			$this->sw['data-splashscreen'] = "?{$this->routename}/splashscreen";
			$ua = $webapp->request_device();
			match (TRUE)
			{
				preg_match('/cid\/([0-9a-z]{4})/i', $ua, $cid) === 1 => $cid = $cid[1],
				is_string($cid = $webapp->query['cid'] ?? NULL) && preg_match('/^[0-9a-z]{4}$/i', $cid) => $cid,
				default => NULL
			} && $webapp->response_cookie('cid', $cid);
			match (TRUE)
			{
				preg_match('/cid\/([0-9a-z]{16})/i', $ua, $did) === 1 => $did = $did[1],
				is_string($did = $webapp->query['did'] ?? NULL) && preg_match('/^[0-9a-z]{16}$/i', $did) => $did,
				default => NULL
			} && $webapp->response_cookie('did', $did);
			$webapp->break($this->init(...), $ua);
			unset($this->xml->head->link, $this->xml->head->script[0]);
		}
		else
		{
			$this->xml->head->meta[1]['content'] .= ',maximum-scale=1,user-scalable=0';
			$authenticate && $this->initauth($authenticate, 'post_init', 'get_splashscreen', ...$allow);
		}
	}
	function __toString():string
	{
		return $this->init
			? parent::__toString()
			: [(string)$this->webapp->maskdata(parent::__toString(), $key),
				$this->webapp->response_header('Masker-Key', bin2hex($key))][0];
	}
	function init(string $ua):int
	{
		$this->title('Initializing');
		$this->main->text(preg_match('/iphone os (\d+)/i', $ua, $iphone) && $iphone[1] < 15
			? 'Please upgrade system or device to continue'
			: 'Enable JavaScript and cookies to continue');
		return 200;
	}
	function post_init():int
	{
		$this->json($this->webapp->request_content('application/json'));
		return 200;
	}
	function splashscreen(string $cover, string $jumpurl, string $skip = 'SKIP', int $cd = 4, bool $auto = FALSE, string $hash = NULL):webapp_html
	{
		$body = $this->xml->body;
		$body->append('a', [
			'href' => $jumpurl,
			//'style' => "display:block;height:100vh;background:url({$cover})white no-repeat center/100% 100%",
			'style' => "display:block;height:100vh;background:url({$cover})white no-repeat center/cover",
			'onclick' => 'return masker.clickad(this), requestAnimationFrame(masker.close)',
			'data-hash' => $hash]);
		$auto = $auto ? 'true' : 'false';
		$this->script("masker.skipsplashscreen('{$skip}',{$cd},{$auto})");
		unset($body->div);
		return $body;
	}
	function get_splashscreen():int
	{
		unset($this->xml->head->link, $this->xml->head->title, $this->xml->body->div);
		$this->script('masker.close()');
		return 200;
	}


}
class webapp_echo_admin extends webapp_echo_masker
{
	public array $nav = [], $submenu = [];
	function __construct(webapp $webapp, webapp|string $authenticate = NULL, string ...$allow)
	{
		parent::__construct($webapp, $authenticate ?? $webapp['admin_cookie'], ...$allow);
		if ($this->init)
		{
			unset($this->sw['data-splashscreen']);
		}
		if ($this->auth)
		{
			$this->title('Admin');
			$this->nav && $this->nav($this->nav);
			if (isset($this->submenu[$this->webapp->method]))
			{
				foreach ($this->submenu[$this->webapp->method] as $submenu)
				{
					$this->aside->append('a', [$submenu[0], 'href' => $submenu[1]]);
				}
			}
		}
	}
	function authenticate(string $username, string $password, int $signtime, string $additional = NULL):array
	{
		return $this->webapp->admin($username, $password, $signtime, $additional);
	}
}
class webapp_echo_sitemap extends webapp_echo_xml
{
	const xmlns = 'http://www.sitemaps.org/schemas/sitemap/0.9';
	public readonly string $origin, $entry;
	private string $tag = 'url';
	private webapp_xml $loc;
	public readonly array $google;
	function __construct(webapp $webapp)
	{
		$this->origin = $webapp->request_origin();
		parent::__construct($webapp, 'urlset');
	}
	function index():webapp_xml
	{
		$this->tag = 'sitemap';
		$newnode = $this->document->createElementNS(static::xmlns, 'sitemapindex');
		$this->document->replaceChild($newnode, $this->document->documentElement);
		//$this->document->replaceChildren($newnode);
		return $this->xml = webapp_xml::from($newnode);
	}
	function loc(string $url, array $params = []):webapp_xml
	{
		$this->loc = $this->xml->append($this->tag);
		$this->loc->append('loc', $url);
		if ($params)
		{
			/*
			<lastmod> - 可选，指定该<url>最后修改日期。采用W3C Datetime格式，一般使用YYYY-MM-DD格式省略时间部分。
				需注意，这个值和http header中的If-Modified-Since (304)是相互独立的，搜索引擎会根据自身的实现分别取对应的值。
			<changefreq> - 可选，指定<url>的大概更新频率。用于告诉搜索引擎大概多久重新抓取一次。
				有效值有：always、hourly、daily、weekly、monthly、yearly、never。
				awalys代表每次访问该网址都返回不一样的内容。never用于表示一个已归档的资源。
				需注意的是，这里只是向搜索引擎告知网站资源的更新情况，而搜索引擎的实际抓取频率取决于搜索引擎自身的实现。另外Google不支持这个标签，Baidu支持。
			<priority> - 可选，该<url>在网站中的比重，取值0.0-1.0之间。默认值为0.5。需注意该比重只是相对于当前网站下。另外Google不支持该标签，Baidu支持。
			*/
			foreach (['lastmod', 'changefreq', 'priority'] as $name)
			{
				isset($params[$name]) && $this->loc->append($name)->text((string)$params[$name]);
			}
		}
		return $this->loc;
	}
	function path(string ...$gets)
	{
		foreach ($gets as $get)
		{
			$this->loc($this->origin . $get);
		}
	}
	function google()
	{
		$this->google = [
			'image:namespace' => 'http://www.google.com/schemas/sitemap-image/1.1',
			'video:namespace' => 'http://www.google.com/schemas/sitemap-video/1.1'
		];
		foreach ($this->google as $qualified => $namespace)
		{
			$this->document->createAttributeNS($namespace, $qualified);
		}
	}
	function google_image(string $image):webapp_xml
	{
		//https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps?hl=zh-cn
		return $this->loc->addChild('image', namespace: $this->google['image:namespace'])->append('loc', $image);
	}
	function google_video(array $video):webapp_xml
	{
		//https://developers.google.com/search/docs/crawling-indexing/sitemaps/video-sitemaps?hl=zh-cn
		$node = $this->loc->addChild('video', namespace: $this->google['video:namespace']);
		foreach ($video as $key => $value)
		{
			if ($key === 'tag')
			{
				foreach ($value as $tag)
				{
					$node->append('tag')->cdata($tag);
				}
				continue;
			}
			in_array($key, ['title', 'description'], TRUE)
				? $node->append($key)->cdata($value)
				: $node->append($key, $value);
		}
		return $node;
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
