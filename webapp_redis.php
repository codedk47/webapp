<?php
declare(strict_types=1);
class webapp_redis extends Redis
{
	public array $errors = [];
	function __construct(public readonly webapp $webapp)
	{
		$this->pconnect('127.0.0.1', $post = 6379);
	}
	function assoc(string $table, string $primary = 'key', string $field = 'value', ...$commands):array
	{
		if ($this->type($key = "{$table}#assoc") === self::REDIS_HASH)
		{
			return $this->hGetAll($key);
		}
		$this->hMSet($key, $data = $this->webapp->mysql->{$table}(...$commands)->column($field, $primary));
		return $data;
	}
	function clear(string $table):bool
	{
		return $this->del("{$table}#assoc") === 1;
	}


	function table(string $name, string|callable $context = NULL, ...$params):webapp_redis_table
	{

		return new webapp_redis_table($this, $name, $context, ...$params);
	}


	// function expire(string $key, int $value, bool $timestamp = FALSE)
	// {
	// 	$timestamp ? parent::expireAt() : parent::expire()
	// }

}
class webapp_redis_table implements ArrayAccess, IteratorAggregate, Countable, Stringable
{
	public readonly ?webapp $webapp;
	public readonly ?webapp_redis_table $before;
	public readonly webapp_redis $redis;
	public readonly string $table;
	public readonly bool $writable;
	public readonly ?string $primary;
	private readonly string $key;
	function __construct(webapp_redis|webapp_redis_table $context, string $key, NULL|string|Closure $content, ...$params)
	{
		$this->webapp = $context->webapp;
		[$this->before, $this->redis, $this->table, $this->writable] = $context instanceof webapp_redis
			? [NULL, $context, $key, $context->sAdd($key, $this->key = "{$key}#") === 1]
			: [$context, $context->redis, $context->table, $context->redis->sAdd($context->table, $this->key = "{$context->table}#{$key}#") === 1];
		$this->writable && $this->redis->sAdd($this->table, "{$this->key}list");
		if (is_object($content))
		{
			$this->primary = $content->call($this, ...$params);
		}
		else
		{
			$this->primary = $content;
			if ($this->writable && $this->primary)
			{
				foreach ($this() as $data)
				{
					$this[$data[$this->primary]] = $data;
				}
			}
		}
	}
	function __debugInfo():array
	{
		return $this->redis->sMembers($this->table);
	}
	function __invoke(...$commands):webapp_mysql_table
	{
		return $this->webapp->mysql->{$this->table}(...$commands);
	}
	function __toString():string
	{
		return $this->key;
	}
	function offsetExists(mixed $primary):bool
	{
		return $this->redis->exists("{$this->table}:{$primary}") === 1;
	}
	function offsetGet(mixed $primary):mixed
	{
		return isset($this[$primary]) ? $this->redis->hGetAll("{$this->table}:{$primary}") : NULL;
	}
	function offsetSet(mixed $primary, mixed $value):void
	{
		if ($this->redis->sAdd($this->key, $primary))
		{
			$this->redis->rPush("{$this->key}list", $primary);
		}
		if ($this->before === NULL)
		{
			$this->redis->hMSet("{$this->table}:{$primary}", $value);
		}
	}
	function offsetUnset(mixed $primary):void
	{
		if ($this->redis->sRem($this->key, $primary))
		{
			$this->redis->lRem("{$this->key}list", $primary, 0);
		}
		if ($this->before === NULL)
		{
			$this->redis->del("{$this->table}:{$primary}");
		}
	}
	function getIterator(int $start = 0, int $end = -1):Traversable
	{
		foreach ($this->keys($start, $end) as $key)
		{
			if (is_null($value = $this[$key]))
			{
				unset($this[$key]);
				continue;
			}
			yield $key => $value;
		}
	}
	function count():int
	{
		return $this->redis->sCard($this->key);
	}

