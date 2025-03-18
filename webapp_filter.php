<?php
class webapp_filter_debug extends php_user_filter
{
	//注意：过滤流在内部读取时只能过滤一个队列，这是一个BUG？
	function filter($in, $out, &$consumed, $closing):int
	{
		echo "\r\n", $consumed === NULL
			? '>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>'
			: '<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<',
			"\r\n";
		while ($bucket = stream_bucket_make_writeable($in))
		{
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
			echo quoted_printable_encode($bucket->data);
		}
		return PSFS_PASS_ON;
	}
}
stream_filter_register('webapp.filter_debug', 'webapp_filter_debug');
class webapp_filter_mask extends php_user_filter
{
	private int $offset = 0;
	private array $key = [];
	private readonly string $method;
	function onCreate():bool
	{
		return ($this->params === NULL || ((is_array($this->params)
			? (count($this->key = $this->params) === 8)
			: (is_string($this->params) && strlen($this->params) === 8))))
			&& method_exists($this, $this->method = substr(strrchr($this->filtername, '.'), 1));
	}
	function filter($in, $out, &$consumed, $closing):int
	{
		while ($bucket = stream_bucket_make_writeable($in))
		{
			$i = 0;
			if (count($this->key) < 8)
			{
				switch ($this->method)
				{
					case 'encode':
						if (is_string($this->params))
						{
							$i = 8;
							$bucket->data = $this->params . $bucket->data;
							$bucket->datalen += 8;
							$this->offset += 8;
							$this->key = array_map(ord(...), str_split($this->params));
						}
						break;
					case 'decode':
						if ($this->params === NULL)
						{
							for ($n = 0; count($this->key) < 8 && $n < $bucket->datalen; ++$n)
							{
								$this->key[] = ord($bucket->data[$n]);
							}
							$bucket->data = substr($bucket->data, $n);
							$bucket->datalen -= $n;
						}
						break;
				}
			}
			for (;$i < $bucket->datalen; ++$i)
			{
				$bucket->data[$i] = chr($this->{$this->method}(ord($bucket->data[$i]), $this->offset++ % 8));
			}
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
	}
	function encode(int $code, int $offset):int
	{
		return $this->key[$offset] = $code ^ $this->key[$offset];
	}
	function decode(int $code, int $offset):int
	{
		return $this->key[$offset] ^ $this->key[$offset] = $code;
	}
}
stream_filter_register('webapp.filter_mask.*', 'webapp_filter_mask');