<?php
class webapp_nfs_client extends webapp_client_http
{
	private readonly Closure $request;
	private readonly string $username, $password, $bucket;
	function __construct(string $api, string ...$options)
	{
		parent::__construct(current([$url, $this->request] = match ($api)
		{
			#https://developers.cloudflare.com/r2/tutorials/postman/
			'cloudflare_r2' => [],
			#https://docs.aws.amazon.com/AmazonS3/latest/API/RESTAuthentication.html
			'amazon_s3' => ["https://{$options[2]}.s3.{$options[3]}.amazonaws.com",
			function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				$date = date(DATE_RFC2822);
				$signature = base64_encode(hash_hmac('sha1', "{$method}\n\n{$type}\n{$date}\nx-amz-acl:public-read\n/{$this->bucket}{$path}", $this->password, TRUE));
				$this->headers(['Authorization' => "AWS {$this->username}:{$signature}", 'Date' => $date, 'x-amz-acl' => 'public-read']);
				return parent::request($method, $path, $body, $type);
			}],
			#https://help.aliyun.com/zh/oss/developer-reference/overview-24
			'aliyun_oss' => ["https://{$options[2]}.{$options[3]}.aliyuncs.com",
			function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				$date = gmdate(DATE_RFC7231);
				$signature = base64_encode(hash_hmac('sha1', "{$method}\n\n{$type}\n{$date}\nx-oss-acl:public-read\n/{$this->bucket}{$path}", $this->password, TRUE));
				$this->headers(['Authorization' => "OSS {$this->username}:{$signature}", 'Date' => $date, 'x-oss-acl' => 'public-read']);
				return parent::request($method, $path, $body, $type);
			}],
			default => [$api, function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				static $entry = $this->path;
				$this->headers(['Authorization' => 'Bearer ' . webapp::signature($this->username, $this->password, webapp::hash($method . $path, TRUE) . $this->bucket)]);
				return parent::request($method, $entry . $path, $body, $type);
			}]
		}), ['autoretry' => 2, 'autojump' => 1]);
		[$this->username, $this->password, $this->bucket] = $options;
	}
	function request(string $method, string $path, $body = NULL, string $type = NULL):bool
	{
		return ($this->request)($method, $path, $body, $type);
	}
	function put(string $filename, $stream):bool
	{
		return is_resource($stream) && $this->request('PUT', $filename,
			$stream, 'application/octet-stream') && $this->status() === 200;
	}
	function delete(string $filename):bool
	{
		return $this->request('DELETE', $filename) && $this->status() === 204;
	}
	function upload_directory(string $path, string $from):bool
	{
		foreach (scandir($from) as $file)
		{
			if (is_file("{$from}/{$file}"))
			{
				if (is_resource($stream = fopen("{$from}/{$file}", 'r'))
					&& $this->put("{$path}/{$file}", $stream)
					&& fclose($stream)) {
					continue;
				}
				return FALSE;
			}
		}
		return TRUE;
	}
}
class webapp_nfs implements Countable, IteratorAggregate
{
	//private ?string $uid = NULL;
	public array $paging = [];
	private array $conditions = [];
	//private readonly webapp_mysql_table $table;
	//private readonly webapp_redis_table $cache;
	private readonly array $where;
	private readonly Closure $format;
	function __construct(public readonly webapp_ext_nfs_base $webapp, public readonly int $sort, Closure $format = NULL, ...$conditions)
	{
		$this->where = ['WHERE `sort`=?i' . ($conditions ? ' AND ' . array_shift($conditions) : ''), $this->sort, ...$conditions];
		$this->format = $format ?? fn($data) => $data;
	}
	function __invoke(...$conditions):static
	{
		$this->conditions = $conditions;
		return $this;
	}
	function count(string &$cond = NULL):int
	{
		return $this->table()->count($cond);
	}
	function getIterator():Traversable
	{
		foreach ($this->table() as $file)
		{
			yield ($this->format)($file);
		}
	}
	private function table():webapp_mysql_table
	{
		$cond = $this->where;
		if ($syntax = array_shift($this->conditions))
		{
			$cond[0] .= (preg_match('/^\s*(?:(?:group|order)\s+by|limit)\s+/i', $syntax) ? ' ' : ' AND ') . $syntax;
			array_push($cond, ...array_splice($this->conditions, 0));
		}
		return $this->webapp->mysql->{$this->webapp::tablename}(...$cond);
	}
	private function cache():webapp_mysql_table{}
	private function primary(string $hash):webapp_mysql_table
	{
		return $this('`hash`=?s LIMIT 1', $hash)->table();
	}
	function search(string $syntax, ...$values):static
	{
		return $this(preg_replace('/\$\.(\w+)/', 'extdata->"$.$1"', $syntax), ...$values);
	}
	function remove(string ...$fields):bool
	{
		return $this->table()->update('`extdata`=JSON_REMOVE(`extdata`,??)',
			join(',', array_map(fn($field) => "'$.{$field}'", $fields))) === $this->count();
	}
	function append(string $field, string $value = 'NULL'):bool
	{
		return $this->table()->update("`extdata`=JSON_SET(`extdata`, '$.{$field}', {$value})") === $this->count();
	}
	function paging(int $index, int $rows = 10):static
	{
		$conditions = $this->conditions;
		$this->paging['count'] = $this->count($this->paging['cond']);
		$this->paging['max'] = ceil($this->paging['count'] / $rows = abs($rows));
		$this->paging['index'] = max(1, $index);
		$this->paging['skip'] = ($this->paging['index'] - 1) * $rows;
		$this->paging['rows'] = $rows;
		$conditions[0] = (isset($conditions[0]) ? "{$conditions[0]} " : '') . "LIMIT {$this->paging['skip']},{$rows}";
		return $this(...$conditions);
	}
	function random(int $rows = 1):iterable
	{
		foreach ($this->table()->random($rows) as $file)
		{
			yield ($this->format)($file);
		}
	}
	function filename(string $hash, string $suffix = NULL):string
	{
		return sprintf('/%d/%04X/%s%s', $this->sort, $this->webapp->hashtime33($hash) % 0xffff, $hash, $suffix);
	}
	function create(array $data, int $type = 0):?string
	{
		return $this->webapp->mysql->{$this->webapp::tablename}->insert([
			'hash' => $hash = array_key_exists('hash', $data)
				&& $this->webapp->is_long_hash($data['hash'])
					? $data['hash'] : $this->webapp->random_hash(FALSE),
			'sort' => $this->sort,
			'type' => $type,#Type 0 and 1 is NFS reserved use
			't0' => $t0 = $this->webapp->time(),
			't1' => $t0,
			'size' => $data['size'] ?? 0,
			'views' => $data['views'] ?? 0,
			'likes' => $data['likes'] ?? 0,
			'shares' => $data['shares'] ?? 0,
			'name' => $data['name'] ?? '',
			'key' => $data['key'] ?? NULL,
			'node' => $data['node'] ?? NULL,
			'extdata' => isset($data['extdata']) && is_array($data['extdata']) ? json_encode($data['extdata'],
				JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE) : $data['extdata'] ?? NULL]) ? $hash : NULL;
	}
	function delete(string $hash):bool
	{
		return $this->primary($hash)->delete() === 1;
	}
	function update(string $hash, array $data = []):bool
	{
		$syntax = ['t1' => $this->webapp->time()];
		foreach (['size', 'views', 'likes', 'shares', 'name', 'node', 'key'] as $field)
		{
			if (array_key_exists($field, $data))
			{
				$syntax[$field] = $data[$field];
			}
		}
		if (array_key_exists('extdata', $data))
		{
			$syntax = $this->webapp->mysql->format('?v', $syntax);
			$syntax .= ',`extdata`=';
			if (is_array($data['extdata']))
			{
				$syntax = [$syntax, 'JSON_REPLACE(`extdata`'];
				$values = [];
				foreach ($data['extdata'] as $key => $value)
				{
					[$type, $values[]] = match (get_debug_type($value))
					{
						'null' => ['??', 'NULL'],
						'bool' => ['??', $value ? 'true' : 'false'],
						'int', 'float' => ['??', $value],
						default => ['?s', $value]
					};
					$syntax[] = ",'$.{$key}',{$type}";
				}
				$syntax[] = ')';
				return $this->primary($hash)->update(join($syntax), ...$values) === 1;
			}
			$syntax .= $data['extdata'];
		}
		return $this->primary($hash)->update($syntax) === 1;
	}
	function fetch(string $hash, ?array &$data = NULL):bool
	{
		if ($this->primary($hash)->fetch($rawdata))
		{
			$data = ($this->format)($rawdata);
			return TRUE;
		}
		return FALSE;
	}
	function rename(string $hash, string $newname):bool
	{
		return $this->primary($hash)->update(['t1' => $this->webapp->time(), 'name' => $newname]) === 1;
	}
	function create_tree(string $name, ?array $extdata = NULL):?string
	{
		return $this->create(['name' => $name, 'extdata' => $extdata]);
	}
	function delete_file(string $hash):bool
	{
		return $this->webapp->mysql->sync(fn() => $this->delete($hash) && $this->webapp->client->delete($this->filename($hash)));
	}
	function create_uploadedfile(string $name, array $data = [], bool $mask = FALSE):?string
	{
		$key = $mask ? $this->webapp->random(8) : NULL;
		$uploadedfile = $this->webapp->request_uploadedfile($name);
		return $this->webapp->mysql->sync(fn(&$hash) => $uploadedfile->count()
			&& is_string($hash = $this->create(['key' => $key ? bin2hex($key) : $key] + $data + $uploadedfile(), 1))
			&& $this->webapp->client->put($this->filename($hash), $uploadedfile->open(0, $mask, $key)), $hash) ? $hash : NULL;
	}
	function update_uploadedfile(string $hash, array $data = [], string $name = NULL, bool $mask = FALSE):bool
	{
		$key = $mask ? $this->webapp->random(8) : NULL;
		return $name && count($uploadedfile = $this->webapp->request_uploadedfile($name))
			? $this->webapp->mysql->sync(fn() => $this->update($hash, ['key' => $key ? bin2hex($key) : $key] + $data + $uploadedfile[0])
				&& $this->webapp->client->put($this->filename($hash), $uploadedfile->open(0, $mask, $key)))
			: $this->update($hash, $data);
	}
	function upload_directory(string $hash, string $from):bool
	{
		return $this->webapp->client->upload_directory($this->filename($hash), $from);
		//return $this->fetch($hash) && $this->webapp->client->upload_directory($this->filename($hash), $from);
	}

