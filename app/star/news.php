<?php
class webapp_router_news extends webapp_echo_html
{
	private string $origin = 'https://3minz.com';
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title($this->webapp['app_name']);

		$this->xml->head->link['href'] = '/webapp/app/star/news.css?' . $this->webapp->random_hash(TRUE);
		$this->script(['src' => '/webapp/app/star/news.js?v=jk']);
		$this->footer[0] = NULL;
	}
	function add_meta_seo(string $keywords, string $description)
	{
		$this->meta(['name' => 'keywords', 'content' => $keywords]);
		$this->meta(['name' => 'description', 'content' => $description]);
	}

	function set_header_nav()
	{
		$this->header->append('a', ['href' => '?news'])->svg()->icon('markdown', 24);
		$this->header->append('input', ['type' => 'search']);
		$this->header->append('a', ['href' => '?news/search,keywords:high'])->svg()->icon('search', 24);
		//$this->header->append('a', ['href' => '?news/user'])->svg()->icon('person', 24);
	}


	function add_div_videos(webapp_html $node, iterable $videos, int $page = 1, int $size = 20):webapp_html
	{
		$pagination = $videos instanceof webapp_redis_table;
		$element = $node->append('div', ['class' => 'videos']);
		foreach ($pagination ? $videos->paging($page, $size) : $videos as $video)
		{
			$anchor = $element->append('a', ['href' => "?news/watch,hash:{$video['hash']}"]);
			$anchor->figure($this->origin . substr($video['poster'], 1, 24) . '.jpg');

			

			$anchor->append('strong', preg_replace('/[^\w]+/', '', $video['name']));
		}
		if ($pagination && ($max = ceil($videos->count() / $size)) > 1)
		{
			$size = max(1, $size);
			$url = $this->webapp->at(['page' => '']);
			$page = $node->append('div', ['class' => 'pages']);
			$show = 8;
			if ($max > $show)
			{
				$halved = intval($show * 0.5);
				$offset = min($max, max($size, $halved) + $halved);
				$ranges = range(max(1, $offset - $halved * 2 + 1), $offset);
				$size > 1 && $page->append('a', ['Top', 'href' => "{$url}1"]);
				foreach ($ranges as $index)
				{
					$curr = $page->append('a', [$index, 'href' => "{$url}{$index}"]);
					if ($index == $size)
					{
						$curr['class'] = 'selected';
					}
				}
				$size < $max && $page->append('a', ['End', 'href' => $url . $max]);
			}
			else
			{
				for ($i = 1; $i <= $max; ++$i)
				{
					$curr = $page->append('a', [$i, 'href' => "{$url}{$i}"]);
					if ($i === $size)
					{
						$curr['class'] = 'selected';
					}
				}
			}
		}


		return $element;
	}

	function add_ads_video()
	{
		
	}


	function get_home()
	{
		$this->add_meta_seo($this->webapp['app_name'], $this->webapp['app_name']);
		$this->set_header_nav();

		//$this->main->append('h1', 'test');


		$this->add_div_videos($this->main, $this->webapp->fetch_videos->with('FIND_IN_SET("lqe2",tags)')->paging(1, 30));
	}

	function get_watch(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->set_header_nav();


		$this->main['class'] = 'watch';


		$this->tags = $this->webapp->fetch_tags->shortname();
		$player = $this->main->append('div', ['class' => 'player']);
		if ($video = $this->webapp->fetch_videos[$hash])
		{
			$this->title("{$this->webapp['app_name']} {$video['name']}");
			$watch = $player->append('webapp-video', [
				'data-poster' => $this->origin . substr($video['poster'], 1, 24) . '.jpg',
				'data-m3u8' => $this->origin . substr($video['m3u8'], 1, 23) . '.m3u8',
				'oncanplay' => 'console.log(this)',
				//'autoheight' => NULL,
				//'autoplay' => NULL,
				//'muted' => NULL,
				'controls' => NULL
			]);
			$videoinfo = $player->append('div', ['class' => 'videoinfo']);
			$videoinfo->append('strong', preg_replace('/[^\w]+/', '', $video['name']));
			$tags = [];
			if ($video['tags'])
			{
				$taginfo = $videoinfo->append('div', ['data-label' => 'Label:']);
				foreach (explode(',', $video['tags']) as $tag)
				{
					if (isset($this->tags[$tag]))
					{
						
						$tags[$tag] = $this->tags[$tag];
						$taginfo->append('a', [$this->tags[$tag], 'href' => 'javascript:;']);
					}
					
				}
			}
			$this->add_meta_seo(join(' ', array_values($tags)), $video['name']);
			//影片信息（扩展数据）
			if ($video['extdata'])
			{
				$extdata = array_filter(json_decode($video['extdata'], TRUE), trim(...));
				isset($extdata['issue']) && $videoinfo->append('div', [$extdata['issue'], 'data-label' => 'Issue:']);

				isset($extdata['actor']) && $videoinfo->append('div', ['data-label' => 'Actor:'])
					->append('a', [$extdata['actor'], 'href' => 'javascript:;']);
				isset($extdata['publisher']) && $videoinfo->append('div', ['data-label' => 'Publisher:'])
					->append('a', [$extdata['publisher'], 'href' => 'javascript:;']);
				isset($extdata['director']) && $videoinfo->append('div', ['data-label' => 'Director:'])
					->append('a', [$extdata['director'], 'href' => 'javascript:;']);
				isset($extdata['series']) && $videoinfo->append('div', ['data-label' => 'Series:'])
					->append('a', [$extdata['series'], 'href' => 'javascript:;']);

				if (isset($extdata['actress']))
				{
					$extinfo = $videoinfo->append('div', ['data-label' => 'Actress:']);
					foreach (explode(',', $extdata['actress']) as $actress)
					{
						$extinfo->append('a', [$actress, 'href' => 'javascript:;']);
					}
				}
			}

		}
		else
		{
			
			$player->append('strong', '您所观看的影片不见啦 :(');
		}
		
		
		$this->add_div_videos($player, $this->webapp->fetch_videos->with('FIND_IN_SET("lqe2",tags)')->paging(1, 8))['class'] = 'playleft';

		$this->add_div_videos($this->main, $this->webapp->fetch_videos->with('FIND_IN_SET("lqe2",tags)')->paging(1, 10))['class'] = 'playright';

		// $relates = $this->main->append('div', ['class' => 'relate']);
		
		// foreach ($this->webapp->fetch_videos->paging(1, 10) as $relate)
		// {
		// 	$anchor = $relates->append('a', ['href' => "?news/watch,hash:{$relate['hash']}"]);
		// 	$figure = $anchor->append('figure');
		// 	$figure->append('img', ['loading' => 'lazy', 'src' => $this->origin . substr($relate['poster'], 1, 24) . '.jpg']);
		// 	$anchor->append('strong', $relate['name']);
		// }

		
	}

	function get_search(string $keywords, int $page = 1)
	{
		$this->add_meta_seo($keywords = urldecode($keywords), $this->webapp['app_name']);
		$this->set_header_nav();

		//$this->main->append('h1', 'test');


		$this->add_div_videos($this->main, $this->webapp->fetch_videos->with('name LIKE ?s', "%{$keywords}%"), $page);




		
	}

	function get_user()
	{

	}
}