<?php
class webapp_router_home extends webapp_echo_masker
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($this->init)
		{
			$this->webapp->origin($this);
		}
		else
		{
			$this->footer[0] = '';
			unset($this->xml->head->link[1]);
			
			$this->script(['src' => '/webapp/static/js/slideshows.js']);
			$this->stylesheet('/webapp/extend/vod/home.css');
			
			$this->link(['rel' => 'prefetch', 'href' => '/webapp/static/js/player.js', 'as' => 'script']);

		}
		// if ($this->webapp->redis->dosevasive(20))
		// {
		// 	$this->echo('');
		// 	return $this->webapp->response_status(403);
		// }
	}
	function post_init():int
	{
		$this->json();
		// $this->echo['data'] = $this->webapp->request_content('application/json');
		//$this->echo->error('你的访问被拒绝');
		//$this->echo->redirect('asdasdawdawd');
		//$this->echo->message('ddddd');
		// var_dump(123);
		// print_r($this->webapp->request_content());
		return 200;
	}
	function get_splashscreen():int
	{
		return parent::get_splashscreen();
		if (count($ad = $this->webapp->fetch_ad(0)))
		{
			$ad = $this->webapp->random_weights($ad);
			$this->splashscreen($ad['src'], $ad['jumpurl'], '跳过', 4, TRUE, $ad['hash']);
			$this->webapp->nfs(0)->views($ad['hash']);
			return 200;
		}
		return parent::get_splashscreen();
	}
	function draw_header_index():webapp_html
	{
		$this->header->append('a', ['href' => '?home'])->svg(['width' => 24, 'height' => 24])->logo();


		$this->header->append('input', ['type' => 'search', 'value' => $this->webapp->query['search'] ?? NULL]);
		$this->header->append('a', ['href' => 'javascript:;'])->svg()->icon('search', 24);
		return $this->header;
	}
	function draw_header_name(string $title, string $goback = 'javascript:history.back();'):webapp_html
	{
		$this->header->append('a', ['href' => $goback])->svg()->icon('chevron-left', 24);
		$this->header->append('strong', $title);
		
		return $this->header;
	}




	function draw_footer()
	{
		$this->footer['class'] = 'nav';
		$this->footer->append('a', ['导航', 'href' => '?home/home']);
		$this->footer->append('a', ['视频', 'href' => '?home/video']);
		$this->footer->append('a', ['抖音', 'href' => '?home/short']);
		$this->footer->append('a', ['新闻', 'href' => '?home/news']);
		
	}
	function draw_ad_banner(webapp_html $node):?webapp_html
	{
		if ($ads = $this->webapp->fetch_ad(2))
		{
			$element = $node->append('div', ['class' => 'grid-banner']);
			foreach ($ads as $ad)
			{
				$element->append('a', [
					'href' => $ad['jumpurl'],
					'onclick' => 'return masker.clickad(this)',
					'data-hash' => $ad['hash']])->figure($ad['src'], '广告');
			}
		}
		return NULL;
	}
	function draw_ad_navicon(webapp_html $node, string $name = NULL):void
	{
		if ($ads = $this->webapp->fetch_ad(3))
		{
			is_string($name) && $node->append('div', ['class' => 'titles'])->append('strong', $name);
			
			$element = $node->append('div', ['class' => 'grid-icon']);
			foreach ($ads as $ad)
			{

				$anchor = $element->append('a', [
					'href' => $ad['jumpurl'],
					'onclick' => 'return masker.clickad(this)',
					'data-hash' => $ad['hash']
				
				
				]);
				$anchor->figure($ad['src']);
				$anchor->append('strong', $ad['name']);
			}
		}
	}
	function draw_ad_slideshows(webapp_html $node, int $duration = 5, string $clickad = '')
	{
		if ($ads = $this->webapp->fetch_ad(4))
		{
			//masker.lognews(`?home/news,hash:${this.current.hash}`)
			//$this->webapp->mysql->ads('WHERE hash IN(?S)', array_column($ads, 'hash'))->update('`view`=`view`+1');
			return $node->append('webapp-slideshows', [
				'data-contents' => json_encode(array_map(fn($ad) => [
					'picture' => $ad['src'],
					'support' => $ad['jumpurl'],
					'hash' => $ad['hash'],
				], $ads), JSON_UNESCAPED_UNICODE),
				'data-duration' => $duration,
				'data-target' => '_blank',
				'onchange' => 'this.active.onclick=()=>masker.clickad(this.active)'
			]);
		}
		return NULL;
	}




	function get_home()
	{
		$this->title('导航');
		$this->draw_header_index('asdasd');

		$this->draw_ad_banner($this->aside);

		$this->draw_ad_slideshows($this->main);

		
		$this->draw_ad_navicon($this->main);


		$this->draw_footer();
	}
	function get_video()
	{
		$this->title('视频');
		$this->draw_ad_slideshows($this->main);
		$this->draw_footer();
	}
	function get_short()
	{
		$this->title('抖音');
		$this->draw_footer();
	}
	function get_news()
	{
		$this->title('新闻');
		$this->draw_footer();
	}
	function get_watch(string $hash)
	{
		$this->draw_header_name('asdasdasd');
		$this->draw_footer();
	}
}