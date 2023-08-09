<?php
require 'user.php';
class base extends webapp
{
	const video_sync = ['waiting' => '等待处理', 'exception' => '处理异常', 'finished' => '审核中', 'allow' => '正常（上架视频）', 'deny' => '拒绝（下架视频）'];
	const video_type = ['h' => '横版视频', 'v' => '竖版视频'];
	static function format_duration(int $second):string
	{
		return sprintf('%02d:%02d:%02d', intval($second / 3600), intval(($second % 3600) / 60), $second % 60);
	}
	function form_video(webapp_html|array $ctx = NULL, string $hash = NULL):webapp_form
	{
		$form = new webapp_form($ctx ?? $this, '?video-value/');
		
		$cover = $form->fieldset->append('div', ['class' => 'cover', 'style' => 'width:512px;height:288px']);

		$change = $form->fieldset()->append('input', ['type' => 'file', 'accept' => 'image/*', 'onchange' => 'cover_preview(this,document.querySelector("div.cover"))']);
		$form->button('更新封面', 'button', ['onclick' => 'video_cover(this.previousElementSibling)']);

		$form->fieldset('影片名称');
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);

		$form->fieldset('视频类型 / 预览时段 / 下架：-2、会员：-1、免费：0、金币 / 排序');
		$form->field('type', 'select', ['options' => base::video_type]);
		function preview_format($v, $i)
		{
			if ($i)
			{
				$t = explode(':', $v);
				return $t[0] * 60 * 60 + $t[1] * 60 + $t[2];
			}
			return base::format_duration($v);
		}
		$form->field('preview_start', 'time', ['value' => '00:00:00', 'step' => 1], preview_format(...));
		$form->field('preview_end', 'time', ['value' => '00:00:10', 'step' => 1], preview_format(...));

