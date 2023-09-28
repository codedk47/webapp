<?php
declare(strict_types=1);
require 'webapp_client.php';
require 'webapp_dom.php';
require 'webapp_echo.php';
require 'webapp_image.php';
require 'webapp_mysql.php';
interface webapp_io
{
	function request_ip():string;
	function request_scheme():string;
	function request_method():string;
	function request_entry():string;
	function request_query():string;
	function request_header(string $name):?string;
	function request_cookie(string $name):?string;
	function request_content():string;
	function request_formdata():array;
	function request_uploadedfile():array;
	function response_sent():bool;
	function response_status(int $code):void;
	function response_header(string $value):void;
	function response_cookie(float|string ...$values):void;
	function response_content(string $data):bool;
	function response_sendfile(string $filename):bool;
}
abstract class webapp implements ArrayAccess, Stringable, Countable
{
	const version = '4.7a', key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
	public readonly self $webapp;
	public readonly array $query;
	public object|string $router;
	public string $method;
	private array $errors = [], $cookies = [], $headers = [], $uploadedfiles, $configs, $route, $entry;
	private static array $libary = [], $remote = [];
	// static private array $locks = [];
	// static function lock(string $filename = __FILE__):bool
	// {
	// 	fopen('php://memory', 'r+');
	// 	return is_resource(self::$locks[$filename] = fopen($filename, 'r')) && flock(self::$locks[$filename], LOCK_EX | LOCK_NB);
	// }
// 	static function fsync(string $filename, callable $sync = NULL, &$value = NULL):bool
// 	{
// 		$status = FALSE;
// 		if ($resource = fopen($filename, 'r+'))
// 		{
// 			if (flock($resource, LOCK_EX | LOCK_NB))
// 			{
// 				$value = is_callable($sync) ? $sync($resource) : $sync;
// 				flock($resource, LOCK_UN);
// 				$status = TRUE;
// 			}
// 			else
// 			{
// // 				ob_start();
// // readfile("text.txt");
// // $data = ob_get_clean();
// // 				//fread()
// // 				flock($resource, LOCK_SH);
// 				$value = file_get_contents($filename);
// 				//$value = stream_get_contents($resource);
// 				//$value = fread($resource, 8014);
				
// 			}
// 			fclose($resource);
// 		}
// 		return $status;
// 	}
	static function lib(string $filename)
	{
		return array_key_exists($name = strtolower($filename), static::$libary)
			? static::$libary[$name]
			: static::$libary[$name] = require __DIR__ . "/lib/{$name}";
	}
	static function qrcode(string $content, int $level = 0):IteratorAggregate&Countable
	{
		return static::lib('qrcode/interface.php')($content, $level);
	}
	static function time(int $offset = 0):int
	{
		return time() + $offset;
	}
	static function time33(string $data):int
	{
		// static $bit = PHP_INT_MAX >> 6, $add = PHP_INT_MAX >> 2;
		// for ($hash = 5381, $i = strlen($data); $i;)
		// {
		// 	$hash = (($hash & $bit) << 5) + ($hash & $add) + ord($data[--$i]);
		// }
		// return $hash;
		for ($hash = 5381, $i = strlen($data); $i;)
		{
			$hash = ($hash & 0xfffffffffffffff) + (($hash & 0x1ffffffffffffff) << 5) + ord($data[--$i]);
		}
		return $hash;
	}
	static function time33hash(int $code, bool $care = FALSE):string
	{
		for ($hash = '', [$i, $n, $b] = $care ? [10, 6, 63] : [12, 5, 31]; $i;)
		{
			$hash .= self::key[$code >> --$i * $n & $b];
		}
		return $hash;
	}
	static function hashtime33(string $hash):int
	{
		for ($code = 0, [$i, $n, $b] = strlen($hash) === 10 ? [10, 6, 54] : [12, 5, 55]; $i;)
		{
			$code |= strpos(self::key, $hash[--$i]) << $b - $i * $n;
		}
		return $code;
	}
	static function hash(string $data, bool $care = FALSE):string
	{
		return static::time33hash(static::time33($data), $care);
	}
	static function hashfile(string $filename, bool $care = FALSE):?string
	{
		return is_string($hash = hash_file('haval160,4', $filename, TRUE)) ? static::hash($hash, $care) : NULL;
	}
	static function random(int $length):string
	{
		return random_bytes($length);
	}
	static function random_int(int $min, int $max):int
	{
		return random_int($min, $max);
	}
	static function random_time33():int
	{
		return static::time33(static::random(16));
	}
	static function random_hash(bool $care):string
	{
		return static::hash(static::random(16), $care);
	}
	static function random_weights(array $items, string $key = 'weight'):array
	{
		if ($items)
		{
			$weight = array_combine(array_keys($items), array_column($items, $key));
			$random = static::random_int($current = 0, max(0, array_sum($weight) - 1));
			foreach ($weight as $index => $value)
			{
				if ($random >= $current && $random < $current + $value)
				{
					break;
				}
				$current += $value;
			}
			return $items[$index];
		}
		return $items;
	}
	static function iphex(string $ip):string
	{
		return str_pad(bin2hex(inet_pton($ip)), 32, '0', STR_PAD_LEFT);
	}
	static function hexip(string $hex):string
	{
		return inet_ntop(hex2bin($hex));
	}
	static function url64_encode(string $data):string
	{
		for ($i = 0, $length = strlen($data), $buffer = ''; $i < $length;)
		{
			$value = ord($data[$i++]) << 16;
			$buffer .= self::key[$value >> 18 & 63];
			if ($i < $length)
			{
				$value |= ord($data[$i++]) << 8;
				$buffer .= self::key[$value >> 12 & 63];
				if ($i < $length)
				{
					$value |= ord($data[$i++]);
					$buffer .= self::key[$value >> 6 & 63];
					$buffer .= self::key[$value & 63];
					continue;
				}
				$buffer .= self::key[$value >> 6 & 63];
				break;
			}
			$buffer .= self::key[$value >> 12 & 63];
			break;
		}
		return $buffer;
	}
	static function url64_decode(string $data):?string
	{
		do
		{
			if (rtrim($data, self::key))
			{
				break;
			}
			for ($i = 0, $length = strlen($data), $buffer = ''; $i < $length;)
			{
				$value = strpos(self::key, $data[$i++]) << 18;
				if ($i < $length)
				{
					$value |= strpos(self::key, $data[$i++]) << 12;
					$buffer .= chr($value >> 16 & 255);
					if ($i < $length)
					{
						$value |= strpos(self::key, $data[$i++]) << 6;
						$buffer .= chr($value >> 8 & 255);
						if ($i < $length)
						{
							$buffer .= chr($value | strpos(self::key, $data[$i++]) & 255);
						}
					}
					continue;
				}
				break 2;
			}
			return $buffer;
		} while (0);
		return NULL;
	}
	static function encrypt(?string $data):?string
	{
		return is_string($data) && is_string($binary = openssl_encrypt($data, 'aes-128-gcm', static::key, OPENSSL_RAW_DATA, $iv = static::random(12), $tag)) ? static::url64_encode($tag . $iv . $binary) : NULL;
	}
	static function decrypt(?string $data):?string
	{
		return is_string($data) && strlen($data) > 37
			&& is_string($binary = static::url64_decode($data))
			&& is_string($result = openssl_decrypt(substr($binary, 28), 'aes-128-gcm', static::key, OPENSSL_RAW_DATA, substr($binary, 16, 12), substr($binary, 0, 16))) ? $result : NULL;
	}
	static function signature(string $username, string $password, string $additional = NULL):?string
	{
		return static::encrypt(pack('VCCa*', static::time(), strlen($username), strlen($password), $username . $password . $additional));
	}
	static function authorize(?string $signature, callable $authenticate):array
	{
		return is_string($data = static::decrypt($signature))
			&& strlen($data) > 5
			&& extract(unpack('Vsigntime/C2length', $data)) === 3
			&& strlen($data) > 5 + $length1 + $length2
			&& is_array($acc = unpack("a{$length1}uid/a{$length2}pwd/a*add", $data, 6))
				? $authenticate($acc['uid'], $acc['pwd'], $signtime, $acc['add']) : [];
	}
	static function captcha_random(int $length, int $expire):?string
	{
		$random = static::random($length * 3);
		for ($i = 0; $i < $length; ++$i)
		{
			$random[$i] = chr((ord($random[$i]) % 26) + 65);
		}
		return static::encrypt(pack('VCa*', static::time($expire), $length, $random));
	}
	static function captcha_result(?string $random):?array
	{
		if (is_string($binary = static::decrypt($random))
			&& strlen($binary) > 4
			&& extract(unpack('Vexpire/Clength', $binary)) === 2
			&& strlen($binary) > 4 + $length * 3
			&& is_array($values = unpack("a{$length}code/c{$length}size/c{$length}angle", $binary, 5))) {
			if ($length > 1)
			{
				for ($result = [$expire, '', [], []], $i = 0; $i < $length;)
				{
					$result[1] .= $values['code'][$i++];
					$result[2][] = $values["size{$i}"];
					$result[3][] = $values["angle{$i}"];
				}
				return $result;
			}
			return [$expire, $values['code'], [$values['size']], [$values['angle']]];
		}
		return NULL;
	}
	static function captcha_verify(string $random, string $answer):bool
	{
		return is_array($result = static::captcha_result($random)) && $result[0] > static::time() && $result[1] === strtoupper($answer);
	}
	static function xml(mixed ...$params):webapp_xml
	{
		try
		{
			libxml_clear_errors();
			libxml_use_internal_errors(TRUE);
			$xml = new webapp_xml(...$params);
		}
		catch (Throwable $errors)
		{
			$xml = new webapp_xml('<errors/>');
			$xml->cdata((string)$errors);
			foreach (libxml_get_errors() as $error)
			{
				$xml->append('error', [
					'level' => $error->level,
					'code' => $error->code,
					'line' => $error->line
				])->cdata($error->message);
			}
		}
		libxml_use_internal_errors(FALSE);
		return $xml;
	}
	static function iterator(iterable ...$aggregate):iterable
	{
		foreach ($aggregate as $iter)
		{
			foreach ($iter as $item)
			{
				yield $item;
			}
		}
	}

