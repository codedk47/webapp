<?php
declare(strict_types=1);
#http://doc.redisfans.com/
#https://github.com/phpredis/phpredis?tab=readme-ov-file
class webapp_redis extends Redis
{
	public array $errors = [];
	function __construct(public readonly webapp $webapp, ...$open)
	{
		$this->pconnect(...$open ? $open : ['127.0.0.1', 6379]);
	}
	function dosevasive(int $count = 30, int $denytime = 600):bool
	{
		$this->expire($key = $this->webapp->iphex($this->webapp->request_ip(TRUE)),
			($deny = $this->incr($key) > $count) ? $denytime : 1);
		return $deny;
	}
	function uniqueip(int $expire = NULL):bool
	{
		return $this->sAdd('uniqueip', $this->webapp->iphex($this->webapp->request_ip(TRUE)))
			&& ($this->sCard('uniqueip') > 1 || $this->expireAt('uniqueip', $expire ?? mktime(23, 59, 59)));
	}
	function clear(string|webapp_redis_table $table = NULL):void
	{
		if ($table)
		{
			if (is_string($table))
			{
				foreach ($this->sMembers($table) as $key)
				{
					$this->del($key, ...$this->sMembers($key));
				}
				$this->del($table);
			}
			else
			{
				$table->flush();
			}
		}
		else
		{
			$this->flushDb();
		}
	}
	function table(string $tablename, string $primary, ...$commands):webapp_redis_table
	{
		return webapp_redis_table::from($this, $tablename, $primary, ...$commands);
	}
	function assoc(string $tablename, string $primary, string $field, ...$commands)
	{
		return $this->table($tablename, $primary, ...$commands)->column($field, $primary);
	}
}
abstract class webapp_redis_table implements ArrayAccess, IteratorAggregate, Countable, Stringable
{
	private bool $cache;
	private readonly array $args;
	public readonly webapp $webapp;
	public readonly webapp_redis $redis;
	public readonly webapp_redis_table $root;
	public readonly string $cond, $sort, $key, $value;
	protected string $tablename = '', $primary = '', $expire = '';
	abstract function format(array $data):array;
	function __construct(webapp_redis|webapp_redis_table $context, ...$commands)
	{
		$this->args = func_get_args();
		$this->webapp = $context->webapp;
		$cond = '';
		$sort = NULL;
		$save = TRUE;
		if ($command = $commands[0] ?? '')
		{
			if (is_int($pos = stripos($command, 'ORDER BY ')))
			{
				$sort = substr($command, $pos);
				$commands[0] = trim(substr($command, 0, $pos));
			}
			$cond = ($save = count($commands) === 1) ? current($commands) : $this->webapp->mysql->format(...$commands);
		}
		if ($context instanceof webapp_redis)
		{
			[$this->redis, $this->root, $this->cond, $this->sort] = [$context, $this, $cond ? "WHERE {$cond}" : '', $sort ?? ''];
			$this->cache = $this->redis->sIsMember($this->tablename, $this->key = "{$this->tablename}." . $this->webapp->hash($this->value = $sort . $command, TRUE));
		}
		else
		{
			[$this->redis, $this->root, $this->cond, $this->sort] = [$context->redis, $context->root, $cond
				? ($context->cond ? "{$context->cond} AND {$cond}" : "WHERE {$cond}") : $context->cond, $sort ?? $context->sort];
			if ($this->cache = $this->redis->sIsMember($this->tablename, $this->key = "{$this->tablename}." . $this->webapp->hash($this->value = $context->value . $sort . $cond, TRUE)))
			{
				$this->root->time() === $this->time() || $this->flush();
			}
			else
			{
				$save && $this->cache();
			}
		}
	}
	// function stacks(bool $detail = FALSE):array
	// {
	// 	$stacks = [];
	// 	foreach ($this->redis->sMembers($this->tablename) as $key)
	// 	{
	// 		$stacks[$key] = [];
	// 		foreach ($this->redis->sMembers($key) as $type)
	// 		{
	// 			$stacks[$key][$type] = match ($this->redis->type($type))
	// 			{
	// 				Redis::REDIS_STRING => $detail ? $this->redis->get($type) : 'String',
	// 				Redis::REDIS_SET => $detail ? $this->redis->sMembers($type) : 'Set',
	// 				Redis::REDIS_LIST => $detail ? $this->redis->lRange($type, 0, -1) : 'List',
	// 				Redis::REDIS_ZSET => $detail ? $this->redis->zRange($type, 0, -1) : 'SortedSet',
	// 				Redis::REDIS_HASH => $detail ? $this->redis->hGetAll($type, 0, -1) : 'Hash',
	// 				#Redis::REDIS_NOT_FOUND,
	// 				default => $detail ? NULL : 'Not Found'
	// 			};
	// 		}
	// 	}
	// 	return $stacks;
	// }
	function __debugInfo():array
	{
		$debugs = [];
		foreach ($this->redis->sMembers($this->tablename) as $key)
		{
			$debugs[$key] = $this->redis->sMembers($key);
		}
		return $debugs;
	}
	function __invoke(...$sort):webapp_mysql_table
	{
		$sort = current($sort) ? (count($sort) > 1 ? $this->webapp->mysql->format(...$sort) : current($sort)) : $this->sort;
		return $this->webapp->mysql->{$this->tablename}("{$this->cond} {$sort}");
	}
	function __toString():string
	{
		return "{$this->cond} {$this->sort}";
	}
	function offsetExists(mixed $primary):bool
	{
		return $this->redis->exists("{$this->tablename}:{$primary}") === 1;
	}
	function offsetGet(mixed $primary):mixed
	{
		return isset($this[$primary]) ? $this->redis->hGetAll("{$this->tablename}:{$primary}") : NULL;
	}
	function offsetSet(mixed $primary, mixed $value):void
	{
	}
	function offsetUnset(mixed $primary):void
	{
	}
	function count():int
	{
		if ($this->alloc($key, 'count'))
		{
			$this->redis->set($key, $count = $this()->count());
			return $count;
		}
		return $this->cache ? intval($this->redis->get($key)) : $this()->count();
	}
	function getIterator(?int $offset = NULL, ?int $length = NULL, bool $keyonly = FALSE):Traversable
	{
		if ($this->cacheable(list:$list))
		{
			$iter = $this->redis->lRange($list, ...match (TRUE)
			{
				$offset === NULL => [0, -1],
				$length === NULL => ($offset -= 1) < 0 ? [-1, 0] : [0, $offset],
				default => $offset < 0 || $length < 1 ? [-1, 0] : [$offset, $offset + $length - 1]
			});
			if ($keyonly) foreach ($iter as $key) yield $key;
			else foreach ($iter as $key) if (is_array($value = $this[$key])) yield $key => $value;
			return;
		}
		$iter = $this(...match (TRUE)
		{
			$offset === NULL => [],
			$length === NULL => ["{$this->sort} LIMIT ?i", max(0, $offset)],
			default => ["{$this->sort} LIMIT ?i,?i", max(0, $offset), max(0, $length)]
		});
		if ($keyonly) foreach ($iter->select($this->primary) as $data) yield $data[$this->primary];
		else foreach ($iter as $data) yield $data[$this->primary] => $this->format($data);
	}
	function eval(...$additional):webapp_mysql_table
	{
		return $this->webapp->mysql->{$this->tablename}(($this->cond ? "{$this->cond} AND " : 'WHERE ') . $this->webapp->mysql->format(...$additional));
	}
	// function evalkeys(...$additional):iterable
	// {
	// 	return $this->eval(...$additional)->select($this->primary)
	// }
	function time():int
	{
		return intval($this->redis->get("{$this->key}.time"));
	}
	function alloc(&$key, string $keyname, int $expire = NULL):bool
	{
		$key = "{$this->key}.{$keyname}";
		return $this->cache && $this->redis->sAdd($this->key, $key);
	}
	function flush():static
	{
		if ($this->cache)
		{
			$this->redis->del($this->key, ...$this->redis->sMembers($this->key));
			$this->redis->sRem($this->tablename, $this->key);
			return new static(...$this->args);
		}
		$this->root === $this && $this->redis->clear($this->tablename);
		return $this;
	}
	function cache():static
	{
		$this->cache = TRUE;
		if ($this->redis->sAdd($this->tablename, $this->key))
		{
			if ($this->root === $this)
			{
				$this->cacheable();
				$time = $this->webapp->time();
			}
			else
			{
				$time = $this->root->time();
			}
			$time
				&& $this->alloc($key, 'time')
				&& $this->redis->set($key, $time);
		}
		return $this;
	}
	function cacheable(&$set = NULL, &$list = NULL):bool
	{
		if ($this->alloc($set, 'set'))
		{
			$this->alloc($list, 'list');
			if ($this->root === $this) foreach ($this() as $data)
			{
				$this->redis->sAdd($set, $key = $data[$this->primary])
					&& $this->redis->hMSet($primary = "{$this->tablename}:{$key}", $this->format($data))
					&& $this->redis->rPush($list, $key)
					&& $this->expire
					&& $this->redis->expireAt($primary, (int)$data[$this->expire]);
			} else foreach ($this()->select($this->primary) as $data)
			{
				$this->redis->sAdd($set, $key = $data[$this->primary]) && $this->redis->rPush($list, $key);
			}
		}
		else
		{
			$list = substr($set, 0, -4) . '.list';
		}
		return $this->cache;
	}
	// function refresh():static
	// {
	// 	if ($this->cacheable(list:$list))
	// 	{
			
