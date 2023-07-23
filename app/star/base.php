<?php
require 'user.php';
class base extends webapp
{
	function ua():string
	{
		return $this->request_device();
	}
	function ip():string
	{
		return $this->request_ip();
	}
	function cid():string
	{
		return is_string($cid = $this->request_cookie('cid') ?? $this->query['cid'] ?? NULL)
			&& preg_match('/^\w{4,}/', $cid) ? substr($cid, 0, 4)
			: (preg_match('/CID\:(\w{4})/', $this->ua, $pattern) ? $pattern[1] : '0000');
	}
	function did():?string
	{
		return preg_match('/DID\:(\w{16})/', $this->ua, $pattern) ? $pattern[1] : NULL;
	}
	function howago(int $mtime):string
	{
		$timediff = $this->time - $mtime;
		return match (TRUE)
		{
			$timediff < 60 => '刚刚',
			$timediff < 600 => '10分钟前',
			$timediff < 3600 => '1小时前',
			$timediff < 10800 => '3小时前',
			$timediff < 21600 => '6小时前',
			$timediff < 43200 => '12小时前',
			$timediff < 86400 => '1天前',
			default => date('Y-m-d', $mtime)
		};
	}
	function user(string $id = '2lg7yhjGn_'):user
	{
		return $id ? user::from_id($this, $id) : new user($this,
			$this->authorize($this->request_authorization($type),
				fn($id) => $this->mysql->users('WHERE id=?s LIMIT 1', $id)->array()));
	}
	function form_video(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this, '?video-value');

		if ($form->echo)
		{
			//$this->signature()
		}