	function random(int $count):iterable
	{
		foreach ($this->redis->sRandMember($this->key, $count) as $key)
		{
			if (is_null($value = $this[$key]))
			{
				unset($this[$key]);
				continue;
			}
			yield $key => $value;
		}
	}
	function iter(string ...$keys):iterable
	{
		foreach ($keys as $key)
		{
			if (is_null($value = $this[$key]))
			{
				unset($this[$key]);
				continue;
			}
			yield $key => $value;
		}
	}

	function all(bool $key = FALSE):array
	{
		return iterator_to_array($this, $key);
	}
	function weight(string $field = 'weight'):array
	{
		return $this->webapp->random_weights($this->all(), $field);
	}
	function column(string $field, string $index = NULL):array
	{
		return array_column($this->all(), $field, $index);
	}
	function keys(int $start = 0, int $end = -1):array
	{
		return $this->redis->lRange("{$this->key}list", $start, $end);
	}
	function values(int $start = 0, int $end = -1):array
	{
		return iterator_to_array($this->getIterator($start, $end), FALSE);
	}
	function paging(int $page, int $size = 21):iterable
	{
		return $this->getIterator($i = max(0, ($page - 1) * $size), $i + $size - 1);
	}


	function rank(string $mark, string $primary):bool
	{
		if ($this->redis->sIsMember($this->key, $primary))
		{
			$this->redis->sAdd($this->table, $key = "{$this->key}rank@{$mark}" . date('md'));
			$this->redis->zIncrBy($key, 1, $primary);
			return TRUE;
		}
		// $time = time();
		// for ($i = 0; $i < 47; $i++)
		// {
		// 	$this->redis->sAdd($this->table, $key = "{$this->key}rank@{$mark}" . date('md', $time - $i * 86400));
		// 	foreach ($this->random(random_int(5, 12)) as $primary => $content)
		// 	{
		// 		$this->redis->zIncrBy($key, random_int(1, 10), $primary);
		// 	}
		// }
		return FALSE;
	}
	function ranking(string $mark, int $ago, int $limit, bool $asc = FALSE):iterable
	{
		$keys = [];
		foreach ($this->stacks("{$this->key}rank@{$mark}") as $key)
		{
			$keys[$key] = intval(substr($key, -4));
		}
		arsort($keys);
		while (count($keys) > 30)
		{
			$this->redis->del($key = array_key_last($keys));
			$this->redis->sRem($this->table, $key);
			array_pop($keys);
		}
		$scores = [];
		[$sortredis, $sortarray] = $asc
			? [$this->redis->zRange(...), asort(...)]
			: [$this->redis->zRevRange(...), arsort(...)];
		foreach (array_slice($keys, 0, $ago) as $key => $value)
		{
			foreach ($sortredis($key, 0, $limit - 1, TRUE) as $key => $value)
			{
				$scores[$key] = ($scores[$key] ?? 0) + $value;
			}
		}
		$sortarray($scores);
		foreach (array_slice($scores, 0, $limit) as $key => $value)
		{
			if (is_array($value = $this[$key]))
			{
				yield $value;
			}
		}
	}

	function time(string $key = 'time'):int
	{
		return intval($this->redis->get($this->key . $key));
	}
	function updatable(string $key = 'time'):bool
	{
		if ($this->writable === FALSE)
		{
			$time = $this->before ? $this->before->time() : (func_num_args() ? $this->time() : $this->webapp->time());
			if ($this->time($key) !== $time || $this->redis->exists($this->key . $key) === 0)
			{
				$this->redis->set($key = $this->key . $key, $time);
				$this->redis->sRem($this->table, $key);
				return $this->redis->sAdd($this->table, $key) === 1;
			}
		}
		return $this->writable;
	}

