<?php
interface webapp_nfs_access
{
	function put(string $filename, string $source):bool;
	function delete(string $filename):bool;
}
class webapp_nfs_standard_io extends webapp_client_http implements webapp_nfs_access
{
	function put(string $filename, string $source):bool
	{
		return FALSE;
	}
	function delete(string $filename):bool
	{
		return FALSE;
	}
}
class webapp_nfs_cloudflare_r2 extends webapp_client_http implements webapp_nfs_access
{
	#https://developers.cloudflare.com/r2/tutorials/postman/
	function put(string $filename, string $source):bool
	{
		return FALSE;
	}
	function delete(string $filename):bool
	{
		return FALSE;
	}
}
class webapp_nfs_amazon_s3 extends webapp_client_http implements webapp_nfs_access
{
	#https://docs.aws.amazon.com/AmazonS3/latest/API/RESTAuthentication.html
	private readonly string $bucket_name, $access_key, $secret_key;
	function __construct(string $region, array $options = ['BucketName', 'AWSAccessKeyId', 'YourSecretAccessKey'])
	{
		parent::__construct("https://s3.{$region}.amazonaws.com");
		$this->bucket_name = '/' . array_shift($options);
		[$this->access_key, $this->secret_key] = $options;
	}
	function request(string $method, string $path, $body = NULL, string $type = NULL):bool
	{
		$date = date(DATE_RFC2822);
		$path = $this->bucket_name . $path;
		$signature = base64_encode(hash_hmac('sha1', "{$method}\n\n{$type}\n{$date}\nx-amz-acl:public-read\n{$path}", $this->secret_key, TRUE));
		$this->headers(['Authorization' => "AWS {$this->access_key}:{$signature}", 'Date' => $date, 'x-amz-acl' => 'public-read']);
		return parent::request($method, $path, $body, $type);
	}
	function put(string $filename, $stream):bool
	{
		return (is_resource($stream) || is_file($source)) && $this->request('PUT',
			$filename, $stream, 'application/octet-stream') && $this->status() === 200;
	}
	function delete(string $filename):bool
	{
		return $this->request('DELETE', $filename) && $this->status() === 204;
	}
}
class webapp_nfs implements IteratorAggregate, Countable
{
	//private ?string $uid = NULL;
	private array $callbacks = [], $sites;

	function __construct(public readonly webapp_ext_nfs_base $webapp, public readonly int $sort)
	{

		// foreach ($sites as $site)
		// {
		// 	$urls = parse_url($site);
		// 	$this->sites[] = [
		// 		'original' => $original = "{$urls['scheme']}://{$urls['host']}" . (isset($urls['port']) ? ":{$urls['port']}" : ''),
		// 		'callback' => $urls['path'],
		// 		'username' => $urls['user'] ?? $this->webapp['admin_username'],
		// 		'password' => $urls['pass'] ?? $this->webapp['admin_password']
		// 	];
	
		// }
		// print_r($this->sites);
		
	}
	function __invoke(...$conditions):webapp_mysql_table
	{
		return $this->webapp->mysql->{$this->webapp::tablename}('WHERE sort=?i' . ($conditions
			? ' AND ' . array_shift($conditions) : ''), $this->sort, ...$conditions);
	}
	function count():int
	{
		return count($this());
	}
	function getIterator(...$conditions):Traversable
	{
		foreach ($this(...$conditions) as $file)
		{
			$file['path'] = $this->filename($file['hash']) . "?{$file['t1']}";
			//print_r( str_split($a['hash'], 3) );


			//$a['url'] = "{$this->sites[0]['original']}/{$a['sort']}/{$a['hash']}";

			yield $file;
		}
		//return $this($folder ? 'site IS NULL' : 'site IS NOT NULL');
		//return $this->webapp->mysql->nfs('WHERE sort=?i', $this->sort)
	}
	function paging(int $index, int $rows = 21, bool $overflow = FALSE):static
	{
		
	}
	function filename(string $hash):string
	{
		return "/{$this->sort}/" . substr(chunk_split($hash, 3, '/'), 0, 15);
	}
	function create(array $data, bool $folder = FALSE):?string
	{
		return $this->webapp->mysql->{$this->webapp::tablename}->insert([
			'hash' => $hash = $folder ? $this->webapp->serial_hash(FALSE, 'W') : (array_key_exists('hash', $data)
				&& preg_match('/^[0-9A-V]{12}/', $data['hash']) ? $data['hash'] : $this->webapp->random_hash(FALSE)),
			'sort' => $this->sort,
			't0' => $t0 = $this->webapp->time(),
			't1' => $t0,
			'size' => $data['size'] ?? 0,
			'views' => $data['views'] ?? 0,
			'likes' => $data['likes'] ?? 0,
			'shares' => $data['shares'] ?? 0,
			'name' => $data['name'] ?? '',
			'node' => $data['node'] ?? NULL,
			'extdata' => isset($data['extdata']) && is_array($data['extdata']) ? json_encode(
				$data['extdata'], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE) : $data['extdata'] ?? NULL]) ? $hash : NULL;
	}
	function delete(string $hash):bool
	{
		return $this('`hash`=?s LIMIT 1', $hash)->delete() === 1;
	}
	function update(string $hash, array $data = []):bool
	{
		$file = ['t1' => $this->webapp->time()];
		foreach (['size', 'views', 'likes', 'shares', 'name', 'node'] as $field)
		{
			if (array_key_exists($field, $data))
			{
				$file[$field] = $data[$field];
			}
		}
		if (array_key_exists('extdata', $data))
		{
			$file['extdata'] = is_array($data['extdata']) ? json_encode(
				$data['extdata'], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE) : $data['extdata'];
		}
		return $this('hash=?s LIMIT 1', $hash)->update($file) === 1;
	}
	function fetch(string $hash, ?array &$data = NULL, ?array &$extdata = NULL):bool
	{
		if ($this('`hash`=?s LIMIT 1', $hash)->fetch($data))
		{
			if ($data['extdata'] && func_num_args() > 2)
			{
				$extdata = json_decode($data['extdata'], TRUE);
			}
			return TRUE;
		}
		return FALSE;
	}
	function rename(string $hash, string $newname):bool
	{
		return $this('hash=?s', $hash)->update(['t1' => $this->webapp->time(), 'name' => $newname]) === 1;
	}
	function delete_file(string $hash):bool
	{
		return $this->webapp->mysql->sync(fn() => $this->delete($hash) && $this->webapp->access->delete($this->filename($hash)));
	}
	function create_folder(string $name):?string
	{
		return $this->create(['name' => $name], TRUE);
	}
	function create_uploadedfile(string $name, array $data = [], bool $mask = FALSE):?string
	{
		$uploadedfile = $this->webapp->request_uploadedfile($name);
		return $this->webapp->mysql->sync(fn(&$hash) => $uploadedfile->count()
			&& is_string($hash = $this->create($data + $uploadedfile()))
			&& $this->webapp->access->put($this->filename($hash), $uploadedfile->open(0, $mask)), $hash) ? $hash : NULL;
	}
	function update_uploadedfile(string $hash, array $data = [], string $name = NULL, bool $mask = FALSE):bool
	{
		return $name && count($uploadedfile = $this->webapp->request_uploadedfile($name))
			? $this->webapp->mysql->sync(fn() => $this->update($hash, $data + $uploadedfile[0])
				&& $this->webapp->access->put($this->filename($hash), $uploadedfile->open(0, $mask)))
			: $this->update($hash, $data);
	}
}