		$form->fieldset('影片名称');
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);

		$form->fieldset('预览时段');
		$form->field('preview_start', 'time', ['value' => '00:00:00', 'step' => 1]);
		$form->field('preview_end', 'time', ['value' => '00:00:10', 'step' => 1]);

		$form->field('require', 'number', [
			'value' => 0,
			'min' => -2,
			'style' => 'width:21rem',
			'placeholder' => '下架：-2、会员：-1、免费：0、金币',
			'required' => NULL
		]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'style' => 'width:4rem', 'required' => NULL]);



		$form->fieldset('专题');
		$subjects = $this->mysql->subjects->column('name', 'hash');
		$form->field('subjects', 'checkbox', ['options' => $subjects], fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'video_tags';

		$form->fieldset('标签');
		$tags = $this->mysql->tags->column('name', 'hash');
		$form->field('tags', 'checkbox', ['options' => $tags], fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'video_tags';

		$form->fieldset();
		$form->button('更新视频', 'submit');


		

		$form->xml['data-bind'] = 'submit';
		$form->xml->append('script', 'document.querySelectorAll("ul.video_tags>li>label").forEach(label=>(label.onclick=()=>label.className=label.firstElementChild.checked?"checked":"")());');
		return $form;
	}
	function post_video_value(string $signature)
	{
		
	}
	function rootdir_video(array $video):string
	{
		return sprintf('%s/%s/%s', $this['rootdir_video'], date('ym', $video['mtime']), $video['hash']);
	}
	//本地命令行运行专题获取更新
	function get_subject_fetch()
	{
		if (PHP_SAPI !== 'cli') return 404;
		function words(string $haystack, array $needles):bool
		{
			foreach ($needles as $needle)
			{
				if (str_contains($haystack, $needle)) return TRUE;
			}
			return FALSE;
		}
		$subjects = [];
		foreach ($this->mysql->subjects as $subject)
		{
			$subjects[$subject['hash']] = [
				'fetch_method' => $subject['fetch_method'],
				'fetch_values' => $subject['fetch_values'] ? explode(',', $subject['fetch_values']) : []
			];
		}
		foreach ($this->mysql->videos('WHERE sync="allow"') as $video)
		{
			$vsubjects = [];
			foreach ($subjects as $hash => $data)
			{
				match ($data['fetch_method'])
				{
					'tags' => $video['tags'] && count(array_intersect(
						explode(',', $video['tags']), $data['fetch_values'])
					) === count($data['fetch_values']),
					'words' => words($video['name'], $data['fetch_values']),
					'uploader' => in_array($video['userid'], $data['fetch_values'], TRUE),
					default => FALSE
				} && $vsubjects[] = $hash;
			}
			$vsubject = join(',', $vsubjects);
			if ($video['subjects'] !== $vsubject)
			{
				$success = $this->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])
					->update('subjects=?s', $vsubject) === 1 ? 'OK' : 'NO';
				echo "{$video['hash']} -> [{$video['subjects']}] >> [{$vsubject}] {$success}\n";
			}
		}
	}
	//本地命令行运行视频同步处理
	function get_sync()
	{
		//if (PHP_SAPI !== 'cli') return 404;
		foreach ($this->mysql->videos('WHERE sync="allow"') as $video)
		{

			$rootdir_video = $this->rootdir_video($video);
			echo $rootdir_video,"\n";

		}


		//var_dump(123);
	}
	//创建用户
	function create_user(array $user):user
	{
		return user::create($this, $user);
	}
	//获取源
	function fetch_origins():array
	{
		return [];
	}
	//获取所有UP主
	function fetch_uploaders():iterable
	{
		foreach ($this->mysql->users('ORDER BY mtime DESC') as $user)
		//foreach ($this->mysql->users('WHERE uid!=0 ORDER BY mtime DESC') as $user)
		{
			yield [
				'id' => $user['id'],
				'ctime' => $user['ctime'],
				'mtime' => $user['mtime'],
				'fid' => $user['fid'],
				'nickname' => $user['nickname'],
				'followed_ids' => '',
				'follower_num' => 0,
				'like' => 0
			];
		}
	}
	//获取所有视频
	function fetch_videos():iterable
	{
		foreach ($this->mysql->videos('ORDER BY mtime DESC') as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "/{$ym}/{$video['hash']}/cover";
			$video['playm3u8'] = "/{$ym}/{$video['hash']}/play";
			$video['comment'] = 0;
			yield $video;
		}
	}
	//获取指定广告（位置）
	function fetch_ads(int $seat):array
	{
		$ads = [];
		foreach ($this->mysql->ads('WHERE seat=?i AND display="show"', $seat) as $ad)
		{
			$ads[] = [
				'hash' => $ad['hash'],
				'weight' => $ad['weight'],
				'imgurl' => "/news/{$ad['hash']}?{$ad['ctime']}",
				'acturl' => $ad['acturl']
			];
		}
		return $ads;
	}
	//获取开屏广告
	function fetch_adsplashscreen():array
	{
		return empty($ad = $this->random_weights($this->fetch_ads(0))) ? $ad : [
			'duration' => 5,
			'mask' => TRUE,
			'picture' => $ad['imgurl'],
			'support' => $ad['acturl'],
			'autoskip' => TRUE
		];
	}
	//获取所有标签
	function fetch_tags():iterable
	{
		foreach ($this->mysql->tags('ORDER BY mtime DESC') as $tag)
		{
			yield $tag;
		}
	}
	//获取产品
	function fetch_prods():array
	{
		$prods = [];
		foreach ($this->webapp->mysql->prods as $prod)
		{

		}
		return [];
	}


	//根据分类标签ID拉取专题
	function fetch_subjects(string $tagid):array
	{
		$subjects = [];
		foreach ($this->mysql->subjects('WHERE tagid=?s ORDER BY sort DESC', $tagid) as $subject)
		{
			$subjects[] = [
				'hash' => $subject['hash'],
				'name' => $subject['name'],
				'view' => 0,
				'num' => 0,
				'style' => $subject['style'],
				'videos' => $subject['videos'] ? str_split($subject['videos'], 12) : []
			];
		}
		
		return $subjects;
	}
	//专题HASH拉取视频
	function fetch_subject_videos(string $hash):iterable
	{
		foreach ($this->mysql->videos('WHERE FIND_IN_SET(?s,subjects) ORDER BY ctime DESC,sort DESC', $hash) as $video)
		{
			yield [
				'hash' => $video['hash'],
				'mtime' => $video['mtime'],
				'ctime' => $video['ctime'],
				'sort' => $video['sort']
			];
		}
	}


	//话题
	function fetch_topics()
	{

	}




	//标获取签（参数级别）


	//获取全部专题数据


	//专题获取视频数据



	// //获取首页展示数据结构
	// function topdata()
	// {
		
	// }


	//用户上传接口
	function post_uploading(string $token)
	{
		if ($acc = $this->authorize($token, fn($uid, $cid) => [$uid]))
		{

			$uploading = $this->request_uploading();



			$this->response_content_type('@application/json');
			$this->echo(json_encode(['a' => 1, 'b' => 2], JSON_UNESCAPED_UNICODE));

			return 200;
		}
		return 404;
	}

}