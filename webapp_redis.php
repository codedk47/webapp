<?php
declare(strict_types=1);
class webapp_redis extends Redis
{
	public array $errors = [];
	function __construct(string $host = '127.0.0.1', int $post = 6379)
	{
		$this->pconnect($host, $post);
	}

}