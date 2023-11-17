<?php
declare(strict_types=1);
class webapp_redis extends Redis
{
	public array $errors = [];
	//public readonly webapp $webapp, 
	function __construct(string $host = '127.0.0.1', int $post = 6379)
	{
		$this->pconnect($host, $post);
	}

	// function table(string $name, string $primary = NULL):webapp_redis_table
	// {
	// 	return new webapp_redis_table($this, $name, $primary);
	// }
	// function key(string $name):webapp_redis_key
	// {
	// 	return new webapp_redis_key($this, $name);
	// }

}
//class webapp_redis_table implements IteratorAggregate, Countable, Stringable
// class webapp_redis_table
// {
// 	//public readonly webapp $webapp;
// 	public readonly string $primary;
// 	function __construct(protected readonly webapp_redis $redis, protected readonly string $name, ?string $primary = NULL)
// 	{
// 		$this->primary = 'hash';
// 		//$this->webapp = $redis->webapp;
// 	}
// 	function primary(string $value):array
// 	{
// 		//if ($this->redis->exists($key = "{$this->name}"));

// 		return [];
// 	}
// }
// class webapp_redis_key extends ArrayObject implements Stringable
// {
// 	function __construct(protected readonly webapp_redis $redis, protected readonly string $key)
// 	{

// 	}
// 	function __toString():string
// 	{
// 		return 'test';
// 	}
// 	function fetch()
// 	{

// 	}
// 	function key($name):static
// 	{
// 		return new static($this->$redis, "{$this->key}:{$name}");
// 	}


// }