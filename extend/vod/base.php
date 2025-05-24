<?php
class webapp_ext_vod_base extends webapp_ext_nfs_base
{
	public array $proxy_origins = ['http://localhost'];
	public array $origins = ['http://localhost'];
	public string $origin = '';
	// function __construct(array $config = [], webapp_io $io = new webapp_stdio)
	// {
	// 	parent::__construct($config, $io);
	// 	if ($this->redis->dosevasive(3))
	// 	{
	// 		$this->response_status(500);
	// 	}
	// }

	function origin(webapp_echo_masker $masker, string $file = 'robots.txt'):void
	{
		foreach ($this->origins as $origin)
		{
			$masker->link(['rel' => 'preconnect', 'href' => $origin, 'data-file' => $file, 'crossorigin' => NULL]);
		}
	}
	function cid(string $default = 'NULL'):string
	{
		return $this->request_header('user-cid') ?? $default;
	}
	function did():?string
	{
		return $this->request_header('user-did') ?? NULL;
	}
	function access_log()
	{

	}
	function nfs_ads():webapp_nfs
	{
		return $this->nfs(0, 1, function($data)
		{
			$data += json_decode($data['extdata'], TRUE);
			$data['src'] = $this->src($data);
			unset($data['extdata']);
			return $data;
		});
	}
	function nfs_classifies():webapp_nfs
	{
		return $this->nfs(1, 0, function($data)
		{
			$data += json_decode($data['extdata'], TRUE);
			unset($data['extdata']);
			return $data;
		});
	}
	function nfs_actors():webapp_nfs
	{
		return $this->nfs(1, 1, function($data)
		{
			return $data;
		});
	}
	function nfs_videos():webapp_nfs
	{
		return $this->nfs(1, 2, function($data)
		{
			$data += json_decode($data['extdata'], TRUE);
			$data['poster'] = $this->src($data, "/{$data['poster']}.cover");
			$data['m3u8'] = $data['proxy']
				? sprintf("?proxy/%d,m3u8:%s", $data['proxy'][0], $this->url64_encode($data['proxy'][1]))
				: strstr($this->src($data, '/ts.m3u8'), '#', TRUE);
				//: $this->src($data, '/ts');
			//$data['m3u8'] = $this->src($data, '/ts');
			unset($data['extdata'], $data['cover']);
			return $data;
		});
	}
	function video_create(array $value = []):?string
	{
		return $this->nfs_videos->create(['name' => $value['name'] ?? '',
			'hash' => $value['hash'] ?? $this->random_hash(FALSE),
			'size' => $value['size'] ?? 0,//时长（秒）
			'key' => $value['key'] ?? bin2hex($this->random(8)),
			'extdata' => [
				'cover' => $value['cover'] ?? 0,//封面数量
				'poster' => $value['poster'] ?? 0,//海报封面
				'require' => $value['require'] ?? 0,//-1会员,0免费,大于0价格
				'tags' => $value['tags'] ?? '',//标签集
				'actors' => $value['actors'] ?? '',//演员集
				'subjects' => $value['subjects'] ?? '',//专题集
				'proxy' => $value['proxy'] ?? NULL
		]], 2);
	}
	function video_create_proxy(//创建一个代理视频
		int $proxy_origin,//源站ID
		string $cover_url,//封面地址
		string $m3u8_path,//M3U8路径
		int $video_duration,//视频时长（秒）
		string $video_name,//视频名称
		string $md5 = NULL//视频MD5
		):bool {
		static $client = new webapp_client_http($this->proxy_origins[$proxy_origin], ['autoretry' => 2, 'autojump' => 1]);
		$hash = $this->hash(is_string($md5) ? hex2bin($md5) : join(func_get_args()), FALSE);
		$key = $this->random(8);
		return $this->mysql->sync(fn() => is_string($this->video_create([
				'hash' => $hash,
				'size' => $video_duration,
				'name' => $video_name,
				'key' => bin2hex($key),
				'proxy' => [$proxy_origin, $m3u8_path]]))
			&& $client->goto($cover_url)->status() === 200
			&& is_resource($cover = $this->masker(tmpfile(), $key))
			&& $client->to($cover)
			&& rewind($cover)
			&& $this->client->put($this->nfs_videos->filename($hash, '/0.cover'), $cover)
			// && is_resource($m3u8 = tmpfile())
			// && fwrite($m3u8, "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=0\n{$m3u8_url}") !== FALSE
			// && rewind($m3u8)
			// && $this->client->put($this->nfs_videos->filename($hash, '/ts.m3u8'), $m3u8)
			// && rewind($m3u8)
			// && $this->masker($m3u8, $key) === $m3u8
			// && $this->client->put($this->nfs_videos->filename($hash, '/ts'), $m3u8)
		);
	}
	function video_delete(string $hash):bool
	{
		return FALSE;
	}
	function video_update(string $hash, array $value, string $cover):bool
	{
		$data = isset($value['name']) ? ['name' => $value['name']] : [];
		foreach (['poster', 'tags', 'actors', 'require'] as $field)
		{
			if (array_key_exists($field, $value))
			{
				$data['extdata'][$field] = $value[$field];
			}
		}
		$videos = $this->nfs_videos;
		$uploadedfile = $this->request_uploadedfile($cover);
		if ($uploadedfile->count() && $videos->fetch($hash, $file))
		{
			return $this->mysql->sync(fn() => $videos->update($hash, $data)
				&& $this->client->put($videos->filename($hash, '/0.cover'),
					$uploadedfile->open(0, TRUE, $file['key'])));
		}
		return $videos->update($hash, $data);
	}


	


