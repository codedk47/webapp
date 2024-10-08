<?php
const LOCALE = 'en';
class webapp_router_news extends webapp_echo_html
{
	private array $tags;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		
		$this->xml->head->meta[1]['content'] .= ',user-scalable=0';
		$this->xml->head->link['href'] = '/webapp/app/star/news.css?v=v8';
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
		
		$this->aside->append('a', ['Home', 'href' => '?news']);
		$this->aside->append('a', ['Channels', 'href' => '?news/channels']);
		$this->aside->append('a', ['Star', 'href' => '?news/star']);
	}


	function add_div_videos(webapp_html $node, iterable $videos, int $index = 1, int $count = 30):webapp_html
	{
		$pagination = $videos instanceof webapp_redis_table;
		$element = $node->append('div', ['class' => 'videos']);
		foreach ($pagination ? $videos->paging($index, $count) : $videos as $video)
		{
			$anchor = $element->append('a', [
				'href' => "?news/watch,hash:{$video['hash']}",
				'data-preview' => $this->webapp->origin . substr($video['poster'], 1, 18) . '/preview.mp4']);
			$anchor->figure($this->webapp->origin . str_replace('?mask', '.jpg?', substr($video['poster'], 1)));
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
			$cover = $this->webapp->origin . substr($video['poster'], 1, 24) . '.jpg';
			if (in_array($this->webapp->request_country(), [
				//北美
				'CA',	//加拿大
				'US',	//美国
				//欧洲
				'AT',	//奥地利
				'BE',	//比利时
				'CZ',	//捷克共和国
				'DK',	//丹麦
				'FI',	//芬兰
				'FR',	//法国
				'DE',	//德国
				'IE',	//爱尔兰
				'IT',	//意大利
				'NL',	//荷兰
				'NO',	//挪威
				'PL',	//波兰
				'PT',	//葡萄牙
				'SK',	//斯洛伐克
				'ES',	//西班牙
				'SE',	//瑞典
				'CH',	//瑞士
				'GB',	//英国
				//亚太
				'AU',	//澳大利亚
				'JP'	//日本
			], TRUE)) {
				$player->append('strong', 'Sorry, this video is not available in your region temporarily.');
			}
			else
			{
				$watch = $player->append('webapp-video', [
					'data-poster' => $cover,
					'data-m3u8' => $this->webapp->origin . substr($video['m3u8'], 1, 23) . '.m3u8',
					'oncanplay' => 'console.log(this)',
					//'autoheight' => NULL,
					//'autoplay' => NULL,
					//'muted' => NULL,
					'controls' => NULL
				]);
			}
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
			$show = ['star' => [], 'chns' => []];
			if ($video['subjects'])
			{
				$star = $this->webapp->fetch_subjects->star();
				$chns = $this->webapp->fetch_subjects->chns();
				foreach (explode(',', $video['subjects']) as $subject)
				{
					if ($need = match (TRUE) {
						isset($star[$subject]) => ['star', 6],
						isset($chns[$subject]) => ['chns', 16],
						default => []
					}) {
						foreach ($this->webapp->fetch_subjects->item($subject) as $content)
						{
							if (count($show[$need[0]]) < $need[1])
							{
								$show[$need[0]][] = $content;
							}
							else
							{
								break;
							}
						}
					}
				}
			}
			if ($need = (6 + 16) - (count($show['star']) + count($show['chns'])))
			{
				foreach ($this->webapp->fetch_videos->random($need) as $content)
				{
					$show[count($show['star']) < 6 ? 'star' : 'chns'][] = $content;
				}
			}
			$this->add_div_videos($this->main, new ArrayIterator($show['star']))['class'] = 'playright';
			$this->add_div_videos($player, new ArrayIterator($show['chns']))['class'] = 'playleft';
		}
		else
		{
			$player->append('strong', 'The video you were watching is gone :(');
			$this->add_div_videos($this->main, $this->webapp->fetch_videos->random(6))['class'] = 'playright';
			$this->add_div_videos($player, $this->webapp->fetch_videos->random(16))['class'] = 'playleft';
		}
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


	function add_div_item(webapp_html $node, string $type, iterable $item, int $index = 1, int $count = 30):webapp_html
	{
		$pagination = $item instanceof webapp_redis_table;
		$element = $node->append('div', ['class' => "item {$type}"]);
		foreach ($pagination ? $item->paging($index, $count) : $item as $content)
		{
			$anchor = $element->append('a', ['href' => "?news/subjects,hash:{$content['hash']}"]);
			$anchor->figure(match($type)
			{
				'star' => "{$this->webapp->origin}/star/{$content['hash'][0]}/{$content['hash']}.jpg",
				'chns' => "{$this->webapp->origin}/channels/{$content['hash']}.jpg",
				default => ''
			});
			$anchor->append('strong', $content['name']);
		}
		if ($pagination && ($max = ceil($item->count() / $count)) > 1)
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
	function get_star(int $page = 1)
	{
		$this->set_header_nav();
		$this->add_meta_seo('Star', $this->webapp['app_name']);
		$this->add_div_item($this->main, 'star', $this->webapp->fetch_subjects->star(), $page, 30);
	}

	function get_channels(int $page = 1)
	{
		$this->set_header_nav();
		$this->add_meta_seo('Channels', $this->webapp['app_name']);
		$this->add_div_item($this->main, 'chns', $this->webapp->fetch_subjects->chns(), $page, 30);
	}

	function get_subjects(string $hash, int $page = 1)
	{
		$this->set_header_nav();
		$this->add_meta_seo('Subject', $this->webapp['app_name']);
		$this->add_div_videos($this->main, $this->webapp->fetch_subjects->item($hash), $page, 30);

	}

	function get_test()
	{
		$this->webapp->fetch_videos->actress();

	}
}