	// static function debugtime(?float &$time = 0):float
	// {
	// 	return $time = microtime(TRUE) - $time;
	// }
	// static function splitchar(string $content):array
	// {
	// 	return preg_match_all('/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/', $content, $pattern) === FALSE ? [] : $pattern[0];
	// }
	static function simplified_chinese(string $content):string
	{
		//simplified_text
		//convert_simplified_chinese
		return webapp::lib('hanzi/interface.php')($content);
	}
	static function filenameescape(string $basename):string
	{
		return str_replace(['\\', '/', ':', '*', '?', '"', '<', '>'], '_', $basename);
	}
	static function build_test_router(bool $dataurl = FALSE, ...$urls):string
	{
		$code = stream_get_line(fopen(__DIR__ . '/res/js/test_router.js', 'r'), 0xffff, "\n");
		$code = str_replace('{ERRORPAGE}', array_shift($urls), $code);
		$code = str_replace('{BASE64URLS}', base64_encode(join(',', $urls)), $code);
		return $dataurl
			? 'data:text/html;base64,' . base64_encode("<script>{$code}</script>")
			: 'javascript:eval(atob(\''. base64_encode($code) .'\'));';
			//: 'javascript:'. rawurlencode($code) .';';
			//: 'javascript:Function(atob(\''. base64_encode($code) .'\'))();';

	}
	static function maskdata(string $source):string
	{
		$bin = static::random(8);
		$key = array_map(ord(...), str_split($bin));
		$length = strlen($source);
		for ($i = 0; $i < $length; ++$i)
		{
			$source[$i] = chr(ord($source[$i]) ^ $key[$i % 8]);
			//$source[$i] = chr($key[$i % 8] = ord($source[$i]) ^ $key[$i % 8]);
		}
		return $bin . $source;
	}
	static function maskfile(string $source, string $destination):bool
	{
		return file_put_contents($destination, static::maskdata($data = file_get_contents($source))) === strlen($data) + 8;
	}
	static function unmasker(string $binkey, string $bindata):?string
	{
		if (count($key = array_map(ord(...), str_split($binkey))) > 7)
		{
			$length = strlen($bindata);
			for ($i = 0; $i < $length; ++$i)
			{
				$bindata[$i] = chr(ord($bindata[$i]) ^ $key[$i % 8]);
				//$bindata[$i] = chr($key[$i % 8] ^ $key[$i % 8] = ord($bindata[$i]));
			}
			return $bindata;
		}
		return NULL;
	}
	function __construct(array $config = [], private readonly webapp_io $io = new webapp_stdio)
	{
		[$this->webapp, $this->configs] = [$this, $config + [
			//Request
			'request_method'	=> in_array($method = strtolower($io->request_method()), ['get', 'post', 'put', 'patch', 'delete', 'options'], TRUE) ? $method : 'get',
			'request_query'		=> $io->request_query(),
			//Application
			'app_hostname'		=> 'localhost',
			'app_charset'		=> 'utf-8',
			'app_called'		=> FALSE,
			'app_router'		=> 'webapp_router_',
			'app_index'			=> 'home',
			//Admin
			'admin_username'	=> 'admin',
			'admin_password'	=> 'nimda',
			'admin_cookie'		=> 'webapp',
			'admin_expire'		=> 604800,
			//MySQL
			'mysql_hostname'	=> 'p:127.0.0.1:3306',
			'mysql_username'	=> 'root',
			'mysql_password'	=> '',
			'mysql_database'	=> 'webapp',
			'mysql_maptable'	=> 'webapp_maptable_',
			'mysql_charset'		=> 'utf8mb4',
			//Captcha
			'captcha_length'	=> 4,
			'captcha_expire'	=> 99,
			'captcha_params'	=> [210, 86, __DIR__ . '/res/fonts/ArchitectsDaughter_R.ttf', 28],
			//QRCode
			'qrcode_echo'		=> TRUE,
			'qrcode_ecc'		=> 0,
			'qrcode_size'		=> 4,
			'qrcode_maxdata'	=> 256,
			//Misc
			'copy_webapp'		=> 'Web Application v' . self::version,
			'gzip_level'		=> -1,
			'manifests'			=> []]];
		[$this->route, $this->entry] = method_exists($this, $route = sprintf('%s_%s', $this['request_method'],
			$track = preg_match('/^[-\w]+(?=\/([\-\w]*))?/', $this['request_query'], $entry)
				? strtr($entry[0], '-', '_') : $entry[] = $this['app_index']))
			? [[$this, $route], array_slice($entry, 1)]
			: [[$this['app_router'] . $track, sprintf('%s_%s', $this['request_method'],
				count($entry) > 1 ? strtr($entry[1], '-', '_') : $this['app_index'])], []];
		[&$this->router, &$this->method] = $this->route;
		$this->query = preg_match_all('/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/', $this['request_query'],
			$pattern, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) ? array_column($pattern, 2, 1) : [];
	}
	function __destruct()
	{
		do
		{
			if (method_exists(...$this->route) && ($tracert = new ReflectionMethod(...$this->route))->isPublic())
			{
				do
				{
					if (($router = is_string($this->router)
							&& ($method = new $this->router($this))::class === $this->router
								? $method : $this->router)::class === 'Closure') {
						$status = $router(...$this->entry);
					}
					else
					{
						if ($tracert->isUserDefined() === FALSE)
						{
							break;
						}
						if ($this->query)
						{
							foreach (array_slice($tracert->getParameters(), intval($router === $this)) as $parameter)
							{
								if (array_key_exists($parameter->name, $this->query))
								{
									$this->entry[$parameter->name] ??= match ((string)$parameter->getType())
									{
										'int' => intval($this->query[$parameter->name]),
										'float' => floatval($this->query[$parameter->name]),
										'string' => (string)$this->query[$parameter->name],
										default => $this->query[$parameter->name]
									};
									continue;
								}
								if ($parameter->isOptional() === FALSE)
								{
									break 2;
								}
							}
						}
						if ($tracert->getNumberOfRequiredParameters() > count($this->entry))
						{
							break;
						}
						$status = $tracert->invoke($router, ...$this->entry);
					}
					$tracing = property_exists($this, 'app') ? $this->app : $method ?? $router;
					if ($tracing !== $this && $tracing instanceof Stringable)
					{
					 	$this->echo((string)$tracing);
					}
					break 2;
				} while (0);
			}
			$status = 404;
		} while (0);
		if ($this->io->response_sent() === FALSE)
		{
			if (is_int($status))
			{
				$this->io->response_status($status);
			}
			foreach ($this->cookies as $values)
			{
				$this->io->response_cookie(...$values);
			}
			foreach ($this->headers as $name => $value)
			{
				$this->io->response_header("{$name}: {$value}");
			}
			if (property_exists($this, 'buffer'))
			{
				if ($this['gzip_level']
					&& is_string($encoding = $this->request_header('Accept-Encoding'))
					&& stripos($encoding, 'gzip') !== FALSE
					&& stream_filter_append($this->buffer, 'zlib.deflate', STREAM_FILTER_READ,
						['level' => $this['gzip_level'], 'window' => 31, 'memory' => 9])) {
					$this->io->response_header('Content-Encoding: gzip');
				}
				// $this->io->response_header('Content-Length: ' . strlen($data = (string)$this));
				// $this->io->response_content($data);
				$this->io->response_content((string)$this);
				unset($this->buffer);
			}
		}
	}
	function __toString():string
	{
		return stream_get_contents($this->buffer, -rewind($this->buffer));
	}
	// function __call(string $tablename, array $conditionals):webapp_mysql_table
	// {
	//	//和静态魔术调用方法冲突
	// 	return $this->mysql->{$tablename}(...$conditionals);
	// }
	function __get(string $name):mixed
	{
		if (method_exists($this, $name))
		{
			$loader = new ReflectionMethod($this, $name);
			if ($loader->isPublic() && $loader->getNumberOfRequiredParameters() === 0)
			{
				return $this->{$name} = $loader->invoke($this);
			}
		}
		return $this->mysql->{$name};
	}
	final function __invoke(object $object):object
	{
		if (property_exists($object, 'webapp') === FALSE)
		{
			$object->webapp = $this;
		}
		if ($object instanceof ArrayAccess)
		{
			$object['errors'] = &$this->errors;
		}
		else
		{
			$object->errors = &$this->errors;
		}
		return $object;
	}
	final function offsetExists(mixed $key):bool
	{
		return array_key_exists($key, $this->configs);
	}
	final function &offsetGet(mixed $key):mixed
	{
		return $this->configs[$key];
	}
	final function offsetSet(mixed $key, mixed $value):void
	{
		$this->configs[$key] = $value;
	}
	final function offsetUnset(mixed $key):void
	{
		unset($this->configs[$key]);
	}
	final function count():int
	{
		return property_exists($this, 'buffer') ? ftell($this->buffer) : 0;
	}
	final function app(string $name, mixed ...$params):object
	{
		return $this($this->app = new $name($this, ...$params));
	}
	final function break(Closure|array $router, mixed ...$params):void
	{
		[$this->route[0], $this->route[1]] = [$router, '__invoke'];
		if (func_num_args() > 1)
		{
			$this->entry = $params;
		}
	}
	final function entry(array $params):void
	{
		$this->entry = $params + $this->entry;
	}
	final function buffer():mixed
	{
		return fopen('php://memory', 'r+');
	}
	function echo(string $data):bool
	{
		return fwrite($this->buffer, $data) === strlen($data);
	}
	function printf(string $format, string ...$params):int
	{
		return fprintf($this->buffer, $format, ...$params);
	}
	function println(string $data):int
	{
		return $this->printf("%s\n", $data);
	}
	function putcsv(array $values, string $delimiter = ',', string $enclosure = '"'):int
	{
		return fputcsv($this->buffer, $values, $delimiter, $enclosure);
	}
	function at(array $params, string $router = NULL):string
	{
		return array_reduce(array_keys($replace = array_reverse($params + $this->query, TRUE)),
			fn($carry, $key) => is_scalar($replace[$key])
				? (is_bool($replace[$key]) ? $carry : "{$carry},{$key}:{$replace[$key]}")
				: "{$carry},{$key}", $router ?? strstr("?{$this['request_query']},", ',', TRUE));
	}
	function admin(?string $signature = NULL):array
	{
		return static::authorize(func_num_args() ? $signature : $this->request_cookie($this['admin_cookie']), $this->authenticate(...));
	}
	function authenticate(string $username, string $password, int $signtime, string $additional):array
	{
		return $signtime > static::time(-$this['admin_expire'])
			&& $username === $this['admin_username']
			&& $password === $this['admin_password']
				? [$username, $password, $additional] : [];
	}
	function authorization(Closure $authenticate = NULL):array
	{
		return $authenticate
			? static::authorize($this->request_authorization(), $authenticate)
			: $this->admin($this->request_authorization());
	}
	function authorized(string $additional = NULL):array
	{
		return ['Authorization' => 'Bearer ' . static::signature($this['admin_username'], $this['admin_password'], $additional)];
	}
	function generatetext(int $count, int $start = 0x4e00, int $end = 0x9fa5)
	{
		$random = unpack('V*', random_bytes($count * 4));
		$mod = $end - $start;
		foreach ($random as &$unicode)
		{
			$unicode = iconv('UCS-4LE', $this['app_charset'], pack('V', $unicode % $mod + $start));
		}
		return join($random);
	}
	function strlen($text):int
	{
		return iconv_strlen($text, $this['app_charset']);
	}
	function webappxml():webapp_xml
	{
		return static::xml(sprintf('<?xml version="1.0" encoding="%s"?><webapp version="%s"/>', $this['app_charset'], self::version));
	}
	//---------------------