	function format_duration(int $second):string
	{
		return sprintf('%02d:%02d:%02d', intval($second / 3600), intval(($second % 3600) / 60), $second % 60);
	}
	function format_video(string $filename, string $outdir, ?string $poster = NULL, int $cover = 9):array
	{
		static $ffmpeg = static::libary('ffmpeg/interface.php');
		while (is_dir($outdir)
			&& is_string($hash = is_string($basename = strstr(basename($filename), '.', TRUE))
				&& $this->is_long_hash($basename) ? $basename : $this->hashfile($filename))
			&& $this->nfs_videos->fetch($hash) === FALSE
			&& (is_dir($folder = "{$outdir}/{$hash}") || mkdir($folder, recursive: TRUE))) {
			$video = $ffmpeg($filename, '-hide_banner -loglevel error -stats -y');
			$key = $this->random(8);
			if (($poster
				? copy($poster, "{$folder}/0.jpg")
				: $video->jpeg("{$folder}/0.jpg")) === FALSE) break;
			if ($cover ? $video->preview($folder, $cover) === FALSE : FALSE) break;
			for ($i = 0; $i <= $cover; ++$i)
			{
				if (($this->maskfile("{$folder}/{$i}.jpg", "{$folder}/{$i}.cover", $key)
					&& unlink("{$folder}/{$i}.jpg")) === FALSE) break 2;
			}
			if ($video->m3u8($folder) === FALSE) break;
			if ($this->maskfile("{$folder}/ts.m3u8", "{$folder}/ts", $key) === FALSE) break;
			return ['hash' => $hash, 'size' => intval($video->duration), 'key' => bin2hex($key), 'poster' => 0, 'cover' => $cover];
		}
		if (isset($folder))
		{
			array_filter(glob("{$folder}/*"), unlink(...));
			rmdir($folder);
		}
		return [];
	}


	
	function post_clickad():int
	{
		$this->nfs_ads->likes($hash = $this->request_content('text/plain'));
		return 200;
	}
	function post_video(string $record):int
	{
		in_array($record, ['views', 'likes', 'shares'], TRUE)
			&& $this->nfs_videos->fetch($hash = $this->request_content('text/plain'))
			&& $this->nfs_videos->{$record}($hash);
		return 200;
	}
	function get_proxy(int $origin, string $m3u8):int
	{
		if (isset($this->proxy_origins[$origin]) && is_string($m3u8 = $this->url64_decode($m3u8)))
		{
			$this->response_content_type('application/vnd.apple.mpegurl');
			$this->echo("#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=0\n{$this->proxy_origins[$origin]}{$m3u8}");
			return 200;
		}
		return 404;
	}
	function cli_refresh_tasks()
	{
		function detect(string $haystack, array $needles, callable $method):bool
		{
			foreach ($needles as $needle)
			{
				if ($method($haystack, $needle)) return TRUE;
			}
			return FALSE;
		}
		$classifies = [];
		foreach ($this->nfs_classifies->search('LENGTH($.values)') as $classify)
		{
			$classifies[$classify['hash']] = [
				'name' => $classify['name'],
				'method' => $classify['method'],
				'values' => explode(',', $classify['values'])];
		}
		foreach ($this->nfs_videos as $video)
		{
			foreach ($classifies as $hash => $classify)
			{
				if (match ($classify['method'])
				{
					// 'intersect' => $video['tags'] && array_intersect(explode(',', $video['tags']), $subject['fetch_values']),
					// 'union' => count(array_intersect($subject['fetch_values'], explode(',', $video['tags']))) === count($subject['fetch_values']),
					'starts' => detect($video['name'], $classify['values'], str_starts_with(...)),
					'ends' => detect($video['name'], $classify['values'], str_ends_with(...)),
					'contains' => detect($video['name'], $classify['values'], str_contains(...)),
					// 'uploader' => in_array($video['userid'], $subject['fetch_values'], TRUE),
					// 'chns' => str_contains(strtolower($video['name']), $subject['fetch_values'][0]),
					// 'star' => str_contains(strtolower($video['name']), ".{$subject['fetch_values'][0]}."),
					default => FALSE }) {
					echo "{$video['hash']} -> {$classify['name']}\n";
					$this->nfs_videos->update($video['hash'], ['node' => $hash]);
					break;
				}
			}
		}
	}
	function local_video_uploader(string $dir, callable $format = NULL)
	{
		foreach (scandir($dir) as $file)
		{
			if (preg_match('/\.(mp4)$/i', $file) === 1)
			{
				$name = substr($file, 0, strrpos($file, '.'));
				is_file($poster = "{$dir}/{$name}.jpg") || $poster = NULL;
				if ($data = $this->format_video("{$dir}/$file", $dir, $poster, 9))
				{
					$data['name'] = $name;
					if ($format("{$dir}/{$data['hash']}", $data, "{$dir}/{$name}"))
					{
						array_filter(glob("{$dir}/{$data['hash']}/*"), unlink(...));
						rmdir("{$dir}/{$data['hash']}");
					}
				}
			}
		}
	}
	// function cli_local_video_uploader()
	// {
	// 	$this->local_video_uploader('X:/video_dir', function($dir, $data, $basename)
	// 	{
	// 		echo "{$dir}\n{$data['name']}\n";
	// 		#在这里处理影片DATA值
	// 		return (is_string($this->video_create($data))
	// 			&& $this->nfs_videos->upload_directory($data['hash'], $dir))
	// 			|| $this->nfs_videos->delete($data['hash']) === NULL;
	// 	});
	// }
}