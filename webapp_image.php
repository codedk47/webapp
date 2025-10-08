<?php
declare(strict_types=1);
class webapp_image implements IteratorAggregate
{
	private $width, $height;
	function __construct(private GdImage $image)
	{
		$this->width = imagesx($image);
		$this->height = imagesy($image);
	}
	function __destruct()
	{
		imagedestroy($this->image);
	}
	function getIterator():Traversable
	{
		for ($y = 0; $y < $this->height; ++$y)
		{
			for ($x = 0; $x < $this->width; ++$x)
			{
				yield $x => $y;
			}
		}
	}
	// function copy():?static
	// {
	// 	$image = imagecreatetruecolor($this->width, $this->height);
	// 	return imagecopy($image, $this->image, 0, 0, 0, 0, $this->width, $this->height) ? new static($image) : NULL;
	// }
	function colorallocate(int $red, int $green, int $blue):int
	{
		return imagecolorallocate($this->image, $red, $green, $blue);
	}
	function colorallocatealpha(int $red, int $green, int $blue, int $alpha):int
	{
		return imagecolorallocatealpha($this->image, $red, $green, $blue, $alpha);
	}
	function colorat(int $x, int $y):int
	{
		return imagecolorat($this->image, $x, $y);
	}
	function arc(int $x, int $y, int $width, int $height, int $start, int $end, int $color, int $style = NULL)
	{
		($style === NULL ? 'imagearc' : 'imagefilledarc')($this->image, ...func_get_args());
		return $this;
	}
	function fill(int $x, int $y, int $color):static
	{
		imagefill($this->image, $x, $y, $color);
		return $this;
	}
	function line(int $from_x, int $from_y, int $to_x, int $to_y, int $color):static
	{
		imageline($this->image, $from_x, $from_y, $to_x, $to_y, $color);
		return $this;
	}
	function setpixel(int $x, int $y, int $color):static
	{
		imagesetpixel($this->image, $x, $y, $color);
		return $this;
	}
	// function polygon(array $points, bool $filled = FALSE, bool $open = FALSE):static
	// {
	// 	($filled ? 'imagefilledpolygon' : ($open ? 'imageopenpolygon' : 'imagepolygon'))($this->image, $points, count($points), $this->color);
	// 	return $this;
	// }
	// function rectangle(int $x0, int $y0, int $x1, int $y1, bool $filled = FALSE):static
	// {
	// 	($filled ? 'imagefilledrectangle' : 'imagerectangle')($this->image, $x0, $y0, $x1, $y1, $this->color);
	// 	return $this;
	// }
	// function square(int $x, int $y, int $size, bool $filled = FALSE):static
	// {
	// 	return $this->rectangle($x, $y, $x + $size, $y + $size, $filled);
	// }
	// function string(int $x, int $y, string $word, int $font = 4):static
	// {
	// 	imagestring($this->image, $font, $x, $y, $word, $this->color);
	// 	return $this;
	// }
	function ttftext(float $size, float $angle, int $x, int $y, int $color, string $fontfile, string $text):static
	{
		imagettftext($this->image, $size, $angle, $x, $y, $color, $fontfile, $text);
		return $this;
	}
	function resize(int $width, int $height):static
	{
		$image = imagecreate($width, $height);
		imagecopyresized($image, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
		imagedestroy($this->image);
		$this->image = $image;
		$this->width = $width;
		$this->height = $height;
		return $this;
	}
	//将真彩色图像转换为调色板图像
	function truecolortopalette(bool $dither = TRUE, int $ncolors = 255):static
	{
		imagetruecolortopalette($this->image, $dither, $ncolors);
		return $this;
	}
	//对图像使用过滤器
	function filter(int $filtertype, ...$params):static
	{
		imagefilter($this->image, $filtertype, ...$params);
		return $this;
	}
	//反转图像的所有颜色
	function filter_negate():static
	{
		return $this->filter(IMG_FILTER_NEGATE);
	}
	//将图像转换为灰度
	function filter_grayscale():static
	{
		return $this->filter(IMG_FILTER_GRAYSCALE);
	}
	//改变图像的亮度，用 arg1 设定亮度级别
	function filter_brightness(int $level):static
	{
		return $this->filter(IMG_FILTER_BRIGHTNESS, $level);
	}
	//改变图像的对比度，用 arg1 设定对比度级别
	function filter_contrast(int $level):static
	{
		return $this->filter(IMG_FILTER_CONTRAST, $level);
	}
	//与 IMG_FILTER_GRAYSCALE 类似，不过可以指定颜色。用 arg1，arg2 和 arg3 分别指定 red，blue 和 green。每种颜色范围是 0 到 255
	function filter_colorize(int $red, int $green, int $blue, int $alpha = 255):static
	{
		return $this->filter(IMG_FILTER_COLORIZE, $red, $green, $blue, $alpha);
	}
	//使用边缘检测来突出显示图像中的边缘
	function filter_edgedetect():static
	{
		return $this->filter(IMG_FILTER_EDGEDETECT);
	}
	//压花图像
	function filter_emboss():static
	{
		return $this->filter(IMG_FILTER_EMBOSS);
	}
	//用高斯算法模糊图像
	function filter_gaussian_blur():static
	{
		return $this->filter(IMG_FILTER_GAUSSIAN_BLUR);
	}
	//模糊图像
	function filter_selective_blur():static
	{
		return $this->filter(IMG_FILTER_SELECTIVE_BLUR);
	}
	//用平均移除法来达到轮廓效果
	function filter_mean_removal():static
	{
		return $this->filter(IMG_FILTER_MEAN_REMOVAL);
	}
	//使图像更平滑
	function filter_smooth(int $level = 1):static
	{
		return $this->filter(IMG_FILTER_SMOOTH, $level);
	}
	//将像素化效果应用于图像
	function filter_pixelate(int $block, bool $advanced = FALSE):static
	{
		return $this->filter(IMG_FILTER_PIXELATE, $block, $advanced);
	}
	function colortone(int $bit, int $length = -1):array
	{
		[$colors, $bit] = match ($bit)
		{
			8 => [array_fill(0, 255, 0), 8],
			4 => [array_fill(0, 16, 0), 4],
			default => [[0, 0], 1]
		};
		$to = [static::class, "to{$bit}bit"];
		foreach ($this as $x => $y)
		{
			++$colors[$to($this->colorat($x, $y))];
		}
		arsort($colors);
		return array_slice(array_keys(array_filter($colors)), 0, $length);
	}
	function octbit()
	{
		$i = 0;
		foreach ($this as $x => $y)
		{
			//$c = imagecolorat($this->image, $x, $y);
			$color = static::to8bit(imagecolorat($this->image, $x, $y));
			imagesetpixel($this->image, $x, $y, static::from8bit($color));
		}
		
		
		
	}
	// function wave(array $values = []):webapp_img
	// {
	// 	$values += ['x0' => 12, 'x1' => 14, 'y0' => 11, 'y1' => 5];
	// 	$values['x0'] *= mt_rand(1, 3);
	// 	$x = mt_rand(0, 100);
	// 	for ($i = 0; $i < $this->width; ++$i)
	// 	{
	// 		imagecopy($this->image, $this->image, sin($x + $i / $values['x0']) * $values['x1'], $i - 1, 0, $i, $this->width, 1);
	// 	}
	// 	$values['y0'] *= mt_rand(1, 3);
	// 	$y = mt_rand(0, 100);
	// 	for ($i = 0; $i < $this->width; ++$i)
	// 	{
	// 		imagecopy($this->image, $this->image, $i - 1, sin($y + $i / $values['y0']) * $values['y1'], $i, 0, 1, $this->height);
	// 	}
	// 	imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
	// 	return $this;
	// }
	//输出图像
	// function avif(mixed $output = 'php://output', int $quality = -1, int $speed = -1):bool
	// {
	// 	return imageavif($this->image, $output, $quality, $speed);
	// }
	function bmp(mixed $output = 'php://output', bool $compressed = TRUE):bool
	{
		return imagebmp($this->image, $output, $compressed);
	}
	function gif(mixed $output = 'php://output'):bool
	{
		return imagegif($this->image, $output);
	}
	function jpeg(mixed $output = 'php://output', int $quality = 75):bool
	{
		return imagejpeg($this->image, $output, $quality);
	}
	function png(mixed $output = 'php://output'):bool
	{
		return imagepng($this->image, $output);
	}
	function wbmp(mixed $output = 'php://output', ?int $foreground_color = NULL):bool
	{
		return imagewbmp($this->image, $output, $foreground_color);
	}
	function webp(mixed $output = 'php://output', int $quality = -1):bool
	{
		return imagewebp($this->image, $output, $quality);
	}
	function xbm(mixed $output = 'php://output', ?int $foreground_color = NULL):bool
	{
		return imagexbm($this->image, $output, $foreground_color);
	}
	//静态方法
	static function create(int $width, int $height):static
	{
		$image = new static(imagecreatetruecolor($width, $height));
		return $image->fill(0, 0, $image->colorallocate(255, 255, 255));
	}
	static function from(string $filename):?static
	{
		return ($image = is_array($info = @getimagesize($filename)) ? match($info[2])
		{
			1 =>		imagecreatefromgif($filename),
			2 =>		imagecreatefromjpeg($filename),
			3 =>		imagecreatefrompng($filename),
			6 =>		imagecreatefrombmp($filename),
			18 =>		imagecreatefromwebp($filename),
			15 =>		imagecreatefromwbmp($filename),
			16 =>		imagecreatefromxbm($filename),
			default =>	FALSE
		} : imagecreatefromstring($filename)) ? new static($image) : NULL;
	}




	static function hsl_decode(float $hue, float $saturation = 1, float $lightness = 0.5):int
	{
		if ($saturation)
		{
			$color = fn($p, $q, $t) => (($t < 0 ? $t += 1 : $t) > 1 ? $t -= 1 : $t) ? match (TRUE)
			{
				$t < 1 / 6 => $p + ($q - $p) * 6 * $t,
				$t < 1 / 2 => $q,
				$t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
				default => $p
			} : 0;
			$q = $lightness < 0.5 ? $lightness * (1 + $saturation) : $lightness + $saturation - $lightness * $saturation;
			$p = 2 * $lightness - $q;
			$r = $color($p, $q, $hue + 1 / 3);
			$g = $color($p, $q, $hue);
			$b = $color($p, $q, $hue - 1 / 3);
			return round($r * 255) << 16 | round($g * 255) << 8 | round($b * 255);
		}
		return 0;
	}
	# https://m.656463.com/wenda/ruhehuode8weiyanse_351
	static function randomcolor():int
	{
		return hexdec(bin2hex(webapp::random(3)));
	}
	static function rgb_encode(int $color):array
	{
		return [$color >> 16 & 0xff, $color >> 8 & 0xff, $color & 0xff];
	}
	static function rgb_decode(int $red, int $green, int $blue):int
	{
		return $red & 0xff << 16 | $green & 0xff << 8 | $blue & 0xff;
	}
	static function tohex(int $color):string
	{
		return str_pad(dechex($color), 6, '0', STR_PAD_LEFT);
	}
	static function fromhex(string $color):int
	{
		return hexdec($color);
	}
	//将颜色转化到256色
	static function to8bit(int $color):int
	{
		return $color >> 18 & 0b110000 | $color >> 12 & 0b1100 | $color >> 6 & 0b11;
	}
	static function from8bit(int $color):int
	{
		return $color << 18 & 0xc00000 | $color << 12 & 0xc000 | $color << 6 & 0xc0;
	}
	//将颜色转化到16色
	static function to4bit(int $color):int
	{
		return $color >> 21 & 0b100 | $color >> 14 & 0b10 | $color >> 7 & 0b1;
	}
	static function from4bit(int $color):int
	{
		return $color << 21 & 0x800000 | $color << 14 & 0x8000 | $color << 7 & 0x80;
	}









	static function captcha(array $result, int $width, int $height, string $font, int $size)
	{
		$offset = 0;
		$writing = [];
		$fix = $size * 0.4;
		for ($i = 0, $length = strlen($result[1]); $i < $length; ++$i)
		{
			$angle = $result[3][$i] * 0.4;
			$fixsize = $size + ceil($fix * ($result[2][$i] / 128));
			$calc = imagettfbbox($fixsize, $angle, $font, $result[1][$i]);
			$min_x = min($calc[0], $calc[2], $calc[4], $calc[6]);
			$max_x = max($calc[0], $calc[2], $calc[4], $calc[6]);
			$min_y = min($calc[1], $calc[3], $calc[5], $calc[7]);
			$max_y = max($calc[1], $calc[3], $calc[5], $calc[7]);
			$offset += ($writing[] = [
				'size'	=> $fixsize,
				'angle'	=> $angle,
				'left'	=> abs($min_x),
				'top'	=> abs($min_y),
				'width'	=> $max_x - $min_x,
				'height'=> $max_y - $min_y,
				'code'	=> $result[1][$i]
			])['width'];
		}
		$offset = intval(($width - $offset) * 0.5);
		$image = static::create($width, $height);
		foreach ($writing as $write)
		{
			$image->ttftext($write['size'],
				$write['angle'],
				$offset + $write['left'],
				intval(($image->height - $write['height']) * 0.5) + $write['top'],
				0,
				$font,
				$write['code']);
			$offset += $write['width'];
		}
		return $image;
	}
	static function qrcode(IteratorAggregate&Countable $draw, int $pixel = 4, int $margin = 4):static
	{
		$image = static::create($size = count($draw) + $margin * 2, $size);
		foreach ($draw as $x => $y)
		{
			$image->setpixel($x + $margin, $y + $margin, 0);
		}
		return $image->resize($size *= $pixel, $size);
	}
}