<?php
class webapp_ext_vod_base extends webapp_ext_nfs_base
{
	const
	VOD_AD = 0,
	VOD_VIDEO = 1,
	VOD_ORIGINS = ['http://localhost'];
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
		foreach (static::VOD_ORIGINS as $origin)
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
	function fetch_ad(int $type = 0):array
	{
		$ad = [];
		foreach ($this->nfs(static::VOD_AD)->search(['type' => $type], 'extdata->"$.expire">?i ORDER BY extdata->"$.weight" DESC', $this->time()) as $data)
		{
			$ad[] = [
				'hash' => $data['hash'],
				'src' => $this->src($data)
			] + json_decode($data['extdata'], TRUE);
		}
		return $ad;
	}
	function post_clickad():int
	{
		$hash = $this->request_content('text/plain');
		if ($this->nfs(static::VOD_AD)->likes($hash))
		{
		}
		return 200;
	}
}