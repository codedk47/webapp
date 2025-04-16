<?php
/*
CREATE TABLE `nfs` (
  `hash` char(12) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `sort` tinyint unsigned NOT NULL,
  `site` tinyint unsigned NOT NULL,
  `it` int unsigned NOT NULL COMMENT 'insert time',
  `ut` int unsigned NOT NULL COMMENT 'update time',
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
*/
class webapp_nfs implements IteratorAggregate, Countable
{
	private ?string $uid = NULL;
	private array $callbacks = [], $sites;

	function __construct(public readonly webapp $webapp, public readonly int $sort, string ...$sites)
	{
		foreach ($sites as $site)
		{
			$urls = parse_url($site);
			$this->sites[] = [
				'original' => $original = "{$urls['scheme']}://{$urls['host']}" . (isset($urls['port']) ? ":{$urls['port']}" : ''),
				'callback' => $urls['path'],
				'username' => $urls['user'] ?? $this->webapp['admin_username'],
				'password' => $urls['pass'] ?? $this->webapp['admin_password']
			];
	
		}
		print_r($this->sites);
	}
	function __invoke(...$conditions):webapp_mysql_table
	{
		return $this->webapp->mysql->nfs('WHERE sort=?i' . ($conditions
			? ' AND ' . array_shift($conditions) : ''), $this->sort, ...$conditions);
	}
	function count():int
	{
		return count($this());
	}
	function getIterator(...$conditions):Traversable
	{
		foreach ($this(...$conditions) as $a)
		{
			print_r( str_split($a['hash'], 3) );


			$a['url'] = "{$this->sites[0]['original']}/{$a['sort']}/{$a['hash']}";

			yield $a;
		}
		//return $this($folder ? 'site IS NULL' : 'site IS NOT NULL');
		//return $this->webapp->mysql->nfs('WHERE sort=?i', $this->sort)
	}


	function create(string|array $value, array|string $extdata = NULL, int $options = JSON_UNESCAPED_UNICODE):?string
	{
		if (is_string($value))
		{
			$value = ['name' => $value];
		}
		return $this->webapp->mysql->nfs->insert([
			'hash' => $hash = $value['hash'] ?? $this->webapp->random_hash(FALSE),
			'sort' => $this->sort,
			'site' => $value['site'] ?? NULL, //NULL is folder
			'uid' => $this->uid,
			'it' => $this->webapp->time,
			'ut' => $this->webapp->time,
			'size' => $value['size'] ?? 0,
			'views' => $value['views'] ?? 0,
			'likes' => $value['likes'] ?? 0,
			'shares' => $value['shares'] ?? 0,
			'name' => $value['name'] ?? '',
			'node' => $value['node'] ?? NULL,
			'extdata' => ($extdata ??= $value['extdata'] ?? NULL)
				? (is_array($extdata) ? json_encode($extdata, $options) : $extdata) : NULL
		]) ? $hash : NULL;
	}
	function callback(string $hash, string $method, string $query, $body = NULL)
	{
		$this('hash=?s LIMIT 1', $hash)->fetch($data);

		$callback = $this->callbacks[$data['site']] ??= new webapp_client_http($this->sites[$data['site']]['original'], $options = [
			'headers' => ['Authorization' => 'Bearer ' . $this->webapp->signature($this->sites[$data['site']]['username'], 
				$this->sites[$data['site']]['password'], $data['site'])]]);



		$callback->request($method, "{$this->sites[$data['site']]['callback']}?{$query}", $body);

		var_dump($callback->path);

		//$callback->request($method, "{$callback->path}?{$query}");

		//var_dump("{$callback->path}?{$query}");

		//var_dump( $this->sites[$data['site']]['callback'] );
		var_dump($callback->content());
	}
	function delete(string $hash):bool
	{
		$this->callback($hash, 'DELETE', "file/{$hash}");



		return TRUE;
	}
	function rename(string $hash, string $newname):bool
	{
		return $this('hash=?s', $hash)->update(['ut' => $this->webapp->time(), 'name' => $newname]) === 1;
	}





	function storage(string|array $hash, string $name, array $json = NULL, int $options = JSON_UNESCAPED_UNICODE):ArrayObject
	{
		// return new class extends ArrayObject
		// {


		// };
		// $data = is_string($hash)
		// 	? [
		// 		'hash' => $hash,
		// 		'sort' => $this->sort,
		// 		'flag' => 0,
		// 		'size' => 0,
		// 		'name' => $name,
		// 		'json' => $json === NULL ? NULL : json_encode($json, $options)
		// 	]
		// 	: [

		// 	]
	}
	function storage_uploadfile(int $index, string $rename = NULL):bool
	{
	}
	function storage_localfile(string $filename, string $rename = NULL):bool
	{
	}
	function storage_netfile(string $url, string $rename = NULL):bool
	{}
}
class webapp_ext_nfs_base extends webapp
{
	private array $nfs = [];
	public array $sites = ['http://localhost/test.php'];

	function __construct(array $config = [], webapp_io $io = new webapp_stdio)
	{
		parent::__construct($config, $io);
	}
	function nfs(int $sort = 0):webapp_nfs
	{
		return $this->nfs[$sort &= 0xff] ??= new webapp_nfs($this, $sort, ...$this->sites);
	}
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
}