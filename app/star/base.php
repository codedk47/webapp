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
		
		$cover = $form->fieldset->append('img', ['style' => 'width:512px;height:288px']);
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
			$cover['src'] = $video['cover'] === 'finish' && in_array($video['sync'], ['finished','allow','deny'], TRUE)
				? "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}"
				: '/webapp/res/ps/loading.svg';
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
	function patch_video_value(string $encrypt, string $goto = NULL, string $up = NULL)
	{
		$json = $this->app('webapp_echo_json');
		if (is_string($hash = $this->decrypt($encrypt))
			&& $this->form_video(json_decode($this->request_maskdata() ?? '[]', TRUE))->fetch($video)) {

			if (is_string($up) && $video['require'] === '0') //??fix
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
				&& $this->mysql->ads('WHERE hash=?s LIMIT 1', $ad['hash'])
					->update(['ctime' => $this->time(), 'change' => 'none']) === 1
				&& $this->clear_ads($ad['seat'], $ad['hash']) ? "OK\n" : "NO\n";
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
		function detect(string $haystack, array $needles, callable $method):bool
		{
			foreach ($needles as $needle)
			{
				if ($method($haystack, $needle)) return TRUE;
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
					'intersect' => $video['tags'] && array_intersect(explode(',', $video['tags']), $data['fetch_values']),
					'union' => count(array_intersect($data['fetch_values'], explode(',', $video['tags']))) === count($data['fetch_values']),
					'starts' => detect($video['name'], $data['fetch_values'], str_starts_with(...)),
					'ends' => detect($video['name'], $data['fetch_values'], str_ends_with(...)),
					'contains' => detect($video['name'], $data['fetch_values'], str_contains(...)),
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
	const cid = '0000';
	// function ua():string
	// {
	// 	return $this->request_device();
	// }
	// function ip():string
	// {
	// 	return $this->request_ip(TRUE);
	// }
	// function did():?string
	// {
	// 	return preg_match('/DID\/(\w{16})/', $this->ua, $pattern) ? $pattern[1] : NULL;
	// }
	function cid(?string $cid):string
	{
		return $cid && $this->mysql->channels('WHERE hash=?s LIMIT 1', $cid)->fetch() ? $cid : self::cid;
	}
	// function channel(?string $id, string $sid = '0000'):bool
	// {
	// 	return $id && ($id === $sid || $this->mysql->channels('WHERE hash=?s LIMIT 1', $id)->fetch());
	// }
	//IP记录
	// function denyip():bool
	// {
	// 	return FALSE;
	// }
	//记录日志
	function recordlog(string $cid, string $field, int $value = 1, int $nowtime = NULL):bool
	{
		preg_match('/^\w{4}$/', $cid) || $cid = self::cid;
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
		foreach ($values as $fieldname => $value)
		{
			$incrdata[] = $this->mysql->format('?a=?a+?i', $fieldname, $fieldname, $value);
			$hourdata[] = $this->mysql->format('\'$[?i].??\',hourdata->>\'$[?i].??\'+?i', $hour, $fieldname, $hour, $fieldname, $value);
		}
		$update = sprintf('%s,hourdata=JSON_SET(hourdata,%s)', join(',', $incrdata), join(',', $hourdata));
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
		while ($this->mysql->recordlog('WHERE ciddate=?s LIMIT 1', $ciddate)->update($update) !== 1)
		{
			if ($this->mysql->recordlog->insert([
				'ciddate' => $ciddate,
				'cid' => $cid,
				'date' => date('Y-m-d', $nowtime),
				'hourdata' => json_encode(array_fill(0, 24, $insert), JSON_NUMERIC_CHECK),
				...$insert]) === FALSE) {
				return FALSE;
			}
		}
		if ($this->mysql->channels('WHERE hash=?s LIMIT 1', $cid)->fetch($channel))
		{
			$logs = [];
			foreach ($values as $fieldname => $value)
			{
				$logs[] = $this->mysql->format('?a=?a+?f', $fieldname, $fieldname, $value * $channel['rate']);
			}
			while ($this->mysql->recordlogs('WHERE ciddate=?s LIMIT 1', $ciddate)->update(join(',', $logs)) !== 1)
			{
				if ($this->mysql->recordlogs->insert(['ciddate' => $ciddate, 'cid' => $cid, 'date' => date('Y-m-d', $nowtime), ...$insert]) === FALSE)
				{
					break;
				}
			}
			//print_r($this->mysql);
		}
		return TRUE;
	}
	// function user_sync(string $id)
	// {
	// 	$this->webapp->remote($this->webapp['app_sync_call'], 'sync_user', [$id]);
	// }
	//创建用户
	function user_create(array $user):user
	{
		$user = user::create($this, $user, $created);
		$created && $user->id && $this->recordlog($user['cid'], match ($user['device'])
		{
			'android' => 'signup_android',
			'ios' => 'signup_ios',
			default => 'signup'
		});
		return $user;
	}
	function fetch_user(string $id):array
	{
		if (empty($user = $this->redis->hGetAll($key = "user:{$id}")))
		{
			if ($this->mysql->users('WHERE id=?s LIMIT 1', $id)->fetch($user)
				&& $this->redis->hMSet($key, $user)
				&& $this->redis->expireAt($key, mktime(23, 59, $this->random_int(1, 59)))) {
				$this->recordlog($user['cid'], match ($user['device'])
				{
					'android' => 'signin_android',
					'ios' => 'signin_ios',
					default => 'signin'
				});
				$this->mysql->users('WHERE id=?s LIMIT 1', $user['id'])
					->update('`login`=`login`+1,lasttime=?i,lastip=?s',
					$this->time, $this->iphex($this->request_ip(TRUE)));
			}
		}
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
			$this->mysql->users('WHERE id=?s LIMIT 1', $user['id'])->update(
				'`login`=`login`+1,lasttime=?i,lastip=?s',
				$this->time,
				$this->iphex($this->ip));
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
	private function game_exchange(bool $result, array $record):bool
	{
		return $result
			? $this->remote($this['app_sync_call'], 'game_withdraw', [$record])
			: $this->game->transfer($record['userid'], floatval($record['fee']), $orderid);
	}
	private function user_exchange(bool $result, array $record):bool
	{
		return $result || $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])
			->update('balance=balance+?i', $record['fee']) === 1;
	}
	private function prod_vtid_vip_top_up(bool $result, array $record):bool
	{
		$give = match ($record['fee'])
		{
			50 => ['expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, 86400 * 7], //VIP增加7天
			100 => ['expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+10', $this->time, $this->time, 86400 * 30], //VIP增加30天（送10张观影卷）
			200 => ['expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+20', $this->time, $this->time, 86400 * 90], //VIP增加90天（送20张观影卷）
			300 => ['expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+30', $this->time, $this->time, 86400 * 365], //VIP增加365天（送30张观影卷）
			400 => ['expire=IF(expire>?i,expire,?i)+?i,ticket=ticket+100', $this->time, $this->time, 86400 * 365 * 20], //永久VIP（送100张观影卷）
			500 => ['expire=0'], //超级VIP（所有VIP金币视频免费解锁）
			default => []
		};
		return $result && $give && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update(...$give) === 1;
	}
	private function prod_vtid_vip_premium(bool $result, array $record):bool
	{
		//铂金会员卡
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('expire=0') === 1;
	}
	private function prod_vtid_vip_11_11(bool $result, array $record):bool
	{
		//双11福利会员卡
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update('expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, 86400 * 365 * 20) === 1;
	}
	private function prod_vtid_coin_top_up(bool $result, array $record):bool
	{
		return $result && $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update(...match ($record['fee'])
		{
			50 => ['coin=coin+50,expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, 86400 * 3], //增加50个金币（赠3天VIP）
			default => ['coin=coin+?i', $record['fee'] + intval($record['fee'] * 0.05)] //其他金额（赠对应金额5%金币）
		}) === 1;
	}
	private function prod_vtid_game_top_up(bool $result, array $record):bool
	{
		[$fee, $give] = match ($record['fee'])
		{
			100 => [100, []],
			200 => [200, []],
			300 => [300, []],
			400 => [400, []],
			500 => [500, ['expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, 86400 * 7]], //游戏冲 500 送 7天会员
			600 => [600, []],
			800 => [800, []],
			1000 => [1000, []],
			default => [0, 0]
		};
		return $result && $fee
			&& ($give ? $this->mysql->users('WHERE id=?s LIMIT 1', $record['userid'])->update(...$give) === 1 : TRUE)
			&& $this->game->transfer($record['userid'], $fee, $orderid);
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
					->update('result=?s', $result ? 'success' : 'failure') === 1) ? $record : [];
	}
	//获取配置
	function fetch_configs(string $key = NULL):array|string
	{
		$this->redis->exists('configs')
			|| $this->redis->hMSet('configs', $this->mysql->configs->column('value', 'key'));
		return $key === NULL ? $this->redis->hGetAll('configs') : (string)$this->redis->hGet('configs', $key);
	}
	//清除配置
	function clear_configs():bool
	{
		return $this->redis->del('configs') === 1;
	}
	//获取指定广告（位置）
	function fetch_ads(int $seat):array
	{
		$ads = [];
		if ($this->redis->exists($key = "ad:{$seat}"))
		{
			$lost = 0;
			$keys = $this->redis->hGetAll($key);
			foreach ($keys as $i => $hash)
			{
				if ($ad = $this->redis->hGetAll($hash))
				{
					$ads[] = $ad;
				}
				else
				{
					++$lost;
					unset($keys[$i]);
				}
			}
			$lost && $this->redis->hMSet($key, $keys);
		}
		else
		{
			$keys = [];
			foreach ($this->mysql->ads('WHERE seat=?i ORDER BY weight DESC', $seat) as $ad)
			{
				$this->redis->hMSet($hash = $keys[] = "ad:{$ad['hash']}", $ads[] = [
					'hash' => $ad['hash'],
					'weight' => $ad['weight'],
					'picture' => "?/news/{$ad['hash']}?mask{$ad['ctime']}",
					'support' => $ad['acturl'],
					'name' => $ad['name']
				]);
				//$this->redis->expireAt($hash, $ad['expire']);
			}
			$this->redis->hMSet($key, $keys);
		}
		return $ads;
	}
	//清除指定广告（位置，[HASH]）
	function clear_ads(int $seat, string $hash = NULL):bool
	{
		$hash && $this->redis->del("ad:{$hash}");
		return $this->redis->del("ad:{$seat}") === 1;
	}
	const tags_level = [
		0 => '分类',
		1 => '全局',
		2 => '待定',
		3 => '扩展',
		4 => '附有特征',
		5 => '角色类型',
		6 => '人体特征',
		7 => '地点位置',
		8 => '衣着服饰',
		9 => '其他杂项',
		10 => '传媒频道',
		11 => '明星作者',
		12 => '临时添加'
	];
	//获取所有分类或标签
	function fetch_tags(?int $level, bool $detailed = FALSE):array
	{
		if ($this->redis->exists('tags') === 0)
		{
			$tags = [];
			foreach (self::tags_level as $i => $name)
			{
				$tags += $values = $this->mysql->tags('WHERE level=?i ORDER BY sort DESC, time DESC', $i)->column('name', 'hash');
				$this->redis->hMSet("tags:{$i}", $values);
			}
			$this->redis->hMSet('tags', $tags);
		}
		if (isset(self::tags_level[$level]))
		{
			$tags = $this->redis->hGetAll("tags:{$level}");
			if ($detailed === FALSE)
			{
				foreach ($tags as &$tag)
				{
					$tag = strstr($tag, ',', TRUE);
				}
			}
			return $tags;
		}
		if ($detailed)
		{
			$tags = [];
			foreach (self::tags_level as $level => $name)
			{
				$tags[$level] = $this->redis->hGetAll("tags:{$level}");
			}
			return $tags;
		}
		$tags = $this->redis->hGetAll('tags');
		foreach ($tags as &$tag)
		{
			$tag = strstr($tag, ',', TRUE);
		}
		return $tags;
	}
	//清除所有分类和标签
	function clear_tags():bool
	{
		return $this->redis->del('tags') === 1;
	}
	//拉取专题（分类）
	function fetch_subjects(?string $type):array
	{
		$subjects = [];
		if ($this->redis->exists($key = "subjects:{$type}"))
		{
			foreach ($this->redis->hGetAll($key) as $hash)
			{
				if ($subject = $this->redis->hGetAll($hash))
				{
					$videos = [];
					foreach ($subject['videos'] ? str_split($subject['videos'], 12) : [] as $video)
					{
						if ($video = $this->fetch_video($video))
						{
							$videos[] = $video;
						}
					}
					$subject['videos'] = $videos;
					$subjects[] = $subject;
				}
			}
			return $subjects;
		}
		$keys = [];
		if ($type)
		{
			foreach ($this->mysql->subjects('WHERE type=?s ORDER BY sort DESC, ctime DESC', $type) as $data)
			{
				$videos = [];
				$subject = [
					'hash' => $data['hash'],
					'name' => $data['name'],
					'style' => $data['style'],
					'videos' => ''];
				foreach ($this->mysql->videos(...preg_match('/^\d{1,2}$/', $data['videos'])
					? ['WHERE type="h" AND sync="allow" AND ptime<?i AND FIND_IN_SET(?s,subjects) ORDER BY mtime DESC LIMIT ?i', $this->time, $data['hash'], $data['videos']]
					: ['WHERE hash IN(?S)', str_split($data['videos'], 12)]) as $video) {
					$videos[] = $this->fetch_video($video['hash'], $video);
					$subject['videos'] .= $video['hash'];
				}
				$this->redis->hMSet($keys[] = "subject:{$subject['hash']}", $subject);
				$subject['videos'] = $videos;
				$subjects[] = $subject;
			}
		}
		else
		{
			foreach ($this->fetch_tags(0) as $hash => $name)
			{
				$videos = [];
				$subject = [
					'hash' => $hash,
					'name' => "最新{$name}",
					'style' => 0,
					'videos' => ''];
				foreach ($this->mysql->videos('WHERE type="h" AND sync="allow" AND ptime<?i AND FIND_IN_SET(?s,tags) ORDER BY mtime DESC LIMIT 8', $this->time, $hash) as $video)
				{
					$videos[] = $this->fetch_video($video['hash'], $video);
					$subject['videos'] .= $video['hash'];
				}
				$this->redis->hMSet($keys[] = "classify:{$hash}", $subject);
				$subject['videos'] = $videos;
				$subjects[] = $subject;
			}
		}
		$this->redis->hMSet($key, $keys);
		$this->redis->expire($key, 60);
		return $subjects;
	}
	//清除专题（分类）
	function clear_subjects(?string $type):bool
	{
		return $this->redis->del("subjects:{$type}") === 1;
	}
	function fetch_subject(string $hash, int $page = 0, $size = 20):array
	{
		if ($page)
		{
			$videos = [];
			$start = max(0, ($page - 1) * $size);
			$end = $start + $size - 1;
			if ($this->redis->exists($key = "subjectvideos:{$hash}"))
			{
				foreach ($this->redis->lRange($key, $start, $end) as $hash)
				{
					if ($video = $this->redis->hGetAll($hash))
					{
						$videos[] = $video;
					}
				}
				return $videos;
			}
			$keys = [];
			foreach ($this->mysql->videos('WHERE FIND_IN_SET(?s,subjects) ORDER BY sort DESC, ptime DESC', $hash) as $index => $video)
			{
				$keys[] = "video:{$video['hash']}";
				$video = $this->fetch_video($video['hash'], $video);
				if ($index >= $start && $index <= $end)
				{
					$videos[] = $video;
				}
			}
			$this->redis->rPush($key, ...$keys);
			return $videos;
		}
		return $this->redis->hGetAll("subject:{$hash}");
	}
	function fetch_video(string $hash, ?array $update = NULL):array
	{
		$key = "video:{$hash}";
		if ($update)
		{
			$video = $update ?? $this->mysql->videos('WHERE hash=?s AND sync="allow" LIMIT 1', $hash)->array();
			$ym = date('ym', $video['mtime']);
			$this->redis->hMSet($key, $video = [
				'hash' => $video['hash'],
				'type' => $video['type'],
				'm3u8' => "?/{$ym}/{$video['hash']}/play?mask{$video['ctime']}",
				'poster' => "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}",
				'duration' => static::format_duration($video['duration']),
				'preview' => $video['preview'],
				'require' => $video['require'],
				'view' => $video['view'],
				'like' => $video['like'],
				'tags' => $video['tags'],
				'subjects' => $video['subjects'],
				'name' => $video['name']
			]);
			return $video;
		}
		return $this->redis->hGetAll($key);
	}
	function fetch_random_videos(string $type):array
	{
		return [];
	}
	function fetch_like_videos(array $video):array
	{
		if ($tags = $video['tags'] ? explode(',', $video['tags']) : [])
		{
			$cond = ['WHERE type=?s AND sync="allow" AND FIND_IN_SET(?s,tags)', $video['type'], array_shift($tags)];
			if ($tags)
			{
				$like = [];
				foreach ($tags as $tag)
				{
					$like[] = 'FIND_IN_SET(?s,tags)';
					$cond[] = $tag;
				}
				$cond[0] .= ' AND (' . join(' OR ', $like) . ')';
			}
			$cond = $this->mysql->format(...$cond);
			$videos = [];
			if ($this->redis->exists($key = sprintf('like:%s:videos', $this->hash($cond, TRUE))))
			{
				foreach ($this->redis->hGetAll($key) as $hash)
				{
					if ($video = $this->redis->hGetAll($hash))
					{
						$videos[] = $video;
					}
				}
				return $videos;
			}
			$keys = [];
			foreach ($this->mysql->videos("{$cond} AND hash!=?s ORDER BY `view` ASC, `like` DESC LIMIT 20", $video['hash']) as $video)
			{
				$keys[] = "video:{$video['hash']}";
				$videos[] = $this->fetch_video($video['hash'], $video);
			}
			$this->redis->hMSet($key, $keys);
			$this->redis->expire($key, 300);
			return $videos;
		}
		return $this->fetch_random_videos($video['type']);
	}
	function fetch_short_videos(int $page, int $size = 10)
	{
		$videos = [];
		$start = max(0, ($page - 1) * $size);
		$end = $start + $size - 1;
		if ($this->redis->exists($key = "short:videos"))
		{
			foreach ($this->redis->lRange($key, $start, $end) as $hash)
			{
				if ($video = $this->redis->hGetAll($hash))
				{
					$videos[] = $video;
				}
			}
			return $videos;
		}
		$keys = [];
		foreach ($this->mysql->videos('WHERE type="v" AND sync="allow" AND ptime<?i', $this->time) as $index => $video)
		{
			$keys[] = "video:{$video['hash']}";
			$video = $this->fetch_video($video['hash'], $video);
			if ($index >= $start && $index <= $end)
			{
				$videos[] = $video;
			}
		}
		$this->redis->rPush($key, ...$keys);
		return $videos;
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






	//获取所有UP主
	// function fetch_uploaders():iterable
	// {
	// 	foreach ($this->mysql->users('WHERE uid!=0 ORDER BY ctime DESC') as $user)
	// 	{
	// 		yield [
	// 			'id' => $user['id'],
	// 			'mtime' => $user['mtime'],
	// 			'ctime' => $user['ctime'],
	// 			'fid' => (new user($this, $user))->fid(),
	// 			'nickname' => $user['nickname'],
	// 			'gender' => $user['gender'],
	// 			'descinfo' => $user['descinfo'],
	// 			'followed_ids' => $user['followed_ids'],
	// 			'follower_num' => $user['follower_num'],
	// 			'video_num' => $user['video_num'],
	// 			'like_num' => $user['like_num']
	// 		];
	// 	}
	// }
	//获取所有视频
	function fetch_videos(string $hash, bool $update = FALSE):iterable
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


	//获取有产品（分类）
	function fetch_prods(string $type = NULL):array
	{
		$prods = [];
		if ($this->redis->exists($key = "prods:{$type}"))
		{
			foreach ($this->redis->hGetAll($key) as $hash)
			{
				if ($prod = $this->redis->hGetAll($hash))
				{
					$prods[] = $prod;
				}
			}
			return $prods;
		}
		$keys = [];
		foreach ($this->mysql->prods('WHERE count>0 AND ?? ORDER BY price ASC', match ($type)
		{
			'vip' => 'vtid LIKE "prod_vtid_vip%"',
			'coin' => 'vtid LIKE "prod_vtid_coin%"',
			'game' => 'vtid LIKE "prod_vtid_game%"',
			default => 'vtid IS NULL'
		}) as $prod) $this->redis->hMSet($keys[] = "prod:{$prod['hash']}", $prods[] = $prod);
		$this->redis->hMSet($key, $keys);
		return $prods;
	}
	function clear_prods():bool
	{
		return $this->redis->del('tags') === 1;
	}
	// const comment_type = [
	// 	'class' => '社区的分类',
	// 	'topic' => '分类的话题',
	// 	'post' => '话题的帖子',
	// 	'reply' => '帖子的回复',
	// 	'video' => '视频的评论'
	// ];
	// //拉取所有评论
	// function fetch_comments():iterable
	// {
	// 	foreach ($this->mysql->comments('WHERE `check`="allow" ORDER BY ctime DESC,hash ASC') as $topic) {

	// 		if ($topic['type'] === 'reply' || $topic['type'] === 'video')
	// 		{
	// 			$images = $topic['images'];
	// 		}
	// 		else
	// 		{
	// 			$images = [];
	// 			if ($image = $topic['images'] ? str_split($topic['images'], 12) : [])
	// 			{
	// 				foreach ($this->mysql->images('WHERE hash IN(?S)', $image) as $img)
	// 				{
	// 					$ym = date('ym', $img['mtime']);
	// 					$images[] = "/imgs/{$ym}/{$img['hash']}";
	// 				}
	// 			}
	// 		}
	// 		yield [
	// 			'hash' => $topic['hash'],
	// 			'phash' => $topic['phash'],
	// 			'user_id' => $topic['userid'],
	// 			'mtime' => $topic['mtime'],
	// 			'ctime' => $topic['ctime'],
	// 			'count' => $topic['count'],
	// 			'sort' => $topic['sort'],
	// 			'type' => $topic['type'],
	// 			'view' => $topic['view'],
	// 			'content' => $topic['content'],
	// 			'title' => $topic['title'],
	// 			'images' => $images,
	// 			'videos' => $topic['videos'] ? str_split($topic['videos'], 12) : []
	// 		];
	// 	}
	// }
	// function select_topics():array
	// {
	// 	return $this->mysql->comments('WHERE `check`="allow" AND phash IS NULL ORDER BY sort DESC')->column('title', 'hash');
	// }
}