<?php
class webapp_router_home extends webapp_echo_masker
{
	public bool $lazyload = TRUE;
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
			$this->stylesheet('/webapp/extend/vod/home.css');
			$this->script(['src' => '/webapp/extend/vod/home.js']);
			$this->script(['src' => '/webapp/static/js/slideshows.js']);
		}
	}
	function init(string $ua):int
	{
		$this->title('初始化..');
		$this->main['style'] = 'white-space:pre;';
		$this->main->text('初始化网络服务进程，保护隐私安全，请稍等..');
		$this->main->text("\n如果长时间没有反应，请更新你的设备\n");
		$this->main->append('a', ['点击下载最新 Chrome 浏览器', 'href' => 'http://chrome.google.com/']);
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
	function get_splashscreen():int
	{
		return parent::get_splashscreen();
		if (count($ads = $this->fetch_ads()))
		{
			$ad = $this->webapp->random_weights($ads);
			$this->splashscreen($ad['src'], $ad['jumpurl'], '跳过', 4, TRUE, $ad['hash']);
			$this->webapp->nfs_ads->views($ad['hash']);
			return 200;
		}
		return parent::get_splashscreen();
	}
	function draw_header_search():webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', ['href' => '?home', 'style' => 'padding:0 var(--webapp-gapitem)'])->svg(['width' => 32, 'height' => 32])->logo();


		$this->header->append('input', ['type' => 'search',
			'value' => $this->webapp->query['search'] ?? NULL,
			'placeholder' => '请输入关键词搜索']);

		$this->header->append('button', ['搜索']);
		//$this->header->append('a', ['href' => 'javascript:;'])->svg(['fill' => 'white'])->icon('search', 32);
		return $this->header;
	}
	function draw_header_name(string $title, string $goback = 'javascript:history.back();'):webapp_html
	{
		$this->header->append('a', ['href' => $goback])->svg(['fill' => 'white'])->icon('chevron-left', 32);
		$this->header->append('strong', $title);
		
		return $this->header;
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
	function fetch_ads(int $seat = 0):array
	{
		return iterator_to_array($this->webapp->nfs_ads->search('$.seat=?s AND $.expire>?i ORDER BY $.weight DESC', $seat, $this->webapp->time));
	}
	function draw_ads_banner(webapp_html $node, int $type = 2):void
	{
		if ($ads = $this->fetch_ads($type))
		{
			$element = $node->append('div', ['class' => 'grid-banner']);
			foreach ($ads as $ad)
			{
				$element->append('a', [
					'href' => $ad['jumpurl'],
					'onclick' => 'return masker.clickad(this)',
					'data-hash' => $ad['hash']])->figure($ad['src'], $ad['name']);
			}
		}
	}
	function draw_ads_navicon(webapp_html $node, int $type = 3, string $name = NULL):void
	{
		if ($ads = $this->fetch_ads($type))
		{
			is_string($name) && $node->append('div', ['class' => 'titles'])->append('strong', $name);
			$element = $node->append('div', ['class' => 'grid-icon']);
			foreach ($ads as $ad)
			{
				$anchor = $element->append('a', [
					'href' => $ad['jumpurl'],
					'onclick' => 'return masker.clickad(this)',
					'data-hash' => $ad['hash']]);
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
				'data-contents' => json_encode(array_map(fn($ad) => [
					'picture' => $ad['src'],
					'support' => $ad['jumpurl'],
					'hash' => $ad['hash'],
				], $ads), JSON_UNESCAPED_UNICODE),
				'data-duration' => $duration,
				'onchange' => 'this.active.onclick=()=>masker.clickad(this.active)'
			]);
		}
	}
	function draw_videos_lists(webapp_html $node, string|iterable $videos, int $display = 0)
	{
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
		$this->draw_header_search('asdasd');

		$this->draw_ads_banner($this->aside);

		$this->draw_ads_slideshows($this->main);

		
		$this->draw_ads_navicon($this->main);


		$this->draw_footer();
	}
	function get_video(int $page = 0)
	{
		if ($this->lazyload && $page)
		{
			$this->draw_videos_lists($this->frag(), $this->webapp->nfs_videos('ORDER BY t1 DESC, hash ASC')->paging($page));
			return;
		}
		$this->title('视频');
		$this->draw_header_search('asdasd');
		$this->draw_ads_slideshows($this->main);



		$this->draw_videos_lists($this->main, '?home/video,page:');



		$this->draw_footer();
	}
	function get_watch(string $hash)
	{
		$videos = $this->webapp->nfs_videos;
		$this->draw_header_search('asdasd');
		$this->draw_footer();
		$this->aside['class'] = 'watch';
		if ($videos->fetch($hash, $video) === FALSE)
		{
			$this->aside->append('strong', '影片不见了:(');
			return;
		}
		$videos->views($hash);
		$this->title($video['name']);
		//$this->draw_header_name($video['name']);
		$this->script(['src' => '/webapp/static/js/hls.min.js']);
		$this->script(['src' => '/webapp/static/js/video.js']);

		//print_r($video);
		
		$element = $this->aside->append('webapp-video', [
			'data-poster' => $video['poster'],
			'data-m3u8' => $video['m3u8'],
			//'data-m3u8' => preg_replace('/\?mask\d{10}/', '.m3u8', '@' . substr($video['m3u8'], 1)),
			//'oncanplay' => 'masker.canplay(this)',
			//'autoheight' => NULL,
			'autoplay' => NULL,
			//'muted' => NULL,
			'controls' => NULL
		]);
		$videoinfo = $this->main->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong', htmlentities($video['name']));

		
		$this->main['style'] = 'height:1000px';





		
		
	}
	function get_slide(int $page = 0)
	{
		if ($page)
		{
			$this->json(iterator_to_array($this->webapp->nfs_videos->paging($page, 9)));
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
			'onchange' => 'console.log(this.current)',
			'data-fetch' => '?home/slide,page:',
			'data-page' => 1,
			//'autoplay' => NULL,
			'controls' => NULL,
			//'muted' => NULL
		])->append('template');

		$this->draw_footer(FALSE);
	}
	function get_search(string $keywords = NULL)
	{
		$this->draw_header_search();
		$this->draw_footer();
		if (is_string($keywords))
		{
			$this->title($keywords);
		}
		$this->title('搜索');

		$this->script(['src' => '/webapp/static/js/hls.min.js']);
		$this->script(['src' => '/webapp/static/js/video.js']);



	}
}