	// 		print_r(array_diff($this()->select($this->primary)->column($this->primary), $this->keys()));


	// 		foreach ($this()->select($this->primary) as $i => $data)
	// 		{
	// 			//var_dump($i);
	
	// 			if ($data[$this->primary] !== $this->redis->lIndex($list, $i))
	// 			{
	// 				//$this->flush();
	// 				var_dump($i, $data[$this->primary], $this->redis->lIndex($list, $i));
	// 				break;
	// 			}
				
	// 		}
	// 		if (array_diff($this()->select($this->primary)->column($this->primary), $this->keys()))
	// 		{
	// 			var_dump(1);
	// 			$this->flush();
	// 		}

	// 		foreach ($this() as $data)
	// 		{
				
	// 			if ($a = array_diff($this->format($data), $this[$data[$this->primary]] ?? []))
	// 			{
	// 				var_dump($a);
	// 				$this->flush();
	// 				break;
	// 			}
	// 		}
	// 	}
	// 	return $this;
	// }
	function column(string $field, string $index = NULL):array
	{
		if ($this->cache)
		{
			if ($this->alloc($key, "column.{$field}{$index}"))
			{
				$this->redis->hMSet($key, $data = array_column(iterator_to_array($this, FALSE), $field, $index));
				return $data;
			}
			return $this->redis->hGetAll($key);
		}
		return array_column(iterator_to_array($this, FALSE), $field, $index);
	}
	function keys(?int $offset = NULL, ?int $length = NULL):array
	{
		return iterator_to_array($this->getIterator($offset, $length, TRUE), FALSE);
	}
	function values(?int $offset = NULL, ?int $length = NULL):array
	{
		return iterator_to_array($this->getIterator($offset, $length), FALSE);
	}
	function weight(string $field = 'weight'):array
	{
		return $this->webapp->random_weights($this->values(), $field);
	}
	function paging(int $page, int $size = 21):iterable
	{
		return $this->getIterator(max(0, ($page - 1) * $size), $size);
	}
	function random(int $length):iterable
	{
		if ($this->cacheable($set, $list))
		{
			// foreach ($this->redis->sRandMember($set, $length) as $key)
			// {
			// 	if (is_array($value = $this[$key]))
			// 	{
			// 		yield $key => $value;
			// 	}
			// }
			// Better random number provider
			$count = $this->count();
			foreach (unpack('N*', $this->webapp->random($length * 4)) as $i)
			{
				if (is_array($value = $this[$key = $this->redis->lindex($list, $i % $count)]))
				{
					yield $key => $value;
				}
			}
			return;
		}
		foreach ($this('ORDER BY RAND() LIMIT ?i', $length) as $data)
		{
			yield $data[$this->primary] => $this->format($data);
		}
	}
	// function group(string $field)
	// {
	// }
	// function each(callable $detect)
	// {
	// }
	function show(int $length, int $expire = 600):iterable
	{
		if ($this->cache)
		{
			$this->alloc($key, 'show');
			$length = max(0, min($length, $count = $this->count()));
			if ($this->redis->setNx("{$key}.time", $expire))
			{
				$this->redis->expire("{$key}.time", $expire);
				$offset = $this->redis->incrBy($key, $length);
				if ($offset > $count)
				{
					$this->redis->set($key, $offset = $offset >= $count + $length ? $length : $count);
				}
			}
			else
			{
				$offset = max($this->redis->get($key), $length);
			}
			return $this->getIterator($offset - $length, $length);
		}
		return $this->getIterator($length);
	}
	function iter(string ...$keys):iterable
	{
		foreach ($keys as $key)
		{
			if (is_array($value = $this[$key]))
			{
				yield $key => $value;
			}
		}
	}
	function with(...$commands):static
	{
		return new static($this, ...$commands);
	}
	function unique(string $field, string $content = NULL):array|bool
	{
		if ($this->cache)
		{
			$this->alloc($key, "unique.{$field}") && $this->redis->sAdd($key, ...$this('GROUP BY ?a', $field)->select($field)->column($field));
			return $content === NULL ? $this->redis->sMembers($key) : $this->redis->sIsMember($key, $content);
		}
		return $content === NULL ? $this('GROUP BY ?a', $field)->select($field)->column($field) : $this->eval('?a=?s LIMIT 1', $field, $content)->fetch();
	}
	function extract(string $field)
	{
		foreach ($this->keys() as $key)
		{
		
			
			print_r($this->redis->hGet("{$this->tablename}:{$key}", $field));
		}
		//print_r( $this()->select($field)->column($field) );
	}




	function increment(string $primary, string $field, int $number = 1):bool
	{
		return is_int($this->redis->hIncrBy("{$this->tablename}:{$primary}", $field, $number))
			&& $this->eval('?a=?s LIMIT 1', $this->primary, $primary)->update('?a=?a+?i', $field, $field, $number) === 1;
	}
	static function from(webapp_redis|webapp_redis_table $context, string $table, string $primary, ...$commands)
	{
		return new class($context, $table, $primary, ...$commands) extends webapp_redis_table
		{
			function __construct(webapp_redis|webapp_redis_table $context,
				protected string $tablename,
				protected string $primary, ...$commands) {
				parent::__construct($context, ...$commands);
				$this->root === $this && $this->cache();
			}
			function format(array $data):array
			{
				return $data;
			}
		};
	}
}

/*
abstract class webapp_redis_table implements ArrayAccess, IteratorAggregate, Countable, Stringable
{
	public readonly ?webapp $webapp;
	public readonly ?webapp_redis_table $before;
	public readonly webapp_redis $redis;
	public readonly string $table;
	public readonly bool $writable;
	public readonly ?string $primary;
	private readonly string $key;
	abstract function format(array $data):array;



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
*/