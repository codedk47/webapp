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
			$data['poster'] = $this->src($data, "cover?{$data['t1']}");
			$data['m3u8'] = strstr($this->src($data, 'play.m3u8'), '#', TRUE);
			return $data;
		});
	}
	function video_create(string $name, &$key)
	{
		var_dump( $this->nfs(static::VOD_VIDEO)->create(['name' => $name,
			'key' => bin2hex($key = $this->random(8)),
			'extdata' => [
				'duration' => 0,	//时长
				'require' => 0,		//-1会员,0免费,大于0价格
				'preview' => 0,		//预览16位
				'subjects' => '',	//专题集
				'tags' => ''		//标签集
		]], 2) );
	}
	function video_delete(string $hash){}
	function video_update(string $hash){}

	


	function fetch_ad(int $type = 0):array
	{
		return iterator_to_array(($this->nfs_ads)('extdata->"$.type"=?s AND extdata->"$.expire">?i ORDER BY extdata->"$.weight" DESC', $type, $this->time));
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