	function views(string $hash, int $incr = 1):bool
	{
		return $this->primary($hash)->update('`views`=`views`+?i', $incr) === 1;
	}
	function likes(string $hash, int $incr = 1):bool
	{
		return $this->primary($hash)->update('`likes`=`likes`+?i', $incr) === 1;
	}
	function shares(string $hash, int $incr = 1):bool
	{
		return $this->primary($hash)->update('`shares`=`shares`+?i', $incr) === 1;
	}
}
class webapp_ext_nfs_base extends webapp
{
	const tablename = 'nfs';
	private array $nfs = [];
	public string $origin = 'http://localhost';
	static function createtable(webapp_mysql $mysql):bool
	{
		return $mysql->real_query(<<<'SQL'
		CREATE TABLE ?a (
			`hash` char(12) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
			`sort` tinyint unsigned NOT NULL,
			`type` tinyint unsigned NOT NULL COMMENT '0:tree,1:file,2:mixed',
			`t0` int unsigned NOT NULL COMMENT 'insert time',
			`t1` int unsigned NOT NULL COMMENT 'update time',
			`size` bigint unsigned NOT NULL,
			`views` bigint unsigned NOT NULL,
			`likes` bigint unsigned NOT NULL,
			`shares` bigint unsigned NOT NULL,
			`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
			`node` char(12) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
			`key` binary(16) DEFAULT NULL COMMENT 'masker',
			`extdata` json DEFAULT NULL,
			PRIMARY KEY (`hash`),
			KEY `sort` (`sort`),
			KEY `type` (`type`),
			KEY `node` (`node`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
		SQL, static::tablename);
	}
	function client():webapp_nfs_client
	{
		// return new webapp_nfs_client('amazon_s3', 'AccessKeyID', 'AccessKeySecret', 'BucketName', 'region');
		// return new webapp_nfs_client('aliyun_oss', 'AccessKeyID', 'AccessKeySecret', 'BucketName', 'region');
		return new webapp_nfs_client('http://localhost/?', $this['admin_username'], $this['admin_password'], 'D:/');
	}
	function nfs(int $sort = 0, Closure $format = NULL, ...$conditions):webapp_nfs
	{
		return new webapp_nfs($this, $sort, $format, ...$conditions);
	}
	function src(array $file, string $name = NULL):string
	{
		return sprintf('%s/%d/%04X/%s%s?%X#%s',
			$this->origin, $file['sort'],
			$this->hashtime33($file['hash']) % 0xffff,
			$file['hash'], $name, $file['t1'], $file['key']);
	}

	// function tmpdir():?string
	// {
	// 	$tmpdir = sprintf('%s/%s', sys_get_temp_dir(), static::random_hash(FALSE));
	// 	return 
	// 	if (mkdir())
		
	// 	var_dump( $tmpdir );
	// }
	// function put(string $filename, string $source):bool
	// {
	// 	return $this->access->put($filename, $source);
	// }
	// function delete(string $filename):bool
	// {
	// 	return $this->access->delete($filename);
	// }




	
	// function get_sort(int $sort = 0, int $page = 1, int $rows = 21, int $t = 0)
	// {
	// 	$this->echo_xml();
	// 	foreach ($this->mysql->{static::tablename}('WHERE `sort`=?i AND `t1`>?i', $sort, $t)->paging($page, $rows) as $file)
	// 	{
	// 		$this->echo->xml->append('file', [
	// 			'hash' => $file['hash'],
	// 			'type' => $file['type'],
	// 			't0' => $file['t0'],
	// 			't1' => $file['t1'],
	// 			'size' => $file['size'],
	// 			'views' => $file['views'],
	// 			'likes' => $file['likes'],
	// 			'shares' => $file['shares'],
	// 			'node' => $file['node'],
	// 			'key' => $file['key'],
	// 			'name' => $file['name']
	// 		])->cdata((string)$file['extdata']);
	// 	}
	// 	$this->echo->xml->setattr($this->mysql->{static::tablename}->paging);
	// }
}