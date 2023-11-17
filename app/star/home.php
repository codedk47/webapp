<?php
class webapp_router_home extends webapp_echo_masker
{
	private readonly user $user;
	protected array $allow = ['get_splashscreen', 'post_create_account'];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		unset($this->xml->body->div['class']);
		$this->footer[0] = NULL;
		$this->title('Star');
		if ($this->initiated)
		{
			return;
		}
		$this->xml->head->meta[1]['content'] .= ',user-scalable=0';
		$this->link_resources($webapp['app_resorigins']);
		$this->xml->head->link['href'] = '/webapp/app/star/home.css?' . $webapp->random_hash(TRUE);
		$this->script(['src' => '/webapp/app/star/home.js']);
		$this->script(['src' => '/webapp/res/js/slideshows.js']);
		//$this->footer->text('asd');

	}


	
	function authorization($uid, $pwd):array
	{
		$this->user = $this->webapp->user($uid);
		return ['asd', '123'];
		return $this->user->id ? [$this->user->id, $this->user['cid']] : [];
	}
	function form_login(webapp_html $node = NULL)
	{
		$form = new webapp_form($node ?? $this->webapp, '?home/create_account');
		//$form->field('did');
		$form->fieldset();
		$form->fieldset->append('label', ['恢复我的凭证', 'class' => 'button'])->append('input', [
			'type' => 'file',
			'accept' => 'image/*',
			'style' => 'display:none',
			'onchange' => 'masker.revert_account(this)'
		]);
		$form->fieldset();
		$form->button('创建新的账号', 'submit');
		$form->xml['onsubmit'] = 'return masker.create_account(this)';
		return $form;
	}
	function sign_in()
	{
		$this->script(['src' => '/webapp/res/js/zxing-browser.min.js']);
		$this->form_login($this->main)->xml['class'] = 'login';
		return 200;
	}
	function post_create_account()
	{
		if ($this->form_login()->fetch($login))
		{
			$this->json($login);
		}
		
	}
	function set_header_index():webapp_html
	{
		$this->header['class'] = 'index';
		
		return $this->header;
	}
	function set_header_search():webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', ['href' => '?home', 'class' => 'arrow']);
		$this->header->append('input', ['type' => 'search', 'placeholder' => '请输入关键词搜索', 'onkeypress' => 'if(event.keyCode===13)location.href=this.nextElementSibling.dataset.search+this.value']);
		$this->header->append('button', ['搜索', 'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value', 'data-search' => '?home/search,word:']);

		return $this->header;
	}
	function set_header_title(string $name, string $goback = NULL):webapp_html
	{
		if ($goback)
		{
			$this->header->append('a', ['href' => $goback, 'class' => 'arrow']);
		}
		$this->header->append('strong', $name);
		return $this->header;
	}



	function set_footer_menu():webapp_html
	{
		$this->footer->insert('div', 'before')['style'] = 'height:4rem';
		$this->footer['class'] = 'nav';
		$this->footer->append('a', ['首页', 'href' => '?home/home']);
		$this->footer->append('a', ['抖音', 'href' => '?home/short']);
		//$this->footer->append('a', ['游戏', 'href' => '?home/game']);
		$this->footer->append('a', ['剧集', 'href' => '?home/series']);
		$this->footer->append('a', ['我的', 'href' => '?home/my']);
		return $this->footer;
	}


	function add_slideshows_ads(webapp_html $node, int $seat):?webapp_html
	{
		if ($ads = $this->webapp->fetch_ads($seat))
		{
			$element = $node->append('webapp-slideshows');
			$element->cdata(json_encode($ads));
			return $element;
		}
		return NULL;
	}
	function add_nav_ads(webapp_html $node, int $seat, string $title = NULL)
	{
		if ($ads = $this->webapp->fetch_ads($seat))
		{
			if ($title)
			{
				$element = $node->append('div', ['class' => 'titles']);
				$element->append('strong', $title);
			}

			$element = $node->append('div', ['class' => 'grid-icon']);

			$ads = [...$ads, ...$ads, ...$ads, ...$ads];
	
			foreach ($ads as $ad)
			{
				$a = $element->append('a', ['href' => $ad['support']])->append('figure');
				$a->append('img', ['src' => $ad['picture']]);
				$a->append('figcaption', $ad['name']);
				//$element->append('a', ['href' => $ad['support']])->append('img');
			}
			return $element;
		}
		return NULL;
	}

	function add_video_lists(webapp_html $node, iterable|string $videos, int $display = 0, string $title = NULL, string $more = NULL):webapp_html
	{
		if ($title)
		{
			$element = $node->append('div', ['class' => 'titles']);
			$element->append('strong', $title);
			if ($more)
			{
				$element->append('a', ['更多 >>', 'href' => $more]);
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
				$figure = $element->append('a', ['href' => "?home/watch,hash:{$video['hash']}"])->append('figure', ['data-require' => match (intval($video['require'])) {
					-1 => '会员',
					0 => '免费',
					default => "{$video['require']} 金币"
				}]);
				$figure->append('img', ['loading' => 'lazy', 'src' => $video['cover']]);
				$figure->append('figcaption', webapp_html::charsafe($video['name']));
			}
		}
		return $element;
	}

	function get_splashscreen()
	{
		$this->script('postMessage("close")');
		return 200;
		if (empty($ads = $this->webapp->fetch_ads(0)))
		{
			$this->script('postMessage("close")');
			return 200;
		}
		$ad = $this->webapp->random_weights($ads);
		$this->script('masker.then(masker.splashscreen)');
		$this->xml->body->div['class'] = 'splashscreen';
		$this->xml->body->setattr([
			'style' => "background: white url({$ad['picture']}) center/cover no-repeat",
			'data-support' => $ad['support'],
			'data-duration' => 5,
			'data-autoskip' => TRUE
		]);
	}
	function get_home(string $type = NULL)
	{
		$this->webapp->redis->flushall();
		$this->aside['class'] = 'classify';
		$this->aside->append('a', ['最新', 'href' => '?home/home', 'class' => 'selected']);
		foreach ($classify = $this->webapp->fetch_tags() as $hash => $name)
		{
			$node = $this->aside->append('a', [$name, 'href' => "?home/home,type:{$hash}"]);
			if ($hash === $type)
			{
				unset($this->aside->a['class']);
				$node['class'] = 'selected';
			}
		}
		$this->aside->insert('aside', 'after')->setattr('style', join(';', [
			'position: sticky',
			'top: 2rem',
			'height: .4rem',
			'margin-top: -1rem',
			'box-shadow: 0 0 .6rem var(--webapp-edge)',
			'margin-bottom: 1rem',
			'z-index: 1'
		]));
		$this->set_header_search();
		$this->set_footer_menu();
		$this->add_slideshows_ads($this->main, 1);
		$this->main['style'] = 'padding: 0 var(--webapp-gapitem);';
		foreach ($this->webapp->fetch_subjects(isset($classify[$type]) ? $type : NULL) as $subject)
		{
			$videos = [];
			if (strlen($subject['videos']) > 11)
			{
				foreach (str_split($subject['videos'], 12) as $hash)
				{
					if ($video = $this->webapp->fetch_video($hash))
					{
						$videos[] = $video;
					}
				}
			}
			$this->add_video_lists($this->main, $videos, $subject['style'], $subject['name'],
				isset($classify[$type]) ? "?home/subject,hash:{$subject['hash']}" : "?home/home,type:{$subject['hash']}");
		}
	}
	function get_subject(string $hash, int $page = 0)
	{
		if ($page > 0)
		{
			$this->template();
			$this->webapp->fetch_subject($hash, $page);
			//$this->add_video_lists($this->template(), $this->webapp->data_subjects($hash, $page));
			return;
		}

		
		if ($subject = $this->webapp->fetch_subject($hash))
		{
			$this->set_header_title($subject['name'], 'javascript:history.back();')['style'] = 'position:sticky;top:0;z-index:2;box-shadow: 0 0 .4rem var(--webapp-edge)';
			$this->add_slideshows_ads($this->main, 1);
			$this->add_video_lists($this->main, "?home/subject,hash:{$hash},page:", $subject['style']);
			$this->main['style'] = 'padding: 0 var(--webapp-gapitem);';
		}

		//if (empty($subject = $this->webapp->data_subjects($hash))) return 404;
		// $this->aside['style'] = join(';', [
		// 	'position: sticky',
		// 	'top: 2rem',
		// 	'height: .4rem',
		// 	'margin-top: -1rem',
		// 	'box-shadow: 0 0 .6rem var(--webapp-edge)',
		// 	'margin-bottom: 1rem',
		// 	'z-index: 1'
		// ]);

		// $this->set_header_title($subject['name'], 'javascript:history.back();')['style'] = 'position:sticky;top:0;z-index:2;box-shadow: 0 0 .4rem var(--webapp-edge)';
		// $this->add_slideshows_ads($this->main, 1);
		// $this->add_video_lists($this->main, "?home/subjects,hash:{$hash},page:", $subject['style']);
		// $this->main['style'] = 'padding: 0 var(--webapp-gapitem);';

	}
	function get_search(string $word = NULL, string $tags = NULL, int $page = 0)
	{
		if ($page > 0)
		{
			$this->add_video_lists($this->template(), $this->webapp->data_search_video($word, $tags, $page));
			return;
		}
		$this->set_header_search();
		$this->add_slideshows_ads($this->main, 1);
		$this->add_video_lists($this->main, "?home/search,word:{$word},tags:{$tags},page:", 2);
	}

	function get_watch(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);



		//$this->set_header_search();
		

		$video = [];
		if ($this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->fetch($video))
		{
			$ym = date('ym', $video['mtime']);
			$video['poster'] = "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}";
			$video['m3u8'] = "?/{$ym}/{$video['hash']}/play?mask{$video['mtime']}";
		}
		

		$this->set_header_search();
		


		$this->aside['style'] = 'position:sticky;top:0;z-index:1';
		$this->aside->append('webapp-video', [
			'data-poster' => $video['poster'],
			'data-m3u8' => $video['m3u8'],
			'oncanplay' => 'masker.canplay(this)',

			'class' => 'v',
			//'autoheight' => NULL,
			//'autoplay' => NULL,
			'controls' => NULL,
			// 'muted' => NULL
		]);
		$node = $this->main->append('div', ['class' => 'videoinfo']);
		$node->append('strong', $video['name']);
		// $statistics = $node->append('div', ['class' => 'statistics']);
		// $statistics->append('mark', sprintf('%s 次观看, %s', number_format($video['view']), date('Y-m-d', $video['ptime'])));
		if ($video['tags'])
		{
			$nodetags = $node->append('mark');
			$datatags = $this->webapp->data_classify_tags(substr($video['tags'], 0, 4));
			foreach (explode(',', $video['tags']) as $tag)
			{
				if (isset($datatags[$tag]))
				{
					$nodetags->append('a', [$datatags[$tag], 'href' => "?home/search,tag:{$tag}"]);
				}
				
			}
		}
		
		

		
		$this->add_video_lists($this->main, $this->webapp->data_like_videos($video), 2, '可能喜欢');
	}
	function get_short(int $page = 0)
	{
		if ($page)
		{
			$this->json([
				[ 'poster' => '?/2309/1N1VGT0V6UCT/cover?MASK1695742222', 'm3u8' => '?/2309/1N1VGT0V6UCT/play?mask1670409186' ],
				[ 'poster' => '?/2308/V9CUN2SBDION/cover?MASK1695742222', 'm3u8' => '?/2308/V9CUN2SBDION/play?mask1670409186' ],
				[ 'poster' => '?/2309/OKD6KOT82RBP/cover?MASK1695742222', 'm3u8' => '?/2309/OKD6KOT82RBP/play?mask1670409186' ],
				[ 'poster' => '?/2309/OIM8UP1HL9FD/cover?MASK1695742222', 'm3u8' => '?/2309/OIM8UP1HL9FD/play?mask1670409186' ],
				[ 'poster' => '?/2309/SEJFF8KRES9L/cover?MASK1695742222', 'm3u8' => '?/2309/SEJFF8KRES9L/play?mask1670409186' ],
				[ 'poster' => '?/2309/IA9RL8VDDI2J/cover?MASK1695742222', 'm3u8' => '?/2309/IA9RL8VDDI2J/play?mask1670409186' ],
			]);
			return;
		}
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->meta(['name' => 'theme-color', 'content' => 'black']);
		$this->xml->body->div['class'] = 'short';
		$this->header->append('a', ['href' => 'javascript:history.back();', 'class' => 'arrow']);
		$this->header->append('strong', '短视频');
		$this->main->append('webapp-videos', [
			'onchange' => 'masker.shortchanged(this)',
			'data-fetch' => '?home/short,page:',
			'data-page' => 1,
			//'autoplay' => NULL,
			'controls' => NULL,
			// 'muted' => NULL
		]);

		//$this->set_footer_menu();
	}
	function get_game()
	{
		$this->set_footer_menu();
	}
	function get_series()
	{
		$this->add_nav_ads($this->main, 1, 'asdawd');
		$this->set_footer_menu();
	}

	function get_prods()
	{

	}
	function get_top_up(string $type)
	{

	}

	function get_my()
	{

		$this->webapp->redis->hMSet('A', ['a' => 1, 'b' => [1,2,3]]);

		print_r($this->webapp->redis->hGetAll('A'));
		return;




		print_r($this->webapp->fetch_subjects('tAF9'));


		return;
		$this->xml->body->div['class'] = 'my';

		print_r( $this->webapp->fetch_video('MDSE00000036') );

		//$this->mysql->configs->column('value', 'key')


		// var_dump($this->webapp->redis->hmset );
		// var_dump( $this->webapp->redis->hmset('dasd', [
		// 	'asd' => 123,
		// 	'dweawe' => 4654
		// ]) );
		
		//var_dump( $this->webapp->redis->exists('dasd') );


		$this->set_footer_menu();
	}
}