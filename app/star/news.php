<?php
const LOCALE = 'en';
class webapp_router_news extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		
		$this->xml->head->meta[1]['content'] .= ',user-scalable=0';
		$this->xml->head->link['href'] = '/webapp/app/star/news.css?v=cv';
		$this->xml->head->link[1]['type'] = 'image/jpeg';
		$this->xml->head->link[1]['href'] = '/star/logo.jpg';

		$this->script(['src' => '/webapp/app/star/news.js?v=mq']);

		$this->script(['src' => 'https://www.googletagmanager.com/gtag/js?id=G-G65DP9ETZ5']);
		$this->script('window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","G-G65DP9ETZ5")');
		$this->footer[0] = NULL;


		if ($this->webapp->request_cookie('adult') !== 'yes')
		{
			$this->xml->body->div['style'] = 'filter:blur(var(--webapp-gapitem))';
			$adult = $this->xml->body->append('dialog');
			$adult->append('h4', 'Are you 18 years of age or older?');
			$adult->append('pre', ['You must be 18 years or older to access and use this website.
By clicking ENTER below, you certify that you are 18 years or older.']);
			$adult->append('a', ['Enter', 'href' => 'javascript:;', 'onclick' => 'adulted(this.parentNode.remove())']);
			$adult->append('a', ['No', 'href' => 'https://www.google.com/']);
		}


	}
	function add_meta_seo(string $title = NULL, string $keywords = NULL, string $description = NULL, string $image = NULL)
	{
		$this->xml['prefix'] = 'og:https://ogp.me/ns#';
		$this->title($title ??= $this->webapp['app_name']);
		$this->meta(['name' => 'og:title', 'content' => $title]);
		$this->meta(['name' => 'og:image', 'content' => $image ?? '/star/logo.jpg']);
		//$this->meta(['name' => 'og:description', 'content' => $description]);


		$this->meta(['name' => 'og:type', 'content' => 'video.movie']);
		$this->meta(['name' => 'og:url', 'content' => "https://{$this->webapp['app_website']}"]);

		$this->meta(['name' => 'keywords', 'content' => $keywords ?? $this->webapp['iphone_webcilp']['displayname']]);
		$this->meta(['name' => 'description', 'content' => $description ?? $this->webapp['iphone_webcilp']['description']]);
	}

	function set_header_nav()
	{
		$this->header->append('a', ['href' => '?news']);
		$search = $this->header->append('span');
		$search->append('input', ['type' => 'search',
			'placeholder' => 'Search videos',
			'value' => urldecode($this->webapp->query['keywords'] ?? ''),
			'onkeypress' => 'if(event.keyCode===13)location.href=this.nextElementSibling.href+this.value']);
		$search->append('a', ['href' => '?news/search,keywords:',
			'onclick' => 'return !!void(location.href=this.href+this.previousElementSibling.value)'])
			->svg(['fill' => 'white'])->icon('search', 24);
	}


	function add_div_videos(webapp_html $node, iterable $videos, int $index = 1, int $count = 30):webapp_html
	{
		$pagination = $videos instanceof webapp_redis_table;
		$element = $node->append('div', ['class' => 'videos']);
		foreach ($pagination ? $videos->paging($index, $count) : $videos as $video)
		{
			$path = $this->webapp->origin . substr($video['poster'], 1, 18);
			$anchor = $element->append('a', [
				'href' => "?news/watch,hash:{$video['hash']}",
				'data-preview' => "{$path}/preview.mp4"]);
			$anchor->figure("{$path}/cover.jpg?{$video['ctime']}");
			$anchor->append('strong', $video['name']);
		}
		if ($pagination && ($max = ceil($videos->count() / $count)) > 1)
		{
			$index = max(1, $index);
			$url = $this->webapp->at(['page' => '']);
			$page = $node->append('div', ['class' => 'pages']);
			$show = 4;
			if ($max > $show)
			{
				$halved = intval($show * 0.5);
				$offset = min($max, max($index, $halved) + $halved);
				$ranges = range(max(1, $offset - $halved * 2 + 1), $offset);
				$index > 1 && $page->append('a', ['Top', 'href' => "{$url}1"]);
				foreach ($ranges as $i)
				{
					$curr = $page->append('a', [$i, 'href' => "{$url}{$i}"]);
					if ($i == $index)
					{
						$curr['class'] = 'selected';
					}
				}
				$index < $max && $page->append('a', ['End', 'href' => $url . $max]);
			}
			else
			{
				for ($i = 1; $i <= $max; ++$i)
				{
					$curr = $page->append('a', [$i, 'href' => "{$url}{$i}"]);
					if ($i === $index)
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

	
	function get_home(int $page = 1)
	{
		$this->set_header_nav();
		$this->add_meta_seo();
		
		$this->add_div_videos($this->main, $this->webapp->fetch_videos, $page);
	}
	function get_news(int $page = 1)
	{
		$this->get_home($page);
	}
	function get_watch(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->set_header_nav();


		$this->main['class'] = 'watch';


		$this->tags = $this->webapp->fetch_tags->shortname(LOCALE);
		$player = $this->main->append('div', ['class' => 'player']);
		if ($video = $this->webapp->fetch_videos[$hash])
		{
			$watch = $player->append('webapp-video', [
				'data-poster' => $cover = $this->webapp->origin . substr($video['poster'], 1, 24) . '.jpg',
				'data-m3u8' => $this->webapp->origin . substr($video['m3u8'], 1, 23) . '.m3u8',
				'oncanplay' => 'console.log(this)',
				//'autoheight' => NULL,
				//'autoplay' => NULL,
				//'muted' => NULL,
				'controls' => NULL
			]);
			$videoinfo = $player->append('div', ['class' => 'videoinfo']);
			$videoinfo->append('strong', $video['name']);
			$tags = [];
			if ($video['tags'])
			{
				$taginfo = $videoinfo->append('div', ['data-label' => 'Label:']);
				foreach (explode(',', $video['tags']) as $tag)
				{
					if ($tag != 'lqe2' && isset($this->tags[$tag]))
					{
						$tags[$tag] = $this->tags[$tag];
						$taginfo->append('a', [$this->tags[$tag], 'href' => 'javascript:;']);
					}
					
				}
			}
			$this->add_meta_seo(sprintf("%s - {$this->webapp['iphone_webcilp']['label']}", strtr($video['name'], '.', ' ')),
				join(' ', array_values($tags)), image: $cover);
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
		// 	$figure->append('img', ['loading' => 'lazy', 'src' => $this->webapp->origin . substr($relate['poster'], 1, 24) . '.jpg']);
		// 	$anchor->append('strong', $relate['name']);
		// }

		
	}

	function get_search(string $keywords, int $page = 1)
	{
		$this->set_header_nav();
		$tags = array_map(strtolower(...), $this->webapp->fetch_tags->shortname(LOCALE));
		$keyword = join(' ', array_filter(array_map(trim(...), explode(' ', strtolower(urldecode($keywords))))));
		$this->add_meta_seo($keyword, $this->webapp['app_name']);
		$conditions = ['name LIKE ?s', sprintf('%%%s%%', strtr($keyword, ' ', '%'))];
		if ($tag = array_search($keyword, $tags))
		{
			$conditions[0] .= ' OR FIND_IN_SET(?s,tags)';
			$conditions[] = $tag;
		}
		$result = $this->webapp->fetch_videos->with(...$conditions);
		if ($result->count() === 0)
		{
			$this->main->append('h1', 'Not Found');
			return 404;
		}
		$this->add_div_videos($this->main, $result, $page);
	}

	function get_test()
	{
		$this->webapp->fetch_videos->actress();

	}
}