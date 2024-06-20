<?php
class webapp_router_console extends webapp_echo_html
{
	
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp, function(string $uid, string $pwd) use($webapp)
		{
			return $uid === $webapp['admin_username'] && $pwd === $webapp['admin_password'] ? [$uid, $pwd] : [];
		});
		$this->title('Console');
		if (empty($this->auth)) return;
		$this->script(['src' => '/webapp/app/star/base.js?v=w']);
		$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/star/base.css']);
		//$this->link_resources($webapp['app_resources']);
		//$this->script(['src' => '/webapp/app/star/base.js?v=w']);
		$this->nav([
			['Home', '?console/home'],
			['Ads', '?console/ads'],
			//['Tags', '?console/tags'],
			['Videos', '?console/videos'],
			['Configs', '?console/configs'],
			//['Reports', '?console/reports'],
			['Logout', "javascript:location.reload(document.cookie='webapp=0');", 'style' => 'color:maroon']
		]);
	}
	function goto(string $url = NULL):void
	{
		$this->json(['goto' => $url === NULL ? NULL : "?console{$url}"]);
	}
	function dialog(string $msg):void
	{
		$this->json(['dialog' => $msg]);
	}

	function get_home(string $date = NULL, string $cid = '', string $datefrom = '', string $dateto = '')
	{
		if (preg_match('/^\d{4}\-\d{2}$/', $date ??= date('Y-m')))
		{
			$stat = $this->webapp->mysql->recordlog('WHERE date LIKE ?s', "{$date}%")->statmonth($date, 'cid', 'right(date,2)', 
				array_map(fn($v) => "SUM(IF({day}=0 OR right(date,2)={day},{$v},0))", ['pv', 'uv', 'watch', 'dpv', 'dpc', 'signin', 'signup']), 'ORDER BY $2$0 DESC');

			// $fields = ['cid', 'RIGHT(date,2) day', ...array_map(fn($v) => "SUM({$v}) {$v}", $statistics)];
			// $a = $this->webapp->mysql->recordlog('WHERE date LIKE ?s GROUP BY date ORDER BY cid ASC,date ASC', "{$pattern[1]}%")
			// 	->select(join(',', $fields));
			$day = date('t', strtotime("{$date}-01"));
			$table = $this->main->table($stat, function($table, $log, $day)
			{
				$tr = [$table->row()];
				$table->cell([$log['cid'] ?? '所有', 'rowspan' => 7]);
				$table->cell('PV');
				$tr[] = $table->row();
				$table->cell('UV');
				$tr[] = $table->row();
				$table->cell('观影');
				$tr[] = $table->row();


				$table->cell('访问');
				$tr[] = $table->row();
				$table->cell('点击');
				$tr[] = $table->row();
				$table->cell('登录');
				$tr[] = $table->row();
				$table->cell('新增');
				$table->row();
				$table->cell(['-', 'colspan' => $day + 3]);
				for ($i = 0; $i <= $day; ++$i)
				{
					$tr[0]->append('td', number_format($log["\$0\${$i}"]));
					$tr[1]->append('td', number_format($log["\$1\${$i}"]));
					$tr[2]->append('td', number_format($log["\$2\${$i}"]));
					$tr[3]->append('td', number_format($log["\$3\${$i}"]));
					$tr[4]->append('td', number_format($log["\$4\${$i}"]));
					$tr[5]->append('td', number_format($log["\$5\${$i}"]));
					$tr[6]->append('td', number_format($log["\$6\${$i}"]));
				}

			}, $day);
			$table->fieldset('渠道', '记录', '总计', ...range(1, $day));
			$table->header('数据统计 %s 月', $date);

			$table->bar->append('input', ['type' => 'month', 'value' => $date, 'onchange' => 'g({date:this.value||null})', 'style' => 'padding:1px']);
			$table->bar->append('button', ['按天数查看', 'onclick' => 'g({date:""})', 'style' => 'margin-left:.6rem']);

			$table->xml['class'] .= '-statistics';
			return;
		}
		$statistics = [
			'pv',
			'pv_ios',
			'pv_android',
			'uv',
			'uv_ios',
			'uv_android',
			'watch',
			'watch_ios',
			'watch_android',

			'dpv',
			'dpv_ios',
			'dpv_android',
			'dpc',
			'dpc_ios',
			'dpc_android',
			'signin',
			'signin_ios',
			'signin_android',
			'signup',
			'signup_ios',
			'signup_android',
			// 'recharge',
			// 'recharge_new',
			// 'recharge_old',
			// 'recharge_coin',
			// 'recharge_vip',
			// 'recharge_vip_new',
			// 'order',
			// 'order_ok',
			// 'order_ios',
			// 'order_ios_ok',
			// 'order_android',
			// 'order_android_ok'
		];
		if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $datefrom))
		{
			$datefrom = date('Y-m-01');
		}
		if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateto))
		{
			$dateto = date('Y-m-t');
		}
		
		$sum = [];
		$merge = [];
		for ($i = 0; $i < 24; ++$i)
		{
			$fields = [];
			foreach ($statistics as $field) {
				if ($i === 0)
				{
					$sum[] = "SUM(`{$field}`) `{$field}`";
				}
				$fields[] = "'{$field}'";
				$fields[] = sprintf('SUM(JSON_EXTRACT(hourdata,"$[%d].%s"))', $i, $field);
			}
			$merge[] = sprintf('JSON_OBJECT(%s)', join(',', $fields));
		}

		$cond = strlen($cid) === 4
			? ['SELECT cid,??,JSON_ARRAY(??) AS hourdata FROM recordlog WHERE date>=?s AND date<=?s AND cid=?s', join(',', $sum), join(',', $merge), $datefrom, $dateto, $cid]
			: ['SELECT cid,??,JSON_ARRAY(??) AS hourdata FROM recordlog WHERE date>=?s AND date<=?s??', join(',', $sum), join(',', $merge), $datefrom, $dateto, $cid === 'all' ? ' GROUP BY cid' : ''];

		$channels = $this->webapp->mysql->channels->column('name', 'hash');
		$table = $this->main->table($this->webapp->mysql(...$cond), function($table, $log, $statistics, $channels) use($cid)
		{
			$node = [$table->row()];

			$ceil = $table->cell(['rowspan' => 21]);
			$ceil->append('span', [$cid ? $channels[$log['cid']] ?? '未知渠道' : '总计', 'style' => 'font-size:.8rem']);
			$ceil->append('br');
			$ceil->append('span', $cid ? "[{$log['cid']}]" : '全部');
			$table->cell(['PV', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['UV', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['观看', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();


			$table->cell(['访问', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['点击', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['登录', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['新增', 'rowspan' => 3]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('苹果');
			$node[] = $table->row();
			$table->cell('安卓');
			$node[] = $table->row();
			$table->cell(['-', 'colspan' => 28]);
			// $table->cell(['充值', 'rowspan' => 6]);
			// $table->cell('总计');
			// $node[] = $table->row();
			// $table->cell('新充值');
			// $node[] = $table->row();
			// $table->cell('老充值');
			// $node[] = $table->row();
			// $table->cell('金币');
			// $node[] = $table->row();
			// $table->cell('VIP总');
			// $node[] = $table->row();
			// $table->cell('VIP新');
			// $node[] = $table->row();
			// $table->cell(['订单', 'rowspan' => 6]);
			// $table->cell('总计');
			// $node[] = $table->row();
			// $table->cell('成功');
			// $node[] = $table->row();
			// $table->cell('苹果总计');
			// $node[] = $table->row();
			// $table->cell('苹果成功');
			// $node[] = $table->row();
			// $table->cell('安卓总计');
			// $node[] = $table->row();
			// $table->cell('安卓成功');

			// $node[] = $table->row();
			// $table->cell(['计算', 'rowspan' => 2]);
			// $table->cell('新增ARPU');
			// $table->cell(sprintf('%.2f', $log['recharge_vip_new'] ? $log['recharge_new'] / $log['recharge_vip_new'] : 0));
			// $node[] = $table->row();
			// $table->cell('ARPU');
			// $table->cell(sprintf('%.2f', $log['recharge_vip_new'] ? $log['recharge'] / $log['recharge_vip_new'] : 0));



			$hourdata = json_decode($log['hourdata'], TRUE);
			foreach ($statistics as $i => $field)
			{
				$node[$i]->append('td', number_format($log[$field] ?? 0));
				foreach (range(0, 23) as $hour)
				{
					$node[$i]->append('td', number_format($hourdata[$hour][$field] ?? 0));
				}
			}
		}, $statistics, [base::cid => '官方渠道', ...$channels]);
		$table->xml['class'] .= '-statistics';
		$table->fieldset('渠道', '分类', '详细', '总计', ...range(0, 23));
		
		
		$table->header('数据统计');
		$table->bar->append('input', ['type' => 'date', 'value' => $datefrom, 'onchange' => 'g({datefrom:this.value||null})']);
		$table->bar->append('input', ['type' => 'date', 'value' => $dateto, 'onchange' => 'g({dateto:this.value||null})']);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $cid,
			'style' => 'padding:1px;width:8rem',
			'placeholder' => '渠道ID',
			'onkeydown' => 'event.keyCode==13&&g({cid:this.value||null})'
		]);
		$table->bar->append('button', $cid === 'all'
			? ['渠道总计', 'onclick' => 'g({cid:null})']
			: ['渠道分组', 'onclick' => 'g({cid:"all"})']);
		$table->bar->append('button', ['按整月查看', 'onclick' => 'g({date:null})']);
	}
	function patch_flush(string $data)
	{
		match ($data) {
			'ads' => $this->webapp->fetch_ads->flush(),
			'tags' => $this->webapp->fetch_tags->flush(),
			'subjects' => $this->webapp->fetch_subjects->flush()->cache(),
			'subjectvideos' => $this->webapp->get_subject_fetch('fore')
		};
		$this->json(['dialog' => 'Finished']);
	}
	//========广告========
	function ad_seats():array
	{
		return [
			0 => 'Pending'

		];
	}
	function form_ad(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset->append('img', ['style' => 'width:32rem;height:18rem;object-fit:contain']);

		$form->fieldset('Picture / Expire');
		$form->field('ad', 'file', ['accept' => 'image/*', 'onchange' => 'image_preview(this,document.querySelector("form>fieldset>img"))']);
		$form->field('expire', 'date', ['value' => date('Y-m-t')], fn($v, $i) => $i ? strtotime($v) : date('Y-m-d', $v));

		$form->fieldset('Seat / Weight / Name');
		$form->field('seat', 'select', ['options' => $this->ad_seats(), 'required' => NULL]);
		$form->field('weight', 'number', ['min' => 0, 'max' => 255, 'value' => 1, 'required' => NULL]);
		$form->field('name', 'text');

		$form->fieldset('URL');
		$form->field('acturl', 'text', [
			'placeholder' => 'JavaScript or URL',
			'value' => 'javascript:;',
			'maxlength' => 255,
			'style' => 'width:40rem',
			'required' => NULL
		]);

		$form->fieldset();
		$form->button('Submit', 'submit');

		return $form;
	}
	function get_ads(int $page = 1)
	{
		$conds = [[]];
		if (strlen($seat = $this->webapp->query['seat'] ?? ''))
		{
			$conds[0][] = 'seat=?i';
			$conds[] = $seat;
		}
		if ($display = $this->webapp->query['display'] ?? '')
		{
			$conds[0][] = 'display=?s';
			$conds[] = $display;
		}
		$conds[0] = sprintf('%sORDER BY seat ASC,weight DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->ads(...$conds)->paging($page), function($table, $value, $seats, $seat)
		{
			$table->row()['style'] = 'background-color:var(--webapp-hint)';
			$table->cell()->append('a', ['Delete this AD', 'href' => "?console/ad,seat:{$seat},hash:{$value['hash']}", 'data-method' => 'delete', 'data-bind' => 'click']);
			$table->cell(['colspan' => 6])->append('a', ['Change this AD', 'href' => "?console/ad-update,hash:{$value['hash']}"]);

			$table->row();
			$table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144', 'class' => 'cover'])
				->append('img', ['loading' => 'lazy', 'src' => $value['change'] === 'sync'
					? '/webapp/res/ps/loading.svg'
					: "{$this->webapp->origin}/news/{$value['hash']}?{$value['ctime']}"]);

			$table->row();
			$table->cell('HASH');
			$table->cell($value['hash']);
			$table->cell('Create');
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell('Change');
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));

			$table->row();
			$table->cell('Seat');
			$table->cell($seats[$value['seat']] ?? NULL);
			$table->cell('Weight');
			$table->cell($value['weight']);
			$table->cell('Expire');
			$table->cell([date('Y-m-d\\TH:i:s', $value['expire']),
				'style' => $value['expire'] > $this->webapp->time ? 'color:green' : 'color:red']);

			$table->row();
			$table->cell('Name');
			$table->cell($value['name']);
			$table->cell('View');
			$table->cell(number_format($value['view']));
			$table->cell('Click');
			$table->cell(number_format($value['click']));

			$table->row();
			$table->cell('URL');
			$table->cell(['colspan' => 5])->append('a', [$value['acturl'], 'href' => $value['acturl']]);



		}, $seats = $this->ad_seats(), $seat);
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset(1, 2, 3);
		$table->header('Found %d item', $table->count());
		unset($table->xml->tbody->tr[0]);

		$table->bar->append('button', ['Post AD', 'onclick' => 'location.href="?console/ad-insert"']);
		$table->bar->select(['' => 'All Seat'] + $seats)
			->setattr(['onchange' => 'g({seat:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($seat);

		$table->bar->append('button', ['Flush AD',
			'data-src' => '?console/flush,data:ads',
			'data-method' => 'patch',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);
	}
	function get_ad_insert()
	{
		$this->form_ad($this->main);
	}
	function post_ad_insert()
	{
		$hash = $this->webapp->random_hash(FALSE);
		if (count($uploadedfile = $this->webapp->request_uploadedfile('ad'))
			&& $this->form_ad()->fetch($ad) && $this->webapp->mysql->ads->insert([
				'hash' => $hash,
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time,
				'view' => 0,
				'click' => 0,
				'change' => 'sync'] + $ad)
			&& $uploadedfile->move("{$this->webapp['ad_savedir']}/{$hash}")) {
			$this->webapp->response_location("?console/ads,seat:{$ad['seat']}");
		}
		else
		{
			$this->main->append('h4', 'AD Insert failed!');
		}
	}
	function delete_ad(string $hash, string $seat = NULL)
	{
		return $this->dialog('Not support delete');
		$this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->delete() === 1
			? $this->goto("/ads,seat:{$seat}")
			: $this->dialog('AD Delete failed!');
	}
	function get_ad_update(string $hash)
	{
		if ($this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->fetch($ad))
		{
			$form = $this->form_ad($this->main);
			$form->xml->fieldset->img['src'] = $ad['change'] === 'none'
				? "{$this->webapp->origin}/news/{$ad['hash']}?{$ad['ctime']}"
				: '/webapp/res/ps/loading.svg';
			$form->echo($ad);
		}
	}
	function post_ad_update(string $hash)
	{
		if ($this->form_ad()->fetch($ad))
		{
			if (count($uploadedfile = $this->webapp->request_uploadedfile('ad'))
				&& $uploadedfile->move("{$this->webapp['ad_savedir']}/{$hash}")) {
				$ad['change'] = 'sync';
			}
			else
			{
				$ad['ctime'] = $this->webapp->time;
			}
			if ($this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->update($ad))
			{
				$this->webapp->response_location("?console/ads,seat:{$ad['seat']}");
			}
			return 200;
		}
		$this->main->append('h4', 'AD Update failed!');
	}
	//========标签========
	const tags_level = [
		0 => 'Classify',
		1 => 'Globals',
		2 => 'Pending',
		3 => 'Extends',
		4 => 'Append Trait',
		5 => 'Role Types',
		6 => 'Body Trait',
		7 => 'Location',
		8 => 'Clothing',
		9 => 'Else mixed',
		10 => 'Channels',
		11 => 'Actors',
		12 => 'Temporary'
	];
	function tag_types():array
	{
		return $this->webapp->mysql->tags('WHERE phash IS NULL ORDER BY sort DESC,hash ASC')->column('name', 'hash');
	}
	function form_tag(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('Classify / Tagname（Use "," to separate） / Sort（Max to top）');
		$form->field('level', 'select', ['options' => static::tags_level, 'required' => NULL]);
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset();
		$form->button('Submit', 'submit');
		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function get_tags(string $search = NULL, int $page = 1)
	{
		$conds = [[]];
		if (is_string($search))
		{
			$search = urldecode($search);
			if (strlen($search) === 4 && trim($search, webapp::key) === '')
			{
				$conds[0][] = 'hash=?s';
				$conds[] = $search;
			}
			else
			{
				$conds[0][] = 'name LIKE ?s';
				$conds[] = "%{$search}%";
			}
		}
		if (strlen($level = $this->webapp->query['level'] ?? ''))
		{
			$conds[0][] = 'level=?i';
			$conds[] = $level;
		}
		$conds[0] = sprintf('%sORDER BY level ASC,sort DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');

		$table = $this->main->table($this->webapp->mysql->tags(...$conds)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell()->append('a', ['Delete', 'href' => "?console/tag,hash:{$value['hash']}", 'data-method' => 'delete', 'data-bind' => 'click']);
			$table->cell(date('Y-m-d\\TH:i:s', $value['time']));
			$table->cell($value['hash']);
			$table->cell(number_format($value['click']));
			$table->cell(static::tags_level[$value['level']]);
			$table->cell($value['sort']);
			$table->cell()->append('a', [$value['name'], 'href' => "?console/tag,hash:{$value['hash']}"]);
			
		});
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('Delete', 'Create', 'HASH', 'Click', 'Type', 'Sort', 'Name');
		$table->header('Found %d item', $table->count());
		$table->bar->append('button', ['Append tag or classify', 'onclick' => 'location.href="?console/tag"']);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px',
			'placeholder' => 'Type keyword',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->append('span', ['style' => 'margin:0 .6rem'])
			->select(['' => 'All Type'] + static::tags_level)
			->setattr(['onchange' => 'g({level:this.value||null})', 'style' => 'padding:.1rem'])->selected($level);
		$table->bar->append('button', ['Flush Tag',
			'data-src' => '?console/flush,data:tags',
			'data-method' => 'patch',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);
	}
	function get_tag(string $hash = NULL)
	{
		$form = $this->form_tag($this->main);
		if (is_string($hash) && $this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->fetch($tag))
		{
			$form->xml['method'] = 'patch';
			$form->echo($tag);
		}
	}
	function post_tag()
	{
		if ($this->form_tag()->fetch($tag) && $this->webapp->mysql->tags->insert([
				'hash' => substr($this->webapp->random_hash(TRUE), -4),
				'time' => $this->webapp->time,
				'click' => 0] + $tag)) {
			$this->webapp->fetch_tags->flush();
			$this->goto('/tags');
		}
		else
		{
			$this->dialog('Tag insert failed!');
		}
	}
	function delete_tag(string $hash)
	{
		$this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->delete() === 1 && $this->webapp->fetch_tags->flush()
			? $this->goto('/tags')
			: $this->dialog('Tag delete failed!');
	}
	function patch_tag(string $hash)
	{
		if ($this->form_tag()->fetch($tag)
			&& $this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->update($tag)) {
			$this->webapp->fetch_tags->flush();
			$this->goto('/tags');
		}
		else
		{
			$this->dialog('Tag update failed!');
		}
	}

	//========视频========
	const video_sync = [
		'waiting' => 'Waiting',
		'slicing' => 'Slicing',
		'exception' => 'Exception',
		'finished' => 'Finished',
		'allow' => 'Allow (show)',
		'deny' => 'Deny (drop)'], video_type = ['h' => 'H', 'v' => 'V'];
	function get_videos(string $search = NULL, int $page = 1)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$conds = [[]];
		if (is_string($search))
		{
			$search = urldecode($search);
			if (strlen($search) === 12 && trim($search, webapp::key) === '')
			{
				$conds[0][] = 'hash=?s';
				$conds[] = $search;
			}
			else
			{
				$conds[0][] = 'name LIKE ?s';
				$conds[] = "%{$search}%";
			}
		}
		if ($userid = $this->webapp->query['userid'] ?? '')
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}
		if ($tag = $this->webapp->query['tag'] ?? '')
		{
			$tagsearch = explode('.', $tag);
			foreach ($tagsearch as $taghash)
			{
				$conds[0][] = 'FIND_IN_SET(?s,tags)';
				$conds[] = $taghash;
			}
		}
		else
		{
			$tagsearch = [];
		}
		if ($sync = $this->webapp->query['sync'] ?? '')
		{
			switch ($sync)
			{
				case 'uploading':
					$conds[0][] = 'sync="waiting" AND tell<size';
					break;
				case 'waiting':
					$conds[0][] = 'sync="waiting" AND tell>=size';
					break;
				default:
					$conds[0][] = 'sync=?s';
					$conds[] = $sync;
			}
		}
		if ($require = $this->webapp->query['require'] ?? '')
		{
			$conds[0][] = sprintf('`require`%s', match ($require)
			{
				'vip' => '=-1',
				'free' => '=0',
				'coin' => '>0',
				default => '=' . intval($require)
			});
		}
		if ($type = $this->webapp->query['type'] ?? '')
		{
			if ($type === 'notclassify')
			{
				foreach ($this->webapp->mysql->tags('WHERE level=0') as $classify)
				{
					$conds[0][] = 'FIND_IN_SET(?s,tags)=0';
					$conds[] = $classify['hash'];
				}
				$conds[0][] = 'FIND_IN_SET("lQr8",tags)=0';
				$conds[0][] = 'FIND_IN_SET("5IFq",tags)=0';
				$conds[0][] = 'FIND_IN_SET("K3yp",tags)=0';
			}
			else
			{
				$conds[0][] = 'type=?s';
				$conds[] = $type;
			}
		}

		if ($subject = $this->webapp->query['subject'] ?? '')
		{
			$conds[0][] = 'FIND_IN_SET(?s,subjects)';
			$conds[] = $subject;
		}
		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY ' . match ($sort = $this->webapp->query['sort'] ?? '')
		{
			'ctime-desc' => 'ctime DESC',
			'view-desc' => '`view` DESC',
			'like-desc' => '`like` DESC',
			'sales-desc' => '`sales` DESC',
			default => '`mtime` DESC'
		} . ',hash ASC';
		$tags = $this->webapp->fetch_tags->shortname(LOCALE);
		$table = $this->main->table($this->webapp->mysql->videos(...$conds)->paging($page, 10), function($table, $value, $tags, $origin)
		{
			$ym = date('ym', $value['mtime']);

			$table->row()['class'] = 'info';
			$table->cell()->append('a', ["UID：{$value['userid']}", 'href' => "?console/videos,userid:{$value['userid']}"]);
			$table->cell(['colspan' => 8])->append('a', [sprintf('Uptime: %s, Changetime: %s',
				date('Y-m-d\\TH:i:s', $value['mtime']),
				date('Y-m-d\\TH:i:s', $value['ctime'])
			), 'href' => "?console/video,hash:{$value['hash']}"]);

			$table->row();
			$cover = $table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144', 'class' => 'cover']);

			$table->row();
			$table->cell('HASH');
			$table->cell($value['hash']);
			$table->cell('Type');
			$table->cell(static::video_type[$value['type']]);
			$table->cell('Duration');
			$table->cell(base::format_duration($value['duration']));
			$table->cell('Require');
			$table->cell(match (intval($value['require']))
			{
				-2 => 'Drop', -1 => 'Member', 0 => 'Free',
				default => "{$value['require']} Coin"
			});

			$table->row();
			$table->cell('State');
			$syncnode = $table->cell();
			$syncnode->append('span', $value['sync'] === 'waiting' && $value['tell'] < $value['size']
				? 'Uploading' : static::video_sync[$value['sync']]);
			$anchors = [];
			if ($value['sync'] !== 'exception')
			{
				$anchors[] = ['Exception',
					'href' => "?console/video,hash:{$value['hash']},sync:exception",
					'style' => 'color:maroon',
					'data-method' => 'patch',
					'data-dialog' => 'Can\'t undo',
					'data-bind' => 'click'
				];
			}
			if (in_array($value['sync'], ['finished', 'allow'], TRUE))
			{
				$anchors[] = ['Deny',
					'href' => "?console/video,hash:{$value['hash']},sync:deny",
					'style' => 'color:maroon',
					'data-method' => 'patch',
					'data-bind' => 'click'
				];
			}
			if (in_array($value['sync'], ['finished', 'deny'], TRUE))
			{
				$anchors[] = ['Allow',
					'href' => "?console/video,hash:{$value['hash']},sync:allow",
					'data-method' => 'patch',
					'data-bind' => 'click'
				];
			}
			foreach ($anchors as $anchor)
			{
				$syncnode->append('span', ' | ');
				$syncnode->append('a', $anchor);
			}

			$table->cell('View');
			$table->cell(number_format($value['view']));
			$table->cell('Like');
			$table->cell(number_format($value['like']));
			$table->cell('Sale');
			$table->cell(number_format($value['sales']));

			$table->row();
			$table->cell('Tags');
			$tagnode = $table->cell(['colspan' => 7, 'class' => 'tags'])->append('div');
			foreach ($value['tags'] ? explode(',', $value['tags']) : [] as $tag)
			{
				if (isset($tags[$tag]))
				{
					$tagnode->append('a', [$tags[$tag], 'href' => "?console/videos,search:{$tag}"]);
				}
			}

			$table->row();
			$table->cell('Name');
			$title = $table->cell(['colspan' => 7, 'class' => 'name'])->append('a', [htmlentities($value['name']), 'href' => "javascript:;"]);

			if ($value['cover'] === 'finish' && in_array($value['sync'], ['finished', 'allow', 'deny'] ,TRUE))
			{
				$cover->append('img', [
					'loading' => 'lazy',
					'src' => $cover = "{$origin}/{$ym}/{$value['hash']}/cover.jpg?{$value['ctime']}",
					'id' => "v{$value['hash']}",
					'data-cover' => $cover,
					'data-playm3u8' => "{$origin}/{$ym}/{$value['hash']}/play.m3u8",
					'onclick' => "view_video(this.dataset, {$value['preview']})",
					'style' => 'object-fit: contain;'
				]);
				$title['onclick'] = "view_video(document.querySelector('img#v{$value['hash']}').dataset)";
			}
			else
			{
				$cover->append('img', ['src' => '/webapp/res/ps/loading.svg']);
			}

		}, $tags, $this->webapp->origin);



		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset(0, 1);
		$table->header('Found %d item', $table->count());
		unset($table->xml->tbody->tr[0]);

		// $table->bar->append('input', [
		// 	'type' => 'search',
		// 	'value' => $userid,
		// 	'style' => 'padding:2px;width:8rem',
		// 	'placeholder' => '用户ID',
		// 	'onkeydown' => 'event.keyCode==13&&g({userid:this.value||null,page:null})'
		// ]);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $tag,
			'style' => 'padding:2px;width:8rem',
			'placeholder' => 'Tag use “.” span',
			'onkeydown' => 'event.keyCode==13&&g({tag:this.value||null,page:null})'
		]);
		$taglevels = $this->webapp->fetch_tags->levels();
		
		$table->bar->select(['' => 'Tag1', ...$taglevels])
			->setattr(['onchange' => 'g({tag:this.value||null})', 'style' => 'margin-left:.6rem;width:6rem;padding:2px'])
			->selected($tagsearch[0] ?? NULL);
		$table->bar->select(['' => 'Tag2', ...$taglevels])
			->setattr(['onchange' => 'g({tag:this.value?(this.previousElementSibling.value?`${this.previousElementSibling.value}.${this.value}`:this.value):this.previousElementSibling.value})', 'style' => 'margin-left:.6rem;width:6rem;padding:2px'])
			->selected($tagsearch[1] ?? NULL);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px;width:16rem',
			'placeholder' => 'Video HASH or keyword',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->select(['' => 'All State', 'uploading' => 'Uploading'] + static::video_sync)
			->setattr(['onchange' => 'g({sync:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($sync);
		// $table->bar->select(['' => '要求', 'vip' => '会员', 'free' => '免费', 'coin' => '金币'])
		// 	->setattr(['onchange' => 'g({require:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
		// 	->selected($require);
		$table->bar->select(['' => 'All Type', 'notclassify' => '没有分类'] + static::video_type)
			->setattr(['onchange' => 'g({type:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($type);
		$table->bar->select(['' => 'Into ▾',
			'ctime-desc' => 'Change ▾',
			'view-desc' => 'View ▴',
			'like-desc' => 'Like ▾',
			'sales-desc' => 'Sale ▾'])
			->setattr(['onchange' => 'g({sort:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($sort);
		// $table->bar->append('button', ['所有完成视频通过审核',
		// 	'style' => 'margin-left:.6rem',
		// 	'data-src' => '?console/video-all-finished-to-allow',
		// 	'data-method' => 'patch',
		// 	'data-bind' => 'click'
		// ]);
		$table->bar->append('button', ['Clear exception',
			'style' => 'margin-left:.6rem',
			'onclick' => 'location.href="?console/video-exception-clear"'
		]);
		$table->bar->append('button', ['Flush cache',
			'data-src' => '?console/flush,data:subjectvideos',
			'data-method' => 'patch',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);
		$table->bar['style'] = 'white-space:nowrap';
	}
	function form_video(webapp_html $ctx = NULL, array $video = []):webapp_form
	{
		$form = new webapp_form($ctx ?? $this->webapp);
		$play = $form->fieldset->append('webapp-video', [
			'muted' => NULL,
			'controls' => NULL,
			'oncanplay' => 'this.firstElementChild.style.objectFit=this.height>this.width?"contain":"cover"',
			'style' => 'width:600px;height:320px'
		]);

		$picture = $form->fieldset()->append('div', ['class' => 'picture']);
		//$form->field('picture', 'radio');

		//$change = $form->fieldset()->append('input', ['type' => 'file', 'accept' => 'image/*']);

		//$cover = $form->fieldset->append('img', ['style' => 'width:512px;height:288px']);
		// $change = $form->fieldset()->append('input', ['type' => 'file', 'accept' => 'image/*',
		// 	'onchange' => 'video_cover(this,document.querySelector("div.cover"))']);

		$form->fieldset('Name');
		$form->field('name', 'textarea', ['style' => 'width:60rem', 'rows' => 3, 'required' => NULL]);

		$form->fieldset('Extdata');
		$form->field('issue', 'text', ['placeholder' => 'Issue']);
		$form->field('actor', 'text', ['placeholder' => 'Actor']);
		$form->field('publisher', 'text', ['placeholder' => 'Publisher']);
		$form->field('director', 'text', ['placeholder' => 'Director']);
		$form->field('series', 'text', ['placeholder' => 'Series']);
		$form->field('actress', 'text', ['placeholder' => 'Actress']);
		$form->fieldset();

		// $form->fieldset('视频类型 / 预览时段 / 排序 / 下架：-2、会员：-1、免费：0、金币 / 定时发布日期');
		// $form->field('type', 'select', ['options' => base::video_type]);
		// function preview_format($v, $i)
		// {
		// 	if ($i)
		// 	{
		// 		$t = explode(':', $v);
		// 		return $t[0] * 60 * 60 + $t[1] * 60 + $t[2];
		// 	}
		// 	return base::format_duration($v);
		// }
		// $form->field('preview_start', 'time', ['value' => '00:00:00', 'step' => 1], preview_format(...));
		// $form->field('preview_end', 'time', ['value' => '00:00:10', 'step' => 1], preview_format(...));
		// $form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'style' => 'width:4rem', 'required' => NULL]);
		// $form->field('require', 'number', [
		// 	'value' => 0,
		// 	'min' => -2,
		// 	'style' => 'width:13rem',
		// 	'placeholder' => '要求',
		// 	'required' => NULL
		// ]);
		// $form->field('ptime', 'datetime-local', format:fn($v, $i) => $i ? strtotime($v) : date('Y-m-d\\TH:i', $v));

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
		$form->fieldset()['style'] = join(';', [
			'position: fixed',
			'bottom: 2rem',
			'right: 2rem',
			'padding: .6rem',
			'border-radius: .4rem',
			'background-color: rgba(0,0,0,.4)'
		]);
		$form->field('ctime', 'select', ['options' => [
			'no' => 'Skip change time',
			'yes' => 'Update change time'
		]]);
		$form->button('Submit', 'submit')['style'] = 'font-size: 2rem';
		if ($form->echo && $video)
		{
			$ym = date('ym', $video['mtime']);
			$play['data-poster'] = $video['cover'] === 'finish' && in_array($video['sync'], ['finished','allow','deny'], TRUE)
				? "{$this->webapp->origin}/{$ym}/{$video['hash']}/cover.jpg?{$video['ctime']}"
				: '/webapp/res/ps/loading.svg';
			$play['data-m3u8'] = "{$this->webapp->origin}/{$ym}/{$video['hash']}/play.m3u8";

			$url = "{$this->webapp->origin}/{$ym}/{$video['hash']}/picture";
			$res = $this->webapp->open("{$url}/index.txt");
			if ($res->status() === 200)
			{
				$pics = array_filter(explode("\n", $res->content()), fn($v) => strpos($v, '.jpg'));
				if (count($pics) > 2)
				{
					$pics = array_slice($pics, 1, -1);
				}
				foreach ($pics as $pic)
				{
					$picture->labelinput('picture', 'radio', $pic)->append('img', ['src' => "{$url}/{$pic}"]);
				}
			}

			$form->echo($video['extdata'] ? $video + json_decode($video['extdata'], TRUE) : $video);
			$form->xml->append('script', 'document.querySelectorAll("ul.restag>li>label").forEach(label=>(label.onclick=()=>label.className=label.firstElementChild.checked?"checked":"")());');
		}
		return $form;
	}
	function post_video(string $hash)
	{
		if ($this->form_video()->fetch($video, $error))
		{
			$updata = [
				'name' => $video['name'],
				'tags' => $video['tags'],
				'extdata' => [
					'issue' => $video['issue'],
					'actor' => $video['actor'],
					'publisher' => $video['publisher'],
					'director' => $video['director'],
					'series' => $video['series'],
					'actress' => $video['actress']
				]
			];
			$picture = $this->webapp->request_content()['picture'] ?? NULL;
			if ($picture)
			{
				$updata['extdata']['picture'] = $picture;
				$updata['cover'] = 'change';
			}
			$updata['extdata'] = json_encode($updata['extdata'], JSON_UNESCAPED_UNICODE);
			if ($this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update($updata))
			{
				$this->webapp->response_location('?console/videos');
				return 200;
			}
			$this->main->append('h4', 'Video Update failed!');
		}
	}
	function get_video(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		if ($this->webapp->mysql->videos('WHERE hash=?s LIMIT 1', $hash)->fetch($video))
		{
			$form = $this->form_video($this->main, $video);

			
		}
	}
	function patch_video(string $hash, string $sync)
	{
		in_array($sync, ['exception', 'allow', 'deny', TRUE])
			&& $this->webapp->mysql->videos('WHERE hash=?s AND sync!="exception" LIMIT 1', $hash)
				->update('sync=?s,ctime=?i', $sync, $this->webapp->time) === 1 ? $this->goto() : $this->dialog('状态更新失败！');
	}
	function patch_video_all_finished_to_allow()
	{
		if ($this->admin)
		{
			$count = $this->webapp->mysql->videos('WHERE sync="finished"')->update('sync="allow",ctime=?i', $this->webapp->time);
			$this->dialog("总共 {$count} 个视频通过审核！");
			$this->goto();
		}
		else
		{
			$this->dialog('需要超级管理员权限！');
		}
	}
	function get_video_exception_clear()
	{
		$form = $this->main->form();
		
		$form->fieldset->text('请在服务端执行下列指令');
		$form->fieldset();
		$command = $form->field('command', 'textarea', [
			'style' => 'font:.8rem var(--webapp-font-monospace)',
			'rows' => 45,
			'cols' => 90,
			'readonly' => NULL]);
		$form->fieldset();
		$form->button('我已在服务端执行上述指令，确定执行删除数据！', 'submit');
		$form->xml['data-bind'] = 'submit';

		foreach ($this->webapp->mysql->videos('WHERE sync="exception"') as $video)
		{
			$command->text(sprintf("RD /S /Q \"%s\"\nRD /S /Q \"%s\"\n",
				$this->webapp->path_video(FALSE, $video),
				$this->webapp->path_video(TRUE, $video)));
		}
		$command->text('PAUSE');
	}
	function post_video_exception_clear()
	{
		if ($this->admin
			&& is_array($data = $this->webapp->request_content())
			&& isset($data['command'])
			&& is_string($data['command'])
			&& preg_match_all('/[\w\-]{12}/', $data['command'], $pattern)) {
			$count = $this->webapp->mysql->videos('WHERE hash IN(?S)', array_unique($pattern[0]))->delete();
			$this->dialog("删除 {$count} 个数据！");
			$this->goto();
		}
		else
		{
			$this->dialog('需要超级管理员权限！');
		}
	}

	//配置
	function form_configs(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('落地页地址（切勿加上渠道码）');
		$form->field('down_page', 'url', ['style' => 'width:30rem', 'required' => NULL]);

		$form->fieldset('对外H5域名');
		$form->field('h5_domain', 'url', ['style' => 'width:30rem', 'required' => NULL]);

		$form->fieldset('公告标题');
		$form->field('notice_title', 'text', ['style' => 'width:30rem', 'placeholder' => '标题为空关闭公告']);
		$form->fieldset('公告内容');
		$form->field('notice_content', 'textarea', ['rows' => 10, 'cols' => 50]);

		$form->fieldset('游戏列表');
		$form->field('game_entry', 'textarea', ['rows' => 10, 'cols' => 50]);

		$form->fieldset();
		$form->button('更新后台配置', 'submit');
		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	// function get_configs()
	// {
	// 	$this->form_configs($this->main)->echo($this->webapp->fetch_configs());
	// }
	// function post_configs()
	// {
	// 	if ($this->form_configs()->fetch($configs))
	// 	{
	// 		foreach ($configs as $key => $value)
	// 		{
	// 			$this->webapp->mysql->configs('WHERE `key`=?s LIMIT 1', $key)->update('`value`=?s', $value)
	// 				|| $this->webapp->mysql->configs->insert(['key' => $key, 'value' => $value]);
	// 		}
	// 	}
	// 	$this->webapp->clear_configs();
	// 	$this->goto('/configs');
	// }

	// function get_reports(string $search = NULL,int $page = 0)
	// {
	// 	$conds = [[]];
	// 	if ($search)
	// 	{
	// 		if (strlen($search) === 10 && trim($search, webapp::key) === '')
	// 		{
	// 			$conds[0][] = 'userid=?s';
	// 			$conds[] = $search;
	// 		}
	// 		else
	// 		{
	// 			$search = urldecode($search);
	// 			$conds[0][] = 'question LIKE ?s';
	// 			$conds[] = "%{$search}%";
	// 		}
	// 	}
	// 	if ($clientip = $this->webapp->query['clientip'] ?? '')
	// 	{
	// 		$conds[0][] = 'clientip=?s';
	// 		$conds[] = $clientip;
	// 	}
	// 	if ($promise = $this->webapp->query['promise'] ?? '')
	// 	{
	// 		$conds[0][] = 'promise=?s';
	// 		$conds[] = $promise;
	// 	}

	// 	$conds[0] = sprintf('%sORDER BY time DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
	// 	$table = $this->main->table($this->webapp->mysql->reports(...$conds)->paging($page), function($table, $value)
	// 	{
	// 		$table->row();
	// 		$table->cell(date('Y-m-d\\TH:i:s', $value['time']));
	// 		$table->cell($value['hash']);
	// 		$table->cell()->append('a', [$value['userid'], 'href' => "?console/reports,search:{$value['userid']}"]);
	// 		$table->cell()->append('a', [$this->webapp->hexip($value['clientip']), 'href' => "?console/reports,clientip:{$value['clientip']}"]);

	// 		///$table->cell($value['promise']);
	// 		$cell = $table->cell();
	// 		if ($value['promise'] === 'pending')
	// 		{
	// 			$cell->append('a', ['解决',
	// 				'href' => "?console/report,promise:resolve,hash:{$value['hash']}",
	// 				'data-method' => 'patch',
	// 				'data-dialog' => '{"reply":"textarea"}',
	// 				'data-bind' => 'click']);

	// 			$cell->append('span', ' | ');

	// 			$cell->append('a', ['拒绝',
	// 				'href' => "?console/report,promise:reject,hash:{$value['hash']}",
	// 				'data-method' => 'patch',
	// 				'data-dialog' => '{"reply":"textarea"}',
	// 				'data-bind' => 'click']);
	// 		}
	// 		else
	// 		{
	// 			$cell->text(['resolve' => '已解决', 'reject' => '已拒绝'][$value['promise']]);
	// 		}


	// 		$table->row();
	// 		$cell = $table->cell(['colspan' => 5]);
	// 		$cell->append('pre', [$value['question'], 'style' => 'margin:0']);
	// 		if ($value['reply'])
	// 		{
	// 			$cell->append('hr');
	// 			$cell->append('pre', [$value['reply'], 'style' => 'margin:0']);
	// 		}
	// 	});
	// 	$table->fieldset('时间', 'HASH', '用户ID', 'IP', '状态 | 回复');
	// 	$table->header('问题汇报 %d 项', $table->count());
	// 	$table->paging($this->webapp->at(['page' => '']));

	// 	$table->bar->append('input', [
	// 		'type' => 'search',
	// 		'value' => $search,
	// 		'style' => 'padding:2px;width:21rem',
	// 		'placeholder' => '用户ID或问题描述',
	// 		'onkeydown' => 'event.keyCode==13&&g({search:this.value||null,page:null})'
	// 	]);

	// 	$table->bar->select(['' => '全部', 'pending' => '待办的', 'resolve' => '已解决', 'reject' => '已拒绝'])
	// 		->setattr(['onchange' => 'g({promise:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
	// 		->selected($promise);

	// }
	// function patch_report(string $hash, string $promise)
	// {
	// 	$this->webapp->mysql->reports('WHERE hash=?s AND promise="pending" LIMIT 1', $hash)->update([
	// 	 	'promise' => $promise, 'reply' => $this->webapp->request_content()['reply'] ?? '']) === 1
	// 			? $this->goto() : $this->dialog('回复失败！');
	// }

}