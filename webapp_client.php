<?php
declare(strict_types=1);
class webapp_client implements Stringable, Countable
{
	public array $errors = [];
	protected readonly int $timeout, $flags;
	private $ssl, $filter, $buffer, $client;
	function __construct(public readonly string $socket, array $options = [])
	{
		$this->timeout = $options['timeout'] ?? 4;
		$this->flags = $options['flags'] ?? STREAM_CLIENT_CONNECT;
		$this->buffer = fopen('php://memory', 'r+');
		$this->ssl = $options['ssl'] ?? [
			'verify_peer' => FALSE,
			'verify_peer_name' => FALSE,
			'allow_self_signed' => TRUE];
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
		return stream_get_contents($this->buffer, ($length = self::count()) && rewind($this->buffer) ? $length : 0);
	}
	//缓冲区大小
	function count():int
	{
		return ftell($this->buffer);
	}
	//调试
	function debug(int $filter = STREAM_FILTER_WRITE/* STREAM_FILTER_ALL */):void
	{
		stream_filter_append($this->client, 'webapp.filter_debug', $filter);
	}
	//重连
	function reconnect(int $retry = 0):bool
	{
		do
		{
			//var_dump("reconnect");
			if (is_resource($client = @stream_socket_client($this->socket, $erron, $error,
				$this->timeout, $this->flags, stream_context_create(['ssl' => $this->ssl])))
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
		$length = self::count();
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
class webapp_client_smtp extends webapp_client implements Countable
{
	private readonly array $boundary;
	private array $dialog = [], $attachments, $related;
	private bool $accepted = FALSE;
	private string $charset = 'utf-8', $from = 'anonymous', $endchar = "\r\n.\r\n";
	function __construct(string $url, array $options = [])
	{
		$parse = parse_url($url);
		$this->boundary = ['mixed' => static::boundary(), 'alternative' => static::boundary(), 'related' => static::boundary()];
		parent::__construct("{$parse['scheme']}://{$parse['host']}:{$parse['port']}", $options);
		if (self::count() === 220)
		{
			$this->accepted = array_key_exists('user', $parse)
				? $this("EHLO {$parse['host']}")
					&& strpos(join($this->dialog), 'AUTH LOGIN')
					&& $this('AUTH LOGIN', 334)
					&& $this(base64_encode($this->from = $parse['user']), 334)
					&& $this(base64_encode($parse['pass'] ?? ''), 235)
				: $this("HELO {$parse['host']}");
		}
	}
	function __destruct()
	{
		$this('QUIT');
		parent::__destruct();
	}
	function __invoke(string $command, int $retval = 250):bool
	{
		$this->dialog[] = $command;
		$this->send("{$command}\r\n");
		return self::count() === $retval;
	}
	function __debugInfo():array
	{
		return $this->dialog;
	}
	function count():int
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
	
	// function header(string $subject, string $to, string $from = ''):bool
	// {
	// 	return $this->echo(join("\r\n", [
	// 		'Date: ' . date('r'),
	// 		sprintf('Subject: =?UTF-8?B?%s?=', base64_encode($subject)),
	// 		sprintf('To: =?UTF-8?B?%s?=', base64_encode($to)),
	// 		'MIME-Version: 1.0',
	// 		"Content-type: multipart/mixed; boundary={$this->boundary}\r\n"
	// 	]));
	// }
	// function content(string $data, string $type = 'text/plain; charset=utf-8'):bool
	// {
	// 	return $this->echo(join("\r\n", [
	// 		"--{$this->boundary}",
	// 		'Content-type: ' . $type,
	// 		'',
	// 		$data,
	// 		'']));
	// }
	// function attach(string $filename, string $name = NULL)
	// {
	// 	return $this->echo(join("\r\n", [
	// 		"--{$this->boundary}",
	// 		"Content-Disposition: attachment; filename=\"cool.txt\"",
	// 		'Content-Transfer-Encoding: base64',
	// 		'',
	// 		base64_encode(file_get_contents($filename)),
	// 		'']));
	// 	//JVBEDi0xLjMKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmlsdGVyIC9GbGF0
	// }
	function sendline(string ...$contents):bool
	{
		return $this->echo(join("\r\n", $contents));
	}
	function mail(string|array $to, string $from = NULL):bool
	{
		foreach ([$from ?? $this->from, ...is_string($to) ? [$to] : $to] as $i => $command)
		{
			if ($this($i === 0 ? "MAIL FROM: <{$command}>" : "RCPT TO: <{$command}>") === FALSE)
			{
				return FALSE;
			}
		}
		return TRUE;
	}
	/*
		multipart/mixed
			multipart/alternative
				text/plain
				multipart/related
					text/html
					image/gif
					image/gif
			some/thing (disposition: attachment)
			some/thing (disposition: attachment)
	*/

	function data(string|array $subject, string $content = ''):bool
	{
		$data = [];
		foreach (is_string($subject) ? ['subject' => $subject] : $subject as $key => $value)
		{
			$data[] = sprintf('%s: =?UTF-8?B?%s?=', ucfirst($key), base64_encode($value));
		}
		$data[] = 'MIME-Version: 1.0';
		$data[] = "Content-type: multipart/mixed; boundary={$this->boundary['mixed']}";
		$data[] = "\r\n--{$this->boundary['mixed']}";
		$data[] = "Content-Type: multipart/alternative; boundary={$this->boundary['alternative']}";
		$data[] = "\r\n--{$this->boundary['alternative']}";
		$data[] = "Content-type: text/plain; charset={$this->charset}";
		$data[] = "Content-Transfer-Encoding: base64\r\n";
		$data[] = base64_encode($content);
		return $this('DATA', 354) && $this->clear() && $this->sendline(...$data);
	}

	function related(string $content, array $images = [])
	{
		$this->sendline(
			"\r\n--{$this->boundary['alternative']}",
			"Content-Type: multipart/related; boundary={$this->boundary['related']}",
			"\r\n--{$this->boundary['related']}",
			"Content-type: text/html; charset={$this->charset}",
			"Content-Transfer-Encoding: base64\r\n",
			base64_encode($content));
		foreach ($images as $image)
		{

		}

		$this->echo("\r\n--{$this->boundary['related']}--\r\n");
		return TRUE;
	}



	// function content(string $data, ?string $type = NULL):bool
	// {
	// 	$type ??= 'text/plain; charset=utf-8';
	// 	return $this->sendline("\r\n--{$this->boundary['related']}",
	// 		"Content-type: {$type}",
	// 		"Content-Transfer-Encoding: base64\r\n",
	// 		base64_encode($data),
	// 		"\r\n--{$this->boundary['related']}--",
	// 		"\r\n--{$this->boundary['alternative']}--",
	// 		"\r\n--{$this->boundary['mixed']}--");
	// }
	// function attach(mixed $data, string $filename = 'unknown'):bool
	// {
	// 	return $this->sendline("\r\n--{$this->boundary['mixed']}", sprintf('Content-Disposition: attachment; filename="=?UTF-8?B?%s?="',
	// 		base64_encode($filename)), '') && match (TRUE)
	// 		{
	// 			is_bool($data) => $this->sendline($data ? 'True' : 'False'),
	// 			is_string($data) => $this->sendline("Content-Transfer-Encoding: base64\r\n", base64_encode($data)),
	// 			is_scalar($data) => $this->sendline($data),
	// 			is_resource($data) => $this->from($data),
	// 			default => FALSE
	// 		};
	// }
	function end(bool $debug = FALSE):bool
	{
		$end = $this->sendline(
			"\r\n--{$this->boundary['alternative']}--",
			"\r\n--{$this->boundary['mixed']}--\r\n.\r\n");
		if ($debug)
		{
			echo $this;
			return FALSE;
		}
		return $end && $this->push();
	}
	function sendmail(string|array $to, string|array $subject, string $content, array $images = []):bool
	{
		try
		{
			return $this->mail($to)
				&& $this->data($subject)
				&& $this->related($content, $images)
				&& $this->end();
		}
		catch (Error)
		{
			return FALSE;
		}
	}
}
class webapp_client_http extends webapp_client implements ArrayAccess
{
	public string $path;
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
			? (preg_match_all('/([^ =]+)=([^;]+);?/', $replace, $cookies, PREG_SET_ORDER) ? array_column($cookies, 2, 1) : [])
			: $replace as $name => $value) {
			$this->cookies[$name] = $value;
			
		}
		return $this;
	}
	private function form(iterable $data, string $contents, string $filename, string $field = '%s'):bool
	{
		foreach ($data as $name => $value)
		{
			switch (TRUE)
			{
				case $value instanceof webapp_request_uploadedfile:
					foreach ($value as $file)
					{
						if (($this->printf($filename, "{$name}[]", "{$file['name']}.{$file['type']}", $file['mime'])
							&& $this->from($file['file'])
							&& $this->echo("\r\n")) === FALSE) break 2;
					}
					continue 2;
				case is_iterable($value):
					if ($this->form($value, $contents, $filename, sprintf($field, $name) . '[%s]')) continue 2;
					break;
				case is_scalar($value) || is_null($value):
					if ($this->printf($contents, sprintf($field, $name))
						&& $this->echo((string)$value)
						&& $this->echo("\r\n")) continue 2;
					break;
				case is_resource($value):
					if ($this->printf($filename, sprintf($field, $name), basename(stream_get_meta_data($value)['uri']), 'application/octet-stream')
						&& $this->from($value)
						&& $this->echo("\r\n")) continue 2;
					break;
			}
			return FALSE;
		}
		return TRUE;
	}
	function request(string $method, string $path, $data = NULL, string $type = NULL):bool
	{
		$this->path = $path;
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
				'multipart/form-data' => $this->form($data,
					$contents = '--' . join("\r\n", [$boundary = static::boundary(),'Content-Disposition: form-data; name="%s"', "\r\n"]),
					substr($contents, 0, -4) . "; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n")
						&& $this->echo("--{$boundary}--", $type .= "; boundary={$boundary}"),
				'application/json' => $this->echo(json_encode($data, JSON_UNESCAPED_UNICODE)),
				'application/xml' => $this->echo(match (TRUE) {
						$data instanceof DOMDocument => $data->saveXML(),
						$data instanceof SimpleXMLElement => $data->asXML(),
						default => (string)$data}),
				default => is_resource($data) && $this->from($data)})
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
	function status(array &$response = NULL, bool $raw = FALSE):int
	{
		if ($raw)
		{
			$response[] = $this[0];
			foreach (array_slice($this->response, 1) as $name => $value)
			{
				$response[] = "{$name}: {$value}";
			}
		}
		else
		{
			$response = $this->response;
		}
		return $this->response ? intval(substr($this[0], 9)) : 0;
	}
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
			'text/html' => is_string($fix = preg_replace('/<meta\s+charset=([\'"])([^\1]+)\1[^>]*>/i', #<--fix import html charset not recognized
				'<meta http-equiv="Content-Type" content="text/html; charset=\2">',
				(string)$this, 1)) && class_exists('webapp_implementation', FALSE)
				? (($doc = new webapp_implementation)->loadHTML($fix) ? $doc->xml : $fix)
				: (($doc = new DOMDocument)->loadHTML($fix, LIBXML_NOWARNING | LIBXML_NOERROR) ? simplexml_import_dom($doc) : $fix),
			default => (string)$this
		};
	}
	function saveas(string $filename):bool
	{
		return (is_dir($dir = dirname($filename)) || mkdir($dir, recursive: TRUE)) && $this->to($filename);
	}
	function downm3u8(string $downdir):bool
	{
		if ($m3u8 = preg_match_all('/#[^#]+/', $this->content('text/plain'), $pattern) ? $pattern[0] : [])
		{
			$count = 0;
			$path = preg_match('/^(\/[^\/]+){2,}/', $this->path) ? dirname($this->path) : '';
			foreach ($m3u8 as &$value)
			{
				if (str_starts_with($value, '#EXTINF'))
				{
					if (preg_match('/([^\,]+\,\s*)([^\r\n]+)/', $value, $ts)
						&& (is_file($filename = "{$downdir}/" . ($name = sprintf('dx%06d', ++$count)))
							|| $this->goto(preg_match('/^https?:\/\//i', $ts[2]) ? $ts[2] : "{$path}/{$ts[2]}")->saveas($filename))) {
						$value = "{$ts[1]}{$name}\n";
						echo "{$ts[2]} => {$name}\n";
					}
					else
					{
						$value = '';
					}
				}
				else
				{
					if (str_starts_with($value, '#EXT-X-KEY')
						&& preg_match('/URI="([^"]+)/', $value, $key)
						&& $this->goto(preg_match('/^https?:\/\//i', $key[1]) ? $key[1] : "{$path}/{$key[1]}")->saveas("{$downdir}/keycode")) {
						$value = preg_replace('/URI="([^"]+)/', 'URI="keycode', $value);
					}
				}
			}
			return file_put_contents("{$downdir}/play.m3u8", join($m3u8)) !== FALSE;
		}
		return FALSE;
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

	static function chrome(string $url):static
	{
		return new static($url, [
			'autoretry' => 4,
			'autojump' => 4,
			'ssl' => [
				'verify_peer' => FALSE,
				'verify_peer_name' => FALSE,
				'allow_self_signed' => TRUE,
				'disable_compression' => TRUE,
				'ciphers' => 'AES256,AES',
				'security_level' => 3#cloudflare tls fingerprinting with need least level 2
			],
			'headers' => [
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
				'Accept-Encoding' => 'gzip,deflate',
				'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
				'Connection' => 'keep-alive',
				'Sec-Ch-Ua' => '"Not A(Brand";v="99", "Google Chrome";v="121", "Chromium";v="121"',
				'Sec-Ch-Ua-Mobile' => '?0',
				'Sec-Ch-Ua-Platform' => '"Windows"',
				'Sec-Fetch-Dest' => 'document',
				'Sec-Fetch-Mode' => 'navigate',
				'Sec-Fetch-Site' => 'none',
				'Sec-Fetch-User' => '?1',
				'Upgrade-Insecure-Requests' => '1',
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
			]
		]);
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
		])->request('GET', $this->path);
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