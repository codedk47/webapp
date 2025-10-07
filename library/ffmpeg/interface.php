<?php
class ffmpeg implements Stringable, Countable
{
	const ffmpeg = __DIR__ . '/ffmpeg.exe', ffprobe = __DIR__ . '/ffprobe.exe';
	static array $output;
	static int $retval = 0;
	public array $options = ['-hide_banner -loglevel error -stats -y'];
	private array $format, $audio = [], $video = [];
	static function exec(string ...$commands):bool
	{
		static::$output = [];
		$command = rtrim(sprintf('%s ' . array_shift($commands), static::ffmpeg, ...$commands));
		var_dump($command);
		//return FALSE;
		return is_string(exec($command, static::$output, static::$retval)) && static::$retval === 0;
	}
	static function help():?string
	{
		return static::exec('-h') ? join("\n", static::$output) : NULL;
	}
	static function probe(string $filename):array
	{
		return json_decode(shell_exec(static::ffprobe . " -v quiet -print_format json -show_format -show_streams -allowed_extensions ALL \"{$filename}\""), TRUE);
	}
	static function from_m3u8(string $filename):bool
	{
		$ffmpeg = new static($filename, FALSE);
		array_push($ffmpeg->options,
			'-user_agent "Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.182 Safari/537.36"',
			'-allowed_extensions ALL');
		return $ffmpeg;
	}

