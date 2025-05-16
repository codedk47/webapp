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
			$data['src'] = $this->src($data);
			$data += json_decode($data['extdata'], TRUE);
			unset($data['extdata']);
			return $data;
		});
	}
	function nfs_videos():webapp_nfs
	{
		return $this->nfs(1, function($data)
		{
			//$data['poster'] = $this->src($data, "cover?{$data['t1']}");
			$data['poster'] = "/1/6SJ/CS0/2B7/LJ8/cove1?{$data['t1']}#{$data['key']}";
			$data['m3u8'] = strstr($this->src($data, 'play.m3u8'), '#', TRUE);
			$data += json_decode($data['extdata'], TRUE);
			unset($data['extdata']);
			return $data;
		});
	}
	function video_create(string $name, &$key, array $value = [], int $type = 2):?string
	{
		return $this->nfs_videos->create(['name' => $name,
			'size' => $duration = $value['duration'] ?? $value['size'] ?? 0,//时长（秒）
			'key' => bin2hex($key = $this->random(8)),
			'extdata' => [
				'require' => $value['require'] ?? 0,//-1会员,0免费,大于0价格
				'preview' => $value['preview'] ?? (intval($duration * 0.6) << 16 | 10),//预览16位
				'tags' => $value['tags'] ?? NULL,//标签集
				'actors' => $value['actors'] ?? NULL,//演员集
				'subjects' => $value['subjects'] ?? NULL,//专题集
		]], 2);
	}
	function video_delete(string $hash):bool
	{
		return FALSE;
	}
	function video_update(string $hash, array $value, string $cover):bool
	{
		$video = $this->nfs_videos;
		$data = ['name' => $value['name']];
		unset($value['name']);
		$data['extdata'] = $value;
		$uploadedfile = $this->request_uploadedfile($cover);
		if ($uploadedfile->count())
		{
			$stream = $uploadedfile->open(0, TRUE, $key);
			// var_dump(bin2hex($key));
			
			// var_dump( stream_copy_to_stream($stream, fopen('D:/wmhp/work/photo', 'w+')) );
			// return FALSE;


			$data['key'] = bin2hex($key);
			return $this->mysql->sync(fn() => $video->update($hash, $data)
				&& $this->client->put('/1/6SJ/CS0/2B7/LJ8/cove1', $stream));
				//&& $this->client->put($video->filename($hash) . '/cover', $stream));
		}
		return $video->update($hash, $data);
	}

	


	function format_duration(int $second):string
	{
		return sprintf('%02d:%02d:%02d', intval($second / 3600), intval(($second % 3600) / 60), $second % 60);
	}
	function format_video(string $filename, string $outdir, ?string $cover = NULL)
	{
		static $ffmpeg = static::libary('ffmpeg/interface.php');
		if (is_dir($outdir))
		{
			$video = $ffmpeg($filename, '-hide_banner -loglevel error -stats -y');
			print_r($video);



			//return $cover ? $video->m3u8($outdir) && $video->jpeg("{$outdir}/{$cover}") : $video->m3u8($outdir);
		}
		return FALSE;
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