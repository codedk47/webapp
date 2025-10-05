<?php
class ffmpeg
{
	const
	ffmpeg = __DIR__ . '/ffmpeg.exe',
	ffprobe = __DIR__ . '/ffprobe.exe',
	quality = [
		1080 => [
			'scale' => 'w=1920:h=1080',
			'b:a' => '192k',
			'b:v' => '5000k',
			'maxrate' => '5350k',
			'bufsize' => '7500k'
		],
		720 => [
			'scale' => 'w=1280:h=720',
			'b:a' => '128k',
			'b:v' => '2800k',
			'maxrate' => '2996k',
			'bufsize' => '4200k'
		],
		480 => [
			'scale' => 'w=842:h=480',
			'b:a' => '128k',
			'b:v' => '1400k',
			'maxrate' => '1498k',
			'bufsize' => '2100k',
		],
		360 => [
			'scale' => 'w=640:h=360',
			'b:a' => '96k',
			'b:v' => '800k',
			'maxrate' => '856k',
			'bufsize' => '1200k',
		]
	];
	private $format = [], $audio = [], $video = [], $fixed = 1, $options, $filename, $output;
	function __construct(string $filename, string $option = '-hide_banner -loglevel error -stats -y')
	{
		$this->options = [$option];
		$this->filename = realpath($filename);
		if ($probe = json_decode(shell_exec(static::ffprobe . " -v quiet -print_format json -show_format -show_streams \"{$this->filename}\""), TRUE))
		{
			$this->format = $probe['format'];
			foreach ($probe['streams'] as $stream)
			{
				switch ($stream['codec_type'])
				{
					case 'audio': $this->audio = $stream; break;
					case 'video': $this->video = $stream; break;
				}
			}
		}
	}
	function __get(string $name)
	{
		switch ($name)
		{
			case 'filename':	return $this->filename;							//完整文件名称
			case 'dirname':		return dirname($this->filename);				//获取文件所在文件夹
			case 'is_audio':	return boolval($this->audio);					//判断是否含有音频
			case 'is_video':	return boolval($this->video);					//判断是否含有视频
			case 'size':		return intval($this->format('size'));			//格式：文件大小（字节）
			case 'duration':	return floatval($this->format('duration'));		//格式：持续时间（秒）
			case 'level':		return intval($this->video('level'));			//视频：等级（清晰度）
		}
	}
	function __invoke(string ...$options):int
	{
		return static::exec(sprintf('%s -i "%s" %s', join(' ', $this->options), $this->filename, join(' ', $options)), $this->output);
	}
	function __toString():string
	{
		return $this->output ?? static::help();
	}
	function options(string ...$options):self
	{
		array_splice($this->options, $this->fixed, count($this->options), $options);
		return $this;
	}
	function fixed(string ...$options):self
	{
		$this->options(...$options);
		$this->fixed = count($this->options);
		return $this;
	}
	function offset(string $start, string $end = NULL):self
	{
		$offset = ['-ss', $start];
		if ($end)
		{
			$offset[] = '-to';
			$offset[] = $end;
		}
		return $this->options(...$offset);
	}
	//格式信息
	function format(string $name)
	{
		return $this->format[$name] ?? NULL;
	}
	//音频信息
	function audio(string $name)
	{
		return $this->audio[$name] ?? NULL;
	}
	//视频信息
	function video(string $name)
	{
		return $this->video[$name] ?? NULL;
	}
	//静态：执行
	static function exec(string $command, &$output = NULL):int
	{
		exec(sprintf('%s %s', static::ffmpeg, $command), $results, $retval);
		$output = join(PHP_EOL, $results);
		return $retval;
	}
	//静态：帮助
	static function help():string
	{
		return static::exec('-h', $help) === 0 ? $help : NULL;
	}
	//静态：实例化
	static function from(string $filename):self
	{
		return new static($filename);
	}
	//静态：扫描目录
	static function scandir(string $dirname, string $pattern = '/\.(mov|mp[1-4])$/i'):Traversable
	{
		if (is_dir($dirname))
		{
			foreach (scandir($dirname) as $filename)
			{
				if (preg_match($pattern, $filename))
				{
					yield static::from("{$dirname}/{$filename}");
				}
			}
		}
	}
	//获取当前文件名称，是否带有后缀
	function basename(bool $suffix = FALSE):string
	{
		return basename($this->filename, $suffix ? NULL : strrchr($this->filename, '.'));
	}
	//创建文件夹
	function mkdir(string $dirname)
	{
		return is_dir($dirname) || @mkdir($dirname, recursive: TRUE) ? realpath($dirname) : NULL;
	}
	//JPEG is 2-31 with 31 being the worst quality.
	function jpeg(string $filename, int $quality = 2):bool
	{
		return $this(sprintf('-qscale:v %d -frames:v 1 -f image2 "%s"', $quality & 0x1f, $filename)) === 0;
	}
	// function preview(string $filename = NULL):bool
	// {
	// 	return $this('-vf "select=\'lte(mod(t,%d),1)\',scale=-1:240,setpts=N/FRAME_RATE/TB,fps=fps=15" -b:v 480k -vcodec h264 -an "%s"',
	// 		$this->duration / 10, $filename ?? "{$this->dirname}/preview.mp4");
	// }
	function preview(string $outdir, int $count = 10, int $quality = 2):bool
	{
		return ($dirname = $this->mkdir($outdir))
			&& $this(sprintf('-qscale:v %d -frames:v %d -r %f -f image2 "%s/%%d.jpg"',
				$quality & 0x1f, $count, 1 / floor($this->duration / $count), $dirname)) === 0;
	}
	//视频质量选项
	private function v_quality(int $type, bool $strict = FALSE)
	{
		if (array_key_exists($type, static::quality))
		{
			$quality = array_values(static::quality[$type]);
			$options = [//https://docs.peer5.com/guides/production-ready-hls-vod/
				'-vf scale=%s:force_original_aspect_ratio=decrease',//保持宽高比的同时将视频缩放
				'-c:a aac',			//将音频编解码器设置为AAC
				'-ar 48000',		//音频采样率为48kHz
				'-b:a %s',			//音频比特率
				'-c:v h264',		//将视频编解码器设置为H264，这是HLS段的标准编解码器
				'-profile:v main',	//将H264配置文件设置为main，这意味着对现代设备的支持
				'-crf 20',			//恒定速率因子，高水平的整体质量因子
				'-sc_threshold 0',	//不要在场景变化时创建关键帧，仅根据-g
				'-g 48',			//每48帧（约2秒）创建关键帧（I帧）
				'-keyint_min 48',	//稍后将影响片段的正确切片和移交的对齐
				'-b:v %s',			//限制视频比特率，这些特定于演绎版本，并取决于您的内容类型
				'-maxrate %s',
				'-bufsize %s',
				// '-hls_list_size 0',
				// '-hls_segment_size 262144',
				'-hls_time 4',		//细分目标持续时间（以秒为单位）实际长度受关键帧限制
				'-hls_playlist_type vod'
			];
			if ($strict === FALSE)
			{
				unset($quality[0], $options[0]);
			}
			return sprintf(join(' ', $options), ...$quality);
		}
	}
	//视频可支持的质量
	private function v_qualityable():array
	{
		//https://support.google.com/youtube/answer/2853702?hl=zh-Hans
		$bit_type = [300000, 400000, 500000, 1500000, 3000000, 6000000, 13000000];
		$bit_rate = intval($this->video('bit_rate'));
		for ($i = count($bit_type); --$i;)
		{
			if ($bit_rate > $bit_type[$i])
			{
				return array_slice([240, 360, 480, 720, 1080, 1440, 2160], 0, $i + 1);
			}
		}
		return [360];
	}
	//m3u8
	function m3u8_play(string $dirname, array $quality):bool
	{
		$bandwidth = [
			360 => 800000,
			480 => 1400000,
			720 => 2800000,
			1080 => 5000000
		];
		$playlist = ['#EXTM3U', '#EXT-X-VERSION:3'];
		foreach ($quality as $type)
		{
			if (array_key_exists($type, $bandwidth))
			{
				$playlist[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth[$type]}";
				$playlist[] = "{$type}p.m3u8";
			}
		}
		return count($playlist) > 2 && file_put_contents("{$dirname}/play.m3u8", iconv('ASCII','UTF-8', join("\n", $playlist))) !== FALSE;
	}
	function m3u8_multi_quality(string $outdir, array $allow = [1080, 720, 480, 420], bool $strict = FALSE):bool
	{
		if (($dirname = $this->mkdir($outdir))
			&& ($quality = array_intersect($this->v_qualityable(), array_keys(static::quality), $allow))
			&& file_put_contents($keycode = "{$dirname}/keycode", random_bytes(16))
			&& file_put_contents($keyinfo = "{$dirname}/keyinfo", join("\n", ['keycode', $keycode, bin2hex(random_bytes(16))]))) {
			foreach ($quality as $type)
			{
				$options[] = $this->v_quality($type);
				$options[] = "-hls_key_info_file \"{$keyinfo}\"";
				$options[] = "-hls_segment_filename \"{$dirname}/{$type}p%04d.ts\" \"{$dirname}/{$type}p.m3u8\"";
			}
			return $this(...$options) === 0 && unlink($keyinfo) && $this->m3u8_play($dirname, $quality);
		}
		return FALSE;
	}
	function m3u8(string $outdir, int $quality = 720):bool
	{
		return ($dirname = $this->mkdir($outdir))
			&& file_put_contents($keycode = "{$dirname}/keycode", random_bytes(16))
			&& file_put_contents($keyinfo = "{$dirname}/keyinfo", join("\n", ['keycode', $keycode, bin2hex(random_bytes(16))]))
			&& is_string($option = $this->v_quality(in_array($quality, $qualityable = $this->v_qualityable(), TRUE) ? $quality : end($qualityable)))
			&& $this($option, "-hls_key_info_file \"{$keyinfo}\"", "-hls_segment_filename \"{$dirname}/ts%d\" \"{$dirname}/ts.m3u8\"") === 0
			&& unlink($keyinfo);
	}
	//m3u8转化mp4
	static function m3u8convert(string $src, string $dst, array $headerset = []):bool
	{
		$headers = [];
		foreach ($headerset as $name => $value)
		{
			$headers[] = addslashes("{$name}: {$value}");
		}
		return static::exec(sprintf('%s-i "%s" -c copy -bsf:a aac_adtstoasc "%s"',
			$headers ? '-headers "' . join('\r\n', $headers) . '" ' : '', $src, $dst)) === 0;
	}
}