<?php
class webapp_router_home extends webapp_echo_masker
{
	public array $initallow = ['post_clickad', 'post_video'];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		unset($this->xml->head->link);
		if ($this->init)
		{
			// if ($webapp->redis->dosevasive(20))
			// {
			// 	$this->echo('');
			// 	return $webapp->response_status(403);
			// }
			$this->webapp->origin($this);
		}
		else
		{
			$this->footer[0] = '';
			unset($this->xml->head->script[0]);
			$this->stylesheet('/webapp/extend/vod/home.css');
			$this->script(['src' => '/webapp/extend/vod/home.js']);
			$this->script(['src' => '/webapp/static/js/slideshows.js']);
		}
	}
	function init(string $ua):int
	{
		$this->title('初始化..');
		$this->main['style'] = 'white-space:pre';
		$this->main->text('请确保在浏览器中打开，保护隐私安全');
		$this->main->text("\n如果长时间没有反应，请更新你的设备\n");
		$this->main->append('a', ['点击下载最新 Chrome 浏览器', 'href' => 'https://chrome.google.com/']);
		return 200;
	}
	function post_init():int
	{
		$this->json();
		//print_r($this->webapp->request_content('application/json'));
		//$data = $this->webapp->request_content('application/json');
		//$this->echo->error('禁止访问');
		//$this->echo->message('提示消息');
		//$this->echo->redirect('https://volunteer.cdn-go.cn/404/latest/404.html');
		return 200;
	}
	function post_clickad():int
	{
		if ($this->webapp->nfs_ads->likes($hash = $this->webapp->request_content('text/plain')))
		{
			//var_dump($this->fetch_cid());
		}
		return $this->echo_no_content();
	}
	function post_video(string $record):int
	{
		in_array($record, ['views', 'likes', 'shares'], TRUE)
			&& $this->webapp->nfs_videos->{$record}($hash = $this->webapp->request_content('text/plain'));
		return $this->echo_no_content();
	}
	function get_splashscreen():int
	{
		return parent::get_splashscreen();
		if (count($ads = $this->fetch_ads()))
		{
			$ad = $this->webapp->random_weights($ads);
			$this->splashscreen($ad['src'], $ad['jumpurl'], '跳过', 4, FALSE, $ad['hash']);
			$this->webapp->nfs_ads->views($ad['hash']);
			return 200;
		}
		return parent::get_splashscreen();
	}
	function cache_hotword(string $keyword):bool
	{
		return strlen($keyword) < 9 && is_float($this->webapp->redis->zIncrBy('hotwords', 1, $keyword));
	}
	function fetch_hotword(int $limit = 30):array
	{
		return $this->webapp->redis->zRevRange('hotwords', 0, $limit);
	}
	function fetch_cid()
	{
		return $this->webapp->request_cookie('cid');
	}
	function fetch_ads(int $seat = 0):array
	{
		return iterator_to_array($this->webapp->nfs_ads->search('$.seat=?s AND $.expire>?i ORDER BY $.weight DESC', $seat, $this->webapp->time));
	}
	function draw_header_search():webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', ['href' => '?home', 'style' => 'padding:0 var(--webapp-gapitem)'])->svg(['width' => 32, 'height' => 32])->logo();


		$this->header->append('input', ['type' => 'search',
			'placeholder' => '请输入关键词搜索',
			'value' => isset($this->webapp->query['keywords']) ? urldecode($this->webapp->query['keywords']) : NULL,
			'onkeypress' => 'event.keyCode===13&&this.nextElementSibling.onclick()',
			
		]);

		$this->header->append('button', ['搜索',
			'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value',
			'data-search' => '?home/search,keywords:']);
		//$this->header->append('a', ['href' => 'javascript:;'])->svg(['fill' => 'white'])->icon('search', 32);
		return $this->header;
	}
	function draw_header_name(string $title, string $goback = 'javascript:history.back();'):webapp_html
	{
		$this->header->append('a', ['href' => $goback])->svg(['fill' => 'white'])->icon('chevron-left', 32);
		$this->header->append('strong', $title);
		
		return $this->header;
	}

	function draw_aside_classifies(int $level):void
	{
		$this->aside['class'] = 'classifies';
		$selected = $this->webapp->query['classify'] ?? NULL;
		$url = $this->webapp->at(['classify' => '']);
		$this->aside->append('a', ['最新', 'href' => $url, 'class' => 'selected']);
		foreach ($this->webapp->nfs_classifies->search('$.level=?s ORDER BY $.sorting DESC', $level) as $classify)
		{
			$anchor = $this->aside->append('a', [$classify['name'], 'href' => "{$url}{$classify['hash']}"]);
			if ($classify['hash'] === $selected)
			{
				unset($this->aside->a['class']);
				$anchor['class'] = 'selected';
			}
		}
	}


	function draw_footer(bool $copyright = TRUE)
	{
		$copyright && $this->footer->insert('div', 'before')->setattr([$this->webapp['copy_webapp'], 'class' => 'nav']);
		$this->footer['class'] = 'nav';
		$this->footer->append('a', ['导航', 'href' => '?home/home']);
		$this->footer->append('a', ['视频', 'href' => '?home/video']);
		$this->footer->append('a', ['抖音', 'href' => '?home/slide']);
		$this->footer->append('a', ['搜索', 'href' => '?home/search']);
	}

	function draw_ads_banner(webapp_html $node, int $type = 2):void
	{
		if ($ads = $this->fetch_ads($type))
		{
			$element = $node->append('div', ['class' => 'grid-banner', 'onclick' => 'masker.clickad(this,event)', 'data-log' => '?home/clickad']);
			foreach ($ads as $ad)
			{
				$element->append('a', ['href' => $ad['jumpurl'], 'data-key' => $ad['hash']])->figure($ad['src'], $ad['name']);
			}
		}
	}
	function draw_ads_navicon(webapp_html $node, int $type = 3, string $name = NULL):void
	{
		if ($ads = $this->fetch_ads($type))
		{
			is_string($name) && $node->append('div', ['class' => 'titles'])->append('strong', $name);
			$element = $node->append('div', ['class' => 'grid-icon', 'onclick' => 'masker.clickad(this,event)', 'data-log' => '?home/clickad']);
			foreach ($ads as $ad)
			{
				$anchor = $element->append('a', ['href' => $ad['jumpurl'], 'data-key' => $ad['hash']]);
				$anchor->figure($ad['src']);
				$anchor->append('strong', $ad['name']);
			}
		}
	}
	function draw_ads_slideshows(webapp_html $node, int $duration = 5):void
	{
		if ($ads = $this->fetch_ads(4))
		{
			$node->append('webapp-slideshows', [
				//'onchange' => 'console.log(this.active, this.current)',
				'onclick' => 'masker.clickad(this,event)',
				'data-log' => '?home/clickad',
				'data-contents' => json_encode(array_map(fn($ad) => [
					'picture' => $ad['src'],
					'support' => $ad['jumpurl'],
					'key' => $ad['hash']
				], $ads), JSON_UNESCAPED_UNICODE),
				'data-duration' => $duration
			]);
		}
	}
	function draw_videos_lists(
		webapp_html $node,
		string|iterable $videos,
		int $display = 0,
		string $title = NULL,
		string $anchor = NULL,
		string $action = '更多 >>'):void {
		
		if ($title)
		{
			$titles = $this->main->append('div', ['class' => 'titles']);
			$titles->append('strong', $title);
			if ($anchor)
			{
				$titles->append('a', [$action, 'href' => $anchor]);
			}
		}


		$element = $node->getName() === 'template' ? $node : $node->append('div', ['class' => "grid-t{$display}"]);
		if (is_string($videos))
		{
			$node->append('blockquote', ['内容加载中...', 'data-lazy' => $videos, 'data-page' => 1]);
		}
		else
		{


			foreach ($videos as $video)
			{
				$anchor = $element->append('a', ['href' => "?home/watch,hash:{$video['hash']}"]);
				$figure = $anchor->figure($video['poster'], $this->webapp->format_duration($video['size']));
				$anchor->append('strong', htmlentities($video['name']));
			}
		}
	}



	function get_home()
	{
		$this->link(['rel' => 'prefetch', 'href' => '/webapp/static/js/hls.min.js', 'as' => 'script']);
		$this->link(['rel' => 'prefetch', 'href' => '/webapp/static/js/video.js', 'as' => 'script']);
		$this->title('导航');
		$this->draw_header_search();
		$this->draw_ads_slideshows($this->aside);
		$this->draw_ads_banner($this->main);
		$this->draw_ads_navicon($this->main);
		$this->draw_footer();
	}
	function get_video(string $classify = NULL, int $page = 0)
	{
		if ($classify && $page)
		{
			return $this->draw_videos_lists($this->frag(), $this->webapp->nfs_videos->node($classify)->order('t1 DESC, hash ASC')->paging($page));
		}
		$this->title('视频');
		$this->draw_header_search();

		$this->draw_aside_classifies(0);

		$this->draw_ads_slideshows($this->main);
		$this->draw_footer();
		if ($classify && $this->webapp->nfs_classifies->fetch($classify, $data))
		{
			return $this->draw_videos_lists($this->main, "?home/video,classify:{$classify},page:", $data['style']);
		}
		foreach ($this->webapp->nfs_classifies->search('$.level="0" ORDER BY $.sorting DESC') as $classify)
		{
			$this->draw_videos_lists($this->main,
				$this->webapp->nfs_videos->node($classify['hash'])->random(match ((int)$classify['style'])
				{
					1 => 3,
					2, 3, 7 => 6,
					5, 6 => 5,
					4, 8 => 7,
					9 => 4,
					default => 2
				}), $classify['style'] , $classify['name'], "?home/video,classify:{$classify['hash']}");
		}
	}
	function get_watch(string $hash)
	{
		$videos = $this->webapp->nfs_videos;
		$this->draw_header_search();
		$this->draw_footer();
		$this->aside['class'] = 'watch';
		if ($videos->fetch($hash, $video) === FALSE)
		{
			$this->aside->append('strong', '影片不见了:(');
			return;
		}
		$this->title($video['name']);
		$this->script(['src' => '/webapp/static/js/hls.min.js']);
		$this->script(['src' => '/webapp/static/js/video.js']);

		//print_r($video);

		$element = $this->aside->append('webapp-video', [
			'oncanplay' => 'masker.canplay(this)',
			'data-poster' => $video['poster'],
			'data-m3u8' => $video['m3u8'],
			'data-log' => '?home/video,record:views',
			'data-key' => $video['hash'],
			'autoheight' => NULL,
			'autoplay' => NULL,
			'controls' => NULL,
			//'muted' => NULL
		]);
		$videoinfo = $this->main->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong', htmlentities($video['name']));

		$this->draw_ads_banner($this->main);
		$this->draw_videos_lists($this->main, $this->webapp->nfs_videos->random(6), 2, '猜你喜欢');

	}
	function get_slide(int $page = 0)
	{
		if ($page)
		{
			$videos = [];
			foreach ($this->webapp->nfs_videos->random(9) as $video)
			{
				$videos[] = [
					'key' => $video['hash'],
					'size' => $video['size'],
					'views' => $video['views'],
					'likes' => $video['likes'],
					'shares' => $video['shares'],
					'poster' => $video['poster'],
					'm3u8' => $video['m3u8'],
					'name' => $video['name']
				];
			}
			$this->json($videos);
			unset($this->echo['errors']);
			return;
		}
		$this->title('抖音');
		$this->script(['src' => '/webapp/static/js/hls.min.js']);
		$this->script(['src' => '/webapp/static/js/video.js']);

		$this->header->append('a', ['href' => 'javascript:history.back();'])
			->svg(['fill' => 'white'])->icon('chevron-left', 32);


		$this->xml->body->div['class'] = 'slide';
		$template = $this->main->append('webapp-videos', [
			//'onchange' => 'console.log(navigator.sendBeacon("?home/video,record:views",this.current.hash))',

			'autoplay' => NULL,
			'controls' => NULL,
			//'muted' => NULL,
			'data-fetch' => '?home/slide,page:',
			'data-page' => 1,
			'data-log' => '?home/video,record:views'
		])->append('template');

		$this->draw_footer(FALSE);
	}
	function get_search(string $keywords = NULL, int $page = 0)
	{
		if (is_string($keywords) && trim($keyword = urldecode($keywords)) && $page)
		{
			return $this->draw_videos_lists($this->frag(), $this->webapp->nfs_videos
				->search('name LIKE ?s ORDER BY t1 DESC, hash ASC', "%{$keyword}%")->paging($page));
		}
		$this->draw_header_search();
		$this->draw_footer();
		if (isset($keyword))
		{
			$this->title($keyword);
			$this->cache_hotword($keyword);
			$this->draw_videos_lists($this->main, "?home/search,keywords:{$keywords},page:");
		}
		else
		{
			$this->title('搜索');
			$node = $this->main->append('div', ['class' => 'videoinfo']);
			$node->append('strong', '热门关键词');
			$mark = $node->append('mark');
			foreach ($this->fetch_hotword() as $hotword)
			{
				$mark->append('a', [$hotword, 'href' => "?home/search,keywords:{$hotword}"]);
			}
		}
	}
}