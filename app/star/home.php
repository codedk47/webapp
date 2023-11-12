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
			'style' => "background: white url({$ad['imgurl']}) center/cover no-repeat",
			'data-acturl' => $ad['acturl'],
			'data-duration' => 5,
			'data-autoskip' => TRUE
		]);
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
		$this->header->append('input', ['type' => 'search']);
		$this->header->append('button', ['搜索']);

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
		$this->main['style'] = 'margin-bottom:8rem';
		$this->footer['class'] = 'nav';
		$this->footer->append('a', ['首页', 'href' => '?home/home']);
		$this->footer->append('a', ['抖音', 'href' => '?home/short']);
		$this->footer->append('a', ['游戏', 'href' => '?home/game']);
		$this->footer->append('a', ['我的', 'href' => '?home/my']);
		return $this->footer;
	}


	function add_advertisements(webapp_html $node, int $seat):?webapp_html
	{
		if ($ads = $this->webapp->data_advertisements($seat))
		{
			$element = $node->append('webapp-slideshows');
			$element->cdata(json_encode($ads));
			return $element;
		}
		return NULL;
	}

	function add_video_lists(webapp_html $node, iterable $videos, int $display = 1, string $title = NULL, string $more = NULL):webapp_html
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
		$element = $node->append('div', ['class' => "videos-t{$display}"]);
		foreach ($videos as $video)
		{
			$figure = $element->append('a', ['href' => "?home/watch,hash:{$video['hash']}"])->append('figure', ['data-require' => match (intval($video['require'])) {
				-1 => '会员',
				0 => '免费',
				default => "{$video['require']} 金币"
			}]);
			$figure->append('img', ['loading' => 'lazy', 'src' => $video['cover']]);
			$figure->append('figcaption', $video['name']);
		}
		return $element;
	}





	function get_home(string $type = NULL)
	{
		$this->aside['class'] = 'classify';
		$this->aside->append('a', ['最新', 'href' => '?home/home', 'class' => 'selected']);
		foreach ($classify = $this->webapp->data_classify() as $hash => $name)
		{
			$node = $this->aside->append('a', [$name, 'href' => "?home/home,type:{$hash}"]);
			if ($hash === $type)
			{
				unset($this->aside->a['class']);
				$node['class'] = 'selected';
			}
		}
		$this->set_footer_menu();
		$this->add_advertisements($this->main, 1);
		if ($type === NULL)
		{
			foreach ($classify as $hash => $name)
			{
				if ($videos = $this->webapp->data_classify_top_videos($hash))
				{
					$this->add_video_lists($this->main, $videos, 0, "最新{$name}", "?home/home,type:{$hash}");
				}
			}
			return;
		}
		foreach ($this->webapp->data_classify_subjects($type) as $subject)
		{
			$this->add_video_lists($this->main,
				$subject['videos'],
				$subject['style'],
				$subject['name'], "?home/subjects,hash:{$subject['hash']}");
		}
	}
	function get_subjects(string $hash, int $page = 0)
	{
		if (empty($subject = $this->webapp->data_subjects($hash))) return 404;



		$this->set_header_title($subject['name'], 'javascript:history.back();')['style'] = 'position:sticky;top:0;z-index:1';
		$this->add_advertisements($this->main, 1);
		$this->add_video_lists($this->main, $this->webapp->data_subjects($hash, 1), $subject['style']);
		// print_r( $this->webapp->data_subjects($hash) );
		// print_r( $this->webapp->data_subjects($hash, 1) );

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
			//'autoheight' => NULL,
			//'autoplay' => NULL,
			'controls' => NULL,
			'muted' => NULL
		]);
		$node = $this->main->append('div', ['class' => 'videoinfo']);
		$node->append('div', $video['name']);
		$tags = $node->append('div', ['class' => 'tags']);



		
		foreach (explode(',', $video['tags']) as $tag)
		{
			$tags->append('a', [$tag, 'href' => "?home/search,tag:{$tag}"]);
		}

		//$v = $this->webapp->mysql->videos('WHERE sync="allow" ORDER BY ctime DESC LIMIT 20');




		//$this->add_video_lists($this->main, $v, 2, '可能喜欢');


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

		$this->aside->append('webapp-videos', [
			'style' => 'height: 20rem',
			//'onchange' => 'console.log(this.current)',
			'data-fetch' => '?home/short,page:',
			'data-page' => 1,
			'autoplay' => NULL,
			'controls' => NULL,
			// 'muted' => NULL
		]);
		
		$this->set_footer_menu();
	}
	function get_game()
	{
		$this->set_footer_menu();
	}
	function get_my()
	{
		$this->set_footer_menu();
	}
}