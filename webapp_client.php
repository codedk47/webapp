<?php
declare(strict_types=1);
class webapp_client_debug extends php_user_filter
{
	//注意：过滤流在内部读取时只能过滤一个队列，这是一个BUG？
	function filter($in, $out, &$consumed, $closing):int
	{
		echo "\r\n", $consumed === NULL
			? '>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>'
			: '<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<',
			"\r\n";
		while ($bucket = stream_bucket_make_writeable($in))
		{
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
			echo quoted_printable_encode($bucket->data);
		}
		return PSFS_PASS_ON;
	}
}
class webapp_client implements Stringable, Countable
{
	public array $errors = [];
	protected readonly int $timeout, $flags;
	private $filter, $buffer, $client;
	function __construct(public readonly string $socket, array $options = [])
	{
		$this->timeout = $options['timeout'] ?? 4;
		$this->flags = $options['flags'] ?? STREAM_CLIENT_CONNECT;
		$this->buffer = fopen('php://memory', 'r+');
		$this->reconnect();
	}
	function __destruct()
	{
		fclose($this->buffer);
	}
	// function __get(string $name):mixed
	// {
	// 	return match ($name)
	// 	{
	// 		'metadata' =>		stream_get_meta_data($this->stream),
	// 		'remote_name' =>	stream_socket_get_name($this->stream, TRUE),
	// 		'local_name' =>		stream_socket_get_name($this->stream, FALSE),
	// 		'is_lockable' =>	stream_supports_lock($this->stream),
	// 		'is_local' =>		stream_is_local($this->stream),
	// 		'is_tty' =>			stream_isatty($this->stream),
	// 		default =>			NULL
	// 	};
	// }
	//缓冲区内容
	function __toString():string
	{
		return stream_get_contents($this->buffer, ($length = count($this)) && rewind($this->buffer) ? $length : 0);
	}
	//缓冲区大小
	function count():int
	{
		return ftell($this->buffer);
	}
	//调试
	function debug(int $filter = STREAM_FILTER_WRITE/* STREAM_FILTER_ALL */):void
	{
		if (in_array('webapp_client_debug', stream_get_filters(), TRUE) === FALSE)
		{
			stream_filter_register('webapp_client_debug', 'webapp_client_debug');
			stream_filter_append($this->client, 'webapp_client_debug', $filter);
		}
	}
	//重连
	function reconnect(int $retry = 0):bool
	{
		do
		{
			//var_dump("reconnect");
			if (is_resource($client = @stream_socket_client($this->socket, $erron, $error,
				$this->timeout, $this->flags, stream_context_create(['ssl' => [
					'verify_peer' => FALSE,
					'verify_peer_name' => FALSE,
					'allow_self_signed' => TRUE]])))
				&& fwrite($client, '') === 0) {
				$this->client = $client;
				//var_dump( fwrite($client, '') );
				return TRUE;
			}
			
			$this->errors[] = "{$erron}: {$error}";
		} while ($retry-- > 0);
		return FALSE;
	}
	//关闭
	function shutdown(int $mode = STREAM_SHUT_WR):bool
	{
		return stream_socket_shutdown($this->client, $mode);
	}
	//模式（请使用默认阻塞模式，别问为什么，除非你知道在做什么）
	function blocking(bool $mode):bool
	{
		return stream_set_blocking($this->client, $mode);
	}
	//超时
	function timeout(int $seconds):bool
	{
		return stream_set_timeout($this->client, $seconds);
	}
	//缓冲区过滤
	function filter(...$params):bool
	{
		return $this->remove() && is_resource($this->filter = stream_filter_append($this->buffer, ...$params));
	}
	//缓冲区过滤移除
	function remove():bool
	{
		if (is_resource($this->filter))
		{
			if (stream_filter_remove($this->filter) === FALSE)
			{
				return FALSE;
			}
			$this->filter = NULL;
		}
		return TRUE;
	}
	//缓冲区清空
	function clear():bool
	{
		return $this->remove() && rewind($this->buffer);
	}
	//缓冲区输入
	function echo(string $data):bool
	{
		return fwrite($this->buffer, $data) === strlen($data);
	}
	//缓冲区格式化输入
	function printf(string $format, mixed ...$values):bool
	{
		return $this->echo(sprintf($format, ...$values));
	}
	//缓冲区从
	function from($stream, int $length = NULL):bool
	{
		return is_int($copied = stream_copy_to_stream(
			is_resource($stream) ? $stream : fopen($stream, 'r'),
			$this->buffer, $length)) && ($length === NULL || $copied === $length);
	}
	//缓冲区拉取
	function pull(int $length = NULL):bool
	{
		return $this->from($this->client, $length);
	}
	//缓冲区到
	function to($stream):bool
	{
		$length = count($this);
		return stream_copy_to_stream($this->buffer,
			is_resource($stream) ? $stream : fopen($stream, 'w'),
			rewind($this->buffer) ? $length : 0) === $length;
	}
	//缓冲区推送
	function push():bool
	{
		return $this->to($this->client);
	}



