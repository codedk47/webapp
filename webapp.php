<?php
declare(strict_types=1);
require 'webapp_filter.php';
require 'webapp_client.php';
require 'webapp_dom.php';
require 'webapp_echo.php';
class_exists('GdImage') && require 'webapp_image.php';
class_exists('mysqli') && require 'webapp_mysql.php';
class_exists('Redis') && require 'webapp_redis.php';
interface webapp_io
{
	function request_ip():string;
	function request_time():int;
	function request_scheme():string;
	function request_method():string;
	function request_query():string;
	function request_into():string;
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
abstract class webapp extends stdClass implements ArrayAccess, Stringable, Countable
{
	const version = '4.7.1b', key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-', algo = 'xxh3';
	public readonly self $webapp;
	public readonly array $query;
	public readonly string $into;
	public object|string $router;
	public string $method;
	private array $errors = [], $cookies = [], $headers = [], $uploadedfiles, $configs, $route, $entry;
	private static array $lib = [], $remote = [];
	static function lib(string $filename, ...$parameters)
	{
		$lib = array_key_exists($name = strtolower($filename), static::$lib)
			? static::$lib[$name]
			: static::$lib[$name] = require is_file($name) ? $name : __DIR__ . "/library/{$name}";
		return $parameters ? $lib(...$parameters) : $lib;
	}
	static function simplified_chinese(string $content):string
	{
		return static::lib('utf8_convert/simplified.php', $content);
	}
	static function mime(string $filename):string
	{
		return static::lib('fileinfo/mime.php', $filename);
	}
	static function ffmpeg(string ...$filename):Closure|ffmpeg
	{
		return static::lib('ffmpeg/interface.php', ...$filename);
	}
	static function qrcode(string $content, int $level = 0):IteratorAggregate&Countable
	{
		return static::lib('qrcode/interface.php', $content, $level);
	}
	static function time(int $offset = 0):int
	{
		return time() + $offset;
	}
	// static function algo(string $data):int
	// {
	// 	return hexdec(substr(hash(static::algo, $data, FALSE), -15));
	// }
	// static function algoreduce(int $code, bool $care = FALSE):string
	// {
	// 	for ($hash = '', [$i, $n, $b] = $care ? [10, 6, 63] : [12, 5, 31]; $i;)
	// 	{
	// 		$hash .= self::key[$code >> --$i * $n & $b];
	// 	}
	// 	return $hash;
	// }
	// static function algorevert(string $hash):int
	// {
	// 	for ($code = 0, [$i, $n, $b] = strlen($hash) === 10 ? [10, 6, 54] : [12, 5, 55]; $i;)
	// 	{
	// 		$code |= strpos(self::key, $hash[--$i]) << $b - $i * $n;
	// 	}
	// 	return $code;
	// }
	// static function hash(string $data, bool $care = FALSE):string
	// {
	// 	return static::algoreduce(static::algo($data), $care);
	// }
	// static function hashfile(string $filename, bool $care = FALSE):?string
	// {
	// 	return is_file($filename) && is_string($hex = hash_file(static::algo, $filename, FALSE))
	// 		? static::algoreduce(hexdec(substr($hex, -15)), $care) : NULL;
	// }
	// static function random_hash(bool $care):string
	// {
	// 	return static::hash(static::random(8), $care);
	// }

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
		return is_file($filename) && is_string($hash = hash_file('haval160,4', $filename, TRUE)) ? static::hash($hash, $care) : NULL;
	}
	// static function shuffle()
	// {
	// }
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
	static function random_weight(array $items, string $key = 'weight'):array
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
	// static function iterator(iterable ...$aggregate):iterable
	// {
	// 	foreach ($aggregate as $iter)
	// 	{
	// 		foreach ($iter as $item)
	// 		{
	// 			yield $item;
	// 		}
	// 	}
	// }

	// static function debugtime(?float &$time = 0):float
	// {
	// 	return $time = microtime(TRUE) - $time;
	// }
	// static function splitchar(string $content):array
	// {
	// 	return preg_match_all('/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/', $content, $pattern) === FALSE ? [] : $pattern[0];
	// }

