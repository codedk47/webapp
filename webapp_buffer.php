<?php
declare(strict_types=1);
class webapp_buffer implements Stringable
{
	public $context;
	public readonly array $struct;
	public readonly mixed $buffer;
	public readonly mixed $stream;
	public array $sockets = [], $buffers = [];
	public ?Closure $readable, $writable;
	function __construct(array $struct = [], $stream = NULL)
	{
		$this->buffer = fopen('php://memory', 'r+');
		
		if ($this->context)
		{
			$this->struct = stream_context_get_options($this->context)['webapp'];
			$this->stream = $this->struct['stream'] ?? NULL;
			print_r(stream_context_get_options($this->stream));
		}
		else
		{
			$this->struct = $struct;
			$this->stream = $stream;
		}
	}
	function __invoke():bool
	{
		return TRUE;
	}
	function onCreate():bool
	{
		return is_resource($this->buffer ??= fopen('php://memory', 'r+'));
	}
	function onClose():void
	{
		fclose($this->buffer);
		//$this->buffer = NULL;
	}
	// function filter($in, $out, &$consumed, bool $closing):int
	// {
	// 	while ($bucket = stream_bucket_make_writeable($in))
	// 	{
	// 		$consumed += $bucket->datalen;
	// 		stream_bucket_append($out, $bucket);
	// 	}
	// 	return PSFS_PASS_ON;
	// }
	function append()
	{
		return fwrite($this->buffer, stream_get_contents($this->stream)) !== FALSE && $this();
	}
	function dir_closedir():bool{return TRUE;}
	function dir_opendir(string $path, int $options): bool{return TRUE;}
	function dir_readdir(): string{return 'TRUE';}
	function dir_rewinddir(): bool{return TRUE;}
	function mkdir(string $path, int $mode, int $options): bool{return TRUE;}
	function rename(string $path_from, string $path_to): bool{return TRUE;}
	function rmdir(string $path, int $options): bool{return TRUE;}
	function stream_cast(int $cast_as)
	{
		return $cast_as === STREAM_CAST_FOR_SELECT ? $this->stream : $this->buffer;
	}
	function stream_close(): void{}
	function stream_eof(): bool{return TRUE;}
	function stream_flush():bool
	{
		print_r(stream_context_get_options($this->context));
		return $this->pull();
	}
	function stream_lock(int $operation): bool{return TRUE;}
	function stream_metadata(string $path, int $option, mixed $value): 	bool{return TRUE;}
	function stream_open(string $path, string $mode, int $options, ?string &$opened_path):bool
	{
		
		return TRUE;
	}
	function stream_read(int $count): string|false{return TRUE;}
	function stream_seek(int $offset, int $whence = SEEK_SET): bool{return TRUE;}
	function stream_set_option(int $option, int $arg1, int $arg2): bool{return TRUE;}
	function stream_stat(): array|false{return TRUE;}
	function stream_tell(): int{return 1;}
	function stream_truncate(int $new_size): bool{return TRUE;}
	function stream_write(string $data):int
	{
		//fwrite($this->buffer, $data)
		return 1;
	}
	function unlink(string $path): bool{return TRUE;}
	function url_stat(string $path, int $flags): array|false{return TRUE;}
	function __destruct()
	{
		fclose($this->buffer);
		
	}



	private int $length = 0, $offset = 0;
	function __toString():string
	{
		return is_string($data = stream_get_contents($this->buffer, $this->length, 0))
			&& strlen($data) === $this->length ? $data : '';
	}
	function count():int
	{
		return $this->length;
	}
	function rewind():bool
	{
		return rewind($this->buffer);
	}
	function write(string $data):bool
	{
		$length = strlen($data);
		if ($result = fwrite($this->buffer, $data) === $length)
		{
			$this->length += $length;
		}
		return $result;
	}
	function send(string $data):bool
	{
		return @fwrite($this->stream, $data) === strlen($data);
	}
	function push():bool
	{
		return rewind($this->buffer)
			&& stream_copy_to_stream($this->buffer, $this->stream, $this->length) === $this->length;
	}
	function pull():bool
	{
		$length = stream_copy_to_stream($this->stream, $this->buffer);
		$this->length += $length;
		return boolval($length);
	}
	function peek(&$data, int $length, int $offset = 0):bool
	{
		$result = is_string($data = stream_get_contents($this->buffer, $length, $offset)) && strlen($data) === $length;
		return fseek($this->buffer, $this->length, SEEK_SET) === 0 && $result;
	}
	function pos(string $needle):int
	{
		for ($length = strlen($needle), $offset = 0; $offset < $this->length; ++$offset)
		{
			if (stream_get_contents($this->buffer, $length, $offset) === $needle)
			{
				fseek($this->buffer, $this->length, SEEK_SET);
				return $offset + $length;
			}
		}
		return -1;
	}

