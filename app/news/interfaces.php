<?php
require 'admin.php';
require 'pay.php';
require 'unit.php';
require 'upto.php';
class interfaces extends webapp
{
	function clientip():string
	{
		return $this->request_header('X-Client-IP') ?? $this->request_ip();
	}
	function clientiphex():string
	{
		return $this->iphex($this->clientip);
	}
	function sync():webapp_client_http
	{
		return $this->sync[$this->site] ??= (new webapp_client_http("http://{$this['app_site'][$this->site]}/index.php", ['autoretry' => 2]))->headers([
			'Authorization' => 'Bearer ' . $this->signature($this['admin_username'], $this['admin_password']),
			'X-Client-IP' => $this->clientip
		]);
	}
	function call(string $method, ...$params):bool|string|array|webapp_xml
	{
		foreach ($params as &$value)
		{
			if ($value instanceof webapp_xml)
			{
				$value = $value->asXML();
			}
		}
		$sync = $this->sync();
		return is_string($content = $sync->goto("/index.php?sync/{$method}", [
			'method' => 'POST',
			'type' => 'application/json',
			'data' => $params
		])->content()) && preg_match('/^(SUCCESS|OK)/i', $content) ? TRUE : $content;
	}
	function pull(string $router):iterable
	{
		$sync = $this->sync();
		$max = NULL;
		do
		{
			if (is_object($xml = $sync->goto("/index.php?pull/{$router}")->content()))
			{
				$max ??= intval($xml['max']);
				foreach ($xml->children() as $children)
				{
					yield $children;
				}
			}
		} while (--$max > 0);
	}
	function get_sync()
	{
		if (PHP_SAPI !== 'cli' || $this->request_ip() !== '127.0.0.1')
		{
			$this->echo('Please run at the local command line');
			return;
		}
		$ffmpeg = static::lib('ffmpeg/interface.php');
		foreach ($this->mysql->resources('WHERE sync="waiting" ORDER BY time ASC') as $resource)
		{
			$day = date('ym', $resource['time']);
			if (is_dir($outdir = "{$this['app_resoutdir']}/{$day}/{$resource['hash']}"))
			{
				echo "{$resource['hash']}: update -- ",
					copy("{$outdir}/cover.jpg", "{$this['app_resdstdir']}/{$day}/{$resource['hash']}/cover.jpg")
						&& copy("{$outdir}/cover", "{$this['app_resdstdir']}/{$day}/{$resource['hash']}/cover")
						&& $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource['hash'])->update('sync="finished"') ? "OK" : "NO",
				"\n";
				continue;
			}
			$cut = $ffmpeg("{$this['app_respredir']}/{$resource['hash']}");
			echo "{$resource['hash']}: {$day}\n";
			if ($cut->m3u8($outdir))
			{
				$this->maskfile("{$outdir}/play.m3u8", "{$outdir}/play");
				if (is_file("{$this['app_respredir']}/{$resource['hash']}.cover")
					? webapp_image::from("{$this['app_respredir']}/{$resource['hash']}.cover")->jpeg("{$outdir}/cover.jpg", 100)
					: $cut->jpeg("{$outdir}/cover.jpg")) {
					$this->maskfile("{$outdir}/cover.jpg", "{$outdir}/cover");
				}
				echo exec("xcopy \"{$outdir}/*\" \"{$this['app_resdstdir']}/{$day}/{$resource['hash']}/\" /E /C /I /F /Y", $output, $code), ":{$code}\n";
				if ($code === 0
					&& $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource['hash'])->update('sync="finished"')) {
					foreach (explode(',', $resource['site']) as $site)
					{
						if (array_key_exists($site, $this['app_site']))
						{
							$this->site = $site;
							$this->call('saveRes', $this->resource_xml($this->resource_get($resource)));
						}
					}
					unlink("{$this['app_respredir']}/{$resource['hash']}");
					is_file("{$this['app_respredir']}/{$resource['hash']}.cover")
						&& unlink("{$this['app_respredir']}/{$resource['hash']}.cover");
					continue;
				}
			}
			$this->mysql->resources('WHERE hash=?s LIMIT 1', $resource['hash'])->update('sync="exception"');
		}
	}
	function get_pull()
	{
		if (PHP_SAPI !== 'cli' || $this->request_ip() !== '127.0.0.1')
		{
			$this->echo('Please run at the local command line');
			return;
		}
		$units = array_combine($unit = $this->mysql->unitsets->column('unit'), $unit);
		foreach ($this['app_site'] as $site => $ip)
		{
			if ($site > 0) break;
			$this->site = $site;
			echo "\n\nSTART PULL SITE:{$this->site}";
			$status = [0, 0];
			echo "\n-------- PULL ACC LOG --------\n";
			foreach ($this->pull('log-acc') as $acc)
			{
				// echo $acc['uid'], ' - ',
				// 	$this->mysql->accounts('WHERE site=?i AND uid=?s LIMIT 1', $site, $acc['uid'])
				// 		->update('`favorite`=?s,`history`=?s', (string)$acc->favorite, (string)$acc->history) === 1 ? 'OK' : 'NO',
				// 	"\n";
				++$status[$this->mysql->accounts('WHERE site=?i AND uid=?s LIMIT 1', $site, $acc['uid'])
					->update('`favorite`=?s,`history`=?s', (string)$acc->favorite, (string)$acc->history) === 1 ? 0 : 1];
			}
			echo "SUCCESS: {$status[0]}, FAILURE: {$status[1]}\n";

			$status = [[], []];
			echo "\n-------- PULL UNIT --------\n";
			foreach ($this->pull('incr-unit') as $unit)
			{
				// echo $units[(string)$unit['unit']] ?? '0000', ' - ',
				// 	$this->unitincr($units[(string)$unit['unit']] ?? '0000', (string)$unit['time'], [
				// 		'pv' => (int)$unit['pv'],
				// 		'ua' => (int)$unit['ip'],
				// 		'lu' => (int)$unit['lu'],
				// 		'ru' => (int)$unit['ru'],
				// 		'dv' => (int)$unit['dv'],
				// 		'dc' => (int)$unit['dc'],
				// 		'ia' => (int)$unit['ia']]) ? 'OK' : 'NO',
				// 		"\n";
				$status[$this->unitincr($units[(string)$unit['unit']] ?? '0000', (string)$unit['time'], [
					'pv' => (int)$unit['pv'],
					'ua' => (int)$unit['ip'],
					'lu' => (int)$unit['lu'],
					'ru' => (int)$unit['ru'],
					'dv' => (int)$unit['dv'],
					'dc' => (int)$unit['dc'],
					'ia' => (int)$unit['ia']]) ? 0 : 1][] = $units[(string)$unit['unit']] ?? '0000';
			}
			echo 'SUCCESS: ' . join(' ', $status[0]) . "\n";
			echo 'FAILURE: ' . join(' ', $status[1]) . "\n";

			$status = [0, 0];
			echo "\n-------- PULL TAGS --------\n";
			foreach ($this->pull('incr-tag') as $tag)
			{
				// echo $tag['hash'], ' - ',
				// 	$this->mysql->tags('WHERE hash=?s LIMIT 1', $tag['hash'])
				// 		->update('`click`=`click`+?i', $tag['click']) === 1 ? 'OK' : 'NO',
				// 	"\n";
				++$status[$this->mysql->tags('WHERE hash=?s LIMIT 1', $tag['hash'])
					->update('`click`=`click`+?i', $tag['click']) === 1 ? 0 : 1];
			}
			echo "SUCCESS: {$status[0]}, FAILURE: {$status[1]}\n";

			$status = [0, 0];
			echo "\n-------- PULL RESOURCES --------\n";
			foreach ($this->pull('incr-res') as $resource)
			{
				// echo $resource['hash'], ' - ',
				// 	$this->mysql->resources('WHERE FIND_IN_SET(?s,site) AND hash=?s LIMIT 1', $site, $resource['hash'])
				// 		->update('data=json_set(data,\'$."?i".like\',data->>\'$."?i".like\'+?i,\'$."?i".view\',data->>\'$."?i".view\'+?i,\'$."?i".favorite\',data->>\'$."?i".favorite\'+?i)',
				// 			$site, $site, $resource['like'],
				// 			$site, $site, $resource['view'],
				// 			$site, $site, $resource['favorite']) === 1 ? 'OK' : 'NO',
				// 	"\n";
				++$status[$this->mysql->resources('WHERE FIND_IN_SET(?s,site) AND hash=?s LIMIT 1', $site, $resource['hash'])
					->update('data=json_set(data,\'$."?i".like\',data->>\'$."?i".like\'+?i,\'$."?i".view\',data->>\'$."?i".view\'+?i,\'$."?i".favorite\',data->>\'$."?i".favorite\'+?i)',
						$site, $site, $resource['like'],
						$site, $site, $resource['view'],
						$site, $site, $resource['favorite']) === 1 ? 0 : 1];
			}
			echo "SUCCESS: {$status[0]}, FAILURE: {$status[1]}\n";

			$status = [0, 0];
			echo "\n-------- PULL AD --------\n";
			foreach ($this->pull('incr-ad') as $ad)
			{
				// echo $ad['hash'], ' - ',
				// 	$this->mysql->ads('WHERE site=?i AND hash=?s LIMIT 1', $site, $ad['hash'])
				// 		->update('`click`=`click`+?i,`view`=`view`+?i', $ad['click'], $ad['view']) === 1 ? 'OK' : 'NO',
				// 	"\n";
				++$status[$this->mysql->ads('WHERE site=?i AND hash=?s LIMIT 1', $site, $ad['hash'])
					->update('`click`=`click`+?i,`view`=`view`+?i', $ad['click'], $ad['view']) === 1 ? 0 : 1];
			}
			echo "SUCCESS: {$status[0]}, FAILURE: {$status[1]}\n\n";

			// echo "\n-------- PULL COMMENTS --------\n";
			// foreach ($this->pull('comments') as $comment)
			// {
			// 	echo $comment['hash'], ' - ', 
			// 		$this->mysql->comments->insert($comment->getattr() + ['site' => $site, 'content' => (string)$comment]) ? 'OK' : 'NO',
			// 		"\n";
			// }
			//break;
		}
	}
	function packer(string $data):string
	{
		$bin = random_bytes(8);
		$key = array_map(ord(...), str_split($bin));
		$length = strlen($data);
		for ($i = 0; $i < $length; ++$i)
		{
			$data[$i] = chr(ord($data[$i]) ^ $key[$i % 8]);
		}
		return $bin . $data;
	}
	function maskfile(string $src, string $dst):bool
	{
		$bin = random_bytes(8);
		$key = array_map(ord(...), str_split($bin));
		$buffer = file_get_contents($src);
		$length = strlen($buffer);
		for ($i = 0; $i < $length; ++$i)
		{
			$buffer[$i] = chr(ord($buffer[$i]) ^ $key[$i % 8]);
		}
		return file_put_contents($dst, $bin . $buffer) === $length + 8;
	}
	function randhash(bool $care = FALSE):string
	{
		return $this->hash($this->random(16), $care);
	}
	function shorthash(int|string ...$contents):string
	{
		return $this->hash($this->site . $this->time . join($contents), TRUE);
	}
	function runstatus():array
	{
		$status = [
			'os_http_connected' => intval(shell_exec('netstat -ano | find ":80" /c'))
		];
		foreach ($this->mysql('SELECT * FROM performance_schema.GLOBAL_STATUS WHERE VARIABLE_NAME IN(?S)', [
			'Aborted_clients',
			'Aborted_connects',//接到MySQL服务器失败的次数
			'Queries',//总查询
			'Slow_queries',//慢查询
			'Max_used_connections',//高峰连接数量
			'Max_used_connections_time',//高峰连接时间
			'Threads_cached',
			'Threads_connected',//打开的连接数
			'Threads_created',//创建过的线程数
			'Threads_running',//激活的连接数
			'Uptime',//已经运行的时长
		]) as $stat) {
			$status['mysql_' . strtolower($stat['VARIABLE_NAME'])] = $stat['VARIABLE_VALUE'];
		}
		return $status;
	}

	//-----------------------------------------------------------------------------------------------------
	function get_home()
	{
		$this->app->xml->comment(file_get_contents(__DIR__.'/interfaces.txt'));
	}
	function accountsss(string $signature, &$account):bool
	{
		return boolval($account = $this->authorize($signature, fn(string $uid, string $pwd):array
			=> $this->mysql->accounts('WHERE site=?i AND uid=?s AND pwd=?s LIMIT 1', $this->site, $uid, $pwd)->array()));
	}
	function get_units()
	{
		$this->xml->cdata(join($this->mysql->unitsets->column('unit')));
	}
	function unitincr(string $uint, string $date, array $incr):bool
	{
		$time = preg_match('/^(\d{4})-(\d{2})-(\d{2})-(\d{2})$/', $date, $pattern) ? array_slice($pattern, 1) : explode('-', $date = date('Y-m-d-H'));
		$hour = intval($time[3]);
		$keys = array_keys($incr);
		$fill = array_fill(0, 24, array_combine($keys, array_fill(0, count($keys), 0)));
		$fill[$hour] = $incr;
		$data = [
			'code' => "{$time[0]}{$time[1]}{$time[2]}{$uint}",
			'site' => $this->site,
			'date' => substr($date, 0, 10),
			'unit' => $uint,
			'details' => json_encode($fill)
		];
		$update = [];
		$detail = [];
		foreach ($incr as $key => $value)
		{
			$update[] = $this->mysql->format('?a=?a+?i', $key, $key, $value);
			$detail[] = $this->mysql->format('\'$[?i].??\',details->>\'$[?i].??\'+?i', $hour, $key, $hour, $key, $value);
		}
		$update[] = $this->mysql->format('details=JSON_SET(details, ??)', join(',', $detail));
		$status = $this->mysql->real_query('INSERT INTO unitstats SET ?v ON DUPLICATE KEY UPDATE ??', $data + $incr, join(',', $update));

		$ia = floatval($this->mysql->unitrates('WHERE unit=?s', $uint)->select('sum(ia)')->value());
		if ($this->mysql->unitsets('WHERE unit=?s LIMIT 1', $uint)->fetch($uintdata))
		{
			$fake = $update = [];
			array_pop($data);
			foreach ($incr as $key => $value)
			{
				$fake[$key] = $ia < 10 ? $value : $value * $uintdata['rate'];
				$update[] = $this->mysql->format('?a=?a+?f', $key, $key, $fake[$key]);
			}
			$this->mysql->real_query('INSERT INTO unitrates SET ?v ON DUPLICATE KEY UPDATE ??', $data + $fake, join(',', $update));
		}
		return $status;
	}
	//广告
	function form_ad($ctx, string $hash = NULL):webapp_form
	{
		$form = new webapp_form($ctx, is_string($hash)
			? "{$this['app_resdomain']}?ad/{$hash}"
			: "{$this['app_resdomain']}?ad");
		$form->xml['onsubmit'] = 'return upres(this)';
		$form->xml['data-auth'] = $this->signature($this['admin_username'], $this['admin_password'], (string)$this->site);

		$form->fieldset();
		$form->field('ad', 'file', ['accept' => 'image/*']);
		$form->field('weight', 'number', ['min' => 1, 'max' => 255, 'value' => 1, 'required' => NULL]);

		$form->fieldset('名称跳转');
		$form->field('name', 'text', ['style' => 'width:8rem', 'placeholder' => '广告名称', 'required' => NULL]);
		$form->field('goto', 'url', ['style' => 'width:42rem', 'placeholder' => '跳转地址', 'required' => NULL]);

		$form->fieldset('有效时间段，每天展示时间段');
		$form->field('timestart', 'datetime-local', ['value' => date('Y-m-d\T00:00'), 'required' => NULL],
			fn($v,$i)=>$i?strtotime($v):date('Y-m-d\TH:i',$v));
		$form->field('timeend', 'datetime-local', ['value' => date('Y-m-d\T23:59'), 'required' => NULL],
			fn($v,$i)=>$i?strtotime($v):date('Y-m-d\TH:i',$v));

		$form->fieldset('每周几显示，空为时间内展示');
		$form->field('weekset', 'checkbox', ['options' => [
			'星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六']],
			fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'mo';
		$form->fieldset('广告显示位置');
		$form->field('seat', 'checkbox', ['options' => [
			'开屏（大概尺寸是手机竖立尺寸）',
			'播放页面（大概尺寸470x120）',
			'首页弹窗（大概尺寸470x650）',
			'免费视频（大概尺寸230x138）',
			'列表中间（大概尺寸470x120）',
			'个人中心（大概尺寸470x120）', '预留位置7', '预留位置8', '预留位置9',]],
			fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'ad';

		$form->fieldset('展示方式：小于0点击次数，大于0展示次数');
		$form->field('count', 'number', ['value' => 0, 'required' => NULL]);

		$form->fieldset();
		$form->button('Submit', 'submit');

		return $form;
	}
	function options_ad()
	{
		$this->response_header('Allow', 'OPTIONS, POST');
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->response_header('Access-Control-Allow-Headers', '*');
		$this->response_header('Access-Control-Allow-Methods', 'POST');
	}
	function post_ad(string $hash = NULL)
	{
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->app('webapp_echo_json', ['code' => 0]);
		if ($this->form_ad($this->webapp)->fetch($ad))
		{
			if (is_string($hash))
			{
				$ad += $this->webapp->mysql->ads('where site=?i and hash=?s', $this->site, $hash)->array();
				$ok = $this->mysql->ads('where site=?i and hash=?s', $this->site, $hash)->update($ad);
			}
			else
			{
				$ok = $this->mysql->ads->insert($ad += [
					'hash' => $this->randhash(),
					'site' => $this->site,
					'time' => $this->time,
					'click' => 0,
					'view' => 0
				]);
			}
			if ($up = $this->request_uploadedfile('ad')[0] ?? [])
			{
				$this->maskfile($up['file'], "{$this['app_resoutdir']}/{$this['app_addirname']}/{$ad['hash']}");
			}
			if ($ok && $this->call('saveAd', $this->ad_xml($ad)))
			{
				$this->app['goto'] = '?admin/ads';
				return;
			}
			$this->app['errors'][] = '广告提交失败！';
		}
	}
	function options_deletead()
	{
		$this->response_header('Allow', 'OPTIONS, GET');
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->response_header('Access-Control-Allow-Headers', '*');
		$this->response_header('Access-Control-Allow-Methods', 'GET');
	}
	function get_deletead(string $hash)
	{
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->app('webapp_echo_json');
		if ($this->call('delAd', $hash)
			&& $this->mysql->ads->delete('where site=?i and hash=?s', $this->site, $hash)) {
			is_file($filename = "{$this['app_resoutdir']}/{$this['app_addirname']}/{$hash}") && unlink($filename);
			$this->app['goto'] = '?admin/ads';
			return;
		}
		$this->app['errors'][] = '广告删除失败！';
	}
	function ad_xml(array $ad):webapp_xml
	{
		return $this->xml->append('ad', [
			'hash' => $ad['hash'],
			'seat' => $ad['seat'],
			'weight' => $ad['weight'],
			'timestart' => $ad['timestart'],
			'timeend' => $ad['timeend'],
			'weekset' => $ad['weekset'],
			'count' => $ad['count'],
			'click' => $ad['click'],
			'view' => $ad['view'],
			'name' => $ad['name'],
			'goto' => $ad['goto']
		]);
	}
	function get_ads()
	{
		foreach ($this->mysql->ads('WHERE site=?i', $this->site) as $ad)
		{
			$this->ad_xml($ad);
		}
	}
	//资源
	function form_resourceupload($ctx):webapp_form
	{
		$form = new webapp_form($ctx, "{$this['app_resdomain']}?resourceupload");
		$form->xml['onsubmit'] = 'return mupres(this)';
		$form->xml['data-auth'] = $this->signature($this['admin_username'], $this['admin_password'], (string)$this->site);
		//$form->progress()->setattr(['style' => 'width:100%']);
		$form->fieldset('资源文件 / 封面图片');
		$form->field('resource', 'file', ['accept' => 'video/mp4', 'required' => NULL]);
		$form->field('piccover', 'file', ['accept' => 'image/*']);
		$form->field('type', 'select', ['options' => $this->webapp['app_restype']]);
		$form->fieldset('name / actors');
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->field('actors', 'text', ['value' => $form->echo ? $this->admin[0] : NULL, 'required' => NULL]);
		
		
		$form->fieldset('tags（从小到大排列）');
		$tagc = [];
		$tags = [];
		foreach ($this->webapp->mysql->tags('ORDER BY level ASC,click DESC,count DESC')->select('hash,level,name') as $tag)
		{
			$tagc[$tag['hash']] = $tag['level'];
			$tags[$tag['hash']] = $tag['name'];
		}
		$form->field('tags', 'checkbox', ['options' => $tags], fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'restag';

		foreach ($form->fieldset->xpath('ul/li') as $li)
		{
			$level = (string)$li->label->input['value'];
			$li['class'] = "level{$tagc[$level]}";
		}

		$form->fieldset('require(下架：-2、会员：-1、免费：0、金币) / 预览');
		$form->field('require', 'number', ['value' => 0, 'min' => -2, 'required' => NULL]);
		$form->field('preview_start', 'time', ['value' => '00:00:00', 'step' => 1]);
		$form->field('preview_end', 'time', ['value' => '00:00:10', 'step' => 1]);
		$form->fieldset();
		$form->button('Upload Resource', 'submit');
		
		$form->xml['class'] = 'webapp-mrp';
		return $form;
	}
	function resource_create(array $data):bool
	{
		$preview_start = explode(':', $data['preview_start']);
		$preview_start = mktime(8 + $preview_start[0], $preview_start[1], $preview_start[2], 1, 1, 1970);
		$preview_end = explode(':', $data['preview_end']);
		$preview_end = mktime(8 + $preview_end[0], $preview_end[1], $preview_end[2], 1, 1, 1970);
		$preview = $preview_end > $preview_start ? $preview_start << 16 | ($preview_end - $preview_start) & 0xffff : 10;

		if ($preview === 10)
		{
			$preview = ceil($data['duration'] * 0.7) << 16 | 50;
		}

		$name = webapp::lib('hanzi/interface.php')($data['name']);
		return $this->mysql->resources->insert([
			'hash' => $data['hash'],
			'time' => $this->time,
			'preview' => $preview,
			'duration' => $data['duration'],
			'type' => $data['type'],
			'sync' => 'waiting',
			'site' => $this->site,
			'data' => json_encode([$this->site => [
				'require' => intval($data['require']),
				'favorite' => 0,
				'view' => 0,
				'like' => 0,
				'name' => $name
			]], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE),
			'tags' => $data['tags'],
			'actors' => $data['actors'],
			'name' => $name
		]);
	}
	function resource_delete(string $hash):bool
	{
		if ($resource = $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s LIMIT 1', $this->site, $hash)->array())
		{
			$site = explode(',', $resource['site']);
			array_splice($site, array_search(1, $site), 1);
			return $this->mysql->resources('WHERE hash=?s LIMIT 1', $hash)->update('site=?s,data=JSON_REMOVE(data,\'$."?i"\')', join(',', $site), $this->site);
		}
		return TRUE;
	}
	function resource_update(string $hash, array $data):bool
	{
		$preview_start = explode(':', $data['preview_start']);
		$preview_start = mktime(8 + $preview_start[0], $preview_start[1], $preview_start[2], 1, 1, 1970);
		$preview_end = explode(':', $data['preview_end']);
		$preview_end = mktime(8 + $preview_end[0], $preview_end[1], $preview_end[2], 1, 1, 1970);
		$preview = $preview_end > $preview_start ? $preview_start << 16 | ($preview_end - $preview_start) & 0xffff : 10;
		
		$name = webapp::lib('hanzi/interface.php')($data['name']);
		$update = ['preview=?i,data=JSON_SET(data,\'$."?i".require\',?i,\'$."?i".name\',?s),type=?s,tags=?s',
			$preview, $this->site, $data['require'] ?? 0, $this->site, $name, $data['type'], $data['tags']];
		if ($this->admin[2])
		{
			$update[0] .= ',type=?s,tags=?s,actors=?s,name=?s';
			array_push($update, $data['type'], $data['tags'], $data['actors'], $name);
		}
		return $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s LIMIT 1', $this->site, $hash)->update(...$update);
	}

	function resource_get(string|array $resource):array
	{
		if (is_string($resource))
		{
			if (empty($resource = $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s LIMIT 1', $this->site, $resource)->array()))
			{
				return [];
			}
		}
		$name = $resource['name'];
		unset($resource['name']);
		$resource += json_decode($resource['data'], TRUE)[$this->site] ?? [
			'require' => 0,
			'favorite' => 0,
			'view' => 0,
			'like' => 0,
			'name' => $name];
		return $resource;
	}

	function resource_assign(array $resource, int $site, array $value = []):bool
	{
		$sites = $resource['site'] ? explode(',', $resource['site']) : [];
		$value += json_decode($resource['data'], TRUE)[$site] ?? [];
		$sites[] = $site;
		return $this->mysql->resources('WHERE hash=?s LIMIT 1', $resource['hash'])
			->update('site=?s,data=JSON_SET(data,\'$."?i"\',JSON_OBJECT("require",?i,"favorite",0,"view",0,"like",0,"name",?s))',
			join(',', array_unique($sites)), $site, intval($value['require'] ?? 0), $value['name'] ?? $resource['name']);
	}
	function options_resourceupload()
	{
		$this->response_header('Allow', 'OPTIONS, POST');
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->response_header('Access-Control-Allow-Headers', '*');
		$this->response_header('Access-Control-Allow-Methods', 'POST');
	}
	function post_resourceupload()
	{
		$this->response_header('Access-Control-Allow-Origin', '*');
		$resource = [];
		$uploadfile = $this->request_uploadedfile('resource', 1)[0] ?? [];
		$this->app('webapp_echo_json', [
			'resource' => &$resource,
			'uploadfile' => $uploadfile
		]);
		if (empty($uploadfile) || $uploadfile['mime'] !== 'video/mp4')
		{
			$this->app['errors'][] = '请上传有效资源！';
			return;
		}
		if ($this->form_resourceupload($this)->fetch($resource) === FALSE)
		{
			return;
		}
		if ($data = $this->mysql->resources('WHERE hash=?s LIMIT 1', $uploadfile['hash'])->array())
		{
			if ($this->resource_assign($data, $this->site, $resource))
			{
				if ($data['sync'] === 'finished')
				{
					$this->call('saveRes', $this->resource_xml($this->resource_get($data['hash'])));
					$this->app['goto'] = "?admin/resources,search:{$data['hash']}";
				}
				else
				{
					$this->app['goto'] = "?admin/resources,sync:{$data['sync']}";
				}
			}
			else
			{
				$this->app['errors'][] = '资源分配更新失败！';
			}
			return;
		}
		if ($this->form_resourceupload($this)->fetch($resource)
			&& move_uploaded_file($uploadfile['file'], $filename = "{$this['app_respredir']}/{$uploadfile['hash']}")
			&& $this->resource_create($resource + [
				'hash' => $uploadfile['hash'],
				'duration' => intval(static::lib('ffmpeg/interface.php')($filename)->duration)])) {
			if ($piccover = $this->request_uploadedfile('piccover', 1)[0] ?? [])
			{
				move_uploaded_file($piccover['file'], "{$this['app_respredir']}/{$uploadfile['hash']}.cover");
			}
			$this->app['goto'] = '?admin/resources,sync:waiting';
			return;
		}
		isset($filename) && is_file($filename) && unlink($filename);
	}
	// function get_resourcepreview(string $hash)
	// {
	// 	if ($this->mysql->resources('WHERE hash=?s LIMIT 1', $hash)->fetch($res))
	// 	{
	// 		$zip = new ZipArchive;
	// 		$m3u8 = sprintf("{$this['app_resoutdir']}/%s/{$res['hash']}/play.m3u8", date('ym', $res['time']));
	// 		if (webapp::lib('ffmpeg/interface.php')($m3u8)->preview($dirname = "D:/preview/{$res['hash']}", 30)
	// 			&& $zip->open("{$dirname}/preview.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
	// 			$zip->addGlob("{$dirname}/*.jpg", GLOB_BRACE, ['remove_all_path' => TRUE]);
	// 			$zip->close();
	// 			$this->response_content_download("{$res['hash']}.zip");
	// 			$this->response_sendfile("{$dirname}/preview.zip");
	// 		}
	// 	}
	// }
	function resource_xml(array $resource):webapp_xml
	{
		$data = json_decode($resource['data'], TRUE)[$this->site] ?? [];
		$node = $this->xml->append('resource', [
			'hash' => $resource['hash'],
			'time' => $resource['time'],
			'preview' => sprintf('%d,%d', $resource['preview'] >> 16, $resource['preview'] & 0xffff),
			'duration' => $resource['duration'],
			'type' => $resource['type'],
			'tags' => $resource['tags'],
			'actors' => $resource['actors'],
			'require' => $data['require'] ?? 0,
			'favorite' => $data['favorite'] ?? 0,
			'view' => $data['view'] ?? 0,
			'like' => $data['like'] ?? 0
		]);
		$node->cdata($data['name']);
		return $node;
	}
	function get_updatecover(string $hash)
	{
		if (($resource = $this->mysql->resources('WHERE FIND_IN_SET(?i,site) AND hash=?s', $this->site, $hash)->array())
			&& $resource['sync'] === 'finished') {
			$ym = date('ym', $resource['time']);
			if (is_dir($dir = "{$this['app_resoutdir']}/{$ym}/{$resource['hash']}") || mkdir($dir, recursive: TRUE))
			{
				if (file_put_contents("{$dir}/cover.jpg", $this->request_content()) !== FALSE
					&& $this->maskfile("{$dir}/cover.jpg", "{$dir}/cover")
					&& $this->mysql->resources('WHERE hash=?s LIMIT 1', $hash)->update('sync="waiting"')) {
					$this->resource_xml($resource);
				}
			}
		}
	}
	function get_resources(string $hash = NULL, int $page = 1, int $size = 1000)
	{
		$cond = ['WHERE FIND_IN_SET(?i,site) AND sync="finished"', $this->site];
		if ($hash)
		{
			$cond[0] .= ' AND hash like ?s';
			$cond[] = "{$hash}%";
		}
		if (array_key_exists('tag', $this->query) && is_string($this->query['tag']))
		{
			$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
			$cond[] = $this->query['tag'];
		}
		$cond[0] .= ' ORDER BY time DESC';
		
		$resources = $this->mysql->resources(...$cond)->paging($page, $size);
		$this->app->xml->setattr($resources->paging);
		foreach ($resources as $resource)
		{
			$this->resource_xml($resource);
		}
	}
	function get_pres(string $hash = NULL, int $page = 1, int $size = 100)
	{
		$cond = ['WHERE site="0" AND sync="finished"'];
		if ($hash)
		{
			$cond[0] .= ' AND hash like ?s';
			$cond[] = "{$hash}%";
		}
		if (array_key_exists('tag', $this->query) && is_string($this->query['tag']))
		{
			$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
			$cond[] = $this->query['tag'];
		}
		if (array_key_exists('time', $this->query) && is_numeric($this->query['time']))
		{
			$cond[0] .= ' AND time>?i';
			$cond[] = $this->query['time'];
		}
		$cond[0] .= ' ORDER BY time DESC';
		$resources = $this->mysql->resources(...$cond)->paging($page, $size);
		$this->app('webapp_echo_xml')->xml->setattr(['site' => 0]);
		$this->xml = $this->app->xml;
		$this->app->xml->setattr($resources->paging);
		foreach ($resources as $resource)
		{
			$this->resource_xml($resource);
		}
	}
	//标签
	function selecttags():array
	{
		return $this->mysql->tags('ORDER BY level ASC,count DESC,click DESC')->column('name', 'hash');
	}
	function tag_xml(array $tag):webapp_xml
	{
		return $this->xml->append('tag', [
			'hash' => $tag['hash'],
			'time' => $tag['time'],
			'level' => $tag['level'],
			'count' => $tag['count'],
			'click' => $tag['click'],
			'seat' => $tag['seat'],
			'name' => $tag['name'],
			'alias' => $tag['alias']
		]);
	}
	function get_tags(string $type = NULL, int $page = 1, int $size = 1000)
	{
		$tags = ($type ? $this->mysql->tags('WHERE type=?i ORDER BY level ASC,count DESC,click DESC', $type) : $this->mysql->tags)->paging($page, $size);
		$this->app->xml->setattr($tags->paging);
		foreach ($tags as $tag)
		{
			$this->tag_xml($tag);
		}
	}
	//合集
	function settag_xml(array $data)
	{
		$node = $this->xml->append('settag', [
			'hash' => $data['hash'],
			'sort' => $data['sort'],
			'name' => $data['name']
		]);
		$node->append('ads')->cdata($data['ads']);
		$node->append('vods')->cdata($data['vods']);
		return $node;
	}
	function get_settags(string $null = NULL, int $page = 1, int $size = 1000)
	{
		foreach ($this->mysql->settags('WHERE site=?i ORDER BY sort asc', $this->site)->paging($page, $size) as $settag)
		{
			$this->settag_xml($settag);
		}
	}
	function setvod_xml(array $data)
	{
		$node = $this->xml->append('setvod', [
			'hash' => $data['hash'],
			'time' => $data['time'],
			'view' => $data['view'],
			'sort' => $data['sort'],
			'type' => $data['type'],
			'viewtype' => $data['viewtype'],
			'ad' => $data['ad'],
			'name' => $data['name'],
			'tags' => $data['tags']
		]);
		$node->append('describe')->cdata($data['describe']);
		$node->append('resources')->cdata($data['resources']);
		return $node;
	}
	function get_setvods(string $null = NULL, int $page = 1, int $size = 1000)
	{
		foreach ($this->mysql->setvods('WHERE site=?i ORDER BY time desc', $this->site)->paging($page, $size) as $settag)
		{
			$this->setvod_xml($settag);
		}
	}
	function get_config()
	{
		$this->xml->append('config')->cdata($this->mysql->configs('where site=?i', $this->site)->array()['data'] ?? '');
	}



	//账号操作
	// function uid(string $signature, &$uid):bool
	// {
	// 	return is_string($uid = $this->authorize($signature, fn(string $uid):?string
	// 		=> strlen($uid) === 10 && trim($uid, webapp::key) === '' ? $uid : NULL));
	// }
	function account(string $signature, &$account):bool
	{
		return boolval($account = $this->authorize($signature, fn(string $uid, string $pwd):array
			=> $this->mysql->accounts('WHERE site=?i AND uid=?s AND pwd=?s LIMIT 1', $this->site, $uid, $pwd)->array()));
	}
	function account_xml(array $account, string $signature = NULL):webapp_xml
	{
		$node = $this->xml->append('account', [
			'uid' => $account['uid'],
			'signature' => $signature ?? $this->signature($account['uid'], $account['pwd']),
			'expire' => $account['expire'],
			'balance' => $account['balance'],
			'lasttime' => $account['lasttime'],
			'lastip' => $account['lastip'],
			'device' => $account['device'],
			'face' => $account['face'],
			'unit' => $account['unit'],
			'code' => $account['code'],
			'phone' => $account['phone'],
			'name' => $account['name']
		]);
		$node->append('resources')->cdata($account['resources']);
		$node->append('favorite')->cdata($account['favorite']);
		$node->append('history')->cdata($account['history']);
		return $node;
	}
	function account_bind_code(string $code, string $gift):bool
	{
		return strlen($code) === 10
			&& preg_match('/^(expire|balance)\:(\d+)$/', $gift, $value)
			&& $this->mysql->accounts('WHERE uid=?s', $code)->update(...$value[1] === 'expire'
				? ['expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, $value[2]]
				: ['?a=?a+?i', $value[1], $value[1], $value[2]]);
	}
	function post_gift(string $signature)
	{
		if (is_array($gift = $this->request_content())
			&& isset($gift['name'], $gift['value'])
			&& is_string($gift['name'])
			&& in_array($gift['name'], ['expire', 'balance'], TRUE)
			&& is_numeric($gift['value'])
			&& $this->account($signature, $account)
			&& $this->mysql->accounts('WHERE uid=?s', $account['uid'])->update(...$gift['name'] === 'expire'
				? ['expire=IF(expire>?i,expire,?i)+?i', $this->time, $this->time, $gift['value']]
				: ['?a=?a+?i', $gift['name'], $gift['name'], $gift['value']])) {
			if ($gift['name'] === 'expire')
			{
				$account['expire'] = ($account['expire'] > $this->time ? $account['expire'] : $this->time) + $gift['value'];
			}
			else
			{
				$account[$gift['name']] = $account[$gift['name']] + $gift['value'];
			}
			$this->account_xml($account, $signature);
		}
	}
	function post_changedid()
	{
		$input = $this->request_content();
		if (isset($input['old'], $input['new'], $input['did'])
			&& is_string($input['old'])
			&& is_string($input['new'])
			&& is_string($input['did'])
			&& count($old = $this->authorize($input['old'], fn($uid) => [$uid]))
			&& count($new = $this->authorize($input['new'], fn($uid) => [$uid]))) {
			$input['old'] = $old[0];
			$input['new'] = $new[0];
			if ($this->mysql->sync(fn() => $this->mysql->accounts('WHERE uid=?s LIMIT 1', $input['old'])->update('did=NULL')
				&& $this->mysql->accounts('WHERE uid=?s LIMIT 1', $input['new'])->update('did=?s', $input['did']))) {
				$this->account_xml($this->mysql->accounts('WHERE uid=?s LIMIT 1', $input['new'])->array());
			}
		}
	}
	function post_register()
	{
		//这里也许要做频率限制
		$rand = $this->random(16);
		$data = $this->request_content();
		$error = '账号已存在';
		do
		{
			if (isset($data['device'], $data['unit']) === FALSE)
			{
				$error = '缺少必要字段';
				break;
			}
			$did = NULL;
			if (isset($data['phone']) && is_string($data['phone']))
			{
				$phone = $data['phone'];
				if ($this->mysql->accounts('WHERE phone=?s LIMIT 1', $phone)->fetch($account))
				{
					$this->xml['status'] = 'sign-in';
					$this->account_xml($account);
					return;
				}
			}
			else
			{
				// if ((isset($data['random'], $data['answer'])
				// 	&& is_string($data['random'])
				// 	&& is_string($data['answer'])) === FALSE) {
				// 	$error = '缺少认证信息';
				// 	break;
				// }
				// if ($data['random'] !== 'android')
				// {
				// 	if ($this->captcha_verify($data['random'], $data['answer']) === FALSE)
				// 	{
				// 		$error = '验证码无效或者过期';
				// 		break;
				// 	}
				// }
				$phone = NULL;
				if (isset($data['did']) && is_string($data['did']))
				{
					$did = $data['did'];
					if ($this->mysql->accounts('WHERE did=?s LIMIT 1', $did)->fetch($account))
					{
						$this->xml['status'] = 'sign-in';
						$this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update([
							'lasttime' => $account['lasttime'] = $this->time,
							'lastip' => $account['lastip'] = $this->clientiphex
						]);
						$this->account_xml($account);
						return;
					}
				}
			}
			if ($this->mysql->accounts->insert($account = [
				'uid' => $this->hash($rand, TRUE),
				'site' => $this->site,
				'date' => date('Y-m-d', $this->time),
				'time' => $this->time,
				'expire' => $this->time,
				'balance' => 0,
				//时间段送礼
				// 'expire' => $expire = mktime(23, 59, 59, 7, 16, 2022),
				// 'balance' => $expire > $this->time ? 20 : 0,
				'lasttime' => $this->time,
				'lastip' => $this->clientiphex(),
				'device' => match (1) {
					preg_match('/windows phone/i', $data['device']) => 'wp',
					preg_match('/pad/i', $data['device']) => 'pad',
					preg_match('/iphone/i', $data['device']) => 'ios',
					preg_match('/android/i', $data['device']) => 'android',
					default => 'pc'},
				'face' => 0,
				'unit' => strlen($data['unit']) === 4 ? $data['unit'] : '',
				'code' => '',
				'did' => $did,
				'phone' => $phone,
				'pwd' => random_int(100000, 999999),
				'name' => $this->hash($rand),
				'resources' => '',
				'favorite' => '',
				'history' => ''])) {
				if (isset($data['code'], $data['gift'])
					&& is_string($data['code'])
					&& is_string($data['gift'])
					&& $this->account_bind_code($data['code'], $data['gift'])) {
					$account['code'] = $data['code'];
				}
				$this->xml['status'] = 'sign-up';
				$this->account_xml($account);
				return;
			}
		} while (0);
		$this->xml->append('error')->cdata($error);
	}
	function get_signature(string $uid)
	{
		if ($this->mysql->accounts('WHERE site=?i AND uid=?s ', $this->site, $uid)->fetch($account))
		{
			$this->account_xml($account);
		}
	}
	function get_account(string $signature)
	{
		if ($this->account($signature, $account)
			&& $this->mysql->accounts('WHERE site=?i AND uid=?s', $this->site, $account['uid'])->update([
				'lasttime' => $account['lasttime'] = $this->time,
				'lastip' => $account['lastip'] = $this->clientiphex])) {
			$this->account_xml($account, $signature);
		}
	}
	function post_account(string $signature)
	{
		$input = $this->request_content();
		$update = [];
		$pass = FALSE;
		foreach (['face', 'code', 'phone', 'pwd', 'name'] as $allow)
		{
			if (array_key_exists($allow, $input))
			{
				$pass = match ($allow)
				{
					'face' => $input[$allow] > -1 && $input[$allow] < 256,
					'code' => isset($input['gift']) && $this->account_bind_code($input['code'], $input['gift']),
					'phone', 'pwd', => strlen($input[$allow]) < 17,
					'name' => $this->strlen($input[$allow]) < 17,
					default => FALSE
				};
				if ($pass)
				{
					$update[$allow] = $input[$allow];
					continue;
				}
				break;
			}
		}
		if ($pass && $update
			&& $this->account($signature, $account)
			&& $this->mysql->accounts('WHERE site=?i AND uid=?s' . (
				array_key_exists('code', $update) ? ' AND code=""' : ''
			) . ' LIMIT 1', $this->site, $account['uid'])->update($update)) {
			$this->account_xml($update + $account);
		}
	}
	function report_xml(array $report)
	{
		$node = $this->xml->append('report', [
			'hash' => $report['hash'],
			'time' => $report['time'],
			'promise' => $report['promise'],
			'account' => $report['account']
		]);
		$node->cdata($report['describe']);
		return $node;
	}
	function get_reports(string $null = NULL, int $page = 1, int $size = 1000)
	{
		foreach ($this->mysql->reports('WHERE site=?i ORDER BY time desc', $this->site)->paging($page, $size) as $report)
		{
			$this->report_xml($report);
		}
	}
	function post_report(string $signature)
	{
		if ($this->account($signature, $account)
			&& is_array($contents = $this->request_content())
			&& isset($contents['describe'])
			&& is_string($contents['describe'])
			&& strlen($describe = isset($contents['contact']) && is_string($contents['describe'])
				? "{$contents['contact']}\n{$contents['describe']}"
				: $contents['describe']) > 2
			&& strlen($describe) < 128
			&& $this->mysql->reports->insert($report = [
				'hash' => $this->randhash(TRUE),
				'site' => $this->site,
				'time' => $this->time,
				'ip' => $this->clientiphex(),
				'promise' => 'waiting',
				'account' => $account['uid'],
				'describe' => $describe])) {
			$this->report_xml($report);
		}
	}
	
	function bill(string $uid, int $fee, string|array $describe, &$bill):bool
	{
		return $this->mysql->sync(function() use($uid, $fee, $describe, &$bill)
		{
			if (is_array($describe))
			{
				return $this->mysql->accounts('WHERE site=?i AND uid=?s', $this->site, $uid)
						->update('balance=balance+?i,resources=CONCAT(?s,LEFT(resources,2400))', -$fee, $describe['hash']) > 0
					&& $this->mysql->bills->insert($bill = [
						'hash' => $this->randhash(TRUE),
						'site' => $this->site,
						'time' => $this->time,
						'type' => $describe['type'],
						'tym' => date('Ym', $this->time),
						'day' => date('d', $this->time),
						'fee' => $fee,
						'account' => $uid,
						'describe' => $describe['hash']
					]);
			}
			return $this->mysql->accounts('WHERE site=?i AND uid=?s', $this->site, $uid)
					->update('balance=balance+?i', $fee) > 0
				&& $this->mysql->bills->insert($bill = [
					'hash' => $this->randhash(TRUE),
					'site' => $this->site,
					'time' => $this->time,
					'type' => 'undef',
					'tym' => date('Ym', $this->time),
					'day' => date('d', $this->time),
					'fee' => $fee,
					'account' => $uid,
					'describe' => $describe
				]);
		});
	}
	function bill_xml(array $bill)
	{
		$this->xml->append('bill', [
			'hash' => $bill['hash'],
			'time' => $bill['time'],
			'fee' => $bill['fee'],
			'account' => $bill['account'],
		])->cdata($bill['describe']);
	}
	function post_bill(string $signature)
	{
		if ($this->account($signature, $account)
			&& is_array($bill = $this->request_content())
			&& isset($bill['fee'], $bill['describe'])
			&& is_string($bill['describe'])
			&& $this->bill($account['uid'], intval($bill['fee']), $bill['describe'], $bill)) {
			$this->bill_xml($bill);
		}
	}
	function get_bills(string $signature, int $page = 1)
	{
		if ($this->account($signature, $account))
		{
			$bills = $this->mysql->bills('WHERE site=?i AND account=?s', $this->site, $account['uid'])->paging($page);
			$this->xml->setattr($bills->paging);
			foreach ($bills as $bill)
			{
				$this->bill_xml($bill);
			}
		}
	}
	function get_play(string $resource_signature)
	{
		if ($this->account($signature = substr($resource_signature, 12), $account))
		{
			$resource = $this->resource_get(substr($resource_signature, 0, 12));
			if ($resource
				&& $resource['require'] > 0
				&& in_array($resource['hash'], $account['resources'] ? str_split($account['resources'], 12) : []) === FALSE
				&& $this->bill($account['uid'], $resource['require'], $resource, $bill)) {
				$this->xml->append('play', [
					'resource' => $resource['hash'],
					'balance' => $account['balance'] - $resource['require']
				]);
				$this->bill_xml($bill);
			}
		}
	}
	function get_favorite(string $resource_signature)
	{
		$resource = substr($resource_signature, 0, 12);
		if ($this->account($signature = substr($resource_signature, 12), $account))
		{
			$favorite = $account['favorite'] ? str_split(substr($account['favorite'], -384), 12) : [];
			$offset = array_search($resource, $favorite, TRUE);
			if ($offset === FALSE)
			{
				$favorite[] = $resource;
				$value = 'favorite=favorite+1';
			}
			else
			{
				array_splice($favorite, $offset, 1);
				$value = 'favorite=favorite-1';
			}
			if ($this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('favorite=?s', $favorite = join($favorite)))
			{
				$this->mysql->resources('WHERE hash=?s LIMIT 1', $resource)->update($value);
				$this->xml->append('favorite', ['signature' => $signature])->cdata($favorite);
			}
		}
	}
	function get_history(string $resource_signature)
	{
		$resource = substr($resource_signature, 0, 12);
		if ($this->account($signature = substr($resource_signature, 12), $account)
			&& $this->mysql->accounts('WHERE uid=?s LIMIT 1', $account['uid'])->update('history=?s', $history = $account['history']
				? join(array_unique(str_split(substr($account['history'] . $resource, -384), 12)))
				: $resource)) {
			$this->mysql->resources('WHERE hash=?s LIMIT 1', $resource)->update('view=view+1');
			$this->xml->append('history', ['signature' => $signature])->cdata($history);
		}
	}
	//评论
	function get_comments(string $resource)
	{
		$comments = $this->mysql->comments('WHERE site=?i AND resource=?s ORDER BY time DESC LIMIT 200', $this->site, $resource)->all();
		$accounts = $this->mysql->accounts('WHERE uid IN(?S)', array_unique(array_column($comments, 'account')))->column('face', 'name', 'uid');
		$unknown = ['face' => 0, 'name' => 'unknown'];
		foreach ($comments as $comment)
		{
			$account = $accounts[$comment['account']] ?? $unknown;
			$this->xml->append('comment', [
				'hash' => $comment['hash'],
				'time' => $comment['time'],
				//'account' => $comment['account'],
				'face' => $account['face'],
				'name' => $account['name']
			])->cdata($comment['content']);
		}
	}
	//Setvods
	function form_setvod($ctx, string $hash = NULL, string $type = NULL):webapp_form
	{
		$form = new webapp_form($ctx, is_string($hash)
			? "{$this['app_resdomain']}?setvod/{$hash},type:{$type}"
			: "{$this['app_resdomain']}?setvod");

		$form->xml['onsubmit'] = 'return upres(this)';
		$form->xml['data-auth'] = $this->signature($this['admin_username'], $this['admin_password'], (string)$this->site);
		
		$form->fieldset('cover / name / sort / type / viewtype / ad');
		$form->field('cover', 'file', ['accept' => 'image/*']);
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);
		$form->field('type', 'select', ['options' => $this['app_restype'], 'required' => NULL]);
		$form->field('viewtype', 'select', ['options' => ['双联', '横中滑动', '大一偶小', '横小滑动', '竖小', '大横图'], 'required' => NULL]);

		$form->fieldset('describe');
		$form->field('describe', 'text', ['style' => 'width:60rem', 'placeholder' => '合集描述']);

		// $form->fieldset('tags');
		// $form->field('tags', 'checkbox', ['options' => $this->selecttags()],
		// 	fn($v,$i)=>$i?join($v):str_split($v,4))['class'] = 'restag';

		$form->fieldset('tags（从小到大排列）');
		$tagc = [];
		$tags = [];
		foreach ($this->webapp->mysql->tags('ORDER BY level ASC,click DESC,count DESC')->select('hash,level,name') as $tag)
		{
			$tagc[$tag['hash']] = $tag['level'];
			$tags[$tag['hash']] = $tag['name'];
		}
		$form->field('tags', 'checkbox', ['options' => $tags], fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'restag';
	
		foreach ($form->fieldset->xpath('ul/li') as $li)
		{
			$level = (string)$li->label->input['value'];
			$li['class'] = "level{$tagc[$level]}";
		}


		$form->fieldset('resources');
		$form->field('resources', 'text', [
			'style' => 'width:60rem',
			'placeholder' => '请输入展示的资源哈希用逗号间隔',
			'pattern' => '([0-9A-Z]{12})?(,[0-9A-Z]{12})*'
		], fn($v,$i)=>$i?join(explode(',',$v)):join(',',str_split($v,12)));

		$form->fieldset('Ads');
		$form->field('ad', 'checkbox', ['options' => ['' => '请选择展示广告']
			+ $this->mysql->ads('WHERE site=?i ORDER BY time DESC', $this->site)->column('name', 'hash')],
			fn($v,$i)=>$i?join($v):str_split($v,12))['class'] = 'mo';

		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}
	function options_setvod()
	{
		$this->response_header('Allow', 'OPTIONS, POST');
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->response_header('Access-Control-Allow-Headers', '*');
		$this->response_header('Access-Control-Allow-Methods', 'POST');
	}
	function post_setvod(string $hash = NULL, string $type = NULL)
	{
		$this->response_header('Access-Control-Allow-Origin', '*');
		$this->app('webapp_echo_json', ['code' => 0]);
		$form = $this->form_setvod($this);
		if ($hash)
		{
			if ($this->form_setvod($this)->fetch($data)
				&& $this->mysql->setvods('WHERE hash=?s LIMIT 1', $hash)->update($data)
				&& ($newdata = $this->mysql->setvods('WHERE hash=?s LIMIT 1', $hash)->array())
				&& $this->call('saveSetvod', $this->setvod_xml($newdata))) {
				if ($cover = $this->request_uploadedfile('cover')[0] ?? [])
				{
					$this->maskfile($cover['file'], "{$this['app_resoutdir']}/vods/{$hash}");
				}
				return $this->app['goto'] = "?admin/setvods,type:{$type}";
			}
			$this->app['errors'][] = '合集资源更新失败！';
		}
		else
		{
			$form->xml->fieldset[1]->input['required'] = NULL;
			if ($form->fetch($data)
				&& $this->mysql->setvods->insert($data += [
					'hash' => $this->randhash(),
					'site' => $this->site,
					'time' => $this->time,
					'view' => 0])
				&& $this->call('saveSetvod', $this->setvod_xml($data))) {
				if ($cover = $this->request_uploadedfile('cover')[0] ?? [])
				{
					$this->maskfile($cover['file'], "{$this['app_resoutdir']}/vods/{$data['hash']}");
				}
				return $this->app['goto'] = "?admin/setvods,type:{$data['type']}";
			}
			$this->app['errors'][] = '合集资源创建失败！';
		}
	}
	function upto():array
	{
		return $this->upto = $this->webapp->authorize(func_num_args() ? func_get_arg(0) : $this->webapp->request_cookie('upto'),
			fn($uid, $pwd, $st, $add) => array_key_exists($uid, webapp_router_upto::up)
				&& webapp_router_upto::up[$uid]['pwd'] === $pwd
					? webapp_router_upto::up[$uid] + ['uid' => $uid, 'add' => $add] : []);
	}
	function options_upto_upload()
	{
		$this->response_header('Access-Control-Allow-Origin', '*');
	}
	function get_upto_upload(string $sign)
	{
		do
		{
			if (empty($info = $this->upto($sign)))
			{
				break;
			}
			if (is_file($file = "D:/up/{$info['add']}"))
			{
				if (unlink($file) === FALSE)
				{
					break;
				}
			}
			$this->echo('success');
			return;
		} while (0);
		$this->echo('failure');
	}
	function post_upto_upload(string $sign = NULL)
	{
		if ($info = $this->upto($sign))
		{
			if ($file = fopen("D:/up/{$info['add']}", 'a'))
			{
				fwrite($file, $this->request_content());
				fclose($file);
				return 200;
			}
			return 404;
		}
		do
		{
			if (is_array($input = $this->request_content('application/json')) && $this->upto() === FALSE)
			{
				break;
			}
			$input['time'] = $this->time;
			if ($this->mysql->upres('where hash=?s limit 1', $input['hash'])->fetch($file))
			{
				$input['sync'] = 'waiting';
				if (($file['upid'] === $this->upto['uid'] && $this->mysql->upres('where hash=?s limit 1', $input['hash'])->update($input)) === FALSE)
				{
					break;
				}
			}
			else
			{
				$input['upid'] = $this->upto['uid'];
				if ($this->mysql->upres->insert($input) === FALSE)
				{
					break;
				}
			}
			$url = 'https://up.fasdfasd.com/?upto-upload/' . $this->signature($this->upto['uid'], $this->upto['pwd'], $input['hash']);
			if (webapp_client_http::open($url)->content() !== 'success')
			{
				break;
			}
			$this->echo($url);
			return 200;
		} while (0);
		return 404;
	}
};