	function alloc(&$key, string $keyname, int $expire = 0):bool
	{
		if ($this->redis->setNx($key = $this->key . $keyname, $expire))
		{
			$this->redis->sAdd($this->table, $key);
			return $this->redis->expire($key, $expire);
		}
		return FALSE;
	}
	function of(int $limit, int $expire = 0):iterable
	{
		$limit = max(0, min($limit, $count = $this->count()));
		if ($this->alloc($key, 'of', $expire))
		{
			$this->redis->sAdd($this->table, $key .= '@value');
			if ($this->updatable('of@time'))
			{
				$this->redis->set($key, $index = $limit);
			}
			else
			{
				$index = $this->redis->incr($key, $limit);
				if ($index > $count)
				{
					$this->redis->set($key, $index = $index >= $count + $limit ? $limit : $count);
				}
			}
		}
		else
		{
			$index = $this->redis->get("{$key}@value");
		}
		foreach ($this->keys($index - $limit, $index - 1) as $key)
		{
			if (is_array($value = $this[$key]))
			{
				yield $key => $value;
			}
		}
	}
	function by(string $field, string $content = NULL):static
	{
		return new static($this, "by@{$field}{$content}", function($field, $content)
		{
			//var_dump($this->writable);
			if ($this->updatable())
			{
				foreach ($this->before as $key => $value)
				{
					if ($value[$field] === $content)
					{
						$this[$key] = $value;
					}
				}
			}
			return $this->before->primary;
		}, $field, $content);
	}
	function in(string $value, string $field):static
	{
		return new static($this, "in@{$field}{$value}", function($field, $contains)
		{
			//var_dump($this->updatable());
			if ($this->updatable())
			{
				foreach ($this->before as $key => $value)
				{
					if (str_contains(",{$value[$field]},", $contains))
					{
						$this[$key] = $value;
					}
				}
			}
			return $this->before->primary;
		}, $field, ",{$value},");
	}
	function eval(...$commands):static
	{
		return new static($this, 'eval@' . $this->webapp->hash($command = $this->webapp->mysql->format(...$commands), TRUE), function($command)
		{
			$primary = $this->before?->primary;
			if ($primary && $this->updatable())
			{
				foreach ($this($command)->select($primary) as $data)
				{
					$this[$data[$primary]] = $data;
				}
			}
			return $primary;
		}, $command);
	}

	function stacks(string $startwith = ''):iterable
	{
		foreach ($this->__debugInfo() as $key)
		{
			if (str_starts_with($key, $startwith))
			{
				yield $key;
			}
		}
	}
	function clear():void
	{
		if ($this->before)
		{
			foreach ($this->stacks($this->key) as $key)
			{
				$this->redis->sRem($this->table, $key);
				$this->redis->del($key);
			}
		}
		else
		{
			foreach ($this->stacks() as $key)
			{
				$this->redis->del($key);
			}
			$this->redis->del($this->table);
		}
	}






	function increment(string $primary, string $field, int $number = 1):bool
	{
		return is_int($this->redis->hIncrBy("{$this->table}:{$primary}", $field, $number)) && ($this->primary === NULL
			|| $this('WHERE ?a=?s LIMIT 1', $this->primary, $primary)->update('?a=?a+?i', $field, $field, $number) === 1);
	}

	function unique(string $field, string $value = NULL):array|bool
	{
		if ($this->redis->sAdd($this->table, $key = "{$this->key}unique@{$field}"))
		{
			foreach ($this as $data)
			{
				$this->redis->sAdd($key, $data[$field]);
			}
		}
		return func_num_args() > 1 ? $this->redis->sIsMember($key, $value) : $this->redis->sMembers($key);
	}
	



	// function search(string $field, string $contains)
	// {
	// 	return new static($this, "search", function()
	// 	{

	// 	});
	// }



	


	function expire(int $seconds = 1)
	{
		var_dump($this->redis->ttl($this->key));
		//var_dump( $this->redis->expire($this->key, $seconds) );
		
	}









	// function fetch(string $primary, $value)
	// {

	// }










}