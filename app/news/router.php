<?php
function router($request, $info)
{
	$url = 'https://' . match ($info[1])
	{
		'route.domain' => 'api.domain',
		default => 'api.domain'
	} . '/#' . substr($info[0], 1);
	@fwrite($request, packfhi(strlen($url)) . $url);
}
$server = stream_socket_server('tcp://0.0.0.0:80');
stream_set_blocking($server, FALSE);
$clients = [$server];
$buffers = [];
$websockets = [];
while (TRUE)
{
	$read = $clients;
	stream_select($read, $write, $except, NULL);
	//echo "============================\n";
	if (in_array($server, $read))
	{
		if ($client = @stream_socket_accept($server))
		{
			stream_set_timeout($client, 2);
			$id = get_resource_id($client);
			$clients[$id] = $client;
			$buffers[$id] = '';
			// $ip = stream_socket_get_name($client, TRUE);
			// echo "New Client connected from {$ip}\n";
		}
		unset($read[0]);
	}
	foreach ($read as $id => $request)
	{
		$content = stream_get_contents($request);
		if ($content === '')
		{
			stream_socket_shutdown($request, STREAM_SHUT_RDWR);
			unset($clients[$id], $buffers[$id], $websockets[$id]);
			continue;
		}
		$buffers[$id] .= $content;
		$content = $buffers[$id];
		if (isset($websockets[$id]))
		{
			$len = strlen($content);
			if ($len < 2)
			{
				continue;
			}
			$data = unpack('C2byte', $content);
			$hi = [
				'fin' => $data['byte1'] >> 7,
				'rsv' => $data['byte1'] >> 4 & 0x07,
				'opcode' => $data['byte1'] & 0x0f,
				'length' => $data['byte2'] & 0x7f,
				'mask' => []
			];
			if ($hi['length'] > 125)
			{
				$length = $hi['length'] === 126 ? 2 : 8;
				if ($len < 2 + $length)
				{
					continue;
				}
				$hi['length'] = hexdec(bin2hex(substr($content, 2, $length)));
			}
			else
			{
				$length = 0;
			}
			$length += 2;
			if ($data['byte2'] >> 7)
			{
				if ($len < 4 + $length)
				{
					continue;
				}
				$hi['mask'] = array_values(unpack('C4', substr($content, $length, 4)));
				$length += 4;
			}
			if ($len < $length + $hi['length'])
			{
				continue;
			}
			//print_r($hi);
			$buffers[$id] = substr($content, $length + $hi['length']);
			$content = substr($content, $length, $hi['length']);
			if ($mask = $hi['mask'])
			{
				for ($i = 0; $i < $hi['length']; ++$i)
				{
					$content[$i] = chr(ord($content[$i]) ^ $mask[$i % 4]);
				}
			}
			router($request, $websockets[$id]);
			continue;
		}
		if (strpos($content, "\r\n\r\n") === FALSE)
		{
			continue;
		}
		if (stripos($content, 'Connection: Upgrade')
			&& stripos($content, 'Upgrade: websocket')
			&& preg_match('/Sec-WebSocket-Key\:\s?([^\r\n]+)/i', $content, $key)) {
			if (@fwrite($request, join("\r\n", [
				'HTTP/1.1 101 Switching Protocols',
				'Upgrade: websocket',
				'Connection: Upgrade',
				'Sec-WebSocket-Version: 13',
				'Sec-WebSocket-Accept: ' . base64_encode(sha1("{$key[1]}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", TRUE)),
				"\r\n"
			])) !== FALSE) {
				$buffers[$id] = '';
				$websockets[$id] = [
					preg_match('/GET\s+([^\s]+)/', $content, $path) ? $path[1] : '/',
					preg_match('/Host:\s?([^\r\n]+)/i', $content, $host) ? $host[1] : 'unknown'
				];
				$ip = stream_socket_get_name($request, TRUE);
				echo "FROM {$ip} GET {$websockets[$id][0]}\n";
				router($request, $websockets[$id]);
			};
			continue;
		}
		$ip = stream_socket_get_name($request, TRUE);
		echo "FROM {$ip} ", preg_match('/GET\s+([^\s]+)/', $content, $path) ? $path[0] : 'GET /', "\n";
		@fwrite($request, join("\r\n", [
			'HTTP/1.1 404 Not Found',
			'Server: PHP',
			'Content-Length: 0',
			'Connection: close',
			"\r\n"
		]));
	}
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