	//窥视数据
	function peek(&$output, int $length):bool
	{
		return is_string($output = @stream_socket_recvfrom($this->client, $length, STREAM_PEEK)) && strlen($output) === $length;
	}
	//读取
	function read(&$output, int $length = NULL):int
	{
		return is_string($output = @stream_get_contents($this->client, $length)) ? strlen($output) : 0;
	}
	//读取一行
	function readline(&$output, int $length = 65535, string $ending = "\r\n"):bool
	{
		return is_string($output = @stream_get_line($this->client, $length, $ending));
	}
	//读取至流
	// function readinto($stream, int $length = NULL):int
	// {
	// 	return (int)@stream_copy_to_stream($this->client, $stream, $length);
	// }








	//读取剩余内容
	function readfull(int $length = -1):string
	{
		return stream_get_contents($this->stream, $length);
	}


	//发送
	function send(string $data):bool
	{
		return $this->client && @fwrite($this->client, $data) === strlen($data);
	}
	function sendfrom($stream, int $length = NULL):bool
	{
		return is_int($copied = @stream_copy_to_stream($stream, $this->client, $length))
			&& ($length === NULL || $copied === $length);
	}
	static function boundary():string
	{
		return '----WebApp' . bin2hex(random_bytes(16));
	}
}
class webapp_client_smtp extends webapp_client
{
	private array $dialog = [];
	private string $username = 'anonymous@localhost', $boundary;
	function __construct(string $socket, array $options = [])
	{
		parent::__construct($socket, $options);
		$this->boundary = static::boundary();
		if ($this->readstat() === 220)
		{
			$host = parse_url($socket)['host'];
			array_key_exists('username', $options)
				? $this("EHLO {$host}") === 250
					&& strpos(join($this->dialog), 'AUTH LOGIN')
					&& $this('AUTH LOGIN') === 334
					&& $this(base64_encode($this->username = $options['username'])) === 334
					&& $this(base64_encode($options['password'] ?? '')) === 235
				: $this("HELO {$host}") === 250;
		}
	}
	function __destruct()
	{
		$this('QUIT');
		parent::__destruct();
	}
	function __invoke(string $command):int
	{
		$this->dialog[] = $command;
		return $this->send("{$command}\r\n") ? $this->readstat() : -1;
	}
	function __debugInfo():array
	{
		return $this->dialog;
	}
	private function readstat():int
	{
		while ($this->readline($content))
		{
			if (preg_match('/^\d{3}\-/', $this->dialog[] = $content))
			{
				continue;
			}
			return intval($content);
		}
		return -1;
	}

	// function mailto(){}
	// function maildata(){}
	
	function header(string $subject, string $to, string $from = ''):bool
	{
		return $this->echo(join("\r\n", [
			'Date: ' . date('r'),
			sprintf('Subject: =?UTF-8?B?%s?=', base64_encode($subject)),
			sprintf('To: =?UTF-8?B?%s?=', base64_encode($to)),
			'MIME-Version: 1.0',
			'Content-type: multipart/mixed; boundary=' . $this->boundary,
			"\r\n"
		]));
	}
	function content(string $data, string $type = 'text/plain; charset=utf-8'):bool
	{
		return $this->echo(join("\r\n", [
			"--{$this->boundary}",
			'Content-type: ' . $type,
			'',
			$data,
			'']));
	}
	function attach(string $filename, string $name = NULL)
	{
		return $this->echo(join("\r\n", [
			"--{$this->boundary}",
			"Content-Disposition: attachment; filename=\"cool.txt\"",
			'Content-Transfer-Encoding: base64',
			'',
			base64_encode(file_get_contents($filename)),
			'']));
		//JVBEDi0xLjMKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmlsdGVyIC9GbGF0
	}
	function sendto(string $mail)
	{
		$this("MAIL FROM: <{$this->username}>");
		$this("RCPT TO: <{$mail}>");
		$this('DATA');
		//$this->push();
		$this((string)$this);
		$this("--{$this->boundary}--\r\n.\r\n");

		print_r($this);

		
	}

	
	// function mailto(string $to, )
	// {
	// 	$this("MAIL FROM: <{$this->username}>");