	// static function filenameescape(string $basename):string
	// {
	// 	return str_replace(['\\', '/', ':', '*', '?', '"', '<', '>'], '_', $basename);
	// }
	// static function build_test_router(bool $dataurl = FALSE, ...$urls):string
	// {
	// 	$code = stream_get_line(fopen(__DIR__ . '/static/js/test_router.js', 'r'), 0xffff, "\n");
	// 	$code = str_replace('{ERRORPAGE}', array_shift($urls), $code);
	// 	$code = str_replace('{BASE64URLS}', base64_encode(join(',', $urls)), $code);
	// 	return $dataurl
	// 		? 'data:text/html;base64,' . base64_encode("<script>{$code}</script>")
	// 		: 'javascript:eval(atob(\''. base64_encode($code) .'\'));';
	// 		//: 'javascript:'. rawurlencode($code) .';';
	// 		//: 'javascript:Function(atob(\''. base64_encode($code) .'\'))();';

	// }
	static function masker($stream, string &$key = NULL, bool $merged = FALSE)
	{
		$key ??= static::random(8);
		return is_resource(is_string($stream) ? $stream = fopen($stream, 'r') : $stream)
			&& is_resource(stream_filter_append($stream, 'webapp.filter_mask.encode', STREAM_FILTER_READ,
				$merged ? $key : array_map(ord(...), str_split($key)))) ? $stream : NULL;
	}
	static function unmasker($stream, string $key = NULL)
	{
		return is_resource(is_string($stream) ? $stream = fopen($stream, 'r') : $stream)
			&& is_resource(stream_filter_append($stream, 'webapp.filter_mask.decode', STREAM_FILTER_READ,
				$key ? array_map(ord(...), str_split($key)) : NULL)) ? $stream : NULL;
	}
	static function maskfile($from, $to, string &$key = NULL, bool $merged = FALSE):bool
	{
		return is_resource($stream = static::masker($from, $key, $merged))
			&& is_resource(is_string($to) ? $to = fopen($to, 'w') : $to)
			&& stream_copy_to_stream($stream, $to) !== FALSE
			&& feof($stream);
	}
	static function maskdata(string $data, string &$key = NULL, bool $merged = FALSE):?string
	{
		return is_resource($stream = fopen('php://memory', 'w+'))
			&& fwrite($stream, $data) === strlen($data)
			&& is_resource(static::masker($stream, $key, $merged))
			&& rewind($stream)
			&& is_string($result = stream_get_contents($stream)) ? $result : NULL;
	}
	static function unmaskfile($from, $to, string $key = NULL):bool
	{
		return is_resource($stream = static::unmasker($from, $key))
			&& is_resource(is_string($to) ? $to = fopen($to, 'w') : $to)
			&& stream_copy_to_stream($stream, $to) !== FALSE
			&& feof($stream);
	}
	static function unmaskdata(string $data, string $key = NULL):?string
	{
		return is_resource($stream = fopen('php://memory', 'w+'))
			&& fwrite($stream, $data) === strlen($data)
			&& is_resource(static::unmasker($stream, $key))
			&& rewind($stream)
			&& is_string($result = stream_get_contents($stream)) ? $result : NULL;
	}
	function __construct(array $config = [], private readonly webapp_io $io = new webapp_stdio)
	{
		[$this->webapp, $this->into, $this->configs] = [$this, $io->request_into(), $config + [
			//Request
			'request_method'	=> in_array($method = strtolower($io->request_method()), ['cli', 'get', 'post', 'put', 'patch', 'delete', 'options'], TRUE) ? $method : 'get',
			'request_query'		=> $io->request_query(),
			//Application
			'app_charset'		=> 'utf-8',
			'app_called'		=> FALSE,
			'app_router'		=> 'webapp_router_',
			'app_index'			=> 'home',
			'app_help'			=> TRUE,
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
			//Redis
			'redis_open'		=> ['127.0.0.1', 6379],
			'redis_auth'		=> [],
			//Captcha
			'captcha_length'	=> 4,
			'captcha_expire'	=> 99,
			'captcha_params'	=> [210, 86, __DIR__ . '/static/fonts/ArchitectsDaughter_R.ttf', 28],
			//QRCode
			'qrcode_echo'		=> TRUE,
			'qrcode_ecc'		=> 0,
			'qrcode_size'		=> 4,
			'qrcode_maxdata'	=> 256,
			//Misc
			'copy_webapp'		=> 'Web Application v' . self::version,
			'smtp_url'			=> 'ssl://user:pass@smtp.gmail.com:465',
			'gzip_level'		=> -1,
			'manifests'			=> [],]];
		[$this->route, $this->entry] = method_exists($this, $route = sprintf('%s_%s', $this['request_method'],
			$track = preg_match('/^[-\w]+(?=\/([\-\w]*))?/', $this['request_query'], $entry)
				? strtr($entry[0], '-', '_') : $entry[] = $this['app_index']))
			? [[$this, $route], array_slice($entry, 1)]
			: [[$this['app_router'] . $track, sprintf('%s_%s', $this['request_method'],
				count($entry) > 1 ? strtr($entry[1], '-', '_') : $this['app_index'])], []];
		[&$this->router, &$this->method] = $this->route;
		$this->query = preg_match_all('/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/', $this['request_query'],
			$pattern, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) ? array_column($pattern, 2, 1) : [];
		if (method_exists($this, 'authenticate'))
		{
			$this->auth = [];
			if (method_exists(...$this->route)
				&& in_array($this->method, ['get_captcha', 'get_qrcode', 'get_favicon', 'get_manifests', 'get_masker']) === FALSE
				&& empty($this->auth = $this->auth($this->authenticate(...)))) {
				($this->router === $this || $this->router === $this['app_router'] . $this['app_index'])
					&& $this->method === "get_{$this['app_index']}" ? $this->echo_html(authenticate: $this) : $this->response_status(401);
			}
		}
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
					$tracing = property_exists($this, 'echo') ? $this->echo : $method ?? $router;
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
					&& ftell($this->buffer)
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
		return NULL;
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
	// final function app(string $name, mixed ...$params):object
	// {
	// 	return $this($this->echo = new $name($this, ...$params));
	// }
	final function break(Closure|array $router, mixed ...$params):void
	{
		[$this->route[0], $this->route[1]] = [$router, '__invoke'];
		if (func_num_args() > 1)
		{
			$this->entry = $params;
		}
	}
	// final function entry(array $params):void
	// {
	// 	$this->entry = $params + $this->entry;
	// }
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
	// function echo_object(string|object $instance, mixed ...$params):object
	// {
	// 	return $this($this->echo = is_string($instance) ? new ${"$instance"}($this, ...$params) : $instance);
	// }
	function echo_xml(string $type = 'webapp', string ...$params):webapp_echo_xml
	{
		return $this->echo = new webapp_echo_xml($this, $type, ...$params);
	}
	function echo_svg(array $attributes = []):webapp_echo_svg
	{
		return $this->echo = new webapp_echo_svg($this, $attributes);
	}
	function echo_json(array|object $data = []):webapp_echo_json
	{
		return $this($this->echo = new webapp_echo_json($this, $data));
	}
	function echo_html(string $title = NULL, webapp|string $authenticate = NULL):webapp_echo_html
	{
		$this->echo = new webapp_echo_html($this, $authenticate);
		is_string($title) && $this->echo->title($title);
		return $this->echo;
	}
	function routename():string
	{
		return strtr(substr(...is_string($this->router)
			? [$this->router, strlen($this['app_router'])]
			: [$this->method, strlen($this['request_method']) + 1]), '_', '-');
	}
	function admin(string $username, string $password, int $signtime, string $additional = NULL):array
	{
		return $signtime > static::time(-$this['admin_expire'])
			&& $username === $this['admin_username']
			&& $password === $this['admin_password']
				? [$username, $password, $additional] : [];
	}
	function auth(callable $authenticate = NULL, ?string $storage = NULL):array
	{
		return static::authorize($this->request_authorization($type)
			?? $this->request_cookie($storage ?? $this['admin_cookie']), $authenticate
			?? $this->admin(...));
	}
	// function allow(string|self $router, string ...$methods):bool
	// {
	// 	return $this->router === $router
	// 		&& in_array($this->method, $router === $this ? [
	// 			'get_captcha', 'get_qrcode', 'get_favicon', 'get_manifests', ...$methods] : $methods, TRUE);
	// }



	// function request_authorized(callable $authenticate = NULL, string $storage = NULL)
	// {
	// 	$this->request_auth_cookie($this['admin_cookie']);
	// 	$this->request_authorization($type)
	// }

	// function admin(?string $signature = NULL):array
	// {
	// 	return static::authorize(func_num_args() ? $signature : $this->request_cookie($this['admin_cookie']), $this->authenticate(...));
	// }
	// function authenticate(string $username, string $password, int $signtime, string $additional):array
	// {
	// 	return $signtime > static::time(-$this['admin_expire'])
	// 		&& $username === $this['admin_username']
	// 		&& $password === $this['admin_password']
	// 			? [$username, $password, $additional] : [];
	// }
	// function authorization(Closure $authenticate = NULL):array
	// {
	// 	return $authenticate
	// 		? static::authorize($this->request_authorization(), $authenticate)
	// 		: $this->admin($this->request_authorization());
	// }
	function authorized(string $additional = NULL):array
	{
		return ['Authorization' => 'Bearer ' . static::signature($this['admin_username'], $this['admin_password'], $additional)];
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
	function cond(...$conditions):object
	{
		return new class($this->query, ...$conditions)
		{
			private array $syntax = [], $values = [], $merge = [];
			function __construct(private readonly array $query, ...$conditions)
			{
				$this->append(...$conditions);
			}
			function __invoke(callable $object):object
			{
				if ($object instanceof webapp_mysql_table && $this->syntax)
				{
					$this->syntax[0] = "WHERE {$this->syntax[0]}";
				}
				$syntax = join(' AND ', $this->syntax);
				if ($this->merge)
				{
					$syntax = trim($syntax . ' ' . join(',', $this->merge));
				}
				return $syntax ? $object($syntax, ...$this->values) : $object;
			}
			function query(string $name, string $syntax, callable $format = NULL, string $default = NULL):?string
			{
				if (is_string($value = $this->query[$name] ?? $default))
				{
					$this->syntax[] = $syntax;
					$this->values[] = is_callable($format) ? $format($value) : $value;
					return $value;
				}
				return NULL;
			}
			function append(...$conditions):void
			{
				if ($conditions)
				{
					$this->syntax[] = array_shift($conditions);
					array_push($this->values, ...$conditions);
				}
			}
			function merge(string $conditions):void
			{
				$this->merge[] = $conditions;
			}
		};
	}
	function redis():webapp_redis
	{
		$redis = new webapp_redis($this, ...$this['redis_open']);
		$this['redis_auth'] && $redis->auth($this['redis_auth']);
		return $this($redis);
	}
	function locale():array
	{
		return [];
	}
	function smtp(string $url = NULL):webapp_client_smtp
	{
		return new webapp_client_smtp($url ?? $this['smtp_url']);
	}
	//request
	function request_header(string $name):?string
	{
		return $this->io->request_header($name);
	}
	function request_ip(bool $proxy = FALSE):string
	{
		//CF-Connecting-IP
		return $proxy && ($ip = $this->request_header('X-Forwarded-For'))
			? current(explode(',', $ip))
			: $this->io->request_ip();
	}
	function request_country():?string
	{
		//CF-IPCountry
		return $this->request_header('CF-IPCountry') ?? NULL;
	}
	function request_time():int
	{
		return $this->io->request_time();
	}
	function request_scheme():string
	{
		return $this->request_header('X-Forwarded-Proto')
			?? $this->io->request_scheme();
	}
	function request_host():string
	{
		return $this->request_header('X-Forwarded-Host')
			?? $this->request_header('Host')
			?? $this->request_ip();
	}
	function request_origin(string $path = NULL):string
	{
		return sprintf('%s://%s%s', $this->request_scheme(), $this->request_host(), $path);
	}
	// function request_entry(bool $route = FALSE):string
	// {
	// 	return $this->request_origin() . $this->io->request_entry();
	// }
	// function request_dir():string
	// {
	// 	return dirname($this->request_entry());
	// }
	function request_authorization(&$type = NULL):?string
	{
		return is_string($authorization = $this->request_header('Authorization'))
			? ([$type] = explode(' ', $authorization, 2))[1] ?? $type : NULL;
	}
	function request_cookie(string $name):?string
	{
		return $this->io->request_cookie($name);
	}
	function request_cookie_decrypt(string $name):?string
	{
		return static::decrypt($this->request_cookie($name));
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
		// if (in_array($format ??= $this->request_content_type(), ['application/x-www-form-urlencoded', 'multipart/form-data']))
		// {
		// 	return $this->io->request_formdata();
		// }
		// $content = is_string($key = $this->request_header('Mask-Key'))
		// 	? $this->unmasker(hex2bin($key), $this->io->request_content())
		// 	: $this->io->request_content();
		// return match ($format)
		// {
		// 	'application/json' => json_decode($content, TRUE),
		// 	'application/xml' => static::xml($content),
		// 	default => $content
		// };
		// $content = is_string($key = $this->request_header('Mask-Key'))
		// 	? $this->unmasker(hex2bin($key), $this->io->request_content())
		// 	: 
		return match ($format ?? $this->request_content_type())
		{
			'application/x-www-form-urlencoded',
			'multipart/form-data' => $this->io->request_formdata(),
			'application/json' => json_decode($this->io->request_content(), TRUE),
			'application/xml' => static::xml($this->io->request_content()),
			default => $this->io->request_content()
		};
	}
	function request_uploadedfile(string $name, ?int $maximum = 1, int $maxpathdeep = 0):webapp_request_uploadedfile
	{
		static $uploadedfile = $this->io->request_uploadedfile();
		return $this->uploadedfiles[$name] ??= new webapp_request_uploadedfile($this, $name, $uploadedfile, $maximum, $maxpathdeep);
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

	// function request_apple_device_enrollment():array
	// {
	// 	//Apple device enrollment must use HTTPS protocol request method POST and response status 301
	// 	return preg_match_all('/\<(\w+\>)([^\<]+)\<\/\1\s*\<(\w+\>)([^\<]+)\<\/\3/',
	// 		$this->request_content('application/pkcs7-signature'), $pattern)
	// 			? array_combine($pattern[2], $pattern[4]) : [];
	// }
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
	function response_expires(int $timestamp):void
	{
		$this->response_header('Expires', date(DateTimeInterface::RFC7231, $timestamp));
	}
	function response_last_modified(int $timestamp):void
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
	// function response_maskdata(string $content):string
	// {
	// 	$maskdata = $this->maskdata($content);
	// 	$this->response_header('Mask-Key', bin2hex(substr($maskdata, 0, 8)));
	// 	return substr($maskdata, 8);
	// }
	//append function
	function nonematch(string $etag, bool $needhash = FALSE):bool
	{
		$this->response_header('Etag', $hash = '"' . ($needhash ? static::hash($etag, TRUE) : $etag) . '"');
		return $this->request_header('If-None-Match') !== $hash;
	}
	// private function remote_encode_value(NULL|bool|int|float|string|array|webapp_xml $value):array
	// {
	// 	return match (get_debug_type($value))
	// 	{
	// 		'null' => ['null', 'NULL'],
	// 		'bool' => ['boolean', $value ? 'TRUE' : 'FALSE'],
	// 		'int' => ['integer', (string)$value],
	// 		'float' => ['float', (string)$value],
	// 		'string' => ['string', $value],
	// 		'array' => ['array', json_encode($value, JSON_UNESCAPED_UNICODE)],
	// 		default => ['xml' => $value->asXML()]
	// 	};
	// }
	// private function remote_decode_value(string $type, string $value):NULL|bool|int|float|string|array|webapp_xml
	// {
	// 	return match ($type)
	// 	{
	// 		'null' => NULL,
	// 		'boolean' => $value === 'TRUE',
	// 		'integer' => intval($value),
	// 		'float' => floatval($value),
	// 		'string' => $value,
	// 		'array' => json_decode($value, TRUE),
	// 		default => $this->xml($value)
	// 	};
	// }
	// function remote(string $url, string $method, array $params):NULL|bool|int|float|string|array|webapp_xml
	// {
	// 	$host = self::$remote[$url] ??= new webapp_client_http($url, ['autoretry' => 1, 'headers' => $this->authorized()]);
	// 	$input = $this->webappxml();
	// 	foreach ($params as $name => $value)
	// 	{
	// 		[$type, $data] = $this->remote_encode_value($value);
	// 		$input->append('entry', ['name' => $name, 'type' => $type])->cdata($data);
	// 	}
	// 	$output = $host->goto("{$host->path}?called/{$method}", ['method' => 'POST', 'type' => 'application/xml', 'data' => $input])->content();
	// 	if (is_object($output) === FALSE || (string)$output['type'] === 'error')
	// 	{
	// 		$response = [];
	// 		$host->status($response, TRUE);
	// 		$response[] = (string)$output;
	// 		throw new Error(join(PHP_EOL, $response));
	// 	}
	// 	return $this->remote_decode_value((string)$output['type'], (string)$output);
	// }
	// //router extends
	// function post_called(string $method)
	// {
	// 	//$this['app_called'];
	// 	if ($this->authorization())
	// 	{
	// 		try
	// 		{
	// 			$params = [];
	// 			if (is_object($input = $this->request_content()))
	// 			{
	// 				foreach ($input->entry as $entry)
	// 				{
	// 					$params[(string)$entry['name']] = $this->remote_decode_value((string)$entry['type'], (string)$entry);
	// 				}
	// 			}
	// 			[$type, $data] = $this->remote_encode_value($this->{$method}(...$params));
	// 		}
	// 		catch (Error $error)
	// 		{
	// 			[$type, $data] = ['error', (string)$error];
	// 		}
	// 		$this->echo_xml()->xml->setattr(['type' => $type])->cdata($data);
	// 		return 200;
	// 	}
	// 	return 401;
	// }
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
	function get_qrcode(string $encode, string $type = NULL, string $filename = NULL)
	{
		if ($this['qrcode_echo'] && is_string($decode = $this->decrypt($encode)) && strlen($decode) < $this['qrcode_maxdata'])
		{
			if ($this->nonematch($decode . $type, TRUE))
			{
				$draw = static::qrcode($decode, $this['qrcode_ecc']);
				in_array($type, ['png', 'jpeg'], TRUE)
					? $this->response_content_type("image/{$type}")
						|| webapp_image::qrcode($draw, $this['qrcode_size'])->{$type}($this->buffer)
					: $this->echo_svg()->xml->qrcode($draw, $this['qrcode_size']);
				$filename && $this->response_content_download($filename);
				return 200;
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
			$this->echo_svg()->xml->logo();
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
			$this->echo_json($this['manifests']);
			return 200;
		}
		return 404;
	}
	function get_masker()
	{
		$this->response_cache_control('no-transform');
		$this->response_content_type('text/javascript');
		return $this->response_sendfile(__DIR__ . '/static/js/masker.js');
		if ($this->nonematch(self::version, TRUE))
		{
			$this->response_sendfile(__DIR__ . '/static/js/masker.js');
			return 200;
		}
		return 304;
	}
	function get_help(string $method = 'index')
	{
		if ($this['app_help'] && class_exists('webapp_echo_help') === FALSE)
		{
			try
			{
				include 'extend/help/echo.php';
				$this->echo = new webapp_echo_help($this);
				return $this->echo->{$method}(...$this->query);
			}
			catch (Error)
			{
				unset($this->echo);
				return 500;
			}
		}
		return 404;
	}
}
class webapp_request_uploadedfile implements ArrayAccess, IteratorAggregate, Stringable, Countable
{
	private array $uploadedfiles;
	function __construct(
		public readonly webapp $webapp,
		private readonly string $name,
		array $uploadedfiles,
		?int $maximum,
		private readonly int $maxpathdeep) {
		$this->uploadedfiles = array_slice($uploadedfiles[$name] ?? [], 0, $maximum);
	}
	function __debugInfo():array
	{
		return iterator_to_array($this);
	}
	function __toString():string
	{
		return $this->name;
	}
	function __invoke(int $index = 0):array
	{
		$this->hash($index);
		return $this->uploadedfiles[$index];
	}
	function offsetExists(mixed $key):bool
	{
		return array_key_exists($key, $this->uploadedfiles);
	}
	function offsetGet(mixed $key):mixed
	{
		return $this->uploadedfiles[$key] ?? NULL;
	}
	function offsetSet(mixed $key, mixed $value):void
	{
	}
	function offsetUnset(mixed $key):void
	{
	}
	function count():int
	{
		return count($this->uploadedfiles);
	}
	function getIterator():Traversable
	{
		for ($i = 0; $i < count($this); ++$i)
		{
			yield $this->uploadedfiles[$i];
		}
	}
	// function column(string $key):array
	// {
	// 	return array_column($key === 'hash' ? $this->__debugInfo() : $this->uploadedfiles, $key);
	// }
	function path(int $index = 0):string
	{
		return $this->uploadedfiles[$index]['path'];
	}
	function size(?int $index = 0):int
	{
		return $index === NULL ? array_sum($this->column('size')) : $this->uploadedfiles[$index]['size'];
	}
	function mime(int $index = 0):string
	{
		return $this->uploadedfiles[$index]['mime'];
	}
	function file(int $index = 0):string
	{
		return $this->uploadedfiles[$index]['file'];
	}
	function hash(int $index = 0, string $algo = NULL):string
	{
		return $algo === NULL
			? $this->uploadedfiles[$index]['hash'] ??= $this->webapp->hashfile($this->file($index))
			: hash_file($algo, $this->file($index));
	}
	function open(int $index = 0, bool $mask = FALSE, ?string &$key = NULL, bool $merged = FALSE)
	{
		$file = fopen($this->file($index), 'r');
		return $mask ? $this->webapp->masker($file, $key, $merged) : $file;
	}
	function content(int $index = 0):string
	{
		return file_get_contents($this->file($index));
	}
	function validate(int $index):bool
	{
		#验证文件路径深度
		while (count($layer = preg_split('/[\/\\\]/', substr($this->path($index), 1))) <= $this->maxpathdeep + 1)
		{
			foreach ($layer as $name)
			{
				#验证文件名合法性
				if (preg_match('/^\w+(?:\.\w+)*$/', $name) === 0)
				{
					break 2;
				}
			}
			return TRUE;
		}
		return FALSE;
	}
	function move(int $index, string $destination):bool
	{
		if ($this->offsetExists($index)
			&& is_uploaded_file($this->file($index))
			&& move_uploaded_file($this->file($index), $destination)) {
			$this->uploadedfiles[$index]['file'] = $destination;
			return TRUE;
		}
		return FALSE;
	}
	function savedir(string $directory):int
	{
		$count = 0;
		foreach ($this as $index => $item)
		{
			$count += intval($this->validate($index)
				&& (is_dir($dir = $directory . dirname($item['path'])) || mkdir($dir, recursive: TRUE))
				&& $this->move($index, $directory . $item['path']));
		}
		return $count;
	}
	function savezip(string $filename):int
	{
		$count = 0;
		if (class_exists('ZipArchive', FALSE)
			&& is_object($zip = new ZipArchive)
			&& $zip->open($filename, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)) {
			foreach ($this as $index => $item)
			{
				$count += intval($zip->addFile($this->file($index), substr($item['path'], 1)));
			}
		}
		return $count;
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
	// function maskfile(string $filename, int $index = 0):bool
	// {
	// 	if ($this->offsetExists($index) && $this->webapp->maskfile($this->uploadedfiles[$index]['file'], $filename))
	// 	{
	// 		$this->uploadedfiles[$index]['file'] = $filename;
	// 		return TRUE;
	// 	}
	// 	return FALSE;
	// }
	// function post(string $url):webapp_client_http
	// {
	// 	return webapp_client_http::open($url, ['method' => 'POST',
	// 		'headers' => $this->webapp->authorized(),
	// 		'type' => 'multipart/form-data',
	// 		'data' => [$this->name => $this]]);
	// }
}