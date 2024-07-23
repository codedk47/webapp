<?php
class webapp_router_new extends webapp_echo_html
{
	private readonly bool $free;
	private readonly user $user;
	private readonly array $tags;
	protected array $allow = ['get_splashscreen', 'post_create_account', 'get_init'];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		unset($this->xml->head->link[1], $this->xml->body->div['class']);
		$this->free = $webapp['app_free'];
		$this->footer[0] = NULL;

        
		$this->title($webapp['app_name']);
		$this->xml->head->meta[1]['content'] .= ',user-scalable=0';
		$this->link_resources($webapp['app_resources']);
		$this->xml->head->link['href'] = '/webapp/app/star/home.css?v=lm';
		$this->script(['src' => '/webapp/app/star/new.js?v=jk']);
		$this->script(['src' => '/webapp/res/js/slideshows.js?v=w']);
		$this->script(['src' => 'https://www.googletagmanager.com/gtag/js?id=G-W33CFKQCZS']);
		$this->script('window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","G-W33CFKQCZS")');

//      if ($this->webapp->request_cookie('adult') !== 'yes')
// 		{
// 			$this->xml->body->div['style'] = 'filter:blur(var(--webapp-gapitem))';
// 			$adult = $this->xml->body->append('dialog');
// 			$adult->append('h4', 'Are you 18 years of age or older?');
// 			$adult->append('pre', ['You must be 18 years or older to access and use this website.
// By clicking ENTER below, you certify that you are 18 years or older.']);
// 			$adult->append('a', ['Enter', 'href' => 'javascript:;', 'onclick' => 'adulted(this.parentNode.remove())']);
// 			$adult->append('a', ['No', 'href' => 'https://www.google.com/']);
// 		}
    }

	function pv():void
	{
		// $this->user->id && $this->webapp->recordlog($this->user['cid'], match ($this->user['device'])
		// {
		// 	'android' => 'pv_android',
		// 	'ios' => 'pv_ios',
		// 	default => 'pv'
		// });
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
		$this->header->append('button', ['搜索', 'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value', 'data-search' => '?new/search,word:']);

		return $this->header;
	}
	function set_header_search(?string $goback = 'javascript:history.back();', string $word = NULL):webapp_html
	{
		$this->header['class'] = 'search';
		$this->header->append('a', $goback === NULL
			? ['href' => '?new', 'class' => 'logo']
			: ['href' => $goback, 'class' => 'arrow']);

			
		$search = $this->header->append('input', ['type' => 'search',
			'placeholder' => '请输入关键词搜索',
			'onkeypress' => 'if(event.keyCode===13)location.href=this.nextElementSibling.dataset.search+this.value']);
		$goback
			? $search->setattr(['autofocus' => NULL, 'value' => $word])
			: $search->setattr(['onfocus' => 'this.value||location.assign("?new/search")']);
		$this->header->append('button', ['搜索',
			'onclick' => 'location.href=this.dataset.search+this.previousElementSibling.value',
			'data-search' => '?new/search,word:']);

		return $this->header;
	}
	function set_header_title(string $name, string $goback = 'javascript:history.back();'):webapp_html
	{
		$this->header->append('a', ['href' => $goback, 'class' => 'arrow']);
		$this->header->append('strong', $name);
		return $this->header;
	}
	function set_aside_classify(string $url, string $selected = NULL, array $insert = [], &$classify = NULL):webapp_html
	{
		$this->aside['class'] = 'classify';
		foreach ($insert as $hash => $name)
		{
			$node = $this->aside->append('a', [$name, 'href' => $url . $hash]);
			if ($hash === $selected)
			{
				unset($this->aside->a['class']);
				$node['class'] = 'selected';
			}
		}
		foreach ($classify = $this->webapp->fetch_tags->classify() as $hash => $name)
		{
			$node = $this->aside->append('a', [$name, 'href' => $url . $hash]);
			if ($hash === $selected)
			{
				unset($this->aside->a['class']);
				$node['class'] = 'selected';
			}
		}
		return $this->aside;
	}



	function set_footer_menu():webapp_html
	{
		$this->footer->insert('div', 'before')->setattr([
			"本站永久域名 {$this->webapp['app_website']} 回家不迷路！", 'class' => 'nav']);
		
		// ['style'] = 'height:4rem';
		$this->footer['class'] = 'nav';
		$this->footer->append('a', ['首页', 'href' => '?new/home']);
		$this->footer->append('a', ['抖音', 'href' => '?new/short']);
		//$this->footer->append('a', ['游戏', 'href' => '?new/game']);
		$this->footer->append('a', ['剧场', 'href' => '?new/home,type:fL83']);
		$this->footer->append('a', ['我的', 'href' => '?new/my']);
		return $this->footer;
	}
	function set_float_button():webapp_html
	{
		$float = $this->xml->body->xpath('div[@class=float]')[0] ?? NULL;
		if ($float === NULL)
		{
			$float = $this->xml->body->append('div', ['class' => 'float']);
			$float->append('a', ['href' => 'javascript:scrollTo({top:0,behavior:"smooth"});'])->svg(['fill' => 'white'])->icon('move-to-top', 32);
			$float->append('a', ['href' => '?new/search'])->svg(['fill' => 'white'])->icon('search', 32);
		}
		return $float;
	}

	function add_slideshows_ads(webapp_html $node, int $seat, int $duration = 5):?webapp_html
	{
		return NULL;
		if ($ads = $this->webapp->fetch_ads->seat($seat))
		{
			$this->webapp->mysql->ads('WHERE hash IN(?S)', array_column($ads, 'hash'))->update('`view`=`view`+1');
			return $node->append('webapp-slideshows', [
				'data-contents' => json_encode($ads, JSON_UNESCAPED_UNICODE),
				'data-duration' => $duration,
				'style' => 'margin-top:calc(var(--webapp-gapitem) + var(--webapp-gap))',
				'onchange' => 'this.active.onclick=()=>masker.lognews(`?new/news,hash:${this.current.hash}`)'
			]);
		}
		return NULL;
	}
	function add_nav_ads(webapp_html $node, int $seat, string $title = NULL):?webapp_html
	{
		return NULL;
		if ($ads = $this->webapp->fetch_ads->seat($seat))
		{
			$this->webapp->mysql->ads('WHERE hash IN(?S)', array_column($ads, 'hash'))->update('`view`=`view`+1');
			if ($title)
			{
				$element = $node->append('div', ['class' => 'titles']);
				$element->append('strong', $title);
			}
			$element = $node->append('div', ['class' => 'grid-icon']);
			foreach ($ads as $ad)
			{
				$anchor = $element->append('a', ['href' => "?new/news,hash:{$ad['hash']}", 'onclick' => 'return masker.lognews(this)']);
				$anchor->append('figure')->append('img', ['src' => $ad['picture']]);
				$anchor->append('strong', $ad['name']);
				//$a->append('figcaption', $ad['name']);
				//$element->append('a', ['href' => $ad['support']])->append('img');
			}
			return $element;
		}
		return NULL;
	}
	function add_titles(string $strong = NULL):webapp_html
	{
		$titles = $this->main->append('div', ['class' => 'titles']);
		$strong && $titles->append('strong', $strong);
		return $titles;
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
			$titles = $this->add_titles($title);
			if ($anchor)
			{
				$titles->append('a', [$action, 'href' => $anchor]);
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
				$content = $element->append('a', ['href' => "?new/watch,hash:{$video['hash']}"]);
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

				$poster = $this->webapp->origin . substr($video['poster'], 1, -15) . '.jpg';

				$figure->append('img', ['loading' => 'lazy', 'src' => $poster]);
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
		$this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $ad['hash'])->update('`view`=`view`+1');
		$this->script('masker.then(masker.splashscreen)');
		$this->xml->body->div['class'] = 'splashscreen';
		$this->xml->body->setattr([
			'style' => "background: url({$ad['picture']}) center / cover no-repeat var(--webapp-background)",
			//'data-support' => $ad['support'],
			'data-support' => "javascript:masker.lognews('?new/news,hash:{$ad['hash']}');",
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
		if ($ad = $this->webapp->fetch_ads->rand(1))
		{
			$data['popup'] = ['title' => $ad['name'], 'picture' => $ad['picture'], 'support' => $ad['support']];
		}
		$this->json($data);
	}
	function get_news(string $hash)
	{
		$data = [];
		if ($ad = $this->webapp->fetch_ads[$hash])
		{
			$this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->update('click=click+1');
			$data['support'] = $ad['support'];
		}
		$this->json($data);
	}
	function post_log(string $type)
	{
		$content = $this->webapp->request_content('text/plain');
		//file_put_contents('d:/log.txt', "{$type} = {$content}");
		$this->json(['result' => match ($type)
		{
			//'watch' => $this->user->count() && $this->user->watch($content) && $this->user->count(-1),
			'watch' => $this->user->watch($content),
			'liked' => $this->user->like($content),
			'favorited' => $this->user->favorite($content),
			default => FALSE
		}]);
	}
	function get_home(string $type = '')
	{
		$this->pv();
		$this->aside['class'] = 'classify';
		$this->set_aside_classify('?new/home,type:', $type, [ '' => '最新'], $classify);
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
		$this->set_float_button();
		$this->add_slideshows_ads($this->main, 2);
		if (isset($classify[$type]) === FALSE)
		{
			$this->add_nav_ads($this->main, 9, '福利导航');
		}
		$this->tags = $this->webapp->fetch_tags->shortname();
		if ($this->webapp->fetch_subjects->unique('type', $type))
		{
			$subjects = $this->webapp->fetch_subjects->with('type=?s', $type)->cache();
			$i = 0;
			$c = (int)ceil($subjects->count() * 0.5);
			foreach ($subjects as $subject)
			{
				++$i === $c && $this->add_slideshows_ads($this->main, 2);
				$keys = str_split($subject['videos'], 12);
				shuffle($keys);
				$this->add_video_lists($this->main,
					$this->webapp->fetch_videos->iter(...$keys),
					$subject['style'], $subject['name'], "?new/subject,hash:{$subject['hash']}");
			}
		}
		else
		{
			$i = 0;
			$c = (int)ceil(count($classify) * 0.5);
			foreach ($classify as $hash => $name)
			{
				++$i === $c && $this->add_slideshows_ads($this->main, 2);
				$this->add_video_lists($this->main,
					in_array($hash, ['F2i7', 'DQFQ', 'JGKx', '9Oi0'], TRUE)
						? $this->webapp->fetch_videos->randtop($hash)
						: $this->webapp->fetch_videos->with('FIND_IN_SET(?s,tags)', $hash)->cache()->show(7, 3600),
					6, "最新{$name}", "?new/home,type:{$hash}");
				
			}
		}
		$this->add_slideshows_ads($this->main, 2);
	}
	function get_subject(string $hash, int $page = 0)
	{
		$this->pv();
		$this->tags = $this->webapp->fetch_tags->shortname();
		// if ($page)
		// {
		// 	$this->add_video_lists($this->template(), $this->webapp->fetch_subjects->videos($hash, $page));
		// 	return;
		// }
		$this->add_slideshows_ads($this->main, 2);
		
		if (is_array($subject = $this->webapp->fetch_subjects[$hash]))
		{
			$this->set_header_title($subject['name'], 'javascript:history.back();')['style'] = 'position:sticky;top:0;z-index:2;box-shadow: 0 0 .4rem var(--webapp-edge)';
			$this->add_video_lists($this->main, $this->webapp->fetch_videos->with('FIND_IN_SET(?s,subjects)', $hash), $page);
			$this->set_footer_menu();
		}
	}
	function get_search(string $word = '', int $page = 0)
	{
		$this->pv();
		$this->set_footer_menu();
		if ($word = trim(urldecode($word)))
		{
			$cond = ['name LIKE ?s', "%{$word}%"];
			if ($tags = $this->webapp->fetch_tags->like($word))
			{
				$cond[0] = "({$cond[0]} OR FIND_IN_SET(?s,tags))";
				$cond[] = $tag = current($tags);
			}
			if ($classify = $this->webapp->query['classify'] ?? '')
			{
				$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
				$cond[] = $classify;
			}
			if ($tags = $this->webapp->query['tags'] ?? '')
			{
				$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
				$cond[] = $tags;
			}
			$cond[0] .= match ($sort = $this->webapp->query['sort'] ?? '')
			{
				'view' => ' ORDER BY `view` DESC, hash ASC',
				'like' => ' ORDER BY `like` DESC, hash ASC',
				'favorite' => 'ORDER BY `favorite` DESC, hash ASC',
				'issue' => 'ORDER BY extdata->\'$.issue\' DESC, hash ASC',
				default => ''
			};
			// if ($page)
			// {
			// 	$this->add_video_lists($this->template(), $this->webapp->fetch_videos->with(...$cond)->paging($page));
			// 	return;
			// }
			$this->set_header_search(word:$word);
			$this->set_aside_classify($this->webapp->at(['classify' => '', 'page' => NULL]), $classify, [ '' => '全部', '5IFq' => '抖音']);


			$this->add_slideshows_ads($this->main, 2);
			$this->set_float_button();
			$result = $this->webapp->fetch_videos->with(...$cond);
			if ($result->count())
			{
				$this->tags = $this->webapp->fetch_tags->shortname();
				$titles = $this->add_titles();
				$titles->select([
					'' => '过滤：无',
					'qk7U' => '单人作品',
					'vbgd' => '多人作品',
					'_2OO' => '精选推荐'
				])->setattr(['onchange' => 'masker.assign({tags:this.value||null})'])->selected($tags);
				$titles->select([
					'' => '最新上传',
					'view' => '最多观看',
					'like' => '最多喜欢',
					'favorite' => '最多收藏',
					'issue' => '发行日期'
				])->setattr(['onchange' => 'masker.assign({sort:this.value||null})'])->selected($sort);
				$this->add_video_lists($this->main, $result, $page);
			}
			else
			{
				$this->main->append('blockquote', '404 很抱歉，没有找到相关内容');
			}
			return;
		}
		$this->set_header_search();
		$this->add_slideshows_ads($this->main, 2);
		foreach ($this->webapp->fetch_tags->levels(not:[0, 1, 2, 3, 10, 11, 12]) as $describe => $tags)
		{
			$node = $this->main->append('div', ['class' => 'videoinfo']);
			$node->append('strong', $describe);
			$mark = $node->append('mark');
			foreach ($tags as $name)
			{
				$mark->append('a', [$name, 'href' => '/?new/search,word:' . urlencode($name)]);
			}
		}
	}
	function get_ramdom(string $hash)
	{
		$this->tags = $this->webapp->fetch_tags->shortname();
		$this->add_video_lists($this->template(), $this->webapp->fetch_videos->watch_random($hash));
	}
	function get_watch(string $hash)
	{
		$this->pv();
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
		$tags = explode(',', $video['tags']);
		if (match ($this->webapp->request_country())
		{
			//美国、加拿大、澳大利亚
			'US', 'CA', 'AU' => in_array('lqe2', $tags, TRUE) || in_array('F2i7', $tags, TRUE),
			//欧洲
			'AT', 'BE', 'CZ', 'DK', 'FI', 'FR', 'DE', 'IE', 'IT', 'NL', 'NO',
			'PL', 'PT', 'SK', 'ES', 'SE', 'CH', 'GB' => in_array('lqe2', $tags, TRUE),
			//日本
			'JP' => in_array('F2i7', $tags, TRUE),
			default => FALSE
		})
		{
			$this->aside->append('strong', '很抱歉，您所在地区暂时无法播放该视频。');
		}
		else
		{
			$this->aside['data-type'] .= $video['type'];
			//$this->user->watched($hash) || $this->user->watch($hash);
			// if ($this->user->count())
			// {
			// 	if ($this->user->watched($hash) === FALSE)
			// 	{
			// 		$this->user->watch($hash) && $this->user->count(-1);
			// 	}
				//$this->aside['style'] = 'position:sticky;top:0;z-index:9';

				$poster = $this->webapp->origin . substr($video['poster'], 1, -15) . '.jpg';
				$m3u8 = $this->webapp->origin . substr($video['m3u8'], 1, -15) . '.m3u8';
				
				//var_dump($m3u8);
				$watch = $this->aside->append('webapp-video', [
					'data-poster' => $poster,
					//'data-m3u8' => $video['m3u8'],
					'data-m3u8' => $m3u8,
					'oncanplay' => 'masker.canplay(this)',
					//'autoheight' => NULL,
					'autoplay' => NULL,
					//'muted' => NULL,
					'controls' => NULL
				]);
				if ($ad = $this->webapp->fetch_ads->rand(3))
				{
					$this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $ad['hash'])->update('`view`=`view`+1');
					$watch->append('a', ['href' => "?new/news,hash:{$ad['hash']}",
						'data-duration' => 5,
						'data-unit' => '秒',
						'data-skip' => '跳过',
						'onclick' => 'return masker.lognews(this)'
					])->append('img', ['src' => $ad['picture'],
						'onload' => 'this.parentNode.parentNode.splashscreen(this.parentNode)',
						'onerror' => 'this.parentNode.parentNode.removeChild(this.parentNode)'
					]);
				}
				else
				{
					$watch->setattr('autoplay');
				}
			// }
			// else
			// {
			// 	$strong = $this->aside->append('strong')->append('div');
			// 	$strong->append('span', '每日观影剩余次数已耗尽');
			// 	$strong->append('a', ['请点击分享链接', 'href' => '?new/my-shareurl', 'class' => 'button']);
			// 	$strong->append('span', '获得更多次数！');
			// }
		}

		//影片信息（标题）
		$videoinfo = $this->main->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong', htmlentities($video['name']));

		//影片信息（用户行为）
		// $useraction = $videoinfo->append('div', ['class' => 'useraction']);

		// $anchor = $useraction->append('a', ['href' => '?new/log,type:liked', 'onclick' => 'return masker.log(this)', 'data-body' => $hash, 'data-toggle' => '已喜欢']);
		// $anchor->svg(['fill' => 'white'])->icon('heart');
		// $anchor->svg(['fill' => 'white', 'style' => 'display:none'])->icon('heart-fill');
		// $anchor->append('span', $anchor['data-value'] = '喜欢');
		// if ($this->user->liked($hash))
		// {
		// 	[$anchor['data-value'], $anchor['data-toggle'], $anchor->svg[0]['style'], $anchor->svg[1]['style']] = [
		// 		$anchor->span[0] = (string)$anchor['data-toggle'],
		// 		(string)$anchor['data-value'],
		// 		(string)$anchor->svg[1]['style'],
		// 		(string)$anchor->svg[0]['style']];
		// }

		// $anchor = $useraction->append('a', ['href' => '?new/log,type:favorited', 'onclick' => 'return masker.log(this)', 'data-body' => $hash, 'data-toggle' => '已收藏']);
		// $anchor->svg(['fill' => 'white'])->icon('star');
		// $anchor->svg(['fill' => 'white', 'style' => 'display:none'])->icon('star-fill');
		// $anchor->append('span', $anchor['data-value'] = '收藏');
		// if ($this->user->favorited($hash))
		// {
		// 	[$anchor['data-value'], $anchor['data-toggle'], $anchor->svg[0]['style'], $anchor->svg[1]['style']] = [
		// 		$anchor->span[0] = (string)$anchor['data-toggle'],
		// 		(string)$anchor['data-value'],
		// 		(string)$anchor->svg[1]['style'],
		// 		(string)$anchor->svg[0]['style']];
		// }
		// $useraction->select(['' => '线路检测中...'] + array_combine($this->webapp['app_resources'],
		// 	['线路1国内', '线路2国际']))['onchange'] = 'masker.selectorigin(this)';


		//判断是否剧集或者卡通动漫
		if (in_array('K3yp', $tags, TRUE) || in_array('9Oi0', $tags, TRUE))
		{
			$videoseries = $videoinfo->append('mark', ['data-label' => '选集:']);
			foreach ($this->webapp->fetch_videos->eval('type=?s AND name LIKE ?s ORDER BY name ASC',
				$video['type'], trim($video['name'], "0123456789 \n\r\t\v\x00") . '%') as $series) {
				$anchor = $videoseries->append('a', [str_pad(intval(preg_match('/\d+$/', $series['name'], $number)
					? $number[0] : strrchr($series['name'], ' ')), 2, 0, STR_PAD_LEFT), 'href' => "?new/watch,hash:{$series['hash']}"]);
				if ($series['hash'] === $hash)
				{
					$anchor['style'] = 'background-color:var(--webapp-foreground);color:var(--webapp-background)';
				}
			}
		}

		//影片信息（标签）
		$this->tags = $this->webapp->fetch_tags->shortname();
		if ($video['tags'])
		{
			$taginfo = $videoinfo->append('div', ['data-label' => '标签:']);
			foreach ($tags as $tag)
			{
				if (isset($this->tags[$tag]))
				{
					$taginfo->append('a', [$this->tags[$tag], 'href' => '?new/search,word:' . urlencode($this->tags[$tag])]);
				}
			}
		}

		//影片信息（扩展数据）
		if ($video['extdata'])
		{
			$extdata = array_filter(json_decode($video['extdata'], TRUE), trim(...));
			isset($extdata['issue']) && $videoinfo->append('div', [$extdata['issue'], 'data-label' => '发行日期:']);

			isset($extdata['actor']) && $videoinfo->append('div', ['data-label' => '作者:'])
				->append('a', [$extdata['actor'], 'href' => 'javascript:;']);
			isset($extdata['publisher']) && $videoinfo->append('div', ['data-label' => '发行商:'])
				->append('a', [$extdata['publisher'], 'href' => 'javascript:;']);
			isset($extdata['director']) && $videoinfo->append('div', ['data-label' => '导演:'])
				->append('a', [$extdata['director'], 'href' => 'javascript:;']);
			isset($extdata['series']) && $videoinfo->append('div', ['data-label' => '系列:'])
				->append('a', [$extdata['series'], 'href' => 'javascript:;']);

			if (isset($extdata['actress']))
			{
				$extinfo = $videoinfo->append('div', ['data-label' => '女优:']);
				foreach (explode(',', $extdata['actress']) as $actress)
				{
					$extinfo->append('a', [$actress, 'href' => 'javascript:;']);
				}
			}
		}

		//关联影片
		$this->add_slideshows_ads($this->main, 2);
		$this->add_video_lists($this->main, $this->webapp->fetch_videos->watch_actress($video), 2, '相关推荐');

		$this->add_slideshows_ads($this->main, 2);
		$this->add_titles('随机推荐')->append('a', ['换一换', 'href' => "?new/ramdom,hash:{$video['hash']}",
			'onclick' => 'return !fetch(this.href).then(response=>response.text()).then(content=>this.parentNode.nextElementSibling.innerHTML=content)']);
		$this->add_video_lists($this->main, $this->webapp->fetch_videos->watch_random($video['hash']), 2);
	}

	
	function get_short(int $page = 0)
	{
		// if ($this->user->count() === 0)
		// {
		// 	if ($page)
		// 	{
		// 		$this->json([]);
		// 	}
		// 	else
		// 	{
		// 		$this->set_header_search();
		// 		$this->set_footer_menu();
		// 		$this->aside['style'] = 'padding:1rem';
		// 		$strong = $this->aside->append('strong')->append('div');
		// 		$strong->append('span', '每日观影剩余次数已耗尽');
		// 		$strong->append('a', ['请点击分享链接', 'href' => '?new/my-shareurl', 'class' => 'button']);
		// 		$strong->append('span', '获得更多次数！');
		// 	}
		// 	return;
		// }
		if ($page)
		{
			$videos = [];
			$tags = $this->webapp->fetch_tags->shortname();
			foreach ($this->webapp->fetch_videos->with('type="v"')->random(9) as $video)
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

				$videos[] = [
					'hash' => $video['hash'],
					'name' => $video['name'],
	
					'm3u8' => $this->webapp->origin . substr($video['m3u8'], 1, -15) . '.m3u8',
					'poster' => $this->webapp->origin . substr($video['poster'], 1, -15) . '.jpg',
					// 'watched' => $this->user->watched($video['hash']),
					// 'liked' => $this->user->liked($video['hash']),
					// 'favorited' => $this->user->favorited($video['hash']),

					'watched' => FALSE,
					'liked' => FALSE,
					'favorited' => FALSE,
					'tags' => $tagdata
				];
			}
			unset($this->json($videos)['errors']);
			//unset($this->webapp->app->errors);
			return;
		}
		$this->pv();
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js?v=m']);
		$this->meta(['name' => 'theme-color', 'content' => 'black']);
		$this->xml->body->div['class'] = 'short';
		$this->header->append('a', ['href' => 'javascript:history.back();', 'class' => 'arrow']);
		$this->header->append('strong', ['data-title' => '抖 音']);
		// $this->header->select(['' => '线路检测中...'] + array_combine($this->webapp['app_resources'],
		// 	['线路1国内', '线路2国际']))['onchange'] = 'masker.selectorigin(this)';
		// $this->header->append('script')->cdata('const selectorigin = document.querySelector("header>select");
		// selectorigin.firstChild.value || selectorigin.removeChild(selectorigin.firstChild);
		// selectorigin.value = sessionStorage.getItem("origin");');
		$template = $this->main->append('webapp-videos', [
			//'onchange' => 'masker.shortchanged(this)',
			'data-fetch' => '?new/short,page:',
			'data-page' => 1,
			//'autoplay' => NULL,
			'controls' => NULL,
			//'muted' => NULL
		])->append('template');

		$videoinfo = $template->append('div', ['class' => 'videoinfo']);
		$videoinfo->append('strong');
		$videoinfo->append('mark');

		$videolink = $template->append('div', ['class' => 'videolink']);
		$videolink->append('img');

		$anchor = $videolink->append('a', ['href' => '?new/log,type:liked', 'data-log' => 'liked', 'data-label' => '喜欢']);
		$anchor->svg(['fill' => 'white'])->icon('heart', 32);
		$anchor->svg(['fill' => 'white', 'style' => 'display:none'])->icon('heart-fill', 32);

		$anchor = $videolink->append('a', ['href' => '?new/log,type:favorited', 'data-log' => 'favorited', 'data-label' => '收藏']);
		$anchor->svg(['fill' => 'white'])->icon('star', 32);
		$anchor->svg(['fill' => 'white', 'style' => 'display:none'])->icon('star-fill', 32);

		$this->footer->setattr('style', 'height:1rem');
		//$this->set_footer_menu();
	}

	function get_my()
	{
        //return;
        //
		//$this->pv();
		$this->xml->body->div['class'] = 'my';
		$this->set_header_title('个人中心');
        return;

		$this->aside->append('img', ['src' => $qrurl = '?qrcode/' . $this->webapp->encrypt($this->user)]);
		$info = $this->aside->append('div');
		$info->append('a', [$this->user->id, 'href' => 'javascript:;', 'data-label' => '账号：',
			'onclick' => 'navigator.clipboard.writeText(this.textContent).then(()=>alert("复制成功！"))']);
		$info->append('a', [$this->user['nickname'], 'href' => 'javascript:;', 'data-label' => '花名：',
			'onclick' => 'return masker.nickname(this)']);
		$info->append('a', ['点击保存二维码', 'href' => "{$qrurl},type:png,filename:{$this->user->id}.png", 'target' => '_blank', 'data-label' => '凭证：']);

		$anchors = $this->main->append('div', ['class' => 'listmenu']);
		//$anchors->append('a', ['每日观影剩余次数', 'href' => 'javascript:;', 'data-right' => ($count = count($this->user)) === -1 ? '无限' : "{$count} 次"]);
		$anchors->append('a', ['商务洽谈', 'href' => $this->webapp['app_business'], 'target' => '_blank', 'data-right' => 'Telegram']);
		$anchors->append('a', ['官方交流', 'href' => $this->webapp['app_community'], 'target' => '_blank', 'data-right' => 'Telegram']);

		$anchors->append('a', ['输入邀请码',
			'href' => '?new/my-invite,code:',
			'style' => 'color:var(--webapp-primary);font-weight:bold',
			'onclick' => 'return !masker.prompt(this.textContent).then(value=>masker.json(this.href+value.replace(/[^0-9A-Z]/ig,"")))',
			'data-right' => $this->user['iid'] ? '已领取' : '未领取']);
		$anchors->append('a', ['分享链接',
			'href' => '?new/my-shareurl',
			'style' => 'color:var(--webapp-primary);font-weight:bold',
			'data-right' => "{$this->user['share']} 次"]);

		$anchors->append('a', ['收藏记录', 'href' => '?new/my-favorites', 'data-right' => count($this->user->favorites())]);
		$anchors->append('a', ['历史记录', 'href' => '?new/my-historys', 'data-right' => count($this->user->historys())]);
		$anchors->append('a', ['问题反馈', 'href' => '?new/my-report', 'data-right' => '>>']);
		//$anchors->append('a', ['注销账号', 'href' => 'javascript:;', 'onclick' => 'return masker.delete_account(this)', 'data-right' => '退出']);
		$node = $this->main->append('ul');
		// $first = $node->append('li');
		// $first->text('请记住本站回家域名');
		// $first->append('q', [$this->webapp['app_website'], 'style' => 'color:var(--webapp-foreground)']);
		// $first->text('回家不迷路！');
		$node->append('li', '保存凭证后，可以通过凭证找回账号！');
		if ($this->user['did'])
		{
			$logout = $node->append('li');
			$logout->text('您还可以');
			$logout->append('a', ['注销',
				'href' => "?new/home,did:{$this->user['did']}",
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
		$this->pv();
		$this->set_header_title('分享链接');
		$this->main['class'] = 'myshare';
		$ul = $this->main->append('ul');
		$ul->append('li', "{$this->webapp['app_name']}看片不花钱，只要邀请新注册用户，即可免费看片。");
		$ul->append('li', '方法一：请复制或截图二维码与邀请码，发给朋友，对方安装并输入您的邀请码，即可获得免费看片次数。');
		$ul->append('li', '方法二：打开您的二维码，对方手机打开相机扫描二维码，安装并输入您的邀请码，即可获得免费看片次数。');

		$dl = $this->webapp->fetch_configs()['down_page'];
		$this->main->append('figure')->append('img', ['src' => sprintf('?qrcode/%s', $this->webapp->encrypt($dl))]);
		$this->main->append('strong', '手机长安可以选择复制以下邀请码');
		$this->main->append('mark', [join(' ',
			str_split($this->webapp->time33hash($this->webapp->hashtime33($this->user->id), FALSE), 4)), 'data-iid' => '邀请码：']);
		// $this->main->append('a', ['复制二维码地址', 'href' => $dl, 'class' => 'button',
		// 	'onclick' => 'return !navigator.clipboard.writeText(this.href).then(()=>alert("链接拷贝成功，请分享给好友通过浏览器打开下载APP"),()=>location.href=this.href)']);
		$dl = $this->main->append('dl');
		$dl->append('dt', '免费看片规则：');
		$dl->append('dd', '新安装用户获得每日影片观看 +10次。');
		$dl->append('dd', '邀请朋友一次，每日影片观看 +5次。');
		$dl->append('dd', '邀请朋友二次，每日影片观看 +5次。');
		$dl->append('dd', '邀请三个朋友及以上，无限观看影片。');
	}


	function form_report(webapp_html $node = NULL):webapp_form
	{
		$form = new webapp_form($node ?? $this->webapp);
		$form->fieldset();
		$form->field('question', 'textarea', [
			'placeholder' => '请尽可能详细的描述您当前遇到的问题，以便我们可以进行及时有效的处理。',
			'spellcheck' => 'false',
			'maxlength' => 400,
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
		$data = [];
		if ($this->form_report()->fetch($report, $data['dialog'])
			&& $this->user->report($report['question'], $data['dialog'])) {
			$data['reload'] = 0;
		}
		$this->json($data);
	}
	function get_my_report()
	{
		$this->pv();
		$this->set_header_title('问题反馈');
		$this->form_report($this->aside);
		$this->set_footer_menu();
		$this->main['class'] = 'report';
		foreach ($this->webapp->mysql->reports('WHERE userid=?s ORDER BY time DESC LIMIT 10', $this->user->id) as $report)
		{
			$question = $this->main->append('div');
			$question->append('time', ["您：{$report['question']}", 'datetime' => $report['date'], 'class' => 'question']);
			if ($report['reply'])
			{
				$question['class'] = 'reply';
				$question->append('pre', "客服：{$report['reply']}");
			}
		}
	}
	function get_my_historys()
	{
		$this->pv();
		$this->set_header_title('观影记录');
		$this->set_footer_menu();
		$this->add_video_lists($this->main,
			$videos = $this->user->historys(TRUE),
			count($videos) % 2 ? 3 : 2,
			'#最多保留50个记录',
			'javascript:masker.clear("historys");', '清除所有观影记录');
	}
	function get_my_favorites()
	{
		$this->pv();
		$this->set_header_title('收藏记录');
		$this->set_footer_menu();
		$this->add_video_lists($this->main,
			$videos = $this->user->favorites(TRUE),
			count($videos) % 2 ? 3 : 2,
			'#最多保留50个记录',
			'javascript:masker.clear("favorites");', '清除所有收藏记录');
	}
}