<?php
class webapp_router_home extends webapp_echo_masker
{
	private readonly bool $free;
	private readonly user $user;
	private readonly array $tags;
	protected array $allow = ['get_splashscreen', 'post_create_account', 'get_init'];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		unset($this->xml->body->div['class']);
		$this->free = $webapp['app_free'];
		$this->footer[0] = NULL;
		if ($this->initiated)
		{
			return;
		}
		$this->title($webapp['app_name']);
		$this->xml->head->meta[1]['content'] .= ',user-scalable=0';
		$this->link_resources($webapp['app_resources']);
		$this->xml->head->link['href'] = '/webapp/app/star/home.css?' . $webapp->random_hash(TRUE);
		$this->script(['src' => '/webapp/app/star/home.js?v=1']);
		$this->script(['src' => '/webapp/res/js/slideshows.js']);
		
		//$this->footer->text('asd');

	}
	function init(bool $success)
	{
		$this->main->text($success
			? '加密中，防止泄露隐私，请稍等。。。'
			: '您的iOS版本过低，请更新后继续访问。。。');
	}
	function authorization($uid, $pwd):array
	{
		//$this->webapp->redis->flushall();
		$this->user = new user($this->webapp, $this->webapp->fetch_user($uid));
		return $this->user->id ? [$this->user['id'], $this->user['cid']] : [];
	}
	function form_login(webapp_html $node = NULL):webapp_form
	{
		$form = new webapp_form($node ?? $this->webapp, '?home/create_account');
		$form->fieldset->append('img', ['src' => '/webapp/app/star/static/logo.png']);
		$form->fieldset->append('strong', $this->webapp['app_name']);
		// $form->fieldset();
		// $form->button('扫码凭证登录');
		$form->fieldset();
		$form->fieldset->append('label', ['使用凭证登录', 'class' => 'button'])->append('input', [
			'type' => 'file',
			'accept' => 'image/png',
			'style' => 'display:none',
			'onchange' => 'masker.revert_account(this)'
		]);
		$form->fieldset();
		$form->button('我已满18周岁', 'submit');
		$form->fieldset()->text('警告：禁止未满18周岁的用户登录使用！');
		$form->field('cid');
		$form->field('did');
		$form->field('tid');
		if ($form->echo)
		{
			$ua = $this->webapp->request_device();
			$did = $this->webapp->query['did'] ?? NULL;
			$form->echo([
				'cid' => preg_match('/CID\/(\w{4})/', $ua, $pattern) ? $pattern[1] : (string)$this->webapp->redis->get("cid:{$did}"),
				'did' => preg_match('/DID\/(\w{16})/', $ua, $pattern) ? $pattern[1] : $did,
				'tid' => NULL]);
		}
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
		$data = ['token' => NULL];
		if ($this->form_login()->fetch($data['login']))
		{
			$user = $this->webapp->user_create($data['login']);
			$data['token'] = (string)$user;
		}
		$this->json($data);
	}
	function set_header_index():webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', ['href' => $goback, 'class' => 'arrow']);
		$this->header->append('input', ['type' => 'search', 'placeholder' => '请输入关键词搜索', 'onkeypress' => 'if(event.keyCode===13)location.href=this.nextElementSibling.dataset.search+this.value']);
		$this->header->append('button', ['搜索', 'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value', 'data-search' => '?home/search,word:']);

		return $this->header;
	}
	function set_header_search(?string $goback = 'javascript:history.back();', string $word = NULL):webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', $goback === NULL
			? ['href' => 'javascript:location.reload();', 'class' => 'logo']
			: ['href' => $goback, 'class' => 'arrow']);

			
		$search = $this->header->append('input', ['type' => 'search',
			'placeholder' => '请输入关键词搜索',
			'onkeypress' => 'if(event.keyCode===13)location.href=this.nextElementSibling.dataset.search+this.value']);
		$goback
			? $search->setattr(['autofocus' => NULL, 'value' => $word])
			: $search->setattr(['onfocus' => 'this.value||location.assign("?home/search")']);
		$this->header->append('button', ['搜索',
			'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value',
			'data-search' => '?home/search,word:']);

		return $this->header;
	}
	function set_header_title(string $name, string $goback = 'javascript:history.back();'):webapp_html
	{
		$this->header->append('a', ['href' => $goback, 'class' => 'arrow']);
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
		$this->footer->append('a', ['剧场', 'href' => '?home/home,type:K3yp']);
		$this->footer->append('a', ['我的', 'href' => '?home/my']);
		return $this->footer;
	}


	function add_slideshows_ads(webapp_html $node, int $seat, int $duration = 5):?webapp_html
	{
		if ($ads = $this->webapp->fetch_ads->seat($seat))
		{
			$element = $node->append('webapp-slideshows', ['data-duration' => $duration]);
			$element->cdata(json_encode($ads, JSON_UNESCAPED_UNICODE));
			return $element;
		}
		return NULL;
	}
	function add_nav_ads(webapp_html $node, int $seat, string $title = NULL):?webapp_html
	{
		if ($ads = $this->webapp->fetch_ads->seat($seat))
		{
			if ($title)
			{
				$element = $node->append('div', ['class' => 'titles']);
				$element->append('strong', $title);
			}

			$element = $node->append('div', ['class' => 'grid-icon']);


			foreach ($ads as $ad)
			{
				$anchor = $element->append('a', ['href' => $ad['support']]);
				$anchor->append('figure')->append('img', ['src' => $ad['picture']]);
				$anchor->append('strong', $ad['name']);

				//$a->append('figcaption', $ad['name']);
				//$element->append('a', ['href' => $ad['support']])->append('img');
			}
			return $element;
		}
		return NULL;
	}
	function add_video_lists(webapp_html $node,
		string|iterable $videos,
		int $display = 0,
		string $title = NULL,
		string $anchor = NULL,
		string $action = '更多 >>'):webapp_html
	{
		if ($title)
		{
			$element = $node->append('div', ['class' => 'titles']);
			$element->append('strong', $title);
			if ($anchor)
			{
				$element->append('a', [$action, 'href' => $anchor]);
			}
		}
		$pagination = $videos instanceof webapp_redis_table;
		$element = $node->getName() === 'template' ? $node : $node->append('div', ['class' => $pagination ? 'grid' : "grid-t{$display}"]);
		if (is_string($videos))
		{
			$node->append('blockquote', ['内容加载中...', 'data-lazy' => $videos, 'data-page' => 1]);
		}
		else
		{
			$size = 40;
			foreach ($pagination ? $videos->paging($display, $size) : $videos as $video)
			{
				$content = $element->append('a', ['href' => "?home/watch,hash:{$video['hash']}"]);
				$tags = $video['tags'] ? explode(',', $video['tags']) : [];
				$attributes = [];
				if (in_array('_2OO', $tags, TRUE))
				{
					$attributes['data-label'] = '推荐';
				}
				if (in_array('liP_', $tags, TRUE))
				{
					$attributes['data-require'] = '中文';
				}
				// if ($this->free === FALSE)
				// {
				// 	$attributes['data-require'] = match (intval($video['require']))
				// 	{
				// 		-1 => '会员',
				// 		0 => '免费',
				// 		default => "{$video['require']} 金币"
				// 	};
				// }

				$figure = $content->append('figure', $attributes);
				$figure->append('img', ['loading' => 'lazy', 'src' => $video['poster']]);
				$figure->append('figcaption', $video['duration']);

				$content->append('strong', htmlentities($video['name']));
				if (isset($this->tags) && $tags)
				{
					$mark = $content->append('div')->append('mark');
					foreach (array_reverse($tags) as $tag)
					{
						if (isset($this->tags[$tag]))
						{
							$mark->append('span', "#{$this->tags[$tag]}");
						}
						
					}
				}
			}
			if ($pagination && ($max = ceil($videos->count() / $size)) > 1)
			{
				$display = max(1, $display);
				$url = $this->webapp->at(['page' => '']);
				$page = $node->append('div', ['class' => 'page']);
				$show = 4;
				if ($max > $show)
				{
					$halved = intval($show * 0.5);
					$offset = min($max, max($display, $halved) + $halved);
					$ranges = range(max(1, $offset - $halved * 2 + 1), $offset);
					$display > 1 && $page->append('a', ['首页', 'href' => "{$url}1"]);
					foreach ($ranges as $index)
					{
						$curr = $page->append('a', [$index, 'href' => "{$url}{$index}"]);
						if ($index == $display)
						{
							$curr['class'] = 'selected';
						}
					}
					$display < $max && $page->append('a', ['最后', 'href' => $url . $max]);
				}
				else
				{
					for ($i = 1; $i <= $max; ++$i)
					{
						$curr = $page->append('a', [$i, 'href' => "{$url}{$i}"]);
						if ($i === $display)
						{
							$curr['class'] = 'selected';
						}
					}
				}
			}
		}
		return $element;
	}
	function add_scrolltop():void
	{
		$this->xml->body->append('a', ['href' => 'javascript:scrollTo({top:0,behavior:"smooth"});',
			'class' => 'scrolltop'])->svg(['fill' => 'white'])->icon('move-to-top', 32);
	}

	function get_splashscreen()
	{
		// $this->script('postMessage("close")');
		// return 200;
		
		if (empty($ad = $this->webapp->fetch_ads->rand(0)))
		{
			$this->script('postMessage("close")');
			return 200;
		}
		// foreach ($ads as $ad)
		// {
		// 	if($ad['hash'] === 'NS1HM8DBP86K')
		// 	{
		// 		break;
		// 	}
		// }

		$this->script('masker.then(masker.splashscreen)');
		$this->xml->body->div['class'] = 'splashscreen';
		$this->xml->body->setattr([
			'style' => "background: url({$ad['picture']}) center / cover no-repeat var(--webapp-background)",
			'data-support' => $ad['support'],
			'data-duration' => 5,
			'data-autoskip' => TRUE
		]);
	}
	function get_init()
	{
		$data = ['notice' => NULL, 'popup' => NULL];
		$configs = $this->webapp->fetch_configs();
		if ($configs['notice_title'])
		{
			$data['notice'] = ['title' => $configs['notice_title'], 'content' => $configs['notice_content']];
		}
		if ($ad = $this->webapp->fetch_ads->rand(0))
		{
			$data['popup'] = ['title' => $ad['name'], 'picture' => $ad['picture'], 'support' => $ad['support']];
		}
		$this->json($data);
	}
	function get_home(string $type = '')
	{
		$this->aside['class'] = 'classify';
		$this->aside->append('a', ['最新', 'href' => '?home/home', 'class' => 'selected']);
		$classify = $this->webapp->fetch_tags->classify();
		foreach ($classify as $hash => $name)
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
		$this->set_header_search(NULL);
		$this->set_footer_menu();
		$this->add_scrolltop();
		$this->add_slideshows_ads($this->main, 1);
		if (isset($classify[$type]) === FALSE)
		{
			$this->add_nav_ads($this->main, 9, '福利导航');
		}
		$this->tags = $this->webapp->fetch_tags->shortname();
		if ($subjects = $this->webapp->fetch_subjects->classify($type))
		{
			foreach ($subjects as $subject)
			{
				$this->add_video_lists($this->main, $this->webapp->fetch_videos->iter(...str_split($subject['videos'], 12)),
					$subject['style'], $subject['name'], "?home/subject,hash:{$subject['hash']}");
			}
		}
		else
		{
			foreach ($classify as $hash => $name)
			{
				$this->add_video_lists($this->main, $this->webapp->fetch_videos
					->with('type="h" AND FIND_IN_SET(?s,tags)', $hash)->show(5), 3, "最新{$name}", "?home/home,type:{$hash}");
			}
		}
	}
	function get_subject(string $hash, int $page = 0)
	{
		$this->tags = $this->webapp->fetch_tags->shortname();
		// if ($page)
		// {
		// 	$this->add_video_lists($this->template(), $this->webapp->fetch_subjects->videos($hash, $page));
		// 	return;
		// }
		$this->add_slideshows_ads($this->main, 1);
		if (is_array($subject = $this->webapp->fetch_subjects[$hash]))
		{
			$this->set_header_title($subject['name'], 'javascript:history.back();')['style'] = 'position:sticky;top:0;z-index:2;box-shadow: 0 0 .4rem var(--webapp-edge)';
			$this->add_video_lists($this->main, $this->webapp->fetch_videos->with('FIND_IN_SET(?s,subjects)', $hash), $page);
		}
	}
	function get_search(string $word = '', int $page = 0)
	{
		
		if ($word = trim(urldecode($word)))
		{
			$cond = ['name LIKE ?s', "%{$word}%"];
			if ($tags = $this->webapp->fetch_tags->like($word))
			{
				$cond[0] .= ' OR FIND_IN_SET(?s,tags)';
				$cond[] = $tag = current($tags);
			}
			// if ($page)
			// {
			// 	$this->add_video_lists($this->template(), $this->webapp->fetch_videos->with(...$cond)->paging($page));
			// 	return;
			// }
			$this->set_header_search(word:$word);
			$this->add_slideshows_ads($this->main, 1);
			$this->add_scrolltop();
			$classify = $this->webapp->fetch_tags->classify();
			foreach ($classify as $hash => $name)
			{
				$node = $this->aside->append('a', [$name, 'href' => "?home/home,type:{$hash}"]);
				// if ($hash === $type)
				// {
				// 	unset($this->aside->a['class']);
				// 	$node['class'] = 'selected';
				// }
			}
			$this->aside['class'] = 'classify';
			$result = $this->webapp->fetch_videos->with(...$cond);
			if ($result->count())
			{
				$this->add_video_lists($this->main, $result, $page);
			}
			else
			{
				$this->main->append('blockquote', '404 很抱歉，没有找到相关内容');
			}
			return;
		}
		$this->set_header_search();
		$this->add_slideshows_ads($this->main, 1);
		foreach ($this->webapp->fetch_tags->levels(not:[0, 1, 2, 3, 12]) as $describe => $tags)
		{
			$node = $this->main->append('div', ['class' => 'videoinfo']);
			$node->append('strong', $describe);
			$mark = $node->append('mark');
			foreach ($tags as $name)
			{
				$mark->append('a', [$name, 'href' => '/?home/search,word:' . urlencode($name)]);
			}
		}
	}

	function get_watch(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->set_header_search();
		$this->set_footer_menu();
		$this->aside['class'] = 'watch';
		if (empty($video = $this->webapp->fetch_videos[$hash]))
		{
			$this->aside->append('strong', '您所观看的影片不见啦 :(');
			return 404;
		}
		$this->aside['data-type'] .= $video['type'];

		

	

		// if (1)
		// {
		// 	$strong = $this->aside->append('strong')->append('span');
		// 	$strong->text('每日观影剩余次数已耗尽，请点击');
		// 	$strong->append('q', ['style' => 'margin:0 var(--webapp-gap)'])->append('a', ['分享链接', 'href' => '?home/my-shareurl']);
		// 	$strong->text('获得更多次数！');
		// 	return 401;
		// }
		




		$this->user->watch($hash);

		// return;
		// if (in_array(str_split($this->user['favorites'], 12)))
		// {
		// 	$this->user->count(-1);
		// }


		//$this->aside['style'] = 'position:sticky;top:0;z-index:9';
		
		$watch = $this->aside->append('webapp-video', [
			'data-poster' => $video['poster'],
			'data-m3u8' => $video['m3u8'],
			//'oncanplay' => 'masker.canplay(this)',
			//'autoheight' => NULL,
			//'autoplay' => NULL,
			//'muted' => NULL,
			'controls' => NULL
		]);
		
		if ($ad = $this->webapp->fetch_ads->rand(3))
		{
			$watch->append('a', ['href' => $ad['support'],
				'data-duration' => 5,
				'data-unit' => '秒',
				'data-skip' => '跳过',
				'onclick' => 'console.log(123)'
			])->append('img', ['src' => $ad['picture'],
				'onload' => 'this.parentNode.parentNode.splashscreen(this.parentNode)',
				'onerror' => 'this.parentNode.parentNode.removeChild(this.parentNode)'
			]);
		}
		else
		{
			$watch->setattr('autoplay');
		}

		$videoinfo = $this->main->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong', htmlentities($video['name']));

		$this->tags = $this->webapp->fetch_tags->shortname();
		if ($video['tags'])
		{
			$taginfo = $videoinfo->append('mark');
			foreach (explode(',', $video['tags']) as $tag)
			{
				if (isset($this->tags[$tag]))
				{
					$taginfo->append('a', [$this->tags[$tag], 'href' => '?home/search,word:' . urlencode($this->tags[$tag])]);
				}
			}
		}
		$videomenu = $videoinfo->append('div');

		

		$anchor = $videomenu->append('a', ['href' => '?home/my-favorites', 'onclick' => 'return masker.favorite(this)', 'data-hash' => $hash]);
		$anchor->svg(['fill' => 'white'])->icon('star');
		$anchor->svg(['fill' => 'white', 'style' => 'display:none'])->icon('star-fill');
		$anchor->append('span', '收藏');
		if ($this->user->favorited($hash))
		{
			$anchor->svg[0]['style'] = 'display:none';
			$anchor->svg[1]['style'] = 'display:block';
			$anchor->span[0] = '已收藏';
		}
		

		// [$icon, $name] = $this->user->favorite_has($hash) ? ['star-fill', '已收藏'] : ['star', '收藏'];



		// $anchor->svg(['fill' => 'white'])->icon($icon);
		// $anchor->append('span', $name);


		$anchor = $videomenu->append('a', ['href' => 'javascript:alert(1);']);
		$anchor->svg(['fill' => 'white'])->icon('eye');
		$anchor->append('span', $video['view']);

		$anchor = $videomenu->append('a', ['href' => 'javascript:alert(1);']);
		$anchor->svg(['fill' => 'white'])->icon('thumbsup');
		$anchor->append('span', $video['like']);

		
		$this->add_video_lists($this->main, $this->webapp->fetch_videos->similar($hash), 2, '可能喜欢');
	}
	function get_short(int $page = 0)
	{
		if ($page)
		{
			$videos = [];
			$tags = $this->webapp->fetch_tags->shortname();
			foreach ($this->webapp->fetch_videos->with('type="v"')->random(20) as $video)
			//foreach ($this->webapp->fetch_videos->with('type="v"')->paging($page, 6) as $video)
			{
				$tagdata = [];
				foreach ($video['tags'] ? explode(',', $video['tags']) : [] as $taghash)
				{
					if (isset($tags[$taghash]))
					{
						$tagdata[$taghash] = $tags[$taghash];
					}
				}
				$video['tags'] = $tagdata;
				$video['favorited'] = $this->user->favorited($video['hash']);
				$video['liked'] = random_int(0, 1);
				$videos[] = $video;
			}
			$this->json($videos);
			return;
		}
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->meta(['name' => 'theme-color', 'content' => 'black']);
		$this->xml->body->div['class'] = 'short';
		$this->header->append('a', ['href' => 'javascript:history.back();', 'class' => 'arrow']);
		$this->header->append('strong', '短视频');
		$template = $this->main->append('webapp-videos', [
			'onchange' => 'masker.shortchanged(this)',
			'data-fetch' => '?home/short,page:',
			'data-page' => 1,
			//'autoplay' => NULL,
			'controls' => NULL,
			// 'muted' => NULL
		])->append('template');

		$videoinfo = $template->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong');
		$videoinfo->append('mark');

		$videolink = $template->append('div', ['class' => 'videolink']);
		$videolink->append('img');
		$anchor = $videolink->append('a', ['href' => '?home/my-favorites', 'data-field' => 'favorited', 'data-label' => '收藏']);
		$anchor->svg(['fill' => 'white'])->icon('star', 32);
		$anchor->svg(['fill' => 'white'])->icon('star-fill', 32);

		$anchor = $videolink->append('a', ['data-field' => 'liked', 'data-label' => '喜欢']);
		$anchor->svg(['fill' => 'white'])->icon('heart', 32);
		$anchor->svg(['fill' => 'white'])->icon('heart-fill', 32);

		$this->footer->setattr('style', 'height:1rem');
		//$this->set_footer_menu();
	}
	function post_view(string $hash)
	{
		$this->json(['result' => $this->user->watch($hash)]);
	}
	function get_like(string $hash)
	{
		$this->json(['result' => $this->user->watch($hash)]);
	}
	function get_my()
	{
		$this->xml->body->div['class'] = 'my';
		$this->set_header_title('个人中心');

		$this->aside->append('img', ['src' => $qrurl = '?qrcode/' . $this->webapp->encrypt($this->user)]);
		$info = $this->aside->append('div');
		$info->append('a', [$this->user->id, 'href' => 'javascript:;', 'data-label' => '账号：',
			'onclick' => 'navigator.clipboard.writeText(this.textContent).then(()=>alert("复制成功！"))']);
		$info->append('a', [$this->user['nickname'], 'href' => 'javascript:;', 'data-label' => '花名：',
			'onclick' => 'return masker.nickname(this)']);
		$info->append('a', ['点击下载保存凭证', 'href' => "{$qrurl},type:png,filename:{$this->user->id}.png", 'target' => '_blank', 'data-label' => '凭证：']);

		$anchors = $this->main->append('div', ['class' => 'listmenu']);
		$anchors->append('a', ['每日观影剩余次数', 'href' => 'javascript:;', 'data-right' => sprintf('%d 次', count($this->user))]);
		$anchors->append('a', ['商务洽谈', 'href' => $this->webapp['app_business'], 'target' => '_blank', 'data-right' => 'Telegram']);
		$anchors->append('a', ['官方交流', 'href' => $this->webapp['app_community'], 'target' => '_blank', 'data-right' => 'Telegram']);

		$anchors->append('a', ['输入邀请码',
			'href' => '?home/my-invite,code:',
			'onclick' => 'return !masker.prompt(this.textContent).then(value => masker.json(this.href + value))',
			'data-right' => $this->user['iid'] ? '已领取' : '未领取']);
		$anchors->append('a', ['分享链接，双方获得奖励', 'href' => '?home/my-shareurl', 'data-right' => "{$this->user['share']} 次"]);

		$anchors->append('a', ['收藏记录', 'href' => '?home/my-favorites', 'data-right' => count($this->user->favorites())]);
		$anchors->append('a', ['历史记录', 'href' => '?home/my-historys', 'data-right' => count($this->user->historys())]);
		$anchors->append('a', ['问题反馈', 'href' => '?home/my-report', 'data-right' => '>>']);
		//$anchors->append('a', ['注销账号', 'href' => 'javascript:;', 'onclick' => 'return masker.delete_account(this)', 'data-right' => '退出']);
		$node = $this->main->append('ul');
		$first = $node->append('li');
		$first->text('请记住本站回家域名');
		$first->append('q', [$this->webapp['app_website'], 'style' => 'color:var(--webapp-foreground)']);
		$first->text('回家不迷路！');
		$node->append('li', '保存凭证后，可以通过凭证找回账号！');
		if ($this->user['did'])
		{
			$logout = $node->append('li');
			$logout->text('您有还可以');
			$logout->append('a', ['注销',
				'href' => "?home/home,did:{$this->user['did']}",
				'onclick' => 'return masker.delete_account(this)',
				'style' => 'margin: 0 .4rem']);
			$logout->text('您的账号。');
		}
		$this->set_footer_menu();
	}
	function get_my_clear(string $action)
	{
		$this->json(['result' => $this->user->clear($action), 'reload' => 0]);
	}
	//邀请代码
	function get_my_invite(string $code)
	{
		$this->json($this->user->invite(preg_replace('/[^0-9A-Z]+/', '', strtoupper($code)), $error)
			? ['dialog' => '邀请成功！', 'reload' => 0]
			: ['errors' => [$error]]);
	}
	//分享链接
	function get_my_shareurl()
	{
		// var_dump( $this->user->invite('JGGCV5K2FLPQ', $error), $error, $this->user->count() );



		// return;


		$this->set_header_title('分享链接');
		//$configs = $this->webapp->fetch_configs()['down_page'];

		

		$dl = $this->webapp->fetch_configs()['down_page'];

		$this->main['class'] = 'myshare';
		$this->main->append('div', '请保存截图或者使用对方手机扫以下二维码，下载安装后进入个人中心，输入以下邀请代码后，双双获得奖励！');
		$this->main->append('figure')->append('img', ['src' => sprintf('?qrcode/%s', $this->webapp->encrypt($dl))]);

		
		$mark = $this->main->append('mark', ['data-iid' => '邀请码：']);
		foreach (str_split($this->webapp->time33hash($this->webapp->hashtime33($this->user->id), FALSE), 4) as $a)
		{
			$mark->append('span', $a);
		}
		$this->main->append('a', ['复制二维码地址', 'href' => $dl, 'class' => 'button',
			'onclick' => 'return !navigator.clipboard.writeText(this.href).then(()=>alert("链接拷贝成功，请分享给好友通过浏览器打开下载APP"),()=>location.href=this.href)']);
		$dl = $this->main->append('dl');
		$dl->append('dt', '奖励规则：');
		$dl->append('dd', '被邀请人获得每日影片观看 +10 次。');
		$dl->append('dd', '邀请他人一次，每日影片观看 +10 次。');
		$dl->append('dd', '邀请他人二次，每日影片观看 +20 次。');
		$dl->append('dd', '邀请他人三次及以上，无限观看影片。');
	}


	function form_report(webapp_html $node = NULL):webapp_form
	{
		$form = new webapp_form($node ?? $this->webapp);
		$form->fieldset();
		$form->field('question', 'textarea', [
			'placeholder' => '请尽可能详细的描述您当前遇到的问题，以便我们可以进行及时有效的处理。',
			'spellcheck' => 'false',
			'maxlength' => 200,
			'rows' => 12,
			'required' => NULL
		]);
		$form->fieldset();
		$form->button('提交问题', 'submit');
		$form->xml['onsubmit'] = 'return masker.submit(this)';
		return $form;
	}
	function post_my_report()
	{
		$data = ['dialog' => '反馈失败，请稍后重试！'];
		if ($this->form_report()->fetch($report) && $this->user->report($report['question']))
		{
			$data['dialog'] = '我们会尽快处理您反馈的问题！';
			$data['reload'] = 0;
		}
		$this->json($data);
	}
	function get_my_report()
	{
		$this->set_header_title('问题反馈');
		$this->form_report($this->aside);



		$this->set_footer_menu();
	}
	function get_my_historys()
	{
		$this->set_header_title('观影记录');
		$this->set_footer_menu();
		$this->add_video_lists($this->main,
			$videos = $this->user->historys(TRUE),
			count($videos) % 2 ? 3 : 2,
			'#最多保留50个记录',
			'javascript:masker.clear("historys");', '清除所有观影记录');
	}
	function get_my_favorites(string $hash = NULL)
	{
		if ($hash)
		{
			$this->json(['result' => $this->user->favorite($hash)]);
			return;
		}
		$this->set_header_title('收藏记录');
		$this->set_footer_menu();
		$this->add_video_lists($this->main,
			$videos = $this->user->favorites(TRUE),
			count($videos) % 2 ? 3 : 2,
			'#最多保留50个记录',
			'javascript:masker.clear("favorites");', '清除所有收藏记录');
	}
}