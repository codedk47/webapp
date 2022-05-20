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
	function request_header(string $name):?string;
	function request_method():string;
	function request_query():string;
	function request_cookie(string $name):?string;
	function request_content():string;
	function request_formdata():array;
	function request_uploadedfile():array;
	function response_sent():bool;
	function response_status(int $code):void;
	function response_header(string $value):void;
	function response_cookie(string ...$values):void;
	function response_content(string $data):bool;
	function response_sendfile(string $filename):bool;
}
abstract class webapp implements ArrayAccess, Stringable, Countable
{
	const version = '4.7a', key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
	public readonly self $webapp;
	public readonly object|string $router;
	public readonly string $method;
	private array $errors = [], $cookies = [], $headers = [], $uploadedfiles, $configs, $route, $entry;
	protected static array $interfaces = [];
	static function __callStatic(string $name, array $arguments):mixed
	{
		return (self::$interfaces[$name] ??= require __DIR__ . "/lib/{$name}/interface.php")(...$arguments);
	}
	static function res(string $filename = NULL):string
	{
		return "/webapp/res/{$filename}";
	}
	static function random(int $length):string
	{
		return random_bytes($length);
	}
	static function time(int $offset = 0):int
	{
		return time() + $offset;
	}
	static function time33(string $data):int
	{
		for ($hash = 5381, $i = strlen($data); $i;)
		{
			$hash = ($hash & 0xfffffffffffffff) + (($hash & 0x1ffffffffffffff) << 5) + ord($data[--$i]);
		}
		return $hash;
	}
	static function hash(string $data, bool $care = FALSE):string
	{
		for ($code = static::time33($data), $hash = '', [$i, $n, $b] = $care ? [10, 6, 63] : [12, 5, 31]; $i;)
		{
			$hash .= self::key[$code >> --$i * $n & $b];
		}
		return $hash;
	}
	static function hashfile(string $filename, bool $care = FALSE):?string
	{
		return is_string($hash = hash_file('haval160,4', $filename, TRUE)) ? static::hash($hash, $care) : NULL;
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
	static function authorize(?string $signature, callable $authenticate):mixed
	{
		return is_string($data = static::decrypt($signature))
			&& strlen($data) > 5
			&& extract(unpack('Vsigntime/C2length', $data)) === 3
			&& strlen($data) > 5 + $length1 + $length2
			&& is_array($acc = unpack("a{$length1}uid/a{$length2}pwd/a*add", $data, 6))
				? $authenticate($acc['uid'], $acc['pwd'], $signtime, $acc['add']) : NULL;
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
			for ($result = [$expire, '', [], []], $i = 0; $i < $length;)
			{
				$result[1] .= $values['code'][$i++];
				$result[2][] = $values['size' . $i];
				$result[3][] = $values['angle' . $i];
			}
			return $result;
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
	function __construct(array $config = [], private readonly webapp_io $io = new webapp_stdio)
	{
		[$this->webapp, $this->configs] = [$this, $config + [
			//Request
			'request_method'	=> in_array($method = strtolower($io->request_method()), ['get', 'post', 'put', 'patch', 'delete'], TRUE) ? $method : 'get',
			'request_query'		=> $io->request_query(),
			//Application
			'app_charset'		=> 'utf-8',
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
			'mysql_charset'		=> 'utf8mb4',//latin1
			//Captcha
			'captcha_echo'		=> TRUE,
			'captcha_unit'		=> 4,
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
			//'smtp_context'		=> ['ssl://smtp.gmail.com:465', 'username@gmail.com', 'password']
			'smtp_host'			=> 'tcp://username:password@host']];
		[$this->route, $this->entry] = method_exists($this, $route = sprintf('%s_%s', $this['request_method'],
			$track = preg_match('/^[-\w]+(?=\/([\-\w]*))?/', $this['request_query'], $entry)
				? strtr($entry[0], '-', '_') : $entry[] = $this['app_index']))
			? [[$this, $route], array_slice($entry, 1)]
			: [[$this['app_router'] . $track, sprintf('%s_%s', $this['request_method'],
				count($entry) > 1 ? strtr($entry[1], '-', '_') : $this['app_index'])], []];
		[&$this->router, &$this->method] = $this->route;
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
						if (preg_match_all('/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/',
							$this['request_query'], $pattern, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
							$parameters = array_column($pattern, 2, 1);
							foreach (array_slice($tracert->getParameters(), intval($router === $this)) as $parameter)
							{
								if (array_key_exists($parameter->name, $parameters))
								{
									$this->entry[$parameter->name] ??= match ((string)$parameter->getType())
									{
										'int' => intval($parameters[$parameter->name]),
										'float' => floatval($parameters[$parameter->name]),
										'string' => $parameters[$parameter->name] ?? '',
										default => $parameters[$parameter->name]
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
				//Content-Length: 
				$this->io->response_content((string)$this);
				unset($this->buffer);
			}
		}
	}
	function __toString():string
	{
		return stream_get_contents($this->buffer, -rewind($this->buffer));
	}
	// function __call(string $name, array $params):mixed
	// {
	// 	return property_exists($this, 'app') && method_exists($this->app, $name) ? $this->app->{$name}(...$params) : NULL;
	// }
	function __get(string $name):mixed
	{
		if ($this->offsetExists($name))
		{
			return $this[$name];
		}
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
		$object->webapp ??= $this;
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
		//$this->configs[$key] = $value;
	}
	final function offsetUnset(mixed $key):void
	{
		//unset($this->configs[$key]);
	}
	final function count():int
	{
		return property_exists($this, 'buffer') ? ftell($this->buffer) : 0;
	}
	function error(string $message):string
	{
		return $this->errors[] = $message;
	}
	final function app(string $name, mixed ...$params):object
	{
		return $this($this->app = new $name($this, ...$params));
	}
	final function break(Closure $router, mixed ...$params):void
	{
		[$this->route[0], $this->route[1], $this->entry] = [$router, '__invoke', $params];
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
		$replace = array_reverse($params + (preg_match_all('/\,(\w+)(?:\:([\%\+\-\.\/\=\w]*))?/', $this['request_query'],
			$pattern, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) ? array_column($pattern, 2, 1) : []), TRUE);
		return array_reduce(array_keys($replace), fn($carry, $key) => is_scalar($replace[$key])
			? (is_bool($replace[$key]) ? $carry : "{$carry},{$key}:{$replace[$key]}")
			: "{$carry},{$key}", $router ?? strstr("?{$this['request_query']},", ',', TRUE));
	}
	function admin(?string $signature = NULL):mixed
	{
		return static::authorize(func_num_args() ? $signature : $this->request_cookie($this['admin_cookie']),
			fn(string $username, string $password, int $signtime, string $additional):array =>
				$signtime > static::time(-$this['admin_expire'])
				&& $username === $this['admin_username']
				&& $password === $this['admin_password']
					? [$username, $password, $additional] : []);
	}
	function authorization(Closure $authenticate = NULL):mixed
	{
		return $authenticate
			? static::authorize($this->request_authorization(), $authenticate)
			: $this->admin($this->request_authorization());
	}

	//---------------------



	//----------------
	function open(string $url, array $options = []):webapp_client_http
	{
		// $options['headers']['Authorization'] ??= 'Bearer ' . $this->signature($this['admin_username'], $this['admin_password']);
		$options['headers']['User-Agent'] ??= 'WebApp/' . self::version;
		return webapp_client_http::open($url, $options);

		// return $this(new webapp_client_http($url, $timeout))->headers([
		// 	'Authorization' => 'Digest ' . $this->signature($this['admin_username'], $this['admin_password']),
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
	function request_ip():string
	{
		return $this->io->request_ip();
	}
	// function request_query(string $name):?string
	// {
	// 	return preg_match('/^\w+$/', $name) && preg_match('/\,' . $name . '\:([\%\+\-\.\/\=\w]+)/', $this['request_query'], $query) ? $query[1] : NULL;
	// }
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
	function request_referer():?string
	{
		return $this->request_header('Referer');
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
	function request_content(string $format = NULL):array|string|webapp_xml
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
	function request_uploadedfile(string $name, int $maximum = NULL):ArrayObject
	{
		return array_key_exists($name, $this->uploadedfiles ??= $this->io->request_uploadedfile()) && is_object($this->uploadedfiles[$name])
			? $this->uploadedfiles[$name] : $this->uploadedfiles[$name] = new class($this, $this->uploadedfiles[$name] ?? [], $maximum) extends ArrayObject implements Stringable
			{
				function __construct(public readonly webapp $webapp, array $uploadedfiles, int $maximum = NULL)
				{
					parent::__construct(flags: ArrayObject::STD_PROP_LIST);
					foreach (array_slice($uploadedfiles, 0, $maximum) as $uploadedfile)
					{
						$this[] = ['hash'=> $webapp->hashfile($uploadedfile['file']), ...$uploadedfile];
					}
				}
				function __toString():string
				{
					return join('|', $this->column('file'));
				}
				function __debugInfo():array
				{
					return $this->getArrayCopy();
				}
				function column(string $key):array
				{
					return array_column($this->getArrayCopy(), $key);
				}
				function size():int
				{
					return array_sum($this->column('size'));
				}
				// function open(string $hash = NULL):mixed
				// {
				// 	return fopen($hash === NULL ? $this : $this[$hash]['file'], 'r');
				// }
				// function content(string $hash = NULL):string
				// {
				// 	return file_get_contents($hash === NULL ? $this : $this[$hash]['file']);
				// }
				function movefile(int $index, string $filename):bool
				{
					//$index = max(1, $index) < count($this)

					//if (array_key_exists($index, $this))
					return move_uploaded_file($this[$index]['file'], $filename);
				}
				function moveto(string $filename):array
				{
					$success = [];
					foreach ($this as $file)
					{
						// if (move_uploaded_file($file['file'], $filename)
						// {

						// }
						print_r($file);
					}
					// $date = array_combine(['date', 'year', 'month', 'day', 'week', 'yday', 'time', 'hours', 'minutes', 'seconds'], explode(' ', date('Ymd Y m d w z His H i s')));
					// foreach ($this as $hash => $info)
					// {
					// 	if ((is_dir($rootdir = dirname($file = preg_replace_callback('/\{([a-z]+)(?:\,(-?\d+)(?:\,(-?\d+))?)?\}/i', fn(array $format):string => match ($format[1])
					// 	{
					// 		'hash' => count($format) > 2 ? substr($hash, ...array_slice($format, 2)) : $hash,
					// 		'name', 'type' => $info[$format[1]],
					// 		default => $date[$format[1]] ?? $format[0]
					// 	}, $filename))) || mkdir($rootdir, recursive: TRUE)) && move_uploaded_file($this[$hash]['file'], $file)) {
					// 		$this[$hash]['file'] = $file;
					// 		$success[$hash] = $this[$hash];
					// 	}
					// }
					return $success;
				}
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
			};
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
	function response_cache_control(string $command):void
	{
		$this->response_header('Cache-Control', $command);
	}
	function response_content_type(string $mime):void
	{
		$this->response_header('Content-Type', $mime);
	}
	function response_content_download(string $basename):void
	{
		$this->response_content_type('application/force-download');
		$this->response_header('Content-Disposition', 'attachment; filename=' . urlencode($basename));
	}
	function response_sendfile(string $filename):bool
	{
		return $this->io->response_sendfile($filename);
	}
	//append function
	final function init_admin_sign_in(array $config = [], webapp_io $io = new webapp_stdio):bool
	{
		self::__construct($config, $io);
		if (method_exists(...$this->route))
		{
			if ($this->router === $this && in_array($this->method, ['get_captcha', 'get_qrcode'], TRUE)) return TRUE;
			if ($this->admin) return FALSE;
			$this->response_status(403);
		}
		if ($this['request_query'] === '')
		{
			if ($this['request_method'] === 'post')
			{
				$this->app('webapp_echo_json', ['signature' => NULL]);
				if (webapp_echo_html::form_sign_in($this)->fetch($admin))
				{
					if ($this->admin($signature = $this->signature($admin['username'], $admin['password'])))
					{
						$this->response_cookie($this['admin_cookie'], $this->app['signature'] = $signature);
						$this->response_refresh(0);
					}
					else
					{
						$this->app['errors'][] = 'Sign in failed';
					}
				}
			}
			else
			{
				webapp_echo_html::form_sign_in($this->app('webapp_echo_html')->main);
				$this->app->title('Sign In Admin');
			}
			$this->response_status(200);
		}
		return TRUE;
	}
	function get_captcha(string $random = NULL)
	{
		if ($this['captcha_echo'])
		{
			if ($result = static::captcha_result($random))
			{
				$this->response_content_type('image/jpeg');
				webapp_image::captcha($result, ...$this['captcha_params'])->jpeg($this->buffer);
				return;
			}
			if ($random = static::captcha_random($this['captcha_unit'], $this['captcha_expire']))
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
		if ($this['qrcode_echo'] && is_string($decode = $this->url64_decode($encode)) && strlen($decode) < $this['qrcode_maxdata'])
		{
			$this->response_content_type('image/png');
			webapp_image::qrcode(static::qrcode($decode, $this['qrcode_ecc']), $this['qrcode_size'])->png($this->buffer);
			return;
		}
		return 404;
	}
}