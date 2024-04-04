<?php
class webapp_router_news extends webapp_echo_html
{
	private string $origin = 'https://3minz.com';
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);


		$this->xml->head->link['href'] = '/webapp/app/star/news.css?' . $this->webapp->random_hash(TRUE);
		$this->footer[0] = NULL;
	}
	function add_meta_seo(string $keywords, string $description)
	{
		$this->meta(['name' => 'keywords', 'content' => $keywords]);
		$this->meta(['name' => 'description', 'content' => $description]);
	}

	function set_header_nav()
	{
		$this->header->append('a', ['href' => '#'])->svg(['fill' => 'white'])->icon('markdown', 24);
		$this->header->append('input', ['type' => 'search']);
		$this->header->append('a', ['href' => '#'])->svg(['fill' => 'white'])->icon('search', 24);
		$this->header->append('a', ['href' => '?news/user'])->svg(['fill' => 'white'])->icon('person', 24);
	}


	function add_div_videos(webapp_html $none, iterable $videos)
	{
		$element = $none->append('div', ['class' => 'videos']);
		foreach ($videos as $video)
		{
			$anchor = $element->append('a', ['href' => "?news/watch,hash:{$video['hash']}"]);
			$figure = $anchor->append('figure');



			$figure->append('img', ['loading' => 'lazy', 'src' => $this->origin . substr($video['poster'], 1, 24) . '.jpg']);
			$anchor->append('strong', $video['name']);
		}


	}

	function add_ads_video()
	{
		
	}


	function get_home()
	{
		$this->add_meta_seo('asdasd', 'ewewewewe');
		$this->set_header_nav();

		//$this->main->append('h1', 'test');


		$this->add_div_videos($this->main, $this->webapp->fetch_videos->paging(1, 10));
	}

	function get_watch(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->set_header_nav();
		$this->aside['class'] = 'watch';


		if (empty($video = $this->webapp->fetch_videos[$hash]))
		{
			$this->aside->append('strong', '您所观看的影片不见啦 :(');
			return 404;
		}
		$this->tags = $this->webapp->fetch_tags->shortname();
		$tags = [];
		if ($video['tags'])
		{
			foreach (explode(',', $video['tags']) as $tag)
			{
				$tags[$tag] = $this->tags[$tag];
			}
		}

		$this->title($video['name']);
		$this->add_meta_seo(join(' ', array_values($tags)), $video['name']);
		$watch = $this->aside->append('webapp-video', [
			'data-poster' => $this->origin . substr($video['poster'], 1, 24) . '.jpg',
			'data-m3u8' => $this->origin . substr($video['m3u8'], 1, 23) . '.m3u8',
			'oncanplay' => 'console.log(this)',
			//'autoheight' => NULL,
			//'autoplay' => NULL,
			//'muted' => NULL,
			'controls' => NULL
		]);




	}

	function get_user()
	{

	}
}