		$form->field('require', 'number', [
			'value' => 0,
			'min' => -2,
			'style' => 'width:13rem',
			'placeholder' => '要求',
			'required' => NULL
		]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'style' => 'width:4rem', 'required' => NULL]);

		$tagnode = $form->fieldset()->append('ul');
		$tagnode['class'] = 'choosetags';
		$form->field('tags');
		$form->fieldset['style'] = 'height:28rem';

		$form->fieldset();
		$form->button('更新视频', 'submit');

		$form->xml['method'] = 'patch';
		$form->xml['onsubmit'] = 'return video_value(this)';
		//$form->xml->append('script', 'document.querySelectorAll("ul.video_tags>li>label").forEach(label=>(label.onclick=()=>label.className=label.firstElementChild.checked?"checked":"")());');
		if ($form->echo && $hash && $this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->fetch($video))
		{
			$form->xml['action'] .= $this->encrypt($video['hash']);
			$ym = date('ym', $video['mtime']);
			if ($video['cover'] === 'finish' && in_array($video['sync'], ['finished','allow','deny'], TRUE))
			{
				$cover['data-cover'] = "/{$ym}/{$video['hash']}/cover?{$video['ctime']}";
			}
			else
			{
				$cover->append('div', '等待处理...');
			}
			$video['preview_start'] = $video['preview'] >> 16 & 0xffff;
			$video['preview_end'] = ($video['preview'] & 0xffff) + $video['preview_start'];
			$change['data-uploadurl'] = "?video-cover/{$hash}";
			$change['data-key'] = bin2hex($this->random(8));
			foreach ($this->mysql->tags('WHERE phash IS NULL ORDER BY sort DESC')->column('name', 'hash') as $taghash => $tagname)
			{
				$tagnode->append('input', ['type' => 'radio', 'name' => 'tag', 'value' => $taghash, 'id' => "tag{$taghash}"]);
				$tagnode->append('label', [$tagname, 'for' => "tag{$taghash}"]);
				$ul = $tagnode->append('ul');
				foreach ($this->mysql->tags('WHERE phash=?s ORDER BY sort DESC', $taghash) as $tag)
				{
					$ul->append('li')->labelinput("t{$taghash}[]", 'checkbox', $tag['hash'], $tag['name']);
				}
			}
			foreach ($video['tags'] ? explode(',', $video['tags']) : [] as $tag)
			{
				if ($checked = $tagnode->xpath("//input[@value='{$tag}']"))
				{
					$checked[0]->setattr(['checked' => NULL]);
				}
			}
			$form->echo($video);
		}
		return $form;
	}
	function path_video(bool $syncdir, array $video, string $filename = ''):string
	{
		return sprintf('%s/%s/%s%s',
			$this[$syncdir ? 'video_syncdir' : 'video_savedir'],
			date('ym', $video['mtime']),
			$video['hash'], $filename);
	}
	//用户上传接口
	function post_uploading(string $token)
	{
		do
		{
			if (empty($acc = $this->authorize($token, fn($uid, $cid) => [$uid])) || empty($uploading = $this->request_uploading())) break;
			if ($this->mysql->videos('WHERE hash=?s LIMIT 1', $uploading['hash'])->fetch($video))
			{
				if ($video['size'] <= $video['tell'])
				{
					//break;
				}
			}
			else
			{
				$tags = [];
				$names = array_map(trim(...), explode('#', $this->simplified_chinese($uploading['name'])));
				if (count($names) > 1)
				{
					$tagdata = $this->mysql->tags->column('name', 'hash');
					foreach ($names as $index => $name)
					{
						if (is_string($taghash = array_search($name, $tagdata, TRUE)))
						{
							$index && array_splice($names, $index, 1);
							$tags[] = $taghash;
						}
					}
				}
				if (($this->mysql->videos->insert($video = [
					'hash' => $uploading['hash'],
					'userid' => $acc[0],
					'mtime' => $this->time,
					'ctime' => $this->time,
					'size' => $uploading['size'],
					'tell' => 0,
					'cover' => 'finish',
					'sync' => 'waiting',
					'type' => 'h',
					'sort' => 0,
					'duration' => 0,
					'preview' => 0,
					'require' => 0,
					'sales' => 0,
					'view' => 0,
					'like' => 0,
					'tags' => join(',', $tags),
					'subjects' => '',
					'name' => join('#', $names)
				]) && (is_dir($savedir = $this->path_video(FALSE, $video)) || mkdir($savedir, recursive: TRUE))) === FALSE) {
					if ($this->mysql->videos('WHERE hash=?s LIMIT 1', $uploading['hash'])->delete())
					{
						is_dir($savedir) && @rmdir($savedir);
					}
					break;
				}
			}
			$this->response_uploading("?uploaddata/{$uploading['hash']}", $video['tell']);
			//$this->response_uploading("?test");
			return 200;
		} while (FALSE);
		return 404;
	}
	// function post_test()
	// {
	// 	return 202;
	// }
	function post_uploaddata(string $hash)
	{
		return $this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->fetch($video)
			&& $video['tell'] < $video['size']
			&& ($size = $this->request_uploaddata($this->path_video(FALSE, $video, '/video.sb'))) !== -1
			&& $this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update('tell=tell+?i', $size) === 1 ? 200 : 404;
	}
	function patch_video_value(string $encrypt, string $goto = NULL)
	{
		$json = $this->app('webapp_echo_json');
		if (is_string($hash = $this->decrypt($encrypt))
			&& $this->form_video(json_decode($this->request_maskdata() ?? '[]', TRUE))->fetch($video)) {
			$video['preview'] = $video['preview_end'] > $video['preview_start']
				? ($video['preview_start'] & 0xffff) << 16 | ($video['preview_end'] - $video['preview_start']) & 0xffff : 10;
			unset($video['preview_start'], $video['preview_end']);
			if ($this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update(['ctime' => $this->time] + $video) === 1)
			{
				$json->goto($goto ? $this->url64_decode($goto) : NULL);
			}
		}
	}
	function patch_video_cover(string $hash)
	{
		$json = $this->app('webapp_echo_json');
		if ($this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->fetch($video)
			&& is_string($binary = $this->request_maskdata())
			&& file_put_contents($this->path_video(FALSE, $video, '/cover.sb'), $binary) !== FALSE
			&& $this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update(['cover' => 'change']) === 1) {
			$json['dialog'] = '修改完成，后台将在10分钟左右同步缓存数据！';
			return 200;
		}
		$json['dialog'] = '修改失败！';
	}
	//本地命令行运行封面同步处理和广告
	function get_sync_cover()
	{
		if (PHP_SAPI !== 'cli') return 404;
		foreach ($this->mysql->videos('WHERE sync!="exception" && cover="change" ORDER BY sort DESC') as $cover)
		{
			echo "{$cover['hash']} - ";
			if (is_dir($syncdir = $this->path_video(TRUE, $cover)))
			{
				$savedir = $this->path_video(FALSE, $cover);
				echo $this->maskfile("{$savedir}/cover.sb", "{$savedir}/cover")
					&& copy("{$savedir}/cover.sb", "{$syncdir}/cover.sb")
					&& copy("{$savedir}/cover", "{$syncdir}/cover")
					&& $this->mysql->videos('WHERE hash=?s LIMIT 1', $cover['hash'])->update([
						'ctime' => $this->time(), 'cover' => 'finish']) === 1 ? "OK\n" : "NO\n";
				continue;
			}
			echo "WAITING\n";
		}
		foreach ($this->mysql->ads('WHERE `change`="sync" ORDER BY `weight` DESC') as $ad)
		{
			echo "{$ad['hash']} - ",
				is_file($image = "{$this['ad_savedir']}/{$ad['hash']}")
				&& copy($image, "{$this['ad_syncdir']}/{$ad['hash']}")
				&& $this->mysql->ads('WHERE hash=?s LIMIT 1', $ad['hash'])->update([
					'ctime' => $this->time(),
					'change' => 'none']) === 1 ? "OK\n" : "NO\n";
		}
	}
	//本地命令行运行视频同步处理
	function get_sync_video()
	{
		if (PHP_SAPI !== 'cli') return 404;
		$ffmpeg = static::lib('ffmpeg/interface.php');
		foreach ($this->mysql->videos('WHERE sync="waiting" AND size<=tell') as $video)
		{
			$error = 'UNKNOWN';
			do
			{
				$savedir = $this->path_video(FALSE, $video);
				if (is_file($sourcevideo = "{$savedir}/video.sb") === FALSE)
				{
					$error = 'NOT FOUND';
					break;
				}
				$slice = $ffmpeg($sourcevideo);
				if (($slice->m3u8($savedir) && $this->maskfile("{$savedir}/play.m3u8", "{$savedir}/play")) === FALSE)
				{
					$error = 'SLICE ENCRYPT';
					break;
				}
				if ((is_file("{$savedir}/cover.sb") || $slice->jpeg("{$savedir}/cover.sb")) === FALSE)
				{
					$error = 'MAKE COVER';
					break;
				}
				if ($this->maskfile("{$savedir}/cover.sb", "{$savedir}/cover") === FALSE)
				{
					$error = 'COVER ENCRYPT';
					break;
				}
				$syncdir = $this->path_video(TRUE, $video);
				if ((is_string($success = exec("xcopy \"{$savedir}/*\" \"{$syncdir}/\" /E /C /I /F /Y", $output, $code)) && $code === 0) === FALSE)
				{
					$error = 'SYNC COPY';
					break;
				}
				if ($this->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])->update([
					'cover' => 'finish',
					'sync' => 'finished',
					'duration' => $duration = (int)$slice->duration,
					'preview' => $video['preview'] ? $video['preview'] : (intval($duration * 0.6) << 16 | 10)
				]) === 1) {
					$this->mysql->users('WHERE id=?s LIMIT 1', $video['userid'])->update('ctime=?i,video_num=video_num+1', $this->time());
					printf("%s -> FINISHED -> %s\n", $video['hash'], strtoupper($success));
					continue 2;
				}
			} while (FALSE);
			echo "{$video['hash']} -> EXCEPTION -> {$error} ->",
				$this->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])->update('sync="exception"') === 1 ? "OK\n" : "NO\n";
		}
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
	function prod_vtid_vip100(string $userid):bool
	{
		return FALSE;
	}
	//======================以上为内部功能======================
	//======================以下为扩展功能======================
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
	function recordlog(string $field, int $value = 1, int $nowtime = NULL)
	{
		$values = match (TRUE)
		{
			in_array($field, ['dpv_ios', 'dpv_android'], TRUE) => ['dpv' => $value, $field => $value],
			in_array($field, ['dpc_ios', 'dpc_android'], TRUE) => ['dpc' => $value, $field => $value],
			default => []
		};


		$nowtime ??= $this->time();
		$insert = [
			'ciddate'			=> $this->cid() . date('Ymd', $nowtime),
			'date'				=> date('Y-m-d', $nowtime),
			'dpv'				=> 0,
			'dpv_ios'			=> 0,
			'dpv_android'		=> 0,
			'dpc'				=> 0,
			'dpc_ios'			=> 0,
			'dpc_android'		=> 0,
			'signin'			=> 0,
			'signin_ios'		=> 0,
			'signin_android'	=> 0,
			'signup'			=> 0,
			'signup_ios'		=> 0,
			'signup_android'	=> 0,
			'recharge'			=> 0,
			'recharge_new'		=> 0,
			'recharge_old'		=> 0,
			'recharge_coin'		=> 0,
			'recharge_vip'		=> 0,
			'recharge_vip_new'	=> 0,
			'order'				=> 0,
			'order_ok'			=> 0,
			'order_ios'			=> 0,
			'order_ios_ok'		=> 0,
			'order_android'		=> 0,
			'order_android_ok'	=> 0,
			...$values
		];
		$update = [];
		foreach ($values as $field => $value)
		{
			$update[] = $this->mysql->format('?a=?a+?i', $field, $field, $value);
		}
		echo $this->mysql->format('INSERT INTO recordlog SET ?v ON DUPLICATE KEY UPDATE ??', $insert, join(',', $update));

		
		




		//'insert into table (player_id,award_type,num) values(20001,0,1) on DUPLICATE key update num=num+values(num)'
	}
	//创建用户
	function user_create(array $user):user
	{
		return user::create($this, $user);
	}
	//Reids 覆盖此方法获取用户并且缓存
	function user_fetch(string $id):array
	{
		return $this->mysql->users('WHERE id=?s LIMIT 1', $id)->array();
	}
	//用户模型
	function user(string $id = 'FABqsZrf0g'):user
	{
		return $id ? user::from_id($this, $id) : new user($this,
			$this->authorize($this->request_authorization($type), $this->user_fetch(...)));
	}
	//获取源
	function fetch_origins():array
	{
		return [];
	}
	//获取所有UP主
	function fetch_uploaders():iterable
	{
		foreach ($this->mysql->users('WHERE uid!=0 ORDER BY ctime DESC') as $user)
		{
			yield [
				'id' => $user['id'],
				'mtime' => $user['mtime'],
				'ctime' => $user['ctime'],
				'fid' => $user['fid'],
				'nickname' => $user['nickname'],
				'descinfo' => 'webcome my zone',
				'followed_ids' => $user['followed_ids'],
				'follower_num' => $user['follower_num'],
				'video_num' => $user['video_num'],
				'like' => 0
			];
		}
	}
	//获取所有视频
	function fetch_videos():iterable
	{
		foreach ($this->mysql->videos('WHERE sync="allow" ORDER BY ctime DESC') as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "/{$ym}/{$video['hash']}/cover?{$video['ctime']}";
			$video['playm3u8'] = "/{$ym}/{$video['hash']}/play";
			$video['comment'] = 0;
			$video['share'] = 0;
			yield $video;
		}
	}
	//获取指定广告（位置）
	function fetch_ads(int $seat):array
	{
		$ads = [];
		foreach ($this->mysql->ads('WHERE display="show" AND seat=?i', $seat) as $ad)
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
		foreach ($this->mysql->tags('ORDER BY ctime DESC') as $tag)
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



}