	//----------------
	function open(string $url, array $options = []):webapp_client_http
	{
		// $options['headers']['Authorization'] ??= 'Bearer ' . static::signature($this['admin_username'], $this['admin_password']);
		$options['headers']['User-Agent'] ??= 'WebApp/' . self::version;
		return webapp_client_http::open($url, $options);

		// return $this(new webapp_client_http($url, $timeout))->headers([
		// 	'Authorization' => 'Digest ' . static::signature($this['admin_username'], $this['admin_password']),
		// 	'User-Agent' => 'WebApp/' . self::version
		// ]);
		// $client = new webapp_client_http($url);
		// if ($client->errors)
		// {
		// 	array_push($this->errors, ...$client->errors);
		// }
		// return $this($client->headers(['User-Agent' => 'WebApp/' . self::version]));
	}
	// function formdata(array|webapp_html $node = NULL, string $action = NULL):array|webapp_html_form
	// {
	// 	if (is_array($node))
	// 	{
	// 		$form = new webapp_html_form($this);
	// 		foreach ($node as $name => $attr)
	// 		{
	// 			$form->field($name, ...is_array($attr) ? [$attr['type'], $attr] : [$attr]);
	// 		}
	// 		return $form->fetch() ?? [];
	// 	}
	// 	return new webapp_html_form($this, $node, $action);
	// }
	//function sqlite():webapp_sqlite{}
	function mysql(...$commands):webapp_mysql
	{
		if ($commands)
		{
			return ($this->mysql)(...$commands);
		}
		if (property_exists($this, 'mysql'))
		{
			return $this->mysql;
		}
		$mysql = new webapp_mysql($this['mysql_hostname'], $this['mysql_username'], $this['mysql_password'], $this['mysql_database'], $this['mysql_maptable']);
		if ($mysql->connect_errno)
		{
			$this->errors[] = $mysql->connect_error;
		}
		else
		{
			$mysql->set_charset($this['mysql_charset']);
		}
		return $this($mysql);
	}
	//function redis():webapp_redis{}
	//request
	function request_ip(bool $proxy = FALSE):string
	{
		//CF-Connecting-IP
		return $proxy && ($ip = $this->io->request_header('X-Forwarded-For'))
			? current(explode(',', $ip))
			: $this->io->request_ip();
	}
	function request_scheme():string
	{
		return $this->io->request_header('X-Forwarded-Proto')
			?? $this->io->request_scheme();
	}
	function request_host():string
	{
		return $this->io->request_header('X-Forwarded-Host')
			?? $this->io->request_header('Host')
			?? $this['app_hostname'];
	}
	function request_origin():string
	{
		return sprintf('%s://%s', $this->request_scheme(), $this->request_host());
	}
	function request_entry():string
	{
		return $this->request_origin() . $this->io->request_entry();
	}
	function request_dir():string
	{
		return dirname($this->request_entry());
	}
	// function request_cond(string $name = 'cond'):array
	// {
	// 	$cond = [];
	// 	preg_match_all('/(\w+\.(?:eq|ne|gt|ge|lt|le|lk|nl|in|ni))(?:\.([^\/]*))?/', $this->request_query($name), $values, PREG_SET_ORDER);
	// 	foreach ($values as $value)
	// 	{
	// 		$cond[$value[1]] = array_key_exists(2, $value) ? urldecode($value[2]) : NULL;
	// 	}
	// 	return $cond;
	// }
	function request_cookie(string $name):?string
	{
		return $this->io->request_cookie($name);
	}
	function request_cookie_decrypt(string $name):?string
	{
		return static::decrypt($this->request_cookie($name));
	}
	function request_header(string $name):?string
	{
		return $this->io->request_header($name);
	}
	function request_authorization(&$type = NULL):?string
	{
		return is_string($authorization = $this->request_header('Authorization'))
			? ([$type] = explode(' ', $authorization, 2))[1] ?? $type : NULL;
	}
	function request_locale():array
	{
		return is_string($locale = $this->request_cookie('locale') ?? $this->request_header('Accept-Language'))
			&& preg_match('/([a-z]{2})[_\-]([a-z]{2,3})/', strtolower($locale), $name) ? array_slice($name, 1) : ['zh', 'cn'];
	}
	function request_device():string
	{
		return $this->request_header('User-Agent') ?? 'Unknown';
	}
	function request_referer(string $url):string
	{
		return $this->request_header('Referer') ?? $url;
	}
	function request_content_type():string
	{
		return is_string($type = $this->request_header('Content-Type'))
			? strtolower(is_int($offset = strpos($type, ';')) ? substr($type, 0, $offset) : $type)
			: 'application/octet-stream';
	}
	function request_content_length():int
	{
		return intval($this->request_header('Content-Length'));
	}
	function request_content(?string $format = NULL):array|string|webapp_xml
	{
		return match ($format ?? $this->request_content_type())
		{
			'application/x-www-form-urlencoded',
			'multipart/form-data' => $this->io->request_formdata(),
			'application/json' => json_decode($this->io->request_content(), TRUE),
			'application/xml' => static::xml($this->io->request_content()),
			default => $this->io->request_content()
		};
	}
	function request_uploadedfile(string $name, int $maximum = NULL):webapp_request_uploadedfile
	{
		return array_key_exists($name, $this->uploadedfiles ??= $this->io->request_uploadedfile())
			&& $this->uploadedfiles[$name] instanceof webapp_request_uploadedfile ? $this->uploadedfiles[$name]
			: $this->uploadedfiles[$name] = new webapp_request_uploadedfile($this, $name, $this->uploadedfiles[$name] ?? [], $maximum);
	}
	function request_maskdata():?string
	{
		return is_string($key = $this->request_header('Mask-Key'))
			? $this->unmasker(hex2bin($key), $this->io->request_content()) : NULL;
	}
	function request_uploading():array
	{
		return json_decode($this->request_maskdata(), TRUE) ?? [];
	}
	function request_uploaddata(string $filename):int
	{
		return is_string($data = $this->request_maskdata())
			&& is_resource($stream = fopen($filename, 'a'))
			&& flock($stream, LOCK_EX)
			&& fwrite($stream, $data) === strlen($data)
			// && flock($stream, LOCK_UN)
			&& fclose($stream) ? strlen($data) : -1;
	}
	function response_uploading(string $uploadurl, int $offset = 0):void
	{
		$this->app('webapp_echo_json', ['uploadurl' => $uploadurl, 'offset' => $offset]);
	}


