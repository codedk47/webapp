
<?php
class ffmpeg implements Stringable
{
	const ffmpeg = __DIR__ . '/ffmpeg.exe', ffprobe = __DIR__ . '/ffprobe.exe';
	public readonly int $duration;
	private readonly string $dirname;
	private readonly array $format, $audio, $video;
	private array $options = ['-hide_banner -loglevel error -stats -y'];
	static function exec(string ...$parameters):int
	{
		

		var_dump(sprintf('%s ' . array_shift($parameters), static::ffmpeg, ...$parameters));
		return 0;
		// exec(sprintf('%s %s', static::ffmpeg, $command), $results, $retval);
		// $output = join(PHP_EOL, $results);
		// return $retval;
	}
	// static function download_m3u8_as()
	// {
	// 	new static()
	// }


	function __construct(private readonly string $filename)
	{
		if ($probe = json_decode(shell_exec(static::ffprobe . " -v quiet -print_format json -show_format -show_streams -allowed_extensions ALL \"{$this->filename}\""), TRUE))
		{
			if (str_ends_with(strtolower($filename), '.m3u8'))
			{
				$this->options[] = '-allowed_extensions ALL';
			}
			$this->dirname = dirname(realpath($this->filename));
			$this->format = $probe['format'];
			$this->duration = (int)$this->format['duration'];
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
	function __invoke(string ...$parameters):bool
	{
		return static::exec('%s -i "%s" ' . array_shift($parameters), join(' ', $this->options), $this->filename, ...$parameters) === 0;
		//return static::exec(sprintf('%s -i "%s" %s', join(' ', $this->options), $this->filename, join(' ', $options)), $this->output);
	}
	function __debugInfo():array
	{
		return ['format' => $this->format, 'audio' => $this->audio, 'video' => $this->video];
	}
	function __toString():string
	{
		return sprintf('%s %s -i "%s" %%s', static::ffmpeg, join(' ', $this->options), $this->filename);
	}


	function cover(string $filename = NULL, int $quality = 2):bool
	{
		return $this('-qscale:v %d -frames:v 1 -f image2 "%s"',
			$quality & 0x1f, $filename ?? "{$this->dirname}/cover.jpg");
	}
	function preview(string $filename = NULL):bool
	{
		return $this('-vf "select=\'lte(mod(t,%d),1)\',scale=-1:240,setpts=N/FRAME_RATE/TB" -an "%s"',
			$this->duration / 10, $filename ?? "{$this->dirname}/preview.webm");
	}
	function download(string $filename, array $headerset = []):bool
	{
		$headers = [];
		foreach ($headerset as $name => $value)
		{
			$headers[] = addslashes("{$name}: {$value}");
		}
		return $this('-c copy -bsf:a aac_adtstoasc "%s"', $filename);
	}
}