class webapp_ext_nfs_base extends webapp
{
	const tablename = 'nfs';
	private array $nfs = [];
	static function createtable(webapp_mysql $mysql):bool
	{
		return $mysql->real_query(<<<'SQL'
		CREATE TABLE ?a (
			`hash` char(12) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
			`sort` tinyint unsigned NOT NULL,
			`t0` int unsigned NOT NULL COMMENT 'insert time',
			`t1` int unsigned NOT NULL COMMENT 'update time',
			`size` bigint unsigned NOT NULL,
			`views` bigint unsigned NOT NULL,
			`likes` bigint unsigned NOT NULL,
			`shares` bigint unsigned NOT NULL,
			`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
			`node` char(12) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
			`extdata` json DEFAULT NULL,
			PRIMARY KEY (`hash`),
			KEY `type` (`hash`(1)),
			KEY `sort` (`sort`),
			KEY `node` (`node`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
		SQL, static::tablename);
	}
	function access():webapp_nfs_access
	{
		return new webapp_nfs_standard_io('http://localhost', [$this['admin_username'], $this['admin_password']]);
	}
	function nfs(int $sort = 0):webapp_nfs
	{
		return $this->nfs[$sort &= 0xff] ??= new webapp_nfs($this, $sort);
	}
	function src(int $sort, string $hash, int $t1 = 0, bool $mask = FALSE):string
	{
		return sprintf("{$this->readorigin}/{$sort}%s?{$t1}%s", $this->filename($hash), $mask ? '#!' : '');

	}

	// function put(string $filename, string $source):bool
	// {
	// 	return $this->access->put($filename, $source);
	// }
	// function delete(string $filename):bool
	// {
	// 	return $this->access->delete($filename);
	// }




	/*
	function get_view(int $sort = 0)
	{
		$this->app('webapp_echo_xml');

		foreach ($this->mysql->videos->paging(1, 20) as $file)
		{
			$this->app->xml->append('file', [
				'hash' => $file['hash'],
				'size' => $file['size'],
				'name' => $file['name']
			])->cdata((string)$file['extdata']);
			
			
		}
	}
	function host(string $path)
	{
		$a = $this->open("http://127.0.0.1/test.php?{$path}", [
			'headers' => $this->webapp->authorized(0),
			'method' => match ($path)
			{
				'create/file', 'create/folder' => 'POST',
				'ASD' => 'DELETE',
				default => 'GET'
			},
			'data' => ['name' => 'wwwwwwwwwwwwww']
		]);
		//print_r($a);

		var_dump('==',$a->content());
	}

	function get_create()
	{
		$this->app('webapp_echo_json', ['asdasd' => 123]);
		//$this->echo(123);
	}


	function post_create(string $type)
	{
		if ($auth = $this->authorization())
		{
			$data = $this->request_content();
			$data['site'] = $type === 'file' ? $auth[2] : NULL;
			if (is_string($hash = $this->nfs($auth[2])->create($data)))
			{
				$this->echo($hash);
				return 200;
			}
			return 500;
		}
		return 401;
	}
	function delete_a()
	{

	}
	// function path_concat():string
	// {

	// }
	function delete_file(string $hash)
	{
		if ($auth = $this->authorization())
		{



			print_r($auth);
			return 200;


			// $data = $this->request_content();
			// $data['site'] = $type === 'file' ? $auth[2] : NULL;
			// if (is_string($hash = $this->nfs($auth[2])->create($data)))
			// {
			// 	$this->echo($hash);
			// 	return 200;
			// }
			// return 500;
		}
		return 401;
	}
	*/
}