	static function quality()
	{
		// 1080 => [
		// 	'scale' => 'w=1920:h=1080',
		// 	'b:a' => '192k',
		// 	'b:v' => '5000k',
		// 	'maxrate' => '5350k',
		// 	'bufsize' => '7500k'
		// ],
		// 720 => [
		// 	'scale' => 'w=1280:h=720',
		// 	'b:a' => '128k',
		// 	'b:v' => '2800k',
		// 	'maxrate' => '2996k',
		// 	'bufsize' => '4200k'
		// ],
		// 480 => [
		// 	'scale' => 'w=842:h=480',
		// 	'b:a' => '128k',
		// 	'b:v' => '1400k',
		// 	'maxrate' => '1498k',
		// 	'bufsize' => '2100k',
		// ],
		// 360 => [
		// 	'scale' => 'w=640:h=360',
		// 	'b:a' => '96k',
		// 	'b:v' => '800k',
		// 	'maxrate' => '856k',
		// 	'bufsize' => '1200k',
		// ]
	}
	function __construct(string $filename)
	{
		if (array_key_exists('format', $probe = static::probe($filename)))
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
	function __toString():string
	{
		return $this->format['filename'];
	}
	function count():int
	{
		return intval($this->format['duration']);
	}
	function __debugInfo():array
	{
		return ['format' => $this->format, 'audio' => $this->audio, 'video' => $this->video];
	}
	function __invoke(array|string $options, string ...$commands):bool
	{
		$fotmat = is_array($options) ? join(' ', $options) : $options;
		return static::exec("%s -i \"%s\" {$fotmat}", join(' ', $this->options), $this, ...$commands);
	}
	function copyto(string $filename):bool
	{
		return $this("-c copy {$filename}");
	}
	function audio(string $filename, string $type = NULL):bool
	{
		return $this('-vn -c:a %s -b:a %d %s', match ($type
			??= is_string($suffix = strrchr($filename, '.')) ? strtolower(substr($suffix, 1)) : NULL) {
			'aac' => 'aac -f aac',
			'flac' => 'flac -f flac',
			'mp3' => 'libmp3lame -f mp3',
			'opus' => 'libopus -f opus',
			'wav' => 'pcm_s16le -f wav',
			default => $type
		}, $this->format['bit_rate'], $filename);
	}
	function video(string|array $filename, ?int $quality = NULL, bool $return = FALSE):bool|string
	{
		$command = [
			'-crf 18',
			/*
				针对 x264 和 x265 等编码器的基于质量的速率控制，允许您设置目标感知质量而不是固定比特率。
				您可以指定一个介于 0 到 51 之间的值，其中数字越低表示质量越好、文件越大；数字越高表示压缩程度越高、文件越小。
				默认值为 23，“合理”或主观上较好的范围通常为 17-28
			*/
			//'-preset slower',#使用 h264_nvenc 编码必须关闭此选项
			/*
				ultrafast,superfast,veryfast,faster,fast,medium,slow,slower,veryslow
				编码速度与压缩比。较慢的预设会提供更好的压缩率（压缩率是指文件大小对应的质量）。
				这意味着，例如，如果您的目标是特定的文件大小或恒定的比特率，则使用较慢的预设可以获得更好的质量。
				同样，对于恒定质量的编码，选择较慢的预设只会节省比特率。
			*/
			//'-tune fastdecode',
			/*
				film – 用于高质量电影内容；降低去块效应
				animation – 适用于动画片；使用更高的去块效应和更多参考帧
				grain – 保留老旧颗粒状胶片素材中的颗粒结构
				stillimage – 适用于类似幻灯片的内容
				fastdecode – 通过禁用某些滤镜实现更快的解码
				zerolatency – 适用于快速编码和低延迟流媒体播放
				例如，如果您的输入是动画，则使用动画调整；如果您想保留胶片中的颗粒感，则使用颗粒调整。
				如果您不确定要使用什么，或者您的输入与任何调整选项都不匹配，请忽略 -tune 选项。
			*/
			'-c:a aac',
			'-c:v hevc_nvenc',/*
				视频统一采用 H264 编码
				https://trac.ffmpeg.org/wiki/Encode/H.264
				https://trac.ffmpeg.org/wiki/Encode/H.265
			*/
			//'-profile:v high',
			/*
				选项将输出限制为特定的 H.264 配置文件。
				baseline,main,high
				high10 (first 10 bit compatible profile)
				high422 (supports yuv420p, yuv422p, yuv420p10le and yuv422p10le). With -g 1 -pix_fmt yuv422p10le, High 4:2:2 Intra Profile.
				high444 (supports as above as well as yuv444p and yuv444p10le). With -g 1 -pix_fmt yuv444p10le, High 4:4:4 Intra Profile.
				某些设备（大多非常老旧或已淘汰）仅支持功能较为有限的 Constrained Baseline 或 Main 配置文件。
				您可以使用 -profile:v baseline 或 -profile:v main 设置这些配置文件。
				大多数现代设备都支持更高级的 High 配置文件。
			*/
			//'-level:v 4.0',
			//$quality
			...is_string($filename) ? [$filename] : $filename
		];
		return $return ? join(' ', $command) : $this($command);
	}
	function m3u8(string $outdir, int $maxquality = 720)
	{
		return file_put_contents($key = "{$outdir}/key", random_bytes(16))
			&& file_put_contents($tmp = "{$outdir}/tmp", join("\n", ['key', $key, bin2hex(random_bytes(16))]))
			&& $this->video([
			'-sc_threshold 0',	//不要在场景变化时创建关键帧，仅根据-g
			'-g 47',			//每47帧（约2秒）创建关键帧（I帧）
			'-keyint_min 47',	//稍后将影响片段的正确切片和移交的对齐
			'-f hls',
			'-start_number 0',
			'-hls_time 10',
			'-hls_list_size 0',
			'-hls_playlist_type vod',
			"-hls_key_info_file \"{$tmp}\"",
			"-hls_segment_filename \"{$outdir}/ts%%d\"",
			"\"{$outdir}/index.m3u8\""

		]);
	}
	function preview_image(string $outdir, int $count = 9):bool
	{
		return $this([
			'-c:v webp',
			'-frames:v %d -r %f',
			"-an {$outdir}/%%d.webp"
		], $count, 1 / floor(count($this) / $count));
	}
	function preview_video(string $filename)
	{
		$this([
			'-c:v webp',
			'-b:v 480k',
			'-vf "select=\'lte(mod(t,%d),1)\',scale=-1:240,setpts=N/FRAME_RATE/TB,fps=fps=15"',
			'-an "%s"',
		], count($this) / 10, $filename);
	}

	// function preview_image(string $outdir, int $count = 9, int $quality = 2):bool
	// {
	// 	return $this('-qscale:v %d -frames:v %d -r %f -f image2 "%s/%%d.jpg"',
	// 		$quality & 0x1f, $count, 1 / floor(count($this) / $count), $outdir);
	// }
	
	// function preview_video(string $filename)
	// {
	// 	$this('-vf "select=\'lte(mod(t,%d),1)\',scale=-1:240,setpts=N/FRAME_RATE/TB,fps=fps=15" -b:v 480k -vcodec h264 -an "%s"',
	// 		count($this) / 10, $filename);
	// }



}
return fn(string $filename) => new ffmpeg($filename);