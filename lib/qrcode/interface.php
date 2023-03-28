<?php
define('QR_MODE_8', 2);
// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_FROM_RANDOM', 2);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false

define('QRSPEC_VERSION_MAX', 40);
define('QRSPEC_WIDTH_MAX',   177);
define('QRCAP_WIDTH',        0);
define('QRCAP_WORDS',        1);
define('QRCAP_REMINDER',     2);
define('QRCAP_EC',           3);

define('N1', 3);
define('N2', 3);
define('N3', 40);
define('N4', 10);

class qrstr {
	public static function set(&$srctab, $x, $y, $repl, $replLen = false) {
		$srctab[$y] = substr_replace($srctab[$y], ($replLen !== false)?substr($repl,0,$replLen):$repl, $x, ($replLen !== false)?$replLen:strlen($repl));
	}
}	

include 'qrspec.php';
include 'qrinputitem.php';
include 'qrinput.php';
include 'qrbitstream.php';
include 'qrrsitem.php';
include 'qrrs.php';
include 'qrmask.php';
include 'qrrsblock.php';
include 'qrrawcode.php';
include 'qrframefiller.php';
class qrcode implements IteratorAggregate, Countable
{
	private array $data;
	function __construct(string $string, int $level, int $mode = QR_MODE_8)
	{
		// switch (1)
		// {
		// 	case preg_match('/^[0-9]+$/', $data): $mode = QR_MODE_NUM; break;
		// 	case preg_match('/^[0-9A-Z\$%\*\+\–\.\/\: ]+$/', $data): $mode = QR_MODE_AN; break;
		// 	default: $mode = QR_MODE_8;
		// }
		$input = new QRinput(0, $level);
		$input->append($mode, strlen($string), str_split($string));

		$raw = new QRrawcode($input);

		$version = $raw->version;
		$width = QRspec::getWidth($version);
		$frame = QRspec::newFrame($version);

		$filler = new QRFrameFiller($width, $frame);
		if(is_null($filler)) {
			return NULL;
		}

		// inteleaved data and ecc codes
		for($i=0; $i<$raw->dataLength + $raw->eccLength; $i++) {
			$code = $raw->getCode();
			$bit = 0x80;
			for($j=0; $j<8; $j++) {
				$addr = $filler->next();
				$filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
				$bit = $bit >> 1;
			}
		}
		
		unset($raw);
		
		// remainder bits
		$j = QRspec::getRemainder($version);
		for($i=0; $i<$j; $i++) {
			$addr = $filler->next();
			$filler->setFrameAt($addr, 0x02);
		}
		
		$frame = $filler->frame;
		unset($filler);
		
		
		// masking
		$maskObj = new QRmask();
		$this->data = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
		// $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());

		// $this->version = $version;
		// $this->width = $width;
		// $this->data = $masked;
		
		// return $masked;
	}
	function getIterator():Traversable
	{
		$size = count($this);
		for ($y = 0; $y < $size; ++$y)
		{
			for ($x = 0; $x < $size; ++$x)
			{
				if (ord($this->data[$x][$y]) & 1)
				{
					yield $x => $y;
				}
			}
		}
	}
	function count():int
	{
		return count($this->data);
	}
}
return fn(string $data, int $level, int $mode = QR_MODE_8) => new qrcode($data, $level, $mode);