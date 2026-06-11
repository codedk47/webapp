<?php
class webapp_simple_server_buffer implements Stringable
{
	function __construct(private $socket, private readonly string $name)
	{

	}
	function __toString():string
	{
		return $this->name;
	}
	// function __invoke()
	// {

	// }
	function peek(&$buffer, int $length = 1):bool
	{
		return is_string($buffer = stream_socket_recvfrom($this->socket, $length, STREAM_PEEK));
	}
	function readline(&$output, int $length = 65535, string $ending = "\n", string $trim = "\r"):bool
	{
		if (is_string($output = @stream_get_line($this->socket, $length, $ending)))
		{
			$output = rtrim($output, $trim);
			return TRUE;
		}
		return FALSE;
	}
	function readframe(&$data = NULL):bool
	{

		var_dump( stream_get_contents($this->socket) );

		return TRUE;
	}

}
class webapp_simple_server_buffer_http extends webapp_simple_server_buffer
{
	private array $headers;
	function request()
	{

	}
}

abstract class webapp_simple_server implements Stringable, Countable
{
	private $socket;
	private array $clients = [], $writeable = [], $except = [];

	function __construct(string $bind, private readonly string $buffer = 'webapp_simple_server_buffer')
	{
		if ($this->socket = stream_socket_server($bind))
		{
			stream_set_blocking($this->socket, FALSE);
		}
	}
	function __destruct()
	{
		while ($this->socket)
		{
			$readables = [$this->socket, ...$this->clients];
			if (stream_select($readables, $this->writeable, $this->except, 1000000) < 0)
			{
				continue;
			}
			foreach ($readables as $socket)
			{
				if ($socket === $this->socket)
				{
					if ($socket = stream_socket_accept($this->socket, 1, $peer_name))
					{
						$socket = $this->clients[$peer_name] = new $this->buffer($socket, $peer_name);
					}
					else
					{
						continue;
					}
				}
				if ($socket->peek($buffer) && $this->receive($socket))
				{
					continue;
				}
				VAR_DUMP('CLOSE');
				unset($this->clients[(string)$socket]);
				
				// if ($this->receive($socket) === FALSE)
				// {
				// 	//unset($this->clients[(string)$socket]);
				// }
			}
		}
	}
	function __toString():string
	{
		return 'asd';
	}
	function count():int
	{
		return count($this->clients);
	}
	function receive(webapp_simple_server_buffer $buffer)
	{
		// var_dump((string)$buffer);
		// print_r($buffer);

		$buffer->readframe($data);


		return TRUE;
	}
}
new class('tcp://127.0.0.1:8014', 'webapp_simple_server_buffer_http') extends webapp_simple_server
{


};