	// function request_app_channel():string
	// {
	// 	$this->query['wacid'] ?? $this->request_header('webapp-cid')
	// }
	// function request_client_info():string
	// {
	// 	$this->query['cid'] ?? $this->request_header('webapp-cid')
	// }
	function request_apple_mobile_config(array $websockets = [], bool $forcedownload = FALSE):?webapp_implementation
	{
		if (webapp_echo_html::form_mobileconfig($this)->fetch($data)
			&& count($icon = $this->request_uploadedfile('Icon'))) {
			$data['Icon'] = $icon->filename();
			if ($websockets && count($action = explode(',', $data['URL'], 2)) === 2)
			{
				$urls = [$action[0]];
				foreach ($websockets as $websocket)
				{
					$urls[] = "{$websocket}/{$action[1]}";
				}
				$data['URL'] = static::build_test_router(TRUE, ...$urls);
			}
			$payload = [
				'PayloadContent' => [&$data],
				'PayloadDisplayName' => $data['PayloadDisplayName'],
				'PayloadDescription' => $data['PayloadDescription'],
				'PayloadOrganization' => $data['PayloadOrganization'],
				'PayloadIdentifier' => $data['PayloadIdentifier']
			];
			unset($data['PayloadDisplayName'], $data['PayloadDescription'], $data['PayloadOrganization'], $data['PayloadIdentifier']);
			return webapp_echo_xml::mobileconfig($payload, $this, $forcedownload ? $data['Label'] : NULL);
		}
		return NULL;
	}
	function request_apple_device_enrollment():array
	{
		//Apple device enrollment must use HTTPS protocol request method POST and response status 301
		return preg_match_all('/\<(\w+\>)([^\<]+)\<\/\1\s*\<(\w+\>)([^\<]+)\<\/\3/',
			$this->request_content('application/pkcs7-signature'), $pattern)
				? array_combine($pattern[2], $pattern[4]) : [];
	}
	//response
	function response_status(int $code):void
	{
		$this->break(fn():int => $code);
	}
	function response_cookie(string $name, ?string $value = NULL, int $expire = 0, string $path = '', string $domain = '', bool $secure = FALSE, bool $httponly = FALSE):void
	{
		$cookie = func_get_args();
		$cookie[1] ??= '';
		$this->cookies[] = $cookie;
	}
	function response_cookie_encrypt(string $name, ?string $value = NULL, int $expire = 0, string $path = '', string $domain = '', bool $secure = FALSE, bool $httponly = FALSE):void
	{
		$cookie = func_get_args();
		$cookie[1] = static::encrypt($value) ?? '';
		$this->cookies[] = $cookie;
	}
	function response_header(string $name, string $value):void
	{
		//$this->headers[ucwords($name, '-')] = $value;
		$this->headers[$name] = $value;
	}
	function response_location(string $url):void
	{
		$this->response_header('Location', $url);
	}
	function response_refresh(int $second = 0, string $url = NULL):void
	{
		$this->response_header('Refresh', $url === NULL ? (string)$second : "{$second}; url={$url}");
	}
	function response_expires(int $timestamp)
	{
		$this->response_header('Expires', date(DateTimeInterface::RFC7231, $timestamp));
	}
	function response_last_modified(int $timestamp)
	{
		$this->response_header('Last-Modified', date(DateTimeInterface::RFC7231, $timestamp));
	}
	function response_cache_control(string $command):void
	{
		$this->response_header('Cache-Control', $command);
	}
	function response_content_type(string $mime):void
	{
		$this->response_header('Content-Type', $mime);
	}
	function response_content_disposition(string $basename):void
	{
		$this->response_header('Content-Disposition', 'attachment; filename=' . urlencode($basename));
	}
	function response_content_download(string $basename):void
	{
		$this->response_content_type('application/force-download');
		$this->response_content_disposition($basename);
	}
	function response_sendfile(string $filename):bool
	{
		return $this->io->response_sendfile($filename);
	}
	//append function
	function nonematch(string $etag, bool $needhash = FALSE):bool
	{
		$this->response_header('Etag', $hash = '"' . ($needhash ? static::hash($etag, TRUE) : $etag) . '"');
		return $this->request_header('If-None-Match') !== $hash;
	}
	function not_sign_in(callable $authenticate = NULL, string $method = NULL):bool
	{
		if (method_exists(...$this->route))
		{
			if (static::authorize($this->request_cookie($this['admin_cookie']), $authenticate ??= $this->authenticate(...)))
			{
				return FALSE;
			}
			$method ??= $this['app_index'];
			if ($this->method === "post_{$method}")
			{
				$this->app('webapp_echo_json', ['signature' => NULL]);
				if (webapp_echo_html::form_sign_in($this)->fetch($sign)
					&& static::authorize($signature = static::signature($sign['username'], $sign['password']), $authenticate)) {
					$this->response_cookie($this['admin_cookie'], $this->app['signature'] = $signature);
					$this->response_status(200);
					$this->response_refresh(0);
					return FALSE;
				}
				$this->app['errors'][] = 'Sign in failed';
			}
			else
			{
				if ($this->method === "get_{$method}")
				{
					$this->app('webapp_echo_html')->title('Sign In');
					webapp_echo_html::form_sign_in($this->app->main);
				}
			}
			$this->response_status(401);
		}
		return TRUE;
	}
	function allow(string|self $router, string ...$methods):bool
	{
		return $this->router === $router
			&& in_array($this->method, $router === $this ? [
				'get_captcha', 'get_qrcode', 'get_favicon', 'get_manifests', ...$methods] : $methods, TRUE);
	}
	private function remote_encode_value(NULL|bool|int|float|string|array|webapp_xml $value):array
	{
		return match (get_debug_type($value))
		{
			'null' => ['null', 'NULL'],
			'bool' => ['boolean', $value ? 'TRUE' : 'FALSE'],
			'int' => ['integer', (string)$value],
			'float' => ['float', (string)$value],
			'string' => ['string', $value],
			'array' => ['array', json_encode($value, JSON_UNESCAPED_UNICODE)],
			default => ['xml' => $value->asXML()]
		};
	}
	private function remote_decode_value(string $type, string $value):NULL|bool|int|float|string|array|webapp_xml
	{
		return match ($type)
		{
			'null' => NULL,
			'boolean' => $value === 'TRUE',
			'integer' => intval($value),
			'float' => floatval($value),
			'string' => $value,
			'array' => json_decode($value, TRUE),
			default => $this->xml($value)
		};
	}
	function remote(string $url, string $method, array $params):NULL|bool|int|float|string|array|webapp_xml
	{
		$host = self::$remote[$url] ??= new webapp_client_http($url, ['autoretry' => 1, 'headers' => $this->authorized()]);
		$input = $this->webappxml();
		foreach ($params as $name => $value)
		{
			[$type, $data] = $this->remote_encode_value($value);
			$input->append('entry', ['name' => $name, 'type' => $type])->cdata($data);
		}
		$output = $host->goto("{$host->path}?called/{$method}", ['method' => 'POST', 'type' => 'application/xml', 'data' => $input])->content();
		if (is_object($output) === FALSE || (string)$output['type'] === 'error')
		{
			$response = [];
			$host->status($response, TRUE);
			$response[] = (string)$output;
			throw new Error(join(PHP_EOL, $response));
		}
		return $this->remote_decode_value((string)$output['type'], (string)$output);
	}
	//router extends
	function post_called(string $method)
	{
		//$this['app_called'];
		if ($this->authorization())
		{
			try
			{
				$params = [];
				if (is_object($input = $this->request_content()))
				{
					foreach ($input->entry as $entry)
					{
						$params[(string)$entry['name']] = $this->remote_decode_value((string)$entry['type'], (string)$entry);
					}
				}
				[$type, $data] = $this->remote_encode_value($this->{$method}(...$params));
			}
			catch (Error $error)
			{
				[$type, $data] = ['error', (string)$error];
			}
			$this->app('webapp_echo_xml')->xml->setattr(['type' => $type])->cdata($data);
			return 200;
		}
		return 401;
	}
	function get_captcha(string $random = NULL)
	{
		if ($this['captcha_length'])
		{
			if ($result = static::captcha_result($random))
			{
				if ($this->nonematch($random, TRUE))
				{
					$this->response_content_type('image/jpeg');
					webapp_image::captcha($result, ...$this['captcha_params'])->jpeg($this->buffer);
					return;
				}
				return 304;
			}
			if ($random = static::captcha_random($this['captcha_length'], $this['captcha_expire']))
			{
				$this->response_content_type("text/plain; charset={$this['app_charset']}");
				$this->echo($random);
				return;
			}
			return 500;
		}
		return 404;
	}
	function get_qrcode(string $encode)
	{
		if ($this['qrcode_echo'] && is_string($decode = $this->decrypt($encode)) && strlen($decode) < $this['qrcode_maxdata'])
		{
			if ($this->nonematch($decode, TRUE))
			{
				$draw = static::qrcode($decode, $this['qrcode_ecc']);
				$this->app('webapp_echo_svg')->xml->qrcode($draw, $this['qrcode_size']);
				//$this->response_content_type('image/png');
				//webapp_image::qrcode($draw, $this['qrcode_size'])->png($this->buffer);
				return;
			}
			return 304;
		}
		return 404;
	}
	function get_favicon()
	{
		$this->response_cache_control('private, max-age=86400');
		if ($this->nonematch($this->request_ip(), TRUE))
		{
			$this->app('webapp_echo_svg')->xml->logo();
			return 200;
		}
		return 304;
	}
	function get_manifests()
	{
		if ($this['manifests'])
		{
			//https://developer.mozilla.org/zh-CN/docs/Web/Progressive_web_apps
			//https://developer.mozilla.org/zh-CN/docs/Web/Manifest
			$this->app('webapp_echo_json', $this['manifests']);
			return 200;
		}
		return 404;
	}
	function get_service_workers()
	{
		$this->response_content_type('text/javascript');
		$this->response_header('Service-Worker-Allowed', '?');
		$this->response_sendfile(__DIR__ . '/res/js/sw.js');
	}
}
class webapp_request_uploadedfile implements ArrayAccess, IteratorAggregate, Countable, Stringable
{
	private array $uploadedfiles;
	function __construct(public readonly webapp $webapp, private readonly string $name, array $uploadedfiles, int $maximum = NULL)
	{
		$this->uploadedfiles = array_slice($uploadedfiles, 0, $maximum);
	}
	function __toString():string
	{
		return $this->name;
	}
	function __debugInfo():array
	{
		return iterator_to_array($this);
	}
	function offsetExists(mixed $key):bool
	{
		return array_key_exists($key, $this->uploadedfiles);
	}
	function offsetGet(mixed $key):mixed
	{
		if ($this->offsetExists($key))
		{
			$this->uploadedfiles[$key]['hash'] ??= $this->webapp->hashfile($this->uploadedfiles[$key]['file']);
			return $this->uploadedfiles[$key];
		}
		return [];
	}
	function offsetSet(mixed $key, mixed $value):void
	{
	}
	function offsetUnset(mixed $key):void
	{
	}
	function getIterator():Traversable
	{
		for ($i = 0; $i < count($this); ++$i)
		{
			yield $this[$i];
		}
	}
	function count():int
	{
		return count($this->uploadedfiles);
	}
	function filename(int $index = 0):string
	{
		return $this->uploadedfiles[$index]['file'];
	}
	function column(string $key):array
	{
		return array_column($key === 'hash' ? $this->__debugInfo() : $this->uploadedfiles, $key);
	}
	function size():int
	{
		return array_sum($this->column('size'));
	}
	function open(int $index = 0)
	{
		return fopen($this->uploadedfiles[$index]['file'], 'r');
	}
	function content(int $index = 0):string
	{
		return file_get_contents($this->uploadedfiles[$index]['file']);
	}
	function move(string $filename, int $index = 0)
	{
		if ($this->offsetExists($index)
			&& ((is_uploaded_file($this->uploadedfiles[$index]['file'])
				&& move_uploaded_file($this->uploadedfiles[$index]['file'], $filename))
				|| rename($this->uploadedfiles[$index]['file'], $filename))) {
			$this->uploadedfiles[$index]['file'] = $filename;
			return TRUE;
		}
		return FALSE;
	}
	