	// 	$this('RCPT TO: <tonyone480@gmail.com>');


	// 	$this('DATA');
	// 	$this(join("\r\n",[
	// 		'Subject: Test-message',
	// 		'from: <'.$this->username.'>',
	// 		'to: <tonyone480@gmail.com>',
	// 		'MIME-Version: 1.0',
	// 		'',
	// 		'你好 gmail!!!',
	// 		'.'
	// 	]));
	// }
	// static function sendmail()
	// {
		
	// }
}
class webapp_client_http extends webapp_client implements ArrayAccess
{
	public readonly string $path;
	protected readonly int $autoretry, $autojump;
	protected array $headers = [
		'Host' => '*',
		'Connection' => 'keep-alive',
		'User-Agent' => 'WebApp/Client',
		'Accept' => 'application/json,application/xml,text/html;q=0.9,*/*;q=0.8',
		'Accept-Encoding' => 'gzip,deflate',
		'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8'
	], $cookies = [], $response = [];
	function __construct(public readonly string $url, array $options = [], private array &$referers = [])
	{
		[$socket, $this->headers['Host'], $this->path] = $parse = static::parseurl($url);
		$this->autoretry = $options['autoretry'] ?? 0;
		$this->autojump = $options['autojump'] ?? 0;
		$this->referers[$socket] = $this;
		if (count($parse) > 3)
		{
			$this->headers['Authorization'] = 'Basic ' . base64_encode(join(':', array_slice($parse, 3)));
		}
		if (array_key_exists('headers', $options))
		{
			$this->headers($options['headers']);
		}
		if (array_key_exists('cookies', $options))
		{
			$this->cookies($options['cookies']);
		}
		parent::__construct($socket, $options);
	}
	// function __debugInfo():array
	// {
	// 	return $this->response;
	// }
	function offsetExists(mixed $offset):bool
	{
		return array_key_exists($offset, $this->response);
	}
	function offsetGet(mixed $offset):mixed
	{
		return $this->response[$offset] ?? NULL;
	}
	function offsetSet(mixed $offset, mixed $value):void
	{
		$this->headers[$offset] = $value;
	}
	function offsetUnset(mixed $offset):void
	{
		unset($this->headers[$offset]);
	}
	function headers(array $replace):static
	{
		foreach ($replace as $name => $value)
		{
			$this->headers[$name] = $value;
		}
		return $this;
	}
	function cookies(array|string $replace):static
	{
		foreach (is_string($replace)
			? (preg_match_all('/([^=]+)=([^;]+);?/', $replace, $cookies, PREG_SET_ORDER) ? array_column($cookies, 2, 1) : [])
			: $replace as $name => $value) {
			if (strpbrk($name, "=,; \f\n\r\t\v") === FALSE)
			{
				$this->cookies[$name] = $value;
			}
		}
		return $this;
	}
	private function form($data, string $name, string $contents, string $filename):bool
	{
		if (is_array($data))
		{
			foreach ($data as $key => $value)
			{
				if ($this->form($value, $key, $contents, $filename) === FALSE)
				{
					return FALSE;
				}
			}
			return TRUE;
		}
		return match (TRUE)
		{
			is_null($data), is_scalar($data) => $this->printf($contents, $name) && $this->echo((string)$data),
			is_resource($data) => $this->printf($filename, $name, basename(stream_get_meta_data($data)['uri'])) && $this->from($data),
			//$data instanceof self => $this->from($data),
			default => FALSE
		} && $this->echo("\r\n");
	}
	function request(string $method, string $path, $data = NULL, string $type = NULL):bool
	{
		$request = ["{$method} {$path} HTTP/1.1"];
		foreach ($this->headers as $name => $value)
		{
			$request[] = "{$name}: {$value}";
		}
		if ($this->cookies)
		{
			$cookies = [];
			foreach ($this->cookies as $name => $value)
			{
				$cookies[] = "{$name}={$value}";
			}
			$request[] = 'Cookie: ' . join(';', $cookies);
		}
		if ($data === NULL || ($this->clear()
			&& (is_string($data) ? $this->echo($data) : match ($type ??= 'application/x-www-form-urlencoded') {
				'application/x-www-form-urlencoded' => $this->echo(http_build_query($data)),
				'multipart/form-data' => $this->form($data, '',
					$contents = '--' . join("\r\n", [
						$boundary = static::boundary(),
						'Content-Disposition: form-data; name="%s"', "\r\n"]),
					substr($contents, 0, -4) . "; filename=\"%s\"\r\nContent-Type: application/octet-stream\r\n\r\n",
					$type .= "; boundary={$boundary}") && $this->echo("--{$boundary}--"),
				'application/json' => $this->echo(json_encode($data, JSON_UNESCAPED_UNICODE)),
				'application/xml' => $this->echo($data instanceof DOMDocument ? $data->saveXML() : (string)$data),
				'application/octet-stream' => $this->from($data),
				default => FALSE})
			&& is_resource($buffer = fopen('php://memory', 'r+'))
			&& $this->to($buffer)
			&& is_int($length = ftell($buffer))
			&& ($request[] = "Content-Type: {$type}")
			&& ($request[] = "Content-Length: {$length}"))) {
			$request = join($request[] = "\r\n", $request);
			$autoretry = $this->autoretry;
			do
			{
				if ($this->send($request) === FALSE
					|| ($data === NULL || $length === 0 || (rewind($buffer)
						&& $this->sendfrom($buffer, $length))) === FALSE
					|| $this->readline($status) === FALSE) {
					continue;
				}
				$this->response = [$status];
				do
				{
					if ($this->readline($header) === FALSE)
					{
						continue 2;
					}
					if ($offset = strpos($header, ': '))
					{
						$name = ucwords(substr($header, 0, $offset), '-');
						$value = substr($header, $offset + 2);
						if ($name !== 'Set-Cookie')
						{
							$this->response[$name] = $value;
							continue;
						}
						if (preg_match('/^([^=]+)=([^;]+)(?:; expires=([^;]+))?/', $value, $cookies))
						{
							if (array_key_exists(3, $cookies) && strtotime($cookies[3]) < time())
							{
								unset($this->cookies[$cookies[1]]);
								continue;
							}
							$this->cookies[$cookies[1]] = $cookies[2];
						}
					}
				} while ($header);
				if ($this->clear())
				{
					if (array_key_exists('Content-Encoding', $this->response))
					{
						if (match ($this->response['Content-Encoding']) {
							'gzip' => $this->filter('zlib.inflate', STREAM_FILTER_WRITE, ['window' => 31]),
							'deflate' => $this->filter('zlib.inflate', STREAM_FILTER_WRITE),
							default => TRUE} === FALSE) {
							continue;
						};
					}
					if (array_key_exists('Content-Length', $this->response))
					{
						if ($this->pull(intval($this->response['Content-Length'])) === FALSE)
						{
							continue;
						}
					}
					else
					{
						if (array_key_exists('Transfer-Encoding', $this->response)
							&& $this->response['Transfer-Encoding'] === 'chunked') {
							do
							{
								if ($this->readline($code, 8) === FALSE)
								{
									continue 2;
								}
								if ($size = hexdec($code))
								{
									if ($this->pull($size) === FALSE)
									{
										continue 2;
									}
								}
								if ($this->readline($null, 2) === FALSE)
								{
									continue 2;
								}
							} while ($size);
						}
					}
					return $this->remove();
				}
				break;
			} while ($autoretry > 0 && $this->reconnect(--$autoretry));
		}
		$this->response = [];
		$this->clear();
		return FALSE;
	}
	// function status():int
	// {
	// 	return $this->response ? intval(substr($this[0], 9)) : 0;
	// }
	function then(Closure $success, Closure $failure = NULL):static
	{
		#look then like that promise
		$closure = $this->response && strlen($this[0]) > 9 && $this[0][9] === '2'
			? $success->call($this) : ($failure ? $failure->call($this) : NULL);
		return $closure instanceof static ? $closure : $this;
	}
	// function catch(Closure $failure):static
	// {
	// 	return $this->then(fn() => NULL, $failure);
	// }
	function goto(string $url, array $options = []):static
	{
		$autojump = $this->autojump;
		do
		{
			$referer = isset($client) ? $client->url : $this->url;
			if (preg_match('/^https?\:\/\//i', $url) === 0)
			{
				$client = $this;
				$path = $url;
				continue;
			}
			[$socket,, $path] = static::parseurl($url);
			if (array_key_exists($socket, $this->referers))
			{
				$client = $this->referers[$socket];
				continue;
			}
			$path = ($client = new static($url, [
				'timeout' => $this->timeout,
				'flags' => $this->flags,
				'autoretry' => $this->autoretry,
				'autojump' => $this->autojump,
				'headers' => ['User-Agent' => $this->headers['User-Agent']],
				'cookies' => $this->cookies
			], $this->referers))->path;
		} while ($client
			->headers(['Referer' => $referer])
			->request($options['method'] ?? 'GET', $path,
				$options['data'] ?? NULL,
				$options['type'] ?? NULL)
			&& $autojump-- > 0
			&& array_key_exists('Location', $this->response)
			&& ($url = $this->response['Location']));
		return $client;
	}
	function mimetype():string
	{
		return is_string($type = $this['Content-Type'])
			? strtolower(is_int($offset = strpos($type, ';')) ? substr($type, 0, $offset) : $type)
			: 'application/octet-stream';
	}
	function filetype():string
	{
		[$mime, $type] = explode('/', $this->mimetype(), 2);
		return match ($mime)
		{
			'text' => $type === 'plain' ? 'txt' : $type,
			'image' => $type === 'jpeg' ? 'jpg' : $type,
			//'audio', 'video' => $type,
			default => $mime === 'application' && preg_match('/^(xml|svg)$/', $type) ? 'xml' : $type
		};
	}
	function content(?string $mimetype = NULL):string|array|SimpleXMLElement
	{
		return match ($mimetype ?? $this->mimetype())
		{
			'application/json' => json_decode((string)$this, TRUE),
			'application/xml' => class_exists('webapp_xml', FALSE)
				? new webapp_xml((string)$this)
				: new SimpleXMLElement((string)$this),
			'text/html' => class_exists('webapp_implementation', FALSE)
				? (($doc = new webapp_implementation)->loadHTML((string)$this) ? $doc->xml : (string)$this)
				: (($doc = new DOMDocument)->loadHTML((string)$this, LIBXML_NOWARNING | LIBXML_NOERROR) ? simplexml_import_dom($doc) : (string)$this),
			default => (string)$this
		};
	}
	function saveas(string $filename):bool
	{
		return (is_dir($dir = dirname($filename)) || mkdir($dir, recursive: TRUE)) && $this->to($filename);
	}
	static function open(string $url, array $options = []):static
	{
		return ($http = new static($url, $options))->goto($http->path, $options);
	}
	static function parseurl(string $url):array
	{
		$port = 0;
		if (is_array($parse = parse_url($url)) && array_key_exists('scheme', $parse) && array_key_exists('host', $parse))
		{
			switch (strtolower($parse['scheme']))
			{
			 	case 'https':
					$port = 443;
				case 'wss':
					$parse['scheme'] = 'ssl';
					break;
				case 'http':
					$port = 80;
				case 'ws':
					$parse['scheme'] = 'tcp';
					break;
			}
			$host = array_key_exists('port', $parse) ? $parse['host'] .= ":{$parse['port']}" : "{$parse['host']}:{$port}";
			$result = ["{$parse['scheme']}://{$host}", $parse['host'], $parse['path'] ?? '/'];
			if (array_key_exists('query', $parse))
			{
				$result[2] .= "?{$parse['query']}";
			}
			if (array_key_exists('user', $parse))
			{
				$result[] = $parse['user'];
				if (array_key_exists('pass', $parse))
				{
					$result[] = $parse['pass'];
				}
			}
			return $result;
		}
		return ["tcp://127.0.0.1:{$port}", '127.0.0.1', '/'];
	}
}
class webapp_client_websocket extends webapp_client_http
{
	/*
	WebSocket
	Frame format:
	0                   1                   2                   3
	0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
	+-+-+-+-+-------+-+-------------+-------------------------------+
	|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
	|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
	|N|V|V|V|       |S|             |   (if payload len==126/127)   |
	| |1|2|3|       |K|             |                               |
	+-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
	|     Extended payload length continued, if payload len == 127  |
	+ - - - - - - - - - - - - - - - +-------------------------------+
	|                               |Masking-key, if MASK set to 1  |
	+-------------------------------+-------------------------------+
	| Masking-key (continued)       |          Payload Data         |
	+-------------------------------- - - - - - - - - - - - - - - - +
	:                     Payload Data continued ...                :
	+ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
	|                     Payload Data continued ...                |
	+---------------------------------------------------------------+
	*/
	function __construct(string $url)
	{
		parent::__construct($url);
		$this->headers([
			'Upgrade' => 'websocket',
			'Connection' => 'Upgrade',
			'Sec-WebSocket-Version' => 13,
			'Sec-WebSocket-Key' => base64_encode(random_bytes(16))
		]);
	}
	function then(Closure $success, Closure $failure = NULL):static
	{
		$closure = $this->response
			&& $this->response[0] === 'HTTP/1.1 101 Switching Protocols'
			&& array_key_exists('Sec-WebSocket-Accept', $this->response)
			&& base64_encode(sha1("{$this->headers['Sec-WebSocket-Key']}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", TRUE)) === $this->response['Sec-WebSocket-Accept']
			? $success->call($this) : ($failure ? $failure->call($this) : NULL);
		return $closure instanceof static ? $closure : $this;
	}
	function readfhi():array
	{
		if ($this->read($data, 2) === 2
			&& extract(unpack('C2byte', $data)) === 2) {
			do
			{
				$hi = [
					'fin' => $byte1 >> 7,
					'rsv' => $byte1 >> 4 & 0x07,
					'opcode' => $byte1 & 0x0f,
					'length' => $byte2 & 0x7f,
					'mask' => []
				];
				if ($hi['length'] > 125)
				{
					$length = $hi['length'] === 126 ? 2 : 8;
					if ($this->read($data, $length) !== $length)
					{
						break;
					}
					$hi['length'] = hexdec(bin2hex($data));
				}
				if ($byte2 >> 7)
				{
					if ($this->read($mask, 4) !== 4)
					{
						break;
					}
					$hi['mask'] = array_values(unpack('C4', $mask));
				}
				return $hi;
			} while (0);
		}
		$this->shutdown();
		return [];
	}
	function packfhi(int $length, int $opcode = 1, bool $fin = TRUE, int $rsv = 0, string $mask = ''):string
	{
		$format = 'CC';
		$values = [$fin << 7 | ($rsv & 0x07) << 4 | ($opcode & 0x0f)];
		if ($length < 126)
		{
			$values[] = $length;
		}
		else
		{
			if ($length < 65536)
			{
				$format .= 'n';
				$values[] = 126;
			}
			else
			{
				$format .= 'J';
				$values[] = 127;
			}
			$values[] = $length;
		}
		if (strlen($mask) > 3)
		{
			$format .= 'a4';
			$values[] = $mask;
			$values[1] |= 1 << 7;
		}
		return pack($format, ...$values);
	}
	function readframe(&$data = NULL, &$hi = NULL):bool
	{
		if ($hi = $this->readfhi())
		{
			$length = $this->read($data, $hi['length']);
			if ($mask = $hi['mask'])
			{
				for ($i = 0; $i < $length; ++$i)
				{
					$data[$i] = chr(ord($data[$i]) ^ $mask[$i % 4]);
				}
			}
			return $hi['length'] === $length;
		}
		return FALSE;
	}
	function sendframe(string $data, int $opcode = 1, bool $fin = TRUE, int $rsv = 0, string $masker = '', bool $masked = FALSE):bool
	{
		$length = strlen($data);
		if (strlen($masker) > 3 && $masked === FALSE)
		{
			$mask = array_map(ord(...), str_split($masker));
			for ($i = 0; $i < $length; ++$i)
			{
				$data[$i] = chr(ord($data[$i]) ^ $mask[$i % 4]);
			}
		}
		return $this->send($this->packfhi($length, $opcode, $fin, $rsv, $masker)) && $this->send($data);
	}
	/*
	Reference
	The specification requesting the opcode.
	WebSocket Opcode numbers are subject to the "Standards Action" IANA
	registration policy [RFC5226].
	IANA has added initial values to the registry as follows.
	|Opcode  | Meaning                             | Reference |
	+--------+-------------------------------------+-----------|
	| 0      | Continuation Frame                  | RFC 6455  |
	+--------+-------------------------------------+-----------|
	| 1      | Text Frame                          | RFC 6455  |
	+--------+-------------------------------------+-----------|
	| 2      | Binary Frame                        | RFC 6455  |
	+--------+-------------------------------------+-----------|
	| 8      | Connection Close Frame              | RFC 6455  |
	+--------+-------------------------------------+-----------|
	| 9      | Ping Frame                          | RFC 6455  |
	+--------+-------------------------------------+-----------|
	| 10     | Pong Frame                          | RFC 6455  |
	+--------+-------------------------------------+-----------|
	*/
}