
<?php
class ffmpeg implements Stringable
{
	const ffmpeg = __DIR__ . '/ffmpeg.exe', ffprobe = __DIR__ . '/ffprobe.exe';
	private array $options = ['-hide_banner -loglevel error -stats -y'];
	private readonly array $format, $audio, $video;
	static function exec(string $command, &$output = NULL):int
	{
		var_dump(sprintf('%s %s', static::ffmpeg, $command));
		return 0;
		// exec(sprintf('%s %s', static::ffmpeg, $command), $results, $retval);
		// $output = join(PHP_EOL, $results);
		// return $retval;
	}


	function __construct(private readonly string $filename)
	{
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
	function __invoke(string ...$options):int
	{
		return static::exec(sprintf('%s -i "%s" %s', join(' ', $this->options), $this->filename, join(' ', $options)), $this->output);
	}
	function __toString():string
	{
		return sprintf('%s %s -i "%s" %%s', static::ffmpeg, join(' ', $this->options), $this->filename);
	}

	function jpeg(string $filename, int $quality = 2):bool
	{
		return $this(sprintf('-qscale:v %d -frames:v 1 -f image2 "%s"', $quality & 0x1f, $filename)) === 0;
	}



	function cover(string $saveas, int $quality = 2):bool
	{
		return $this(sprintf('-qscale:v %d -frames:v 1 -f image2 "%s"', $quality & 0x1f, $saveas)) === 0;

	}
}
