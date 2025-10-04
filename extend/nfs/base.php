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
			'cloudflare_r2' => ["https://{$options[2]}.{$options[3]}.r2.cloudflarestorage.com",
			function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				[$filename, $query] = ($offset = strpos($path, '?')) === FALSE
					? [$path, '']
					: [substr($path, 0, $offset), substr($path, $offset)];
				if (is_resource($body))
				{
					//没办法为了配合该死的内容SHA256验证，NFS加密文件流需要暂存后在计算哈希上传
					[$hash, $body] = is_resource($dump = tmpfile())
						&& stream_copy_to_stream($body, $dump) !== FALSE
						&& rewind($dump)
						&& is_object($hash = hash_init('sha256'))
						&& is_int(hash_update_stream($hash, $dump))
						&& rewind($dump) ? [hash_final($hash), $dump] : [NULL, NULL];
				}
				else
				{
					$hash = is_string($body ??= '') ? hash('sha256', $body) : NULL;
				}
				if ($hash === NULL)
				{
					//V4签名规定必须要有内容的SHA256，如果没有就别请求了
					return FALSE;
				}
				$date = new DateTime('UTC');
				$t0 = $date->format('Ymd');
				$t1 = $date->format('Ymd\THis\Z');
				$region = 'auto';
				$service = 's3';
				$scope = "{$t0}/{$region}/{$service}/aws4_request";
				$signature = hash_hmac('sha256', join("\n", ['AWS4-HMAC-SHA256', $t1, $scope, hash('sha256',
					"{$method}\n{$filename}\n{$query}\nhost:{$this->headers['Host']}\nx-amz-date:{$t1}\n\nhost;x-amz-date\n{$hash}")]),
						hash_hmac('sha256', 'aws4_request',
							hash_hmac('sha256', $service,
								hash_hmac('sha256', $region,
									hash_hmac('sha256', $t0, "AWS4{$this->password}", TRUE), TRUE), TRUE), TRUE));
				$this->headers([
					'Authorization' => "AWS4-HMAC-SHA256 Credential={$this->username}/{$scope},SignedHeaders=host;x-amz-date,Signature={$signature}",
					'x-amz-date' => $t1, 'x-amz-content-sha256' => $hash]);
				return parent::request($method, $path, $body, $type);
			}],
			#https://docs.aws.amazon.com/AmazonS3/latest/API/RESTAuthentication.html
			'amazon_s3' => ["https://{$options[2]}.s3.{$options[3]}.amazonaws.com",
			function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				$date = date(DATE_RFC2822);
				$filename = strpos($path, '?') ? strstr($path, '?', TRUE) : $path;
				$signature = base64_encode(hash_hmac('sha1', "{$method}\n\n{$type}\n{$date}\nx-amz-acl:public-read\n/{$this->bucket}{$filename}", $this->password, TRUE));
				$this->headers(['Authorization' => "AWS {$this->username}:{$signature}", 'Date' => $date, 'x-amz-acl' => 'public-read']);
				return parent::request($method, $path, $body, $type);
			}],
			#https://help.aliyun.com/zh/oss/developer-reference/overview-24
			'aliyun_oss' => ["https://{$options[2]}.{$options[3]}.aliyuncs.com",
			function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				$date = gmdate(DATE_RFC7231);
				$filename = strpos($path, '?') ? strstr($path, '?', TRUE) : $path;
				$signature = base64_encode(hash_hmac('sha1', "{$method}\n\n{$type}\n{$date}\nx-oss-acl:public-read\n/{$this->bucket}{$filename}", $this->password, TRUE));
				$this->headers(['Authorization' => "OSS {$this->username}:{$signature}", 'Date' => $date, 'x-oss-acl' => 'public-read']);
				return parent::request($method, $path, $body, $type);
			}],
			default => [$api, function(string $method, string $path, $body = NULL, string $type = NULL):bool
			{
				static $entry = $this->path;

				$c = strtolower($method);
				$v = webapp::url64_encode($path);

				$this->headers(['Authorization' => 'Bearer ' . webapp::signature($this->username, $this->password, webapp::hash($c . $v, TRUE) . $this->bucket)]);
				
				
				return parent::request('POST', "{$entry}/{$c},v:{$v}", $body, $type);
			}]
		}), ['autoretry' => 2, 'autojump' => 1]);
		[$this->username, $this->password, $this->bucket] = $options;
	}
	function signature():string
	{
	}
	function test():void
	{
		// $a = $this->request('GET', '/workers/btc.txt');
		// var_dump($a, $this->status($res), $res, $this->content());
	}
	function request(string $method, string $path, $body = NULL, string $type = NULL):bool
	{
		return ($this->request)($method, $path, $body, $type);
	}
	function get(string $filename, string $type = 'application/octet-stream'):?string
	{
		return $this->request('GET', $filename) && $this->status() === 200 ? $this->content($type) : NULL;
	}
	function put(string $filename, $stream, string $type = 'application/octet-stream'):bool
	{
		return $this->request('PUT', $filename, $stream, $type) && $this->status() === 200;
		// return is_resource($stream) && $this->request('PUT', $filename,
		// 	$stream, 'application/octet-stream') && $this->status() === 200;
	}
	function list(string $directory):iterable
	{
		$directory = trim($directory, '/');
		if ($this->request('GET', "/?prefix={$directory}&max-keys=1000") && $this->status() === 200)
		{
			foreach ($this->content()->Contents as $content)
			{
				yield "/{$content->Key}";
			}
		}
		else
		{
			yield NULL;
		}
	}
	function delete(string $filename):bool
	{
		do
		{
			if (str_ends_with($filename, '/'))
			{
				foreach ($this->list($filename) as $filename)
				{
					if ($this->request('DELETE', $filename) && $this->status() === 204)
					{
						continue;
					}
					break;
				}
			}
			return $this->request('DELETE', $filename) && $this->status() === 204;
		} while (0);
		return FALSE;
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
	function upload_uploadedfile(string $path, webapp_request_uploadedfile $uploadedfile, int $maxpathdeep = 0):int
	{
		$count = 0;
		foreach ($uploadedfile as $index => $item)
		{
			if ($uploadedfile->validate($index, $maxpathdeep))
			{
				$count += intval($this->put($path . $item['path'], $uploadedfile->open($index), $uploadedfile->mime($index)));
			}
		}
		return $count;
	}
}
class webapp_nfs implements Countable, IteratorAggregate
{
	//private ?string $uid = NULL;
	public array $paging = [];
	private array $cond = [];
	//private readonly webapp_mysql_table $table;
	//private readonly webapp_redis_table $cache;
	private readonly array $where;
	private readonly Closure $format;
	function __construct(public readonly webapp_extend_nfs $webapp, public readonly int $sort, public readonly int $type, Closure $format = NULL)
	{
		$this->where = ['WHERE `sort`=?i AND `type`=?i', $this->sort, $this->type];
		$this->format = $format ?? fn($data) => $data;
	}
	function __debugInfo():array
	{
		$cond = $this->where;
		if ($this->cond && $this->cond[0])
		{
			$cond[0] .= (preg_match('/^\s*(?:(?:group|order)\s+by|limit)\s+/i', $this->cond[0]) ? ' ' : ' AND ') . $this->cond[0];
			array_push($cond, ...array_slice($this->cond, 1));
		}
		return $cond;
	}
	function __invoke(...$conditions):static
	{
		$this->cond = $conditions;
		return $this;
	}
	function __toString():string
	{
		return $this->webapp->mysql->format(...$this->__debugInfo());
	}
	function count(bool $reserve = FALSE):int
	{
		return $this->table($reserve)->count();
	}
	function getIterator():Traversable
	{
		foreach ($this->table() as $file)
		{
			yield ($this->format)($file);
		}
	}
	private function table(bool $reserve = FALSE):webapp_mysql_table
	{
		$cond = $this->__debugInfo();
		if ($reserve === FALSE)
		{
			$this->cond = [];
		}
		return $this->webapp->mysql->{$this->webapp::tablename}(...$cond);
	}
	private function cache():webapp_redis_table{}
	private function primary(string $hash):webapp_mysql_table
	{
		return $this('`hash`=?s LIMIT 1', $hash)->table();
	}
	function append(string $field, string $value = 'NULL'):bool
	{
		return $this->table(TRUE)->update("`extdata`=JSON_SET(`extdata`, '$.{$field}', {$value})") === $this->count();
	}
	function remove(string ...$fields):bool
	{
		return $this->table(TRUE)->update('`extdata`=JSON_REMOVE(`extdata`,??)',
			join(',', array_map(fn($field) => "'$.{$field}'", $fields))) === $this->count();
	}
	function search(string $syntax, ...$values):static
	{
		return $this(preg_replace('/\$\.(\w+)/', 'extdata->"$.$1"', $syntax), ...$values);
	}
	function paging(int $index, int $rows = 10):static
	{
		$this->paging['count'] = $this->count(TRUE);
		$this->paging['max'] = ceil($this->paging['count'] / $rows = abs($rows));
		$this->paging['index'] = max(1, $index);
		$this->paging['skip'] = ($this->paging['index'] - 1) * $rows;
		$this->paging['rows'] = $rows;
		$this->cond[0] = (isset($this->cond[0]) ? "{$this->cond[0]} " : '') . "LIMIT {$this->paging['skip']},{$rows}";
		return $this;
	}
	function random(int $rows = 1):iterable
	{
		foreach ($this->table()->random($rows) as $file)
		{
			yield ($this->format)($file);
		}
	}
	function node_exist(?string $node):bool
	{
		return $node === NULL || $this->webapp->mysql->{$this->webapp::tablename}
			('WHERE `sort`=?i AND `type`=0 AND `hash`=?s LIMIT 1', $this->sort, $node)->select('hash')->value() === $node;
	}
	function node_empty(?string $node):bool
	{
		return $node !== NULL && $this->webapp->mysql->{$this->webapp::tablename}
			('WHERE `sort`=?i AND `node`=?s LIMIT 1', $this->sort, $node)->select('hash')->value() === NULL;
	}
	function filename(string $hash, string $suffix = NULL):string
	{
		return sprintf('/%d/%04X/%s%s', $this->sort, $this->webapp->hashtime33($hash) % 0xffff, $hash, $suffix);
	}
	function create(array $data):?string
	{
		return $this->webapp->mysql->{$this->webapp::tablename}->insert([
			'hash' => $hash = array_key_exists('hash', $data)
				&& $this->webapp->is_long_hash($data['hash'])
					? $data['hash'] : $this->webapp->random_hash(FALSE),
			'sort' => $this->sort,
			'type' => $this->type,#Type 0 and 1 is NFS reserved use
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
		return $this->type === 0 ? $this->node_empty($hash) && $this->primary($hash)->delete() === 1 : $this->webapp->mysql->sync(fn() =>
			$this->primary($hash)->delete() === 1 && $this->webapp->client->delete($this->filename($hash, $this->type === 1 ? '' : '/')));
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
	function fetch(string $hash, &$data = NULL):bool
	{
		if ($this->primary($hash)->fetch($rawdata))
		{
			$data = ($this->format)($rawdata);
			return TRUE;
		}
		return FALSE;
	}
	function node(string $hash):static
	{
		return $this('`node`=?s', $hash);
	}
	function order(string $command):static
	{
		$this->cond[0] = (isset($this->cond[0]) ? "{$this->cond[0]} " : '') . "ORDER BY {$command}";
		return $this;
	}
	function rename(string $hash, string $newname):bool
	{
		return $this->primary($hash)->update(['t1' => $this->webapp->time(), 'name' => $newname]) === 1;
	}
	function moveto(string $hash, ?string $node):bool
	{
		return $this->node_exist($node) && $node !== $hash
			&& $this->primary($hash)->update(['t1' => $this->webapp->time(), 'node' => $node]) === 1;
	}
	function create_uploadedfile(string $name, array $data = [], bool $mask = FALSE):?string
	{
		$key = $mask ? $this->webapp->random(8) : NULL;
		$uploadedfile = $this->webapp->request_uploadedfile($name);
		return $this->webapp->mysql->sync(fn(&$hash) => $uploadedfile->count()
			&& is_string($hash = $this->create(['key' => $key] + $data + $uploadedfile()))
			&& $this->webapp->client->put($this->filename($hash), $uploadedfile->open(0, $mask, $key), $uploadedfile->mime()), $hash) ? $hash : NULL;
	}
	function update_uploadedfile(string $hash, array $data = [], string $name = NULL, bool $mask = FALSE):bool
	{
		$key = $mask ? $this->webapp->random(8) : NULL;
		return $name && count($uploadedfile = $this->webapp->request_uploadedfile($name))
			? $this->webapp->mysql->sync(fn() => $this->update($hash, ['key' => $key] + $data + $uploadedfile[0])
				&& $this->webapp->client->put($this->filename($hash), $uploadedfile->open(0, $mask, $key), $uploadedfile->mime()))
			: $this->update($hash, $data);
	}
	function replace_inputedfile(string $hash):bool
	{
		return $this->fetch($hash, $data) && $this->webapp->mysql->sync(fn() => $this->update($data['hash'],
			['size' => $this->webapp->request_content_length()])
				&& $this->webapp->client->put($this->filename($data['hash']), $data['key']
					? $this->webapp->maskdata($this->webapp->request_content(), $data['key'])
					: $this->webapp->request_content(), $this->webapp->request_content_type()));
	}

	//upload_localdir
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
class webapp_extend_nfs extends webapp
{
	const tablename = 'nfs';
	private array $nfs = [];
	public string $origin = 'http://localhost/nfs';
	static function formatsize(int $size):string
	{
		return sprintf('%.2f %s', ...match (TRUE)
		{
			$size > 1024 ** 4 => [$size / 1024 ** 4, 'TB'],
			$size > 1024 ** 3 => [$size / 1024 ** 3, 'GB'],
			$size > 1024 ** 2 => [$size / 1024 ** 2, 'MB'],
			$size > 1024 => [$size / 1024, 'KB'],
			default => [$size, 'B']
		});
	}
	static function createtable(webapp_mysql $mysql):bool
	{
		return $mysql->real_query(<<<'SQL'
		CREATE TABLE ?a (
			`hash` char(12) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
			`sort` tinyint unsigned NOT NULL,
			`type` tinyint unsigned NOT NULL COMMENT '0:node,1:file,2:mixed',
			`t0` bigint unsigned NOT NULL COMMENT 'insert time',
			`t1` bigint unsigned NOT NULL COMMENT 'update time',
			`size` bigint unsigned NOT NULL,
			`views` bigint unsigned NOT NULL,
			`likes` bigint unsigned NOT NULL,
			`shares` bigint unsigned NOT NULL,
			`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
			`node` char(12) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
			`key` binary(8) DEFAULT NULL COMMENT 'masker',
			`extdata` json DEFAULT NULL,
			PRIMARY KEY (`hash`),
			KEY `sort` (`sort`),
			KEY `type` (`type`),
			KEY `node` (`node`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
		SQL, static::tablename);
	}
	// static function serial_hash(bool $care, string $prefix = 'W', int $limit = 12):string
	// {
	// 	return substr($prefix . static::random_hash($care), 0, $limit);
	// }
	static function is_short_hash(string $hash):bool
	{
		return preg_match('/^[\w\-]{10}$/', $hash) === 1;
	}
	static function is_long_hash(string $hash):bool
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash) === 1;
	}

	function client():webapp_nfs_client
	{
		// return new webapp_nfs_client('cloudflare_r2', 'access-key-id', 'secret-access-key', 'bucket-name', 'account-id');
		// return new webapp_nfs_client('amazon_s3', 'AccessKeyID', 'AccessKeySecret', 'BucketName', 'region');
		// return new webapp_nfs_client('aliyun_oss', 'AccessKeyID', 'AccessKeySecret', 'BucketName', 'region');
		return new webapp_nfs_client('http://localhost/?server', $this['admin_username'], $this['admin_password'], 'D:/wmhp/work/nfs');
	}
	function nfs(int $sort = 0, int $type = 1, Closure $format = NULL):webapp_nfs
	{
		return $this->nfs[($sort &= 0xff) << 8 | $type &= 0xff] ??= new webapp_nfs($this, $sort, $type, $format);
	}
	function src(array $file, string $name = NULL):string
	{
		return sprintf('%s/%d/%04X/%s%s?%X#%s',
			$this->origin, $file['sort'],
			$this->hashtime33($file['hash']) % 0xffff,
			$file['hash'], $name, $file['t1'], bin2hex((string)$file['key']));
	}

	// function tmpdir():?string
	// {
	// 	$tmpdir = sprintf('%s/%s', sys_get_temp_dir(), static::random_hash(FALSE));
	// 	return 
	// 	if (mkdir())
		
	// 	var_dump( $tmpdir );
	// }

	//NFS客户端都是通过POST方法请求的，请确保调用该函数的路由是POST方法
	function server(string $c, string $v, string $open = NULL):int
	{
		if (current([$root, $v] = $open ? [$open, $this->url64_decode($v)] : $this->authorize($this->request_authorization($type),
			function(string $username, string $password, int $signtime, string $additional = NULL) use($c, $v) {
				return str_starts_with($additional, $this->hash($c . $v, TRUE))
				&& $signtime > $this->time(-$this['admin_expire'])
				&& $username === $this['admin_username']
				&& $password === $this['admin_password']
					? [rtrim(substr($additional, 10), '/'), $this->url64_decode($v)] : [NULL, NULL];
		})) === NULL) return 401;
		if ($c === 'get')
		{
			if (str_starts_with($v, '/?'))
			{
				parse_str(substr($v, 2), $result);
				$result['prefix'] ??= '';
				//$result['max-keys'] ??= 1000;
				$this->echo_xml();
				if (is_dir($root .= "/{$result['prefix']}") && is_resource($dir = opendir($root)))
				{
					while (is_string($entry = readdir($dir)))
					{
						is_file("{$root}/{$entry}") && $this->echo->xml->append('Contents')
							->append('Key', ltrim("{$result['prefix']}/{$entry}", '/'));
					}
				}
				return 200;
			}
			else
			{
				if (is_file($filename = "{$root}{$v}"))
				{
					$this->echo(file_get_contents($filename));
					return 200;
				}
				return 404;
			}
		}
		else
		{
			$dirname = dirname($filename = "{$root}{$v}");
			return match ($c)
			{
				'put' => (is_dir($dirname) || mkdir($dirname, recursive: TRUE))
					&& file_put_contents($filename, $this->request_content()) === $this->request_content_length() ? 200 : 500,
				'delete' => unlink($filename) ? [204, rmdir($dirname)][0] : 404,
				default => 405
			};
		}
	}
}