	function clear():bool
	{
		$this->length = 0;
		return rewind($this->buffer);
	}

	function cut(int $length):bool
	{
		return $length === $this->length
			? $this->clear()
			: $length > 0
				&& $length < $this->length
				&& is_string($pending = stream_get_contents($this->buffer, NULL, $length))
				&& $this->clear()
				&& strlen($pending) === $this->length - $length
				&& $this->write($pending);
	}






	static function open($stream, array $context = NULL)
	{
		if (in_array($scheme = strtr(static::class, '_', '.'), stream_get_wrappers(), TRUE) === FALSE)
		{
			stream_wrapper_register($scheme, static::class);
		}

		//stream_context_set_option('webapp')
		return fopen("{$scheme}://", 'r+', FALSE, stream_context_create($context + [
			'webapp' => ['stream' => $stream]
		]));

	// 	// PRINT_R( stream_get_meta_data($a) );
	// 	// print_r( stream_context_get_params($a));
	// 	// PRINT_R( stream_context_get_options($a) );

	}
	
	static function wrapper($stream):static
	{
		return stream_get_meta_data($stream)['wrapper_data'];
	}


	function add($stream)
	{

		$buffer = new static($this->struct, $stream);
		$buffer->context = $this->context;
		$id = get_resource_id($stream);
		$this->sockets[$id] = $stream;
		$this->buffers[$id] = $buffer;
		$buffer->readable = $this->struct['webapp']['readable'] ?? NULL;
		return $buffer;

		// $this->context
		// $this->sockets[get_resource_id($stream)] = static::open($stream);
	}
	function del($id)
	{
		unset($this->sockets[$id], $this->buffers[$id]);
	}
	function get()
	{
		
	}


	function readable():bool
	{
		return $this->pull();
	}
	function loop():void
	{
		do
		{
			$read = $this->sockets;
			if (stream_select($read, $write, $except, NULL) === FALSE)
			{
				continue;
			}
			if (isset($read[0]))
			{
				if ($stream = @stream_socket_accept($read[0]))
				{
					$this->add($stream);
					
				}
				unset($read[0]);
			}
			foreach ($read as $id => $stream)
			{
				// $this->buffers[$id]->readable()
				// $buffer = $this->buffers[$id];
				if ($this->buffers[$id]->readable())
				{
					continue;
				}
				stream_socket_shutdown($stream, STREAM_SHUT_RDWR);
				unset($this->sockets[$id], $this->buffers[$id]);
			}
			
		} while (TRUE);
	}
	static function server(string $socket, array $contexts = [])
	{
		$context = stream_context_create($contexts);
		$buffer = new static($contexts, stream_socket_server($socket, context: $context));
		stream_set_blocking($buffer->stream, FALSE);
		$buffer->context = $context;
		$buffer->sockets[] = $buffer->stream;
		$buffer->buffers[] = $buffer;
		$buffer->loop();
	}
	static function client(string $socket, array $contexts = [])
	{
	}
}


