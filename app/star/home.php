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
	function init()
	{
		$this->main->text('加密中，防止泄露隐私，请稍等。。。');
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
		$this->header['class'] = 'index';
		
		return $this->header;
	}
	function set_header_search(string $goback = 'javascript:history.back();'):webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', ['href' => $goback, 'class' => 'arrow']);
		$this->header->append('input', ['type' => 'search', 'placeholder' => '请输入关键词搜索', 'onkeypress' => 'if(event.keyCode===13)location.href=this.nextElementSibling.dataset.search+this.value']);
		$this->header->append('button', ['搜索', 'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value', 'data-search' => '?home/search,word:']);

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
		if ($ads = $this->webapp->fetch_ads($seat))
		{
			$element = $node->append('webapp-slideshows', ['data-duration' => $duration]);
			$element->cdata(json_encode($ads, JSON_UNESCAPED_UNICODE));
			return $element;
		}
		return NULL;
	}
	function add_nav_ads(webapp_html $node, int $seat, string $title = NULL):?webapp_html
	{
		if ($ads = $this->webapp->fetch_ads($seat))
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
		iterable|string $videos,
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
		$element = $node->getName() === 'template' ? $node : $node->append('div', ['class' => "grid-t{$display}"]);
		if (is_string($videos))
		{
			$node->append('blockquote', ['内容加载中...', 'data-lazy' => $videos, 'data-page' => 1]);
		}
		else
		{
			foreach ($videos as $video)
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

				$content->append('strong', webapp_html::charsafe($video['name']));
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
		}
		return $element;
	}

	function get_splashscreen()
	{
		// $this->script('postMessage("close")');
		// return 200;
		if (empty($ads = $this->webapp->fetch_ads(0)))
		{
			$this->script('postMessage("close")');
			return 200;
		}
		$ad = $this->webapp->random_weights($ads);
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
		if ($ads = $this->webapp->fetch_ads(0))
		{
			$ad = $this->webapp->random_weights($ads);
			$data['popup'] = ['title' => $ad['name'], 'picture' => $ad['picture'], 'support' => $ad['support']];
		}
		$this->json($data);
	}
	function get_home(string $type = NULL)
	{
		
		$this->aside['class'] = 'classify';
		$this->aside->append('a', ['最新', 'href' => '?home/home', 'class' => 'selected']);
		foreach ($classify = $this->webapp->fetch_tags(0) as $hash => $name)
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
		if (isset($classify[$type]) === FALSE)
		{
			$this->add_nav_ads($this->main, 9, '福利导航');
		}
		$this->tags = $this->webapp->fetch_tags(NULL);
		foreach ($this->webapp->fetch_subjects(isset($classify[$type]) ? $type : NULL) as $subject)
		{
			$this->add_video_lists($this->main, $subject['videos'], $subject['style'], $subject['name'],
				isset($classify[$type]) ? "?home/subject,hash:{$subject['hash']}" : "?home/home,type:{$subject['hash']}");
		}
	}
	function get_subject(string $hash, int $page = 0)
	{
		if ($page > 0)
		{
			$this->tags = $this->webapp->fetch_tags(NULL);
			$this->add_video_lists($this->template(), $this->webapp->fetch_subject($hash, $page));
			return;
		}
		$this->add_slideshows_ads($this->main, 1);
		if ($subject = $this->webapp->fetch_subject($hash))
		{
			$this->set_header_title($subject['name'], 'javascript:history.back();')['style'] = 'position:sticky;top:0;z-index:2;box-shadow: 0 0 .4rem var(--webapp-edge)';
			$this->add_video_lists($this->main, "?home/subject,hash:{$hash},page:");
		}
	}
	function get_search(string $word = NULL, string $tags = NULL, int $page = 0)
	{
		// if ($page > 0)
		// {
		// 	$this->add_video_lists($this->template(), $this->webapp->data_search_video($word, $tags, $page));
		// 	return;
		// }
		$this->set_header_search();
		// $this->add_slideshows_ads($this->main, 1);
		// $this->add_video_lists($this->main, "?home/search,word:{$word},tags:{$tags},page:", 2);
	}

	function get_watch(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		if (empty($video = $this->webapp->fetch_video($hash)))
		{
			return 404;
		}
		$this->user->watch($hash);


		$this->set_header_search();
		$this->set_footer_menu();
		//$this->aside['style'] = 'position:sticky;top:0;z-index:9';
		$this->aside['class'] = 'watch';
		$this->aside->append('webapp-video', [
			'data-poster' => $video['poster'],
			'data-m3u8' => $video['m3u8'],
			'oncanplay' => 'masker.canplay(this)',
			//'autoheight' => NULL,
			//'autoplay' => NULL,
			'controls' => NULL,
			// 'muted' => NULL
		]);

		$videoinfo = $this->main->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong', $video['name']);
		// // $statistics = $node->append('div', ['class' => 'statistics']);
		// // $statistics->append('mark', sprintf('%s 次观看, %s', number_format($video['view']), date('Y-m-d', $video['ptime'])));
		$this->tags = $this->webapp->fetch_tags(NULL);
		if ($video['tags'])
		{
			$taginfo = $videoinfo->append('mark');
			foreach (explode(',', $video['tags']) as $tag)
			{
				if (isset($this->tags[$tag]))
				{
					$taginfo->append('a', [$this->tags[$tag], 'href' => "?home/search,tag:{$tag}"]);
				}
			}
		}
		$this->add_video_lists($this->main, $this->webapp->fetch_like_videos($video), 2, '可能喜欢', 'javascript:alert(1);', '换一换');
	}
	function get_short(int $page = 0)
	{
		if ($page)
		{
			$this->json($this->webapp->fetch_short_videos($page, 1));
			return;
		}
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->meta(['name' => 'theme-color', 'content' => 'black']);
		$this->xml->body->div['class'] = 'short';
		$this->header->append('a', ['href' => 'javascript:history.back();', 'class' => 'arrow']);
		$this->header->append('strong', '短视频');
		$video = $this->main->append('webapp-videos', [
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

	function get_prods()
	{

	}
	function get_top_up(string $type)
	{

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
		$anchors->append('a', ['商务洽谈', 'href' => $this->webapp['app_business'], 'target' => '_blank', 'data-right' => '💬']);
		$anchors->append('a', ['官方交流', 'href' => $this->webapp['app_community'], 'target' => '_blank', 'data-right' => '💬']);
		$anchors->append('a', ['邀请代码', 'href' => '#', 'data-right' => '>>']);
		
		$anchors->append('a', ['分享链接，双方获得奖励', 'href' => '?home/my-shareurl', 'data-right' => "{$this->user['share']} 次"]);
		$anchors->append('a', ['收藏记录', 'href' => '?home/my-favorite', 'data-right' => '>>']);
		$anchors->append('a', ['观影记录', 'href' => '?home/my-watch', 'data-right' => '>>']);
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
		$this->user->clear($action);
		$this->json(['reload' => 0]);
	}
	//分享链接
	function get_my_shareurl()
	{

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
	function get_my_watch()
	{
		$this->set_header_title('观影记录');
		$this->add_video_lists($this->main, $this->user->historys(), 1,
			'#最多保留50个记录', 'javascript:masker.clear("historys");', '清除所有观影记录');
		$this->set_footer_menu();
	}




	function get_my_favorite()
	{
		$this->set_header_title('收藏记录');
		$this->set_footer_menu();
	}
}