	// function movefile(int $index, string $filename):bool
	// {
	// 	//$index = max(1, $index) < count($this)
	// 	//if (array_key_exists($index, $this))
	// 	return move_uploaded_file($this[$index]['file'], $filename);
	// }
	// function moveto(string $filename):array
	// {
	// 	$success = [];
	// 	foreach ($this as $file)
	// 	{
	// 		// if (move_uploaded_file($file['file'], $filename)
	// 		// {
	// 		// }
	// 		print_r($file);
	// 	}
	// 	// $date = array_combine(['date', 'year', 'month', 'day', 'week', 'yday', 'time', 'hours', 'minutes', 'seconds'], explode(' ', date('Ymd Y m d w z His H i s')));
	// 	// foreach ($this as $hash => $info)
	// 	// {
	// 	// 	if ((is_dir($rootdir = dirname($file = preg_replace_callback('/\{([a-z]+)(?:\,(-?\d+)(?:\,(-?\d+))?)?\}/i', fn(array $format):string => match ($format[1])
	// 	// 	{
	// 	// 		'hash' => count($format) > 2 ? substr($hash, ...array_slice($format, 2)) : $hash,
	// 	// 		'name', 'type' => $info[$format[1]],
	// 	// 		default => $date[$format[1]] ?? $format[0]
	// 	// 	}, $filename))) || mkdir($rootdir, recursive: TRUE)) && move_uploaded_file($this[$hash]['file'], $file)) {
	// 	// 		$this[$hash]['file'] = $file;
	// 	// 		$success[$hash] = $this[$hash];
	// 	// 	}
	// 	// }
	// 	return $success;
	// }
	// function detect(string $mime):bool
	// {
	// 	foreach ($this as $files)
	// 	{
	// 		//感觉在不久的将来这里需要改
	// 		if (preg_match('/^(' . str_replace(['/', '*', ','], ['\\/', '.*', '|'], $mime) . ')$/', $files['type']) === 0)
	// 		{
	// 			return FALSE;
	// 		}
	// 	}
	// 	return TRUE;
	// }
	function maskfile(string $filename, int $index = 0):bool
	{
		if ($this->offsetExists($index) && $this->webapp->maskfile($this->uploadedfiles[$index]['file'], $filename))
		{
			$this->uploadedfiles[$index]['file'] = $filename;
			return TRUE;
		}
		return FALSE;
	}
	function post(string $url):webapp_client_http
	{
		return webapp_client_http::open($url, ['method' => 'POST',
			'headers' => $this->webapp->authorized(),
			'type' => 'multipart/form-data',
			'data' => [$this->name => $this]]);
	}
}