class webapp_buffer_http extends webapp_buffer
{
	//const server_name = static::class;
	private int $offset = -1;
	private array $requests = [
		'method' => 'GET',
		'path' => '/',
		'version' => 'HTTP/1.1',
		'headers' => []
	];
	function request_method():string
	{
		return $this->requests['method'];
	}
	function request_path():string
	{
		return $this->requests['path'];
	}
	function request_header(string $name):?string
	{
		return $this->requests['headers'][strtoupper($name)] ?? NULL;
	}
	function request_content_length():int
	{
		return intval($this->request_header('Content-Length'));
	}
	function readable():bool
	{
		do if ($this->pull())
		{
			if ($this->offset === -1)
			{
				$this->offset = $this->pos("\r\n\r\n");
				if ($this->offset !== -1)
				{
					if ($this->peek($header, $this->offset) === FALSE)
					{
						break;
					}
					$pos = strpos($header, "\r\n");
					if (count($request = explode(' ', substr($header, 0, $pos), 3)) !== 3)
					{
						break;
					}
					[$this->requests['method'], $this->requests['path'], $this->requests['version'], $this->requests['headers']] = [...$request,
						preg_match_all('/([\w\-]+)\:\s*([^\r\n]+)/', substr($header, $pos), $matches, PREG_PATTERN_ORDER) === FALSE
							? [] : array_combine(array_map(strtoupper(...), $matches[1]), $matches[2])
					];
					if ($this->request_content_length())
					{
						//暂不处理提交数据
						break;
					}
				}
			}
			return $this->offset !== -1
				&& $this->count() >= ($length = $this->offset + $this->request_content_length())
				? $this->readrequest() && $this->cut($length) && ($this->offset = -1) : TRUE;
		} while (0);
		return FALSE;
	}
	function readrequest():bool
	{
		return $this->readable ? $this->readable->call($this) : $this->send(join("\r\n", [
			'HTTP/1.1 200 OK',
			'Content-Type: text/plain; charset=utf-8',
			'Content-Length: ' . strlen($data = 'simple webapp server'),
			'Connection: close',
			"\r\n{$data}"]));
	}
}
class webapp_buffer_websocket extends webapp_buffer_http
{
	private bool $shakehand = FALSE;
	private int $offset = 0;
	private array $frame = [];
	function mask(string &$data, array $key):void
	{
		for ($length = strlen($data), $i = 0; $i < $length; ++$i)
		{
			$data[$i] = chr(ord($data[$i]) ^ $key[$i % 4]);
		}
	}
	function pack(int $length, int $opcode = 1, bool $fin = TRUE, int $rsv = 0, string $mask = ''):string
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
	function readable():bool
	{
		if ($this->shakehand === FALSE)
		{
			return parent::readable();
		}
		if ($this->pull() === FALSE)
		{
			return FALSE;
		}
		do
		{
			$offset = 0;
			if ($this->peek($data, 2, $offset) === FALSE || extract(unpack('C2byte', $data)) !== 2)
			{
				break;
			}
			$offset += 2;
			$frame = [
				'fin' => $byte1 >> 7,
				'rsv' => $byte1 >> 4 & 0x07,
				'opcode' => $byte1 & 0x0f,
				'length' => $byte2 & 0x7f,
				'mask' => []
			];
			if ($frame['length'] > 125)
			{
				$length = $frame['length'] === 126 ? 2 : 8;
				if ($this->peek($data, $length, $offset) === FALSE)
				{
					break;
				}
				$offset += $length;
				$frame['length'] = hexdec(bin2hex($data));
			}
			if ($byte2 >> 7)
			{
				if ($this->peek($mask, 4, $offset) === FALSE)
				{
					break;
				}
				$offset += 4;
				$frame['mask'] = array_values(unpack('C4', $mask));
			}
			$length = $offset + $frame['length'];

			
			if ($this->count() >= $length)
			{
				$this->offset = $offset;
				$this->frame = $frame;
				$a = $this->readframe();
				$this->offset = 0;
				return $a && $this->cut($length);
			}

			//print_r($this->frame);

		} while (0);
		return TRUE;
	}
	function content():string
	{
		$this->peek($data, $this->frame['length'], $this->offset);
		$this->frame['mask'] && $this->mask($data, $this->frame['mask']);
		return $data;
	}
	function readrequest():bool
	{
		if ($this->request_header('Upgrade') === 'websocket'
			&& $this->request_header('Connection') === 'Upgrade'
			&& $this->request_header('Sec-WebSocket-Version') === '13'
			&& is_string($key = $this->request_header('Sec-WebSocket-Key'))) {
			$this->shakehand = $this->send(join("\r\n", [
				'HTTP/1.1 101 Switching Protocols',
				'Upgrade: websocket',
				'Connection: Upgrade',
				'Sec-WebSocket-Version: 13',
				'Sec-WebSocket-Accept: ' . base64_encode(sha1("{$key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", TRUE)),
				"\r\n"]));
			return $this->shakehand && (isset($this->struct['webapp']['shakehand'])
				? $this->struct['webapp']['shakehand']->call($this) : TRUE);
		}
		return $this->send(join("\r\n", [
			'HTTP/1.1 404 Not Found',
			'Content-Length: 0',
			'Connection: close',
			"\r\n"]));
	}
	function sendframe(string $data, int $opcode = 1, bool $fin = TRUE, int $rsv = 0, string $mask = ''):bool
	{
		$length = strlen($data);
		strlen($mask) > 3 && $this->mask($data, array_map(ord(...), str_split($mask)));
		return $this->send($this->pack($length, $opcode, $fin, $rsv, $mask))
			&& $this->send($data);
	}
	function readframe():bool
	{
		return $this->readable ? $this->readable->call($this) : $this->$this->sendframe($this->content());
	}
}

#passive
#actived
