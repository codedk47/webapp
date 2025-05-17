<?php
class webapp_ext_vod_base extends webapp_ext_nfs_base
{
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
	function cid()
	{
		var_dump($this->request_header('cid'));
	}
	function access_log()
	{

	}
	function nfs_ads():webapp_nfs
	{
		return $this->nfs(0, function($data)
		{
			$data += json_decode($data['extdata'], TRUE);
			$data['src'] = $this->src($data);
			unset($data['extdata']);
			return $data;
		});
	}
	function nfs_videos():webapp_nfs
	{
		return $this->nfs(1, function($data)
		{
			$data += json_decode($data['extdata'], TRUE);
			$data['poster'] = $this->src($data, "/{$data['poster']}.cover");
			$data['m3u8'] = strstr($this->src($data, '/ts.m3u8'), '#', TRUE);
			unset($data['extdata']);
			return $data;
		});
	}
	function video_create(string $name, array $value = [], int $type = 2):?string
	{
		return $this->nfs_videos->create(['name' => $name,
			'hash' => $value['hash'] ?? $this->random_hash(FALSE),
			'type' => $type,//保留01为NFS使用，可以自定义的NFS扩展类型，比如当前影片状态，该值无法通过NFS方法修改
			'size' => $value['size'] ?? 0,//时长（秒）
			'key' => $value['key'] ?? bin2hex($this->random(8)),
			'extdata' => [
				'cover' => $value['cover'] ?? 0,//封面数量
				'poster' => $value['poster'] ?? 0,//海报封面
				'require' => $value['require'] ?? 0,//-1会员,0免费,大于0价格
				'tags' => $value['tags'] ?? '',//标签集
				'actors' => $value['actors'] ?? '',//演员集
				'subjects' => $value['subjects'] ?? ''//专题集
		]], 2);
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
		if ($uploadedfile->count() && $videos->fetch($hash, $data))
		{
			return $this->mysql->sync(fn() => $videos->update($hash, $data)
				&& $this->client->put($videos->filename($hash, '/0.cover'),
					$uploadedfile->open(0, TRUE, $data['key'])));
		}
		return $videos->update($hash, $data);
	}


	


	function format_duration(int $second):string
	{
		return sprintf('%02d:%02d:%02d', intval($second / 3600), intval(($second % 3600) / 60), $second % 60);
	}
	function format_video(string $filename, string $outdir, int $cover = 0):array
	{
		static $ffmpeg = static::libary('ffmpeg/interface.php');
		while (is_dir($outdir)
			&& is_string($hash = $this->hashfile($filename))
			&& is_dir($folder = "{$outdir}/{$hash}") === FALSE
			&& mkdir($folder)) {
			$video = $ffmpeg($filename, '-hide_banner -loglevel error -stats -y');
			$key = $this->random(8);
			if ($video->jpeg("{$folder}/0.jpg") === FALSE) break;
			if ($cover ? $video->preview($folder, $cover) === FALSE : FALSE) break;
			for ($i = 0; $i <= $cover; ++$i)
			{
				if (($this->maskfile("{$folder}/{$i}.jpg", "{$folder}/{$i}.cover", $key)
					&& unlink("{$folder}/{$i}.jpg")) === FALSE) break 2;
			}
			if ($video->m3u8($folder) === FALSE) break;
			return ['hash' => $hash, 'size' => intval($video->duration), 'key' => bin2hex($key), 'poster' => 0, 'cover' => $cover];
		}
		if (isset($folder))
		{
			array_filter(glob("{$folder}/*"), unlink(...));
			rmdir($folder);
		}
		return [];
	}
	function local_video_upload(string $dir, callable $format = NULL)
	{
		// foreach (scandir($dir) as $file)
		// {
		// 	if (preg_match('/\.(mp4)$/i', $file) === 1)
		// 	{
		// 		if ($data = $this->format_video("{$dir}/$file", $dir, 9))
		// 		{
		// 			$data['name'] = substr($file, 0, strrpos($file, '.'));
		// 		}

		// 		$format($dir, $name, );

		// 		var_dump($name);
		// 	}
			
		// }
	}
	
	function post_clickad():int
	{
		$hash = $this->request_content('text/plain');
		if ($this->nfs_ads->likes($hash))
		{
		}
		return 200;
	}
}