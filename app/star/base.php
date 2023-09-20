<?php
require 'user.php';
require 'game.php';
class base extends webapp
{
	const video_sync = [
		'waiting' => '等待处理',
		'slicing' => '正在处理',
		'exception' => '处理异常',
		'finished' => '审核中',
		'allow' => '正常（上架视频）',
		'deny' => '拒绝（下架视频）'];
	const video_type = ['h' => '横版视频', 'v' => '竖版视频'];
	static function format_duration(int $second):string
	{
		return sprintf('%02d:%02d:%02d', intval($second / 3600), intval(($second % 3600) / 60), $second % 60);
	}
	function form_video(webapp_html|array $ctx = NULL, string $hash = NULL):webapp_form
	{
		$form = new webapp_form($ctx ?? $this, '?video-value/');
		
		$cover = $form->fieldset->append('div', ['class' => 'cover', 'style' => 'width:512px;height:288px']);
		$change = $form->fieldset()->append('input', ['type' => 'file', 'accept' => 'image/*',
			'onchange' => 'video_cover(this,document.querySelector("div.cover"))']);

		$form->fieldset('影片名称');
		$form->field('name', 'text', ['style' => 'width:60rem', 'required' => NULL]);

		$form->fieldset('下架：-2、会员：-1、免费：0、金币 / 定时发布日期');
		$form->field('require', 'number', [
			'value' => 0,
			'min' => -2,
			'style' => 'width:13rem',
			'placeholder' => '要求',
			'required' => NULL
		]);
		$form->field('ptime', 'datetime-local', format:fn($v, $i) => $i ? strtotime($v) : date('Y-m-d\\TH:i', $v));

		$form->fieldset('视频类型 / 预览时段 / 排序');
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
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'style' => 'width:4rem', 'required' => NULL]);

		$tagnode = $form->fieldset()->append('ul');
		$tagnode['class'] = 'choosetags';
		$form->field('tags');
		$form->fieldset['style'] = 'height:28rem';

		$form->fieldset();
		$form->button('更新视频', 'submit');

		$form->xml['method'] = 'patch';
		$form->xml['onsubmit'] = 'return video_value(this)';
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
			foreach ($this->mysql->tags('WHERE phash IS NULL ORDER BY sort DESC,hash ASC')->column('name', 'hash') as $taghash => $tagname)
			{
				$tagnode->append('input', ['type' => 'radio', 'name' => 'tag', 'value' => $taghash, 'id' => "tag{$taghash}"]);
				$tagnode->append('label', [$tagname, 'for' => "tag{$taghash}"]);
				$ul = $tagnode->append('ul');
				foreach ($this->mysql->tags('WHERE phash=?s ORDER BY sort DESC,hash ASC', $taghash) as $tag)
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
					break;
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
					'ptime' => $this->time,
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
			$this->response_uploading("?uploaddata/{$uploading['hash']},userid:{$acc[0]}", $video['tell']);
			//$this->response_uploading("?test");
			return 200;
		} while (FALSE);
		return 404;
	}
	// function post_test()
	// {
	// 	return 202;
	// }
	function post_uploaddata(string $hash, string $userid)
	{
		return $this->mysql->videos('WHERE hash=?s AND userid=?s LIMIT 1', $hash, $userid)->fetch($video)
			&& $video['tell'] < $video['size']
			&& ($size = $this->request_uploaddata($this->path_video(FALSE, $video, '/video.sb'))) !== -1
			&& $this->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update('tell=tell+?i', $size) === 1 ? 200 : 404;
	}
	function patch_video_value(string $encrypt, string $goto = NULL)
	{
		$json = $this->app('webapp_echo_json');
		if (is_string($hash = $this->decrypt($encrypt))
			&& $this->form_video(json_decode($this->request_maskdata() ?? '[]', TRUE))->fetch($video)) {

			if ($video['require'] === '0')
			{
				$video['require'] = '-1';
			}

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
	function patch_uploadimage(string $goto = NULL)
	{
		$json = $this->app('webapp_echo_json');
		$ym = date('ym', $this->time);
		if (is_string($key = $this->request_header('Mask-Key'))
			&& preg_match('/^[0-f]{16}$/', $key)
			&& (is_dir("{$this['imgs_savedir']}/{$ym}") || mkdir("{$this['imgs_savedir']}/{$ym}"))) {
			$this->mysql->images->insert([
				'hash' => $hash = $this->time33hash(hexdec($key), FALSE),
				'mtime' => $this->time,
				'ctime' => $this->time,
				'size' => strlen($binary = $this->request_content('binary')),
				'sync' => 'pending'])
			&& file_put_contents("{$this['imgs_savedir']}/{$ym}/{$hash}", hex2bin($key) . $binary);
			$json['errors'] = [];
			$goto && $json->goto($this->url64_decode($goto));
			return;
		}
		//$json['dialog'] = '图片上传失败！';
	}
	//本地命令行运行同步记录
	function get_sync_record()
	{
		if (PHP_SAPI !== 'cli') return 404;
		echo "----SYNC RECORD----\n";
		foreach ($this->mysql->records('WHERE result="success" AND type NOT IN("video","exchange") AND log="pending"')->all() as $record)
		{
			$this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->select('id,date,device')->fetch($user);
			echo "{$record['hash']} - ",
			$this->mysql->sync(fn() => $this->recordlog($record['cid'], match ($user['device'])
				{
					'android' => 'order_android_ok',
					'ios' => 'order_ios_ok',
					default => 'order_ok'
				}, 1, $record['mtime'])
				&& $this->recordlog($record['cid'], sprintf('recharge%s%s',
					in_array($record['type'], ['vip', 'coin'], TRUE) ? "_{$record['type']}" : '',
					date('Y-m-d', $record['mtime']) === $user['date'] ? '_new' : '_old'), $record['fee'], $record['mtime'])
				&& $this->mysql->records('WHERE hash=?s AND log="pending" LIMIT 1', $record['hash'])->update('log="success"') === 1)
			? "OK\n" : "NO\n";
		}
		echo "----SYNC COVER----\n";
		foreach ($this->mysql->videos('WHERE sync!="exception" && cover="change"') as $cover)
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
		echo "----SYNC AD----\n";
		foreach ($this->mysql->ads('WHERE `change`="sync"') as $ad)
		{
			echo "{$ad['hash']} - ",
				is_file($image = "{$this['ad_savedir']}/{$ad['hash']}")
				&& copy($image, "{$this['ad_syncdir']}/{$ad['hash']}")
				&& $this->mysql->ads('WHERE hash=?s LIMIT 1', $ad['hash'])->update([
					'ctime' => $this->time(),
					'change' => 'none']) === 1 ? "OK\n" : "NO\n";
		}
		echo "----SYNC FACE----\n";
		foreach ($this->mysql->users('WHERE uid!=0 AND fid=0') as $user)
		{
			$hash = $this->webapp->time33hash($this->webapp->hashtime33($user['id']));
			echo "{$hash} - ",
				is_file($image = "{$this['face_savedir']}/{$hash}")
				&& copy($image, "{$this['face_syncdir']}/{$hash}")
				&& $this->mysql->users('WHERE id=?s LIMIT 1', $user['id'])->update([
					'ctime' => $this->time(),
					'fid' => 255]) === 1 ? "OK\n" : "NO\n";
		}
		echo "----SYNC IMGS----\n";
		foreach ($this->mysql->images('WHERE sync="pending"') as $img)
		{
			$ym = date('ym', $img['mtime']);
			echo "{$img['hash']} - ",
				is_file($image = "{$this['imgs_savedir']}/{$ym}/{$img['hash']}")
				&& (is_dir($syncdir = "{$this['imgs_syncdir']}/{$ym}") || mkdir($syncdir))
				&& copy($image, "{$syncdir}/{$img['hash']}")
				&& $this->mysql->images('WHERE hash=?s LIMIT 1', $img['hash'])->update([
					'ctime' => $this->time(),
					'sync' => "finished"]) === 1 ? "OK\n" : "NO\n";
		}
	}
	//本地命令行运行视频同步处理
	function get_sync_video()
	{
		if (PHP_SAPI !== 'cli') return 404;
		$ffmpeg = static::lib('ffmpeg/interface.php');
		if ($this->mysql->videos('WHERE sync="waiting" AND size<=tell LIMIT 1')->fetch($video)
			&& $this->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])->update('sync="slicing"') === 1) {
			$error = 'UNKNOWN';
			do
			{
				$savedir = $this->path_video(FALSE, $video);
				if (is_file($sourcevideo = "{$savedir}/video.sb") === FALSE)
				{
					$error = 'NOT FOUND';
					break;
				}
				$slice = $ffmpeg($sourcevideo, '-hide_banner -loglevel error -stats -y');
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
					return 200;
				}
			} while (FALSE);
			echo "{$video['hash']} -> EXCEPTION -> {$error} -> ",
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
		foreach ($this->mysql->videos('WHERE sync="allow" AND type="h"') as $video)
		{
			$vsubjects = [];
			foreach ($subjects as $hash => $data)
			{
				match ($data['fetch_method'])
				{
					'tags' => $video['tags'] && array_intersect(explode(',', $video['tags']), $data['fetch_values']),
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
	function did():?string
	{
		return preg_match('/DID\/(\w{16})/', $this->ua, $pattern) ? $pattern[1] : NULL;
	}
	function cid():string
	{
		return preg_match('/CID\/(\w{4})/', $this->ua, $pattern) ? $pattern[1] : '0000';
		// return is_string($cid = $this->request_cookie('cid') ?? $this->query['cid'] ?? NULL)
		// 	&& preg_match('/^\w{4,}/', $cid) ? substr($cid, 0, 4)
		// 	: (preg_match('/CID\/(\w{4})/', $this->ua, $pattern) ? $pattern[1] : '0000');
	}
	function channel(?string $id, string $sid = '0000'):bool
	{
		return $id && ($id === $sid || $this->mysql->channels('WHERE hash=?s LIMIT 1', $id)->fetch());
	}
	//记录日志
	function recordlog(string $cid, string $field, int $value = 1, int $nowtime = NULL):bool
	{
		if (!preg_match('/\w{4}/', $cid))
		{
			$cid = '0000';
		}
		$ciddate = $cid . date('Ymd', $nowtime);
		$values = match (TRUE)
		{
			$field === 'dpv' => ['dpv' => $value],
			in_array($field, ['dpv_ios', 'dpv_android'], TRUE) => ['dpv' => $value, $field => $value],

			$field === 'dpc' => ['dpc' => $value],
			in_array($field, ['dpc_ios', 'dpc_android'], TRUE) => ['dpc' => $value, $field => $value],

			$field === 'signin' => ['signin' => $value],
			in_array($field, ['signin_ios', 'signin_android'], TRUE) => ['signin' => $value, $field => $value],

			$field === 'signup' => ['signup' => $value],
			in_array($field, ['signup_ios', 'signup_android'], TRUE) => ['signup' => $value, $field => $value],

			in_array($field, ['recharge_new', 'recharge_old'], TRUE) => ['recharge' => $value, $field => $value],

			$field === 'recharge_coin_new'
				=> ['recharge' => $value, 'recharge_coin' => $value, 'recharge_new' => $value],
			$field === 'recharge_coin_old'
				=> ['recharge' => $value, 'recharge_coin' => $value, 'recharge_old' => $value],

			$field === 'recharge_vip_new'
				=> ['recharge' => $value, 'recharge_vip' => $value, 'recharge_new' => $value, 'recharge_vip_new' => 1],
			$field === 'recharge_vip_old'
				=> ['recharge' => $value, 'recharge_vip' => $value, 'recharge_old' => $value],

			$field === 'order' => ['order' => $value],
			in_array($field, ['order_ios', 'order_android'], TRUE) => ['order' => $value, $field => $value],

			$field === 'order_ok' => ['order_ok' => $value],
			in_array($field, ['order_ios_ok', 'order_android_ok'], TRUE) => ['order_ok' => $value, $field => $value],
			default => []
		};
		if (empty($values)) return FALSE;
		$incrdata = [];
		$hourdata = [];
		$hour = date('G', $nowtime);
		foreach ($values as $field => $value)
		{
			$incrdata[] = $this->mysql->format('?a=?a+?i', $field, $field, $value);
			$hourdata[] = $this->mysql->format('\'$[?i].??\',hourdata->>\'$[?i].??\'+?i', $hour, $field, $hour, $field, $value);
		}
		$values = sprintf('%s,hourdata=JSON_SET(hourdata,%s)', join(',', $incrdata), join(',', $hourdata));
		while ($this->mysql->recordlog('WHERE ciddate=?s LIMIT 1', $ciddate)->update($values) !== 1)
		{
			$insert = [
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
				'order_android_ok'	=> 0
			];
			$insert['hourdata'] = json_encode(array_fill(0, 24, $insert), JSON_NUMERIC_CHECK);
			if ($this->mysql->recordlog->insert([
				'ciddate' => $ciddate,
				'cid' => $cid,
				'date' => date('Y-m-d', $nowtime),
				...$insert]) === FALSE) {
				return FALSE;
			}
		}
		return TRUE;
	}
	function user_sync(string $id)
	{
		$this->webapp->remote($this->webapp['app_sync_call'], 'sync_user', [$id]);
	}
	//创建用户
	function user_create(array $user):user
	{
		$user = user::create($this, $user);
		$user->id && $this->recordlog($user['cid'], match ($user['device'])
		{
			'android' => 'signup_android',
			'ios' => 'signup_ios',
			default => 'signup'
		});
		return $user;
	}
	//Reids 覆盖此方法获取用户并且缓存
	function user_fetch(string $id):array
	{
		if ($this->mysql->users('WHERE id=?s LIMIT 1', $id)->fetch($user))
		{
			$this->recordlog($user['cid'], match ($user['device'])
			{
				'android' => 'signin_android',
				'ios' => 'signin_ios',
				default => 'signin'
			});
			$this->mysql->users('WHERE id=?s LIMIT 1', $user['id'])->update([
				'lasttime' => $this->time,
				'lastip' => $this->iphex($this->ip)
			]);
			return $user;
		}
		return [];
	}
	//用户模型
	//'FABqsZrf0g'
	function user(string $id = NULL):user
	{
		return $id ? user::from_id($this, $id) : new user($this,
			$this->authorize($this->request_authorization($type), $this->user_fetch(...)));
	}
	//游戏
	function game():game
	{
		return new game(...$this['game']);
	}
	function game_balance():int
	{
		return $this->game->balance();
	}
	function game_entry():array
	{
		return [
			4 => '百人牛牛',
			1 => '捕鱼',
			8 => '德州扑克',
			6 => '二人麻将',
			46 => '二人牛牛',
			19 => '飞禽走兽',
			7 => '红黑大战',
			12 => '龙虎大战',
			22 => '连环夺宝',
			5 => '抢庄牛牛',
			49 => '疯狂抢庄牛牛',
			28 => '抢庄牌九',
			26 => '抢庄三公',
			24 => '森林舞会',
			50 => '四人牛牛',
			88 => '视讯龙虎',
			90 => '视讯轮盘',
			87 => '视讯牛牛',
			91 => '视讯⾊碟',
			89 => '视讯骰宝',
			17 => '骰宝',
			30 => '通比牛牛',
			37 => '五星宏辉'
		];
	}
	private function user_exchange(bool $result, array $record):bool
	{
		return $result || $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('balance=balance+?i', $record['fee']) === 1;
	}
	private function prod_vtid_vip50(bool $result, array $record):bool
	{
		//VIP增加7天
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, 86400 * 7) === 1;
	}
	private function prod_vtid_vip100(bool $result, array $record):bool
	{
		//VIP增加30天（送10张观影卷）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+10', $this->time, $this->time, 86400 * 30) === 1;
	}
	private function prod_vtid_vip200(bool $result, array $record):bool
	{
		//VIP增加365天（送30张观影卷）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+30', $this->time, $this->time, 86400 * 365) === 1;
	}
	private function prod_vtid_vip300(bool $result, array $record):bool
	{
		//永久VIP（送100张观影卷）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+100', $this->time, $this->time, 86400 * 365 * 20) === 1;
	}
	private function prod_vtid_vip500(bool $result, array $record):bool
	{
		//超级VIP（所有VIP金币视频免费解锁）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('expire=0') === 1;
	}
	private function prod_vtid_coin50(bool $result, array $record)
	{
		//增加50个金币（赠3天VIP）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('coin=coin+50,expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, 86400 * 3) === 1;
	}
	private function prod_vtid_coin100(bool $result, array $record)
	{
		//增加100个金币（赠5%金币）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('coin=coin+105') === 1;
	}
	private function prod_vtid_coin200(bool $result, array $record)
	{
		//增加200个金币（赠5%金币）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('coin=coin+210') === 1;
	}
	private function prod_vtid_coin300(bool $result, array $record)
	{
		//增加300个金币（赠5%金币）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('coin=coin+315') === 1;
	}
	private function prod_vtid_coin500(bool $result, array $record)
	{
		//增加500个金币（赠5%金币）
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('coin=coin+525') === 1;
	}
	const record_results = [
		'pending' => '待定',
		'success' => '完成',
		'failure' => '失败'
	];
	//记录（回调）
	function record(string $hash, bool $result = FALSE):array
	{
		return $this->mysql->records('WHERE hash=?s AND result="pending" AND type!="video" LIMIT 1', $hash)->fetch($record)
			&& $this->mysql->sync(fn() => (array_key_exists('vtid', $record['ext'] = json_decode($record['ext'], TRUE))
				? method_exists($this, $record['ext']['vtid']) && $this->{$record['ext']['vtid']}($result, $record)
				: TRUE) && $this->mysql->records('WHERE hash=?s LIMIT 1', $hash)
					->update('result=?s', $result ? 'success' : 'failure') === 1) ? $record: [];
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
				'fid' => (new user($this, $user))->fid(),
				'nickname' => $user['nickname'],
				'gender' => $user['gender'],
				'descinfo' => $user['descinfo'],
				'followed_ids' => $user['followed_ids'],
				'follower_num' => $user['follower_num'],
				'video_num' => $user['video_num'],
				'like_num' => $user['like_num']
			];
		}
	}
	//获取所有视频
	function fetch_videos():iterable
	{
		foreach ($this->mysql->videos('WHERE ptime<?i ORDER BY ctime DESC', $this->time()) as $video)
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
		foreach ($this->mysql->ads('WHERE display="show" AND seat=?i ORDER BY weight DESC,hash ASC', $seat) as $ad)
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
	//获取所有产品
	function fetch_prods():iterable
	{
		foreach ($this->mysql->prods('WHERE count>0 ORDER BY LEFT(vtid, 13) ASC,price ASC') as $prod)
		{
			yield $prod;
		}
	}
	//根据分类标签ID拉取专题
	function fetch_subjects(string $tagid):array
	{
		$subjects = [];
		foreach ($this->mysql->subjects('WHERE tagid=?s ORDER BY sort DESC', $tagid) as $subject)
		{
			$value = $this->mysql->videos('WHERE FIND_IN_SET(?s,subjects)', $subject['hash'])->select('COUNT(1) c,SUM(view) v')->array();
			$videos = preg_match('/^\d{1,2}$/', $subject['videos'], $count)
				? $this->mysql->videos('WHERE FIND_IN_SET(?s,subjects) ORDER BY mtime DESC LIMIT ?i', $subject['hash'], $count[0])
					->column('hash') : ($subject['videos'] ? str_split($subject['videos'], 12) : []);
			$subjects[] = [
				'hash' => $subject['hash'],
				'name' => $subject['name'],
				'view' => $value['v'] ?? 0,
				'num' => $value['c'] ?? 0,
				'style' => $subject['style'],
				'videos' => $videos,
				'fetch_method' => $subject['fetch_method'],
				'fetch_values' => $subject['fetch_values']
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
				'sort' => $video['sort'],
				'view' => $video['view']
			];
		}
	}
	const comment_type = [
		'class' => '社区的分类',
		'topic' => '分类的话题',
		'post' => '话题的帖子',
		'reply' => '帖子的回复',
		'video' => '视频的评论'
	];
	//拉取所有评论
	function fetch_comments():iterable
	{
		foreach ($this->mysql->comments('WHERE `check`="allow" ORDER BY ctime DESC,hash ASC') as $topic) {

			if ($topic['type'] === 'reply' || $topic['type'] === 'video')
			{
				$images = $topic['images'];
			}
			else
			{
				$images = [];
				if ($image = $topic['images'] ? str_split($topic['images'], 12) : [])
				{
					foreach ($this->mysql->images('WHERE hash IN(?S)', $image) as $img)
					{
						$ym = date('ym', $img['mtime']);
						$images[] = "/imgs/{$ym}/{$img['hash']}";
					}
				}
			}
			yield [
				'hash' => $topic['hash'],
				'phash' => $topic['phash'],
				'user_id' => $topic['userid'],
				'mtime' => $topic['mtime'],
				'ctime' => $topic['ctime'],
				'count' => $topic['count'],
				'sort' => $topic['sort'],
				'type' => $topic['type'],
				'view' => $topic['view'],
				'content' => $topic['content'],
				'title' => $topic['title'],
				'images' => $images,
				'videos' => $topic['videos'] ? str_split($topic['videos'], 12) : []
			];
		}
	}
	function select_topics():array
	{
		return $this->mysql->comments('WHERE `check`="allow" AND phash IS NULL ORDER BY sort DESC')->column('title', 'hash');
	}
}