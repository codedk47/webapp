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
		$form->button('更新视频', 'submit');

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

		$form->fieldset();
		$tagc = [];
		$tags = [];
		foreach ($this->webapp->mysql->tags('ORDER BY level ASC,sort DESC')->select('hash,level,name') as $tag)
		{
			$tagc[$tag['hash']] = $tag['level'];
			$tags[$tag['hash']] = $tag['name'];
		}
		$form->field('tags', 'checkbox', ['options' => $tags], fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'restag';
		$blevel = null;
		$nlevel = self::tags_level;
		foreach ($form->fieldset->xpath('ul/li') as $li)
		{
			$level = (string)$li->label->input['value'];
			$li['class'] = "level{$tagc[$level]}";
			if ($blevel !== $tagc[$level])
			{
				$blevel = $tagc[$level];
				$li->insert('li', 'before')->setattr([$nlevel[$blevel], 'class' => 'part']);
			}
		}
		$form->xml->append('script', 'document.querySelectorAll("ul.restag>li>label").forEach(label=>(label.onclick=()=>label.className=label.firstElementChild.checked?"checked":"")());');

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
		$updatable = FALSE;
		foreach ($this->mysql->videos('WHERE sync="allow" AND type="h" AND ptime<?i', $this->time) as $video)
		{
			$values = [];
			foreach ($subjects as $hash => $subject)
			{
				match ($subject['fetch_method'])
				{
					'intersect' => $video['tags'] && array_intersect(explode(',', $video['tags']), $subject['fetch_values']),
					'union' => count(array_intersect($subject['fetch_values'], explode(',', $video['tags']))) === count($subject['fetch_values']),
					'starts' => detect($video['name'], $subject['fetch_values'], str_starts_with(...)),
					'ends' => detect($video['name'], $subject['fetch_values'], str_ends_with(...)),
					'contains' => detect($video['name'], $subject['fetch_values'], str_contains(...)),
					'uploader' => in_array($video['userid'], $subject['fetch_values'], TRUE),
					default => FALSE
				} && $values[] = $hash;
			}
			$values = join(',', $values);
			if ($video['subjects'] !== $values)
			{
				$updatable = TRUE;
				$success = $this->mysql->videos('WHERE hash=?s LIMIT 1', $video['hash'])
					->update('subjects=?s', $values) === 1 ? 'OK' : 'NO';
				echo "{$video['hash']} -> [{$video['subjects']}] >> [{$values}] {$success}\n";
			}
		}
		
		if ($updatable)
		{
			$this->fetch_videos->flush()->cache();
			$this->fetch_subjects->flush()->cache();
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
	function redis_did_save_cid(?string &$cid):string
	{
		$did = 'iOS_' . $this->random_hash(FALSE);
		$this->redis->set("did:{$did}", $cid = $this->cid($cid), 180);
		return $did;
	}
	function redis_did_read_cid(string $did):string
	{
		$cid = $this->redis->get("did:{$did}");
		return is_string($cid) ? $cid : self::cid;
	}
	// function short_number(int $value):string
	// {

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
	//用户模型
	function user(string $id = NULL):user
	{
		return $id ? user::from_id($this, $id) : new user($this,
			$this->authorize($this->request_authorization($type), $this->user_fetch(...)));
	}
	//游戏
	// function game():game
	// {
	// 	return new game(...$this['game']);
	// }
	// function game_balance():int
	// {
	// 	return $this->game->balance();
	// }
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
		static $configs;
		$configs ??= $this->redis->assoc('configs', 'key', 'value');
		return $key === NULL ? $configs : $configs[$key] ?? '';
	}
	//清除配置
	function clear_configs():void
	{
		$this->redis->clear('configs');
	}
	//获取指定广告（位置）
	function fetch_ads():webapp_redis_table
	{
		return new class($this->redis, '`change`="none" AND expire>?i ORDER BY weight DESC', $this->time) extends webapp_redis_table
		{
			protected string $tablename = 'ads', $primary = 'hash', $expire = 'expire';
			function __construct(webapp_redis|webapp_redis_table $context, ...$commands)
			{
				parent::__construct($context, ...$commands);
				$this->root === $this && $this->cache();
			}
			function format(array $data):array
			{
				return [
					'hash' => $data['hash'],
					'weight' => $data['weight'],
					'picture' => "?/news/{$data['hash']}?mask{$data['ctime']}",
					'support' => $data['acturl'],
					'name' => $data['name']
				];
			}
			function seat(int $i):array
			{
				return $this->with('seat=?i', $i)->cache()->values();
			}
			function rand(int $i):array
			{
				return $this->webapp->random_weights($this->seat($i));
			}
		};
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
	function fetch_tags():webapp_redis_table
	{
		return new class($this->redis, 'ORDER BY sort DESC, time DESC') extends webapp_redis_table
		{
			protected string $tablename = 'tags', $primary = 'hash';
			function __construct(webapp_redis|webapp_redis_table $context, ...$commands)
			{
				parent::__construct($context, ...$commands);
				$this->root === $this && $this->cache()->cacheable();
			}
			function format(array $tag):array
			{
				return [
					'hash' => $tag['hash'],
					'click' => $tag['click'],
					'level' => $tag['level'],
					'name' => $tag['name'],
					'shortname' => strstr($tag['name'], ',', TRUE)
				];
			}
			function classify():array
			{
				return $this->root->with('level=0')->column('shortname', $this->primary);
			}
			function shortname():array
			{
				return $this->root->column('shortname', $this->primary);
			}
			function like(string $word):array
			{
				$likes = [];
				foreach ($this->root as $key => $value)
				{
					if (in_array($word, explode(',', $value['name']), TRUE))
					{
						$likes[] = $key;
					}
				}
				return $likes;
			}
			function levels(bool $fullname = FALSE, array $not = []):array
			{
				$levels = [];
				foreach (base::tags_level as $level => $describe)
				{
					if (in_array($level, $not, TRUE))
					{
						continue;
					}
					$levels[$describe] = $this->root->with('level=?s', $level)->cache()
						->column($fullname ? 'name' : 'shortname', $this->primary);
				}
				return $levels;
			}
		};
	}
	//获取专题
	function fetch_subjects():webapp_redis_table
	{
		return new class($this->redis, 'ORDER BY sort DESC') extends webapp_redis_table
		{
			protected string $tablename = 'subjects', $primary = 'hash';
			function format(array $data):array
			{
				return [
					'hash' => $data['hash'],
					'name' => $data['name'],
					'style' => $data['style'],
					'videos' => is_numeric($data['videos'])
						? join($this->webapp->fetch_videos->with('FIND_IN_SET(?s,subjects)', $data['hash'])->keys(intval($data['videos'])))
						: $data['videos']];
			}
			function videos(string $subject, int $page):iterable
			{
				return is_array($this[$subject]) ? $this->webapp->fetch_videos
					->with('FIND_IN_SET(?s,subjects)', $subject)->cache()->paging($page) : new EmptyIterator;
			}
		};
	}
	//获取影片
	function fetch_videos():webapp_redis_table
	{
		$redis = $this->redis();
		$redis->select(1);
		return new class($redis, 'sync="allow" AND ptime<?i ORDER BY sort DESC, ctime DESC', $this->time) extends webapp_redis_table
		{
			protected string $tablename = 'videos', $primary = 'hash';
			function format(array $data):array
			{
				$ym = date('ym', $data['mtime']);
				return [
					'hash' => $data['hash'],
					'time' => $data['ptime'],
					'type' => $data['type'],
					'm3u8' => "?/{$ym}/{$data['hash']}/play?mask{$data['ctime']}",
					'poster' => "?/{$ym}/{$data['hash']}/cover?mask{$data['ctime']}",
					'duration' => base::format_duration($data['duration']),
					'preview' => $data['preview'],
					'require' => $data['require'],
					'view' => $data['view'],
					'like' => $data['like'],
					'tags' => $data['tags'],
					'name' => $data['name'],
					'extdata' => $data['extdata']
				];
			}
			function randtop(string $tag):iterable
			{
				if ($this->root->alloc($key, "randtop.{$tag}"))
				{
					$keys = $this->eval("FIND_IN_SET(?s,tags) {$this->sort} LIMIT 7", $tag)
						->select($this->primary)->column($this->primary);
					$this->redis->sAdd($key, ...$keys);
				}
				else
				{
					$keys = $this->redis->sMembers($key);
				}
				shuffle($keys);
				return $this->iter(...$keys);
			}
			function similar(array|string $video):static
			{
				if (is_array(is_string($video) ? $video = $this[$video] : $video))
				{
					$cond = ['type=?s', $video['type']];
					if ($classify = array_intersect($this->webapp->fetch_tags->with('level=0')->keys(),
						$video['tags'] ? explode(',', $video['tags']) : [])) {
						$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
						$cond[] = current($classify);
					}
					return $this->root->with(...$cond)->cache();
				}
				return $this->root;
			}
			function watch_actress(array $video):iterable
			{
				$videos = $this->similar($video);
				$extdata = $video['extdata'] ? json_decode($video['extdata'], TRUE) : [];
				$count = 0;
				if (isset($extdata['actress']))
				{
					$actress = $videos->with('FIND_IN_SET(?s,extdata->>\'$.actress\')', current(explode(',', $extdata['actress'], 2)));
					$count = $actress->count();
					foreach ($actress->random(10) as $video)
					{
						yield $video;
					}
				}
				if ($count < 10)
				{
					foreach ($videos->random(10 - $count) as $video)
					{
						yield $video;
					}
				}
			}
			function watch_random(string $hash):iterable
			{
				return $this->similar($hash)->random(8);
			}
		};
	}
}