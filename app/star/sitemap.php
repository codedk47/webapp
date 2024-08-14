<?php
class webapp_router_sitemap extends webapp_echo_sitemap
{
    function get_home()
	{
		$this->path(
			'/new',
			'/?new/search',
			'/?new/home,type:short',
			'/?new/home,type:DQFQ',
			'/?new/home,type:F2i7',
			'/?new/home,type:lqe2',
			'/?new/home,type:JGKx',
			'/?new/home,type:9Oi0',
			'/?new/home,type:fL83'
		);
	}
	function get_video()
	{
		foreach ($this->webapp->mysql->videos as $video)
		{
			$this->path("/?new/watch,hash:{$video['hash']}");
		}
	}
}