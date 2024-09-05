<?php
class webapp_router_sitemap extends webapp_echo_sitemap
{
	private readonly array $tags;
	function get_google(int $page = NULL)
	{
		if ($page === NULL)
		{
			$entry = $this->webapp->request_entry(TRUE);
			$this->index();
			$lastmod = date('Y-m-d', $this->webapp->fetch_videos->time());
			$this->loc("{$entry},page:0", ['lastmod' => $lastmod]);
			$index = ceil($this->webapp->mysql->videos->count() / 1000);
			for ($i = 1; $i <= $index; ++$i)
			{
				$this->loc("{$entry},page:{$i}", ['lastmod' => $lastmod]);
			}
			return 200;
		}
		if ($page < 1)
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
			return 200;
		}
		$res = substr($this->webapp['app_resources'][1], 0, -10);
		$this->tags = $this->webapp->fetch_tags->shortname();
		$this->google();
		foreach ($this->webapp->mysql->videos('ORDER BY mtime DESC,hash ASC')->paging($page, 1000) as $video)
		{
			$ym = date('ym', $video['mtime']);
			$this->loc("{$this->origin}/?new/watch,hash:{$video['hash']}");
			$this->google_image("{$res}{$ym}/{$video['hash']}/cover.jpg");

			$tags = [];
			foreach (explode(',', $video['tags']) as $tag)
			{
				if (isset($this->tags[$tag]))
				{
					$tags[] = $this->tags[$tag];
				}
			}
			$description = [];
			if ($video['extdata'])
			{
				$extdata = array_filter(json_decode($video['extdata'], TRUE), trim(...));
				isset($extdata['issue']) &&		$description[] = "发行日期: {$extdata['issue']}";
				isset($extdata['actor']) &&		$description[] = "作者: {$extdata['actor']}";
				isset($extdata['publisher']) &&	$description[] = "发行商: {$extdata['publisher']}";
				isset($extdata['director']) &&	$description[] = "导演: {$extdata['director']}";
				isset($extdata['series']) &&	$description[] = "系列: {$extdata['series']}";
				isset($extdata['actress']) &&	$description[] = "女优: {$extdata['actress']}";
			}

			$this->google_video([
				'thumbnail_loc' => "{$res}{$ym}/{$video['hash']}/cover.jpg",
				'title' => $video['name'],
				'description' => join(', ', $description),
				'content_loc' => "{$res}{$ym}/{$video['hash']}/play.m3u8",
				'duration' => (string)$video['duration'],
				'tag' => $tags
			]);
		}
	}
}