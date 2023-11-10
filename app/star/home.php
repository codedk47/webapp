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
		$this->header->append('input', ['type' => 'search']);
		$this->header->append('button', ['搜索']);

		return $this->header;
	}
	function set_header_title(string $name, bool $gobackable = FALSE):webapp_html
	{
		$this->header['class'] = 'title';
		if ($gobackable)
		{
			$this->header->append('a', ['GoBack', 'href' => 'javascript:history.back();']);
		}
		$this->header->text($name);
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


	function add_slideshows(webapp_html $node, array $advertisements = []):webapp_html
	{
		$element = $node->append('webapp-slideshows', ['class' => 'home']);
		$element->cdata(json_encode([
			['support' => 'javascript:alert(1);', 'picture' => '?/news/6UA5CO7B769R?mask1696937379'],
			['support' => 'javascript:alert(2);', 'picture' => '?/news/C244JVMLB9Q2?mask1696937379'],
			['support' => 'javascript:alert(3);', 'picture' => '?/news/NSHV5V94QPE6?mask1696937379']
		], JSON_UNESCAPED_UNICODE));
		return $element;
	}

	function add_video_lists(webapp_html $node, string $title, iterable $videos):webapp_html
	{
		$element = $node->append('div', ['class' => 'videos']);
		foreach ($videos as $video)
		{
			$content = $element->append('a', ['href' => "?home/watch,hash:{$video['hash']}"]);
			$ym = date('ym', $video['mtime']);
			$content->append('img', ['loading' => 'lazy', 'src' => "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}"]);

			//$content->append('span', $video['name']);

			$content->append('span', '免费');


		}
		return $element;
	}





	function get_home(string $tag = NULL)
	{
		$this->set_header_index();
		$this->aside['class'] = 'classify';
		foreach ($this->webapp->mysql->tags('WHERE phash IS NULL ORDER BY ctime DESC') as $tag)
		{
			$this->aside->append('a', [$tag['name'], 'href' => "?home/home,tag:{$tag['hash']}"]);
		}
		

		$this->add_slideshows($this->main);

		$v = $this->webapp->mysql->videos('WHERE sync="allow" ORDER BY ctime DESC LIMIT 10');
		$this->add_video_lists($this->main, 'test', $v);



		$this->set_footer_menu();
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
		

		$this->set_header_title($video['name'], TRUE);
		



		$this->aside->append('webapp-video',[
			'data-poster' => $video['poster'],
			'data-m3u8' => $video['m3u8'],
			//'oncanplay' => 'console.log(this)',
			//'autoheight' => NULL,
			//'autoplay' => NULL,
			'controls' => NULL,
			'muted' => NULL
		]);

	}
	function get_short()
	{
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