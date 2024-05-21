<?php
class webapp_router_control extends webapp_echo_masker
{
	private readonly bool $admin;
	private readonly ?string $uid;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Control');
		if ($this->initiated || isset($this->admin) === FALSE)
		{
			$this->admin = FALSE;
			$this->uid = NULL;
			return;
		}

		$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/star/base.css']);
		$this->link_resources($webapp['app_resources']);
		$this->script(['src' => '/webapp/app/star/base.js?v=w']);
		$this->nav([
			['数据', '?control/home'],
			
			['广告', '?control/ads'],
			['渠道', '?control/channels'],
			['分类 & 标签', '?control/tags'],
			['专题', '?control/subjects'],
			//['上传账号', '?control/uploaders'],
			['用户', '?control/users'],
			['视频', '?control/videos'],
			//['产品', '?control/prods'],
			
			['记录', [
				['站点配置', '?control/configs'],
				['问题汇报', '?control/reports'],
				// ['上传的图片', '?control/images'],
				// ['购买影片', '?control/record-video'],
				// ['余额提现', '?control/record-exchange-balance'],
				// ['游戏提现', '?control/record-exchange-game'],
				// ['充值', '?control/record-recharge']
			]],
			//['评论', '?control/comments'],
			
			
			
			['注销登录', "javascript:masker.authorization(null).then(()=>location.replace(location.href));", 'style' => 'color:maroon']
		]);
	}
	function authorization($uid, $pwd):array
	{
		if ($uid === $this->webapp['admin_username'] && $pwd === $this->webapp['admin_password'])
		{
			$this->admin = TRUE;
			return [$this->uid = $uid, $pwd];
		}
		if (array_key_exists($uid, $this->webapp['admin_users']) && $pwd === $this->webapp['admin_users'][$uid])
		{
			$this->admin = FALSE;
			return [$this->uid = $uid, $pwd];
		}
		return [];
	}
	function goto(string $url = NULL):void
	{
		$this->json(['goto' => $url === NULL ? NULL : "?control{$url}"]);
	}
	function dialog(string $msg):void
	{
		$this->json(['dialog' => $msg]);
	}
	// function get_splashscreen()
	// {
	// 	$this->script('setTimeout(()=>postMessage("close"), 5000)');
	// 	$this->xml['style'] = $this->xml->body['style'] = 'background:red;height:100%';
	// 	$this->main->append('h1', 'get_splashscreen');
	// }
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
			'subjects' => $this->webapp->fetch_subjects->flush()->cache(),
			'subjectvideos' => $this->webapp->get_subject_fetch('fore')
		};
		$this->json(['dialog' => '刷新完成！']);
	}
	//========广告========
	function ad_seats():array
	{
		return [
			0 => '开屏广告（全屏幕）',
			1 => '首次弹窗（半屏幕）',
			2 => '首页轮播 21:9',
			3 => '播放视频 16:9',
			4 => '滑动视频（全屏幕）',
			
			// 3 => '游戏轮播',
			// 4 => '社区轮播',
			// 5 => '个人中心',
			// 6 => '弹窗广告',

			9 => '导航图标 1:1'
		];
	}
	function form_ad(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset->append('img', ['style' => 'width:32rem;height:18rem;object-fit:contain']);

		$form->fieldset('广告图片 / 过期时间');
		$form->field('ad', 'file', ['accept' => 'image/*', 'onchange' => 'image_preview(this,document.querySelector("form>fieldset>img"))']);
		$form->field('expire', 'date', ['value' => date('Y-m-t')], fn($v, $i) => $i ? strtotime($v) : date('Y-m-d', $v));

		$form->fieldset('展示位置 / 权重（越大越几率越大） / 名称');
		$form->field('seat', 'select', ['options' => $this->ad_seats(), 'required' => NULL]);
		$form->field('weight', 'number', ['min' => 0, 'max' => 255, 'value' => 1, 'required' => NULL]);
		$form->field('name', 'text');

		$form->fieldset('行为URL');
		$form->field('acturl', 'text', [
			'placeholder' => 'javascript 或者 url',
			'value' => 'javascript:;',
			'maxlength' => 255,
			'style' => 'width:40rem',
			'required' => NULL
		]);

		$form->fieldset();
		$form->button('提交', 'submit');

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
			$table->cell()->append('a', ['删除下面广告', 'href' => "?control/ad,seat:{$seat},hash:{$value['hash']}", 'data-method' => 'delete', 'data-bind' => 'click']);
			$table->cell(['colspan' => 6])->append('a', ['修改下面信息', 'href' => "?control/ad-update,hash:{$value['hash']}"]);

			$table->row();
			$table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144', 'class' => 'cover'])
				->append('img', ['loading' => 'lazy', 'src' => $value['change'] === 'sync'
					? '/webapp/res/ps/loading.svg'
					: "?/news/{$value['hash']}?mask{$value['ctime']}"]);

			$table->row();
			$table->cell('HASH');
			$table->cell($value['hash']);
			$table->cell('创建时间');
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell('修改时间');
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));

			$table->row();
			$table->cell('位置');
			$table->cell($seats[$value['seat']] ?? NULL);
			$table->cell('展示权重');
			$table->cell($value['weight']);
			$table->cell('过期时间');
			$table->cell([date('Y-m-d\\TH:i:s', $value['expire']),
				'style' => $value['expire'] > $this->webapp->time ? 'color:green' : 'color:red']);

			$table->row();
			$table->cell('名称');
			$table->cell($value['name']);
			$table->cell('展示次数');
			$table->cell(number_format($value['view']));
			$table->cell('点击次数');
			$table->cell(number_format($value['click']));

			$table->row();
			$table->cell('URL');
			$table->cell(['colspan' => 5])->append('a', [$value['acturl'], 'href' => $value['acturl']]);



		}, $seats = $this->ad_seats(), $seat);
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('封面', '字段', '信息');
		$table->header('广告 %d 项', $table->count());
		unset($table->xml->tbody->tr[0]);

		$table->bar->append('button', ['添加广告', 'onclick' => 'location.href="?control/ad-insert"']);
		$table->bar->select(['' => '所有位置'] + $seats)
			->setattr(['onchange' => 'g({seat:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($seat);

		$table->bar->append('button', ['刷新前端广告缓存',
			'data-src' => '?control/flush,data:ads',
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
			&& $uploadedfile->maskfile("{$this->webapp['ad_savedir']}/{$hash}")) {
			$this->webapp->response_location('?control/ads');
		}
		else
		{
			$this->main->append('h4', '广告插入失败！');
		}
	}
	function delete_ad(string $hash, string $seat = NULL)
	{
		$this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->delete() === 1
			? $this->goto("/ads,seat:{$seat}")
			: $this->dialog('广告删除失败！');
	}
	function get_ad_update(string $hash)
	{
		if ($this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->fetch($ad))
		{
			$form = $this->form_ad($this->main);
			$form->xml->fieldset->img['src'] = $ad['change'] === 'none'
				? "?/news/{$ad['hash']}?mask{$ad['ctime']}"
				: '/webapp/res/ps/loading.svg';
			$form->echo($ad);
		}
	}
	function post_ad_update(string $hash)
	{
		if ($this->form_ad()->fetch($ad))
		{
			if (count($uploadedfile = $this->webapp->request_uploadedfile('ad'))
				&& $uploadedfile->maskfile("{$this->webapp['ad_savedir']}/{$hash}")) {
				$ad['change'] = 'sync';
			}
			else
			{
				$ad['ctime'] = $this->webapp->time;
			}
			if ($this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->update($ad))
			{
				$this->webapp->response_location('?control/ads');
			}
			return 200;
		}
		$this->main->append('h4', '广告更新失败！');
	}
	//========标签========
	function tag_types():array
	{
		return $this->webapp->mysql->tags('WHERE phash IS NULL ORDER BY sort DESC,hash ASC')->column('name', 'hash');
	}
	function form_tag(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('分类 / 标签名称（用 "," 间隔） / 排序（越大越靠前）');
		$form->field('level', 'select', ['options' => base::tags_level, 'required' => NULL]);
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset();
		$form->button('提交', 'submit');
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
			$table->cell()->append('a', ['删除', 'href' => "?control/tag,hash:{$value['hash']}", 'data-method' => 'delete', 'data-bind' => 'click']);
			$table->cell(date('Y-m-d\\TH:i:s', $value['time']));
			$table->cell($value['hash']);
			$table->cell(number_format($value['click']));
			$table->cell(base::tags_level[$value['level']]);
			$table->cell($value['sort']);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/tag,hash:{$value['hash']}"]);
			
		});
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('删除', '创建时间', 'HASH', '点击', '类型', '排序', '名称');
		$table->header('找到 %d 项', $table->count());
		$table->bar->append('button', ['添加标签或分类', 'onclick' => 'location.href="?control/tag"']);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px',
			'placeholder' => '关键字【Enter】搜索',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->append('span', ['style' => 'margin:0 .6rem'])
			->select(['' => '全部类型'] + base::tags_level)
			->setattr(['onchange' => 'g({level:this.value||null})', 'style' => 'padding:.1rem'])->selected($level);
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
			$this->dialog('标签添加失败！');
		}
	}
	function delete_tag(string $hash)
	{
		$this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->delete() === 1 && $this->webapp->fetch_tags->flush()
			? $this->goto('/tags')
			: $this->dialog('标签删除失败！');
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
			$this->dialog('标签修改失败！');
		}
	}


	//========专题========
	const subject_styles = [
		1 => '1 横版（大）',
		2 => '2 横版（小）',
		3 => '3 竖版',
		4 => '4 竖版（单排滑动）',
		5 => '5 横版（单排滑动）',
		6 => '6 横版（先大后小）',
		7 => '7 横版（右侧封面）',
		8 => '8 横版（右侧封面单排滑动）',
		9 => '9 横版（右侧封面先大后小）',
		255 => '个人（没用）'
	];
	const subject_fetch_methods = [
		'intersect' => '标签HASH交集',
		'union' => '标签HASH并集',
		'starts' => '标题开始关键词',
		'ends' => '标题结尾关键词',
		'contains' => '标题包含关键词',
		'uploader' => 'UP主（用户ID并集）'
	];
	function form_subject(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('专题分类 / 排序（越大越靠前）');

		$form->field('type', 'select', ['options' => $this->webapp->fetch_tags->classify(), 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset('专题名称 / 展示样式');
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('style', 'select', ['options' => self::subject_styles, 'required' => NULL]);

		$form->fieldset('数据来源');
		$form->field('fetch_method', 'select', ['options' => self::subject_fetch_methods]);
		$form->field('fetch_values', 'text', ['placeholder' => '多个值请用 "," 间隔', 'style' => 'width:21rem', 'required' => NULL]);

		$form->fieldset('专题影片');
		$form->field('videos', 'textarea', [
			'cols' => 30,
			'rows' => 10,
			'placeholder' => '请输入视频HASH，最多10个回车'],
			fn($v, $i)=>$i?strtr($v, ["\n" => '', "\r\n" => '']):($v?join("\n", str_split($v, 12)):''));

		$form->fieldset();
		$form->button('提交', 'submit');


		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function get_subjects(int $page = 1)
	{
		$conds = [[]];
		if ($type = $this->webapp->query['type'] ?? '')
		{
			$conds[0][] = 'type=?s';
			$conds[] = $type;
		}

		$conds[0] = sprintf('%sORDER BY type ASC,sort DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$classify = $this->webapp->fetch_tags->classify();
		$table = $this->main->table($this->webapp->mysql->subjects(...$conds)->paging($page), function($table, $value, $classify)
		{
			$table->row();
			$table->cell()->append('a', ['删除',
				'href' => "?control/subject,hash:{$value['hash']}",
				'data-method' => 'delete',
				'data-bind' => 'click'
			]);
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));
			$table->cell($value['hash']);
			$table->cell($value['sort']);
			$table->cell($classify[$value['type']] ?? NULL);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/subject,hash:{$value['hash']}"]);
			$table->cell(self::subject_styles[$value['style']] ?? $value['style']);
			$table->cell()->append('a', ["{$value['fetch_method']}({$value['fetch_values']})",
				'href' => "?control/videos,subject:{$value['hash']}"]);

		}, $classify);
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('删除', '创建时间', '修改时间', 'HASH', '排序', '分类', '名称', '展示样式', '数据来源');
		$table->header('找到 %d 项', $table->count());
		$table->bar->append('button', ['添加专题', 'onclick' => 'location.href="?control/subject"']);
		$table->bar->select(['' => '全部分类', ...$classify])
			->setattr(['onchange' => 'g({type:this.value||null})',
				'style' => 'margin-left:.6rem;padding:.1rem'])->selected($type);

		$table->bar->append('button', ['刷新前端专题缓存',
			'data-src' => '?control/flush,data:subjects',
			'data-method' => 'patch',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);
	}
	function get_subject(string $hash = NULL)
	{
		$form = $this->form_subject($this->main);
		if (is_string($hash) && $this->webapp->mysql->subjects('WHERE hash=?s LIMIT 1', $hash)->fetch($subject))
		{
			$form->xml['method'] = 'patch';
			$form->echo($subject);
		}
	}
	function post_subject()
	{
		if ($this->form_subject()->fetch($subject) && $this->webapp->mysql->subjects->insert([
			'hash' => substr($this->webapp->random_hash(TRUE), -4),
			'mtime' => $this->webapp->time,
			'ctime' => $this->webapp->time] + $subject)) {
			$this->goto("/subjects,type:{$subject['type']}");
		}
		else
		{
			$this->dialog('专题添加失败！');
		}
	}
	function delete_subject(string $hash)
	{
		$this->webapp->mysql->subjects('WHERE hash=?s LIMIT 1', $hash)->delete() === 1
			? $this->goto('/subjects') : $this->dialog('专题删除失败！');
	}
	function patch_subject(string $hash)
	{
		if ($this->form_subject()->fetch($subject)
			&& $this->webapp->mysql->subjects('WHERE hash=?s LIMIT 1', $hash)->update([
			'ctime' => $this->webapp->time] + $subject)) {
			$this->goto("/subjects,type:{$subject['type']}");
		}
		else
		{
			$this->dialog('专题修改失败！');
		}
	}

	//========上传========
	function get_uploaders(string $search = NULL, int $page = 1)
	{
		$conds = [[]];

		$conds[0] = sprintf('%sORDER BY uid ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->uploaders(...$conds)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell($value['date']);
			$table->cell(date('Y-m-d\\TH:i:s', $value['lasttime']));
			$table->cell($this->webapp->hexip($value['lastip']));
			$table->cell($value['uid']);
			$table->cell($value['name']);
			$table->cell()->append('a', ['详细', 'href' => "?control/uploader,uid:{$value['uid']}"]);
		});
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('创建时间', '最后登录时间', '最后登录IP', 'UID', '名称', '详细');
		$table->header('上传账号 %d 项', $table->count());
		$table->bar->append('button', ['添加账号', 'onclick' => 'location.href="?control/uploader"']);

	}
	function form_uploader(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('UID / 密码 / 名称');
		$form->field('uid', 'number', ['min' => 1, 'max' => 65535, 'placeholder' => '账号ID', 'required' => NULL]);
		$form->field('pwd', 'text', ['placeholder' => '密码最多16位', 'required' => NULL]);
		$form->field('name', 'text', ['placeholder' => '名称', 'required' => NULL]);
		$form->button('提交', 'submit');
		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function get_uploader(int $uid = 0, int $page = 1)
	{

		if ($uid && $this->webapp->mysql->uploaders('WHERE uid=?i LIMIT 1', $uid)->fetch($uploader))
		{
			$references = [];
			foreach ($this->webapp->mysql->videos('WHERE userid IN(?S) GROUP BY userid',
				$this->webapp->mysql->users('WHERE uid=?i', $uid)->column('id'))
				->select(join(',', [
					'userid',
					'COUNT(IF(`require`>-2,1,NULL)) `all`',
					'COUNT(IF(`require`=-1,1,NULL)) vip',
					'COUNT(IF(`require`=0,1,NULL)) free',
					'COUNT(IF(`require`>0,1,NULL)) coin'
				])) as $video) {
				$references[$video['userid']] = [
					'all' => $video['all'],
					'vip' => $video['vip'],
					'free' => $video['free'],
					'coin' => $video['coin']
				];
			}
			$table = $this->main->table($this->webapp->mysql->users('WHERE uid=?i', $uid)->paging($page), function($table, $value, $references)
			{
				$table->row();
				$table->cell($value['date']);
				$table->cell(date('Y-m-d\\TH:i:s', $value['lasttime']));
				$table->cell($this->webapp->hexip($value['lastip']));
				$table->cell($value['id']);
				$table->cell($value['nickname']);
				if (isset($references[$value['id']]))
				{
					$reference = $references[$value['id']];
					$table->cell(number_format($reference['all']));
					$table->cell(sprintf('%.01f%%', $reference['free'] ? $reference['free'] / $reference['all'] * 100 : 0));
					$table->cell(sprintf('%.01f%%', $reference['vip'] ? $reference['vip'] / $reference['all'] * 100 : 0));
					$table->cell(sprintf('%.01f%%', $reference['coin'] ? $reference['coin'] / $reference['all'] * 100 : 0));
				}
				else
				{
					$table->cell(number_format($value['video_num']));
					$table->cell('0.0%');
					$table->cell('0.0%');
					$table->cell('0.0%');
				}
				$table->cell(number_format($value['balance']));
			}, $references);
			$table->fieldset('创建日期', '最后登录时间', '最后登录IP', 'ID', '昵称', '视频数', '免费比', '会员比', '金币比', '余额');
			$table->header("上传账号 %s 绑定了 %s 个用户", $uploader['name'], $table->count());
			$table->paging($this->webapp->at(['page' => '']));
			
			$form = $this->form_uploader($table->bar);
			unset($form->xml->fieldset[1]->legend);
			$form->xml['method'] = 'patch';
			$form['uid']['disabled'] = NULL;
			$form->echo($uploader);
			$form->button('添加用户', 'button', [
				'data-method' => 'post',
				'data-src' => "?control/uploader-bind-create-user,uid:{$uploader['uid']}",
				'data-dialog' => '{"nickname":"text"}',
				'data-bind' => 'click']);
			$form->button('删除该账号', 'button', [
				'data-method' => 'delete',
				'data-src' => "?control/uploader,uid:{$uploader['uid']}",
				'data-dialog' => '删除无法撤销',
				'data-bind' => 'click']);
		}
		else
		{
			$this->form_uploader($this->main);
		}
	}
	function post_uploader()
	{
		if ($this->form_uploader()->fetch($uploader)
			&& $this->webapp->mysql->uploaders->insert([
				'date' => date('Y-m-d', $this->webapp->time),
				'lasttime' => $this->webapp->time,
				'lastip' => $this->webapp->iphex('0.0.0.0')
			] + $uploader)) {
			$this->goto("/uploaders,search:{$uploader['uid']}");
			return;
		}
		$this->dialog('上传账号添加失败！');
	}
	function patch_uploader(int $uid)
	{
		$form = $this->form_uploader();
		unset($form['uid']);
		if ($form->fetch($uploader)
			&& $this->webapp->mysql->uploaders('WHERE uid=?i LIMIT 1', $uid)->update($uploader)) {
			$this->goto("/uploader,uid:{$uid}");
			return;
		}
		$this->dialog('上传账号更新失败！');
	}
	function post_uploader_bind_create_user(int $uid)
	{
		$user = $this->webapp->request_content();
		$user['uid'] = $uid;
		user::create($this->webapp, $user);
		$this->goto();
	}
	function delete_uploader(int $uid)
	{
		$this->admin
			&& $this->webapp->mysql->uploaders('WHERE uid=?s LIMIT 1', $uid)->delete()
			? $this->goto('/uploaders') : $this->dialog('上传账号删除失败或需要超级管理员权限！');
	}

	//========用户========
	function form_user(string $id, webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('会员到期 / 金币余额 / 观影券 / 绑定UP主后台上传ID（0非UP主）');
		$form->field('expire', 'date', [], fn($v, $i)=>$i?strtotime($v):date('Y-m-d', $v));
		$form->field('coin', 'number', ['min' => 0]);
		$form->field('ticket', 'number', ['min' => 0]);
		$form->field('uid', 'number', ['min' => 0, 'max' => 65535]);

		$form->fieldset();
		// if ($this->webapp->redis->get("user:{}"))
		// $form->button('从');
		$form->button('增加1次分享')->setattr([
			'data-method' => 'patch',
			'data-src' => "?control/user-share,id:{$id}",
			'data-bind' => 'click'
		]);

		$form->fieldset();
		$form->button('更新', 'submit');

		$form->xml['method'] = 'patch';
		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function patch_user_share(string $id)
	{
		$data = ['dialog' => '操作失败'];
		if ($this->webapp->mysql->users('WHERE id=?s LIMIT 1', $id)->update('share=share+1') === 1)
		{
			$data['dialog'] = '操作成功！';
			if ($this->webapp->redis->exists($key = "user:{$id}"))
			{
				$this->webapp->redis->hIncrBy($key, 'share', 1);
				$this->webapp->redis->hIncrBy($key, 'count', 10);
				$data['dialog'] .= '用户缓存已更新！';
			}
		}
		$this->json($data);
	}
	function get_user_share()
	{
		$dialog = ['所有用户分享统计：'];
		foreach ($this->webapp->mysql->users('WHERE share>0 GROUP BY share ORDER BY share DESC')->select('share,count(1) c') as $data)
		{
			$dialog[] = "分享 {$data['share']} 次：{$data['c']}";
		}
		$this->json(['dialog' => join("\n", $dialog)]);
	}
	function get_user_share_today()
	{
		$dialog = ['今日用户分享统计：'];
		foreach ($this->webapp->mysql->users('WHERE date=?s AND share>0 GROUP BY share ORDER BY share DESC', date('Y-m-d'))->select('share,count(1) c') as $data)
		{
			$dialog[] = "分享 {$data['share']} 次：{$data['c']}";
		}
		$this->json(['dialog' => join("\n", $dialog)]);
	}
	function get_users(int $page = 1)
	{
		$conds = [[]];
		// if (strlen($uid = $this->webapp->query['uid'] ?? ''))
		// {
		// 	if (intval($uid) === -1)
		// 	{
		// 		$conds[0][] = 'uid!=0';
		// 	}
		// 	else
		// 	{
		// 		$conds[0][] = 'uid=?i';
		// 		$conds[] = $uid;
		// 	}
		// }

		$filter_date = $this->webapp->query['date'] ?? date('Y-m-d');
		if ($filter_cid = $this->webapp->query['cid'] ?? '')
		{
			$conds[0][] = 'cid=?s';
			$conds[] = $filter_cid;
		}
		if ($filter_search = $this->webapp->query['search'] ?? '')
		{
			if (strlen($filter_search) === 10 && trim($filter_search, webapp::key) === '')
			{
				$conds[0][] = 'id=?s';
				$conds[] = $filter_search;
			}
			else
			{
				$filter_search = urldecode($filter_search);
				$conds[0][] = 'nickname LIKE ?s';
				$conds[] = "%{$filter_search}%";
			}
		}
		else
		{
			if ($filter_date)
			{
				$conds[0][] = 'date=?s';
				$conds[] = $filter_date;
			}
		}

		if ($filter_share = $this->webapp->query['share'] ?? '')
		{
			[$conds[0][], $conds[]] = match ($filter_share)
			{
				'more0' => ['share>?i', 0],
				'more3' => ['share>?i', 3],
				default => ['share=?i', $filter_share]
			};
		}
		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY ' . match($filter_sort = $this->webapp->query['sort'] ?? '')
		{
			'login-desc' => 'login DESC',
			'watch-desc' => 'watch DESC',
			default => 'mtime DESC'
		} . ',id ASC';
		$table = $this->main->table($this->webapp->mysql->users(...$conds)->paging($page), function($table, $value)
		{
			$table->row();

			$table->cell($value['date']);
			$table->cell()->append('a', [$value['id'], 'href' => "?control/user,id:{$value['id']}"]);
			$table->cell(number_format($value['login']));
			$table->cell(number_format($value['watch']));
			$table->cell(number_format($value['share']));
			$table->cell($value['cid']);

			$table->cell($value['device']);
			// $table->cell($value['tid']);
			$table->cell($value['did']);

			$table->cell($value['nickname']);
			// $table->cell(number_format($value['video_num']));
			// $table->cell(number_format($value['balance']));
			// $table->cell(match (true)
			// {
			// 	$value['expire'] === 0 => '超级会员',
			// 	$value['expire'] < $this->webapp->time => '已经过期',
			// 	$value['expire'] - 316224000 > $this->webapp->time => '永久会员',
			// 	default => date('Y-m-d', $value['expire'])
			// });
			// $table->cell(number_format($value['coin']));
			// $table->cell(number_format($value['ticket']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['lasttime']));
			$table->cell($this->webapp->hexip($value['lastip']));

		});
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('注册日期', 'ID', '登录', '观看', '分享', '渠道ID', '设备类型',
			//'绑定手机',
			'设备ID', '昵称',
			//'影片数', '余额', '会员到期', '金币', '观影券'
			'最后登录日期', '最后登录IP');
		$table->header('用户 %d 项', $table->count());

		// $table->bar->append('input', [
		// 	'type' => 'search',
		// 	'value' => $uid,
		// 	'style' => 'width:10rem',
		// 	'placeholder' => 'UP后台ID，-1 全部',
		// 	'onkeydown' => 'event.keyCode==13&&g({uid:this.value||null,page:null})'
		// ]);
		$table->bar->append('input', [
			'type' => 'date',
			'value' => $filter_date,
			'onchange' => 'g({date:this.value||"",page:null})'
		]);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $filter_cid,
			'style' => 'width:80px;margin-left:.6rem',
			'placeholder' => '渠道ID',
			'onkeydown' => 'event.keyCode==13&&g({cid:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $filter_search,
			'style' => 'margin-left:.6rem',
			'placeholder' => '用户信息按【Enter】搜索',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->select(['' => '全部分享', 'more0' => '至少1次', '1' => '1 次分享', '2' => '2 次分享', '3' => '3 次分享', 'more3' => '大于3次', '0' => '没有分享'])
			->setattr(['onchange' => 'g({share:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($filter_share);

		$table->bar->select(['' => '默认排序', 'login-desc' => '登录降序', 'watch-desc' => '观看降序'])
			->setattr(['onchange' => 'g({sort:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($filter_sort);

		$table->bar->append('button', ['所有用户分享数据',
			'data-src' => '?control/user-share',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);

		$table->bar->append('button', ['今日用户分享数据',
			'data-src' => '?control/user-share-today',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);

	}
	function get_user(string $id)
	{
		if ($this->webapp->mysql->users('WHERE id=?s LIMIT 1', $id)->fetch($user))
		{
			$form = $this->form_user($id, $this->main);
			$form->xml->fieldset[0] = $this->webapp->signature($user['id'], $user['cid']);
			$form->echo($user);
		}
	}

	function patch_user(string $id)
	{
		if ($this->form_user($id)->fetch($user) && $this->webapp->mysql->users('WHERE id=?s LIMIT 1', $id)->update([
			'ctime' => $this->webapp->time
		] + $user)) {
			$this->webapp->user_sync($id);
			$this->goto("/users,search:{$id}");
		}
		else
		{
			$this->dialog('用户信息更新失败');
		}
	}
	//========图片========
	function get_images(string $search = NULL, int $page = 1)
	{
		$conds = [[]];
		if (is_string($search))
		{
			$conds[0][] = 'hash=?s';
			$conds[] = $search;
		}
		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY mtime DESC,hash ASC';
		$figures = NULL;
		$table = $this->main->table($this->webapp->mysql->images(...$conds)->paging($page, 20), function($table, $value) use(&$figures)
		{
			if ($figures === NULL)
			{
				$table->row();
				$figures = $table->cell()->append('div', ['class' => 'images']);
			}
			$ym = date('ym', $value['mtime']);
			$figure = $figures->append('figure');
			$figure->append('img', ['src' => $value['sync'] === 'pending'
				? '/webapp/res/ps/loading.svg'
				: "?/imgs/{$ym}/{$value['hash']}?mask{$value['ctime']}"
			]);
			$figure->append('figcaption', $value['hash']);
		});
		$table->header('图片 %d 项', $table->count());
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('label', '添加图片')->append('input', [
			'type' => 'file',
			'accept' => 'image/*',
			'style' => 'display:none',
			'data-uploadurl' => '?uploadimage',
			'onchange' => 'upload_image(this)'
		]);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'placeholder' => 'HASH',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})',
			'style' => 'margin-left:1rem'
		]);
	}

	//========视频========
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
		$tags = $this->webapp->fetch_tags->shortname();
		$table = $this->main->table($this->webapp->mysql->videos(...$conds)->paging($page, 10), function($table, $value, $tags)
		{
			$ym = date('ym', $value['mtime']);

			$table->row()['class'] = 'info';
			$table->cell()->append('a', ["用户ID：{$value['userid']}", 'href' => "?control/videos,userid:{$value['userid']}"]);
			$table->cell(['colspan' => 8])->append('a', [sprintf('上传时间：%s，最后修改时间：%s',
				date('Y-m-d\\TH:i:s', $value['mtime']),
				date('Y-m-d\\TH:i:s', $value['ctime'])
			), 'href' => "?control/video,hash:{$value['hash']}"]);

			$table->row();
			$cover = $table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144', 'class' => 'cover']);

			$table->row();
			$table->cell('HASH');
			$table->cell($value['hash']);
			$table->cell('类型');
			$table->cell(base::video_type[$value['type']]);
			$table->cell('时长');
			$table->cell(base::format_duration($value['duration']));
			$table->cell('要求');
			$table->cell(match (intval($value['require']))
			{
				-2 => '下架', -1 => '会员', 0 => '免费',
				default => "{$value['require']} 金币"
			});

			$table->row();
			$table->cell('状态');
			$syncnode = $table->cell();
			$syncnode->append('span', $value['sync'] === 'waiting' && $value['tell'] < $value['size']
				? '正在上传' : base::video_sync[$value['sync']]);
			$anchors = [];
			if ($value['sync'] !== 'exception')
			{
				$anchors[] = ['设为异常',
					'href' => "?control/video,hash:{$value['hash']},sync:exception",
					'style' => 'color:maroon',
					'data-method' => 'patch',
					'data-dialog' => '设为异常后不可恢复',
					'data-bind' => 'click'
				];
			}
			if (in_array($value['sync'], ['finished', 'allow'], TRUE))
			{
				$anchors[] = ['拒绝',
					'href' => "?control/video,hash:{$value['hash']},sync:deny",
					'style' => 'color:maroon',
					'data-method' => 'patch',
					'data-bind' => 'click'
				];
			}
			if (in_array($value['sync'], ['finished', 'deny'], TRUE))
			{
				$anchors[] = ['通过',
					'href' => "?control/video,hash:{$value['hash']},sync:allow",
					'data-method' => 'patch',
					'data-bind' => 'click'
				];
			}
			foreach ($anchors as $anchor)
			{
				$syncnode->append('span', ' | ');
				$syncnode->append('a', $anchor);
			}

			$table->cell('观看');
			$table->cell(number_format($value['view']));
			$table->cell('点赞');
			$table->cell(number_format($value['like']));
			$table->cell('销量');
			$table->cell(number_format($value['sales']));

			$table->row();
			$table->cell('标签');
			$tagnode = $table->cell(['colspan' => 7, 'class' => 'tags'])->append('div');
			foreach ($value['tags'] ? explode(',', $value['tags']) : [] as $tag)
			{
				if (isset($tags[$tag]))
				{
					$tagnode->append('a', [$tags[$tag], 'href' => "?control/videos,search:{$tag}"]);
				}
			}

			$table->row();
			$table->cell('名称');
			$title = $table->cell(['colspan' => 7, 'class' => 'name'])->append('a', [htmlentities($value['name']), 'href' => "javascript:;"]);

			if (in_array($value['sync'], ['finished', 'allow', 'deny'] ,TRUE))
			{
				$cover->append('img', [
					'loading' => 'lazy',
					'src' => "?/{$ym}/{$value['hash']}/cover?mask{$value['ctime']}",
					'id' => "v{$value['hash']}",
					'data-cover' => "?/{$ym}/{$value['hash']}/cover?mask{$value['ctime']}",
					'data-playm3u8' => "?/{$ym}/{$value['hash']}/play?mask0000000000",
					'onclick' => "view_video(this.dataset, {$value['preview']})",
					'style' => 'object-fit: contain;'
				]);
				$title['onclick'] = "view_video(document.querySelector('img#v{$value['hash']}').dataset)";
			}
			else
			{
				$cover->append('img', ['src' => '/webapp/res/ps/loading.svg']);
			}

		}, $tags);
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('封面（预览视频）', '信息');
		$table->header('视频 %d 项', $table->count());
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
			'placeholder' => '标签用 “.” 间隔',
			'onkeydown' => 'event.keyCode==13&&g({tag:this.value||null,page:null})'
		]);
		$taglevels = $this->webapp->fetch_tags->levels();
		
		$table->bar->select(['' => '标签1', ...$taglevels])
			->setattr(['onchange' => 'g({tag:this.value||null})', 'style' => 'margin-left:.6rem;width:6rem;padding:2px'])
			->selected($tagsearch[0] ?? NULL);
		$table->bar->select(['' => '标签2', ...$taglevels])
			->setattr(['onchange' => 'g({tag:this.value?(this.previousElementSibling.value?`${this.previousElementSibling.value}.${this.value}`:this.value):this.previousElementSibling.value})', 'style' => 'margin-left:.6rem;width:6rem;padding:2px'])
			->selected($tagsearch[1] ?? NULL);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px;width:16rem',
			'placeholder' => '视频HASH或关键字按【Enter】搜索',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->select(['' => '全部状态', 'uploading' => '正在上传'] + base::video_sync)
			->setattr(['onchange' => 'g({sync:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($sync);
		// $table->bar->select(['' => '要求', 'vip' => '会员', 'free' => '免费', 'coin' => '金币'])
		// 	->setattr(['onchange' => 'g({require:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
		// 	->selected($require);
		$table->bar->select(['' => '全部类型', 'notclassify' => '没有分类'] + base::video_type)
			->setattr(['onchange' => 'g({type:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($type);
		$table->bar->select(['' => '默认（入库降序）',
			'ctime-desc' => '最后修改（降序）',
			'view-desc' => '观看（降序）',
			'like-desc' => '点赞（降序）',
			'sales-desc' => '销量（降序）'])
			->setattr(['onchange' => 'g({sort:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($sort);
		// $table->bar->append('button', ['所有完成视频通过审核',
		// 	'style' => 'margin-left:.6rem',
		// 	'data-src' => '?control/video-all-finished-to-allow',
		// 	'data-method' => 'patch',
		// 	'data-bind' => 'click'
		// ]);
		$table->bar->append('button', ['清理异常',
			'style' => 'margin-left:.6rem',
			'onclick' => 'location.href="?control/video-exception-clear"'
		]);
		$table->bar->append('button', ['刷新前端专题影片缓存',
			'data-src' => '?control/flush,data:subjectvideos',
			'data-method' => 'patch',
			'data-bind' => 'click',
			'style' => 'margin-left:.6rem;padding:.1rem']);
		$table->bar['style'] = 'white-space:nowrap';
	}
	function get_video(string $hash)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		$this->webapp->form_video($this->main, $hash)->xml['action'] .= ',goto:' . $this->webapp->url64_encode('?control/videos');
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



	//========产品========
	function prod_vtids(bool $listed = FALSE):array
	{
		$vtids = [
			'' => '实体产品',
			'基本固定虚拟产品' => [
				'prod_vtid_vip_top_up' => '会员卡',
				'prod_vtid_coin_top_up' => '观影金币',
				'prod_vtid_game_top_up' => '游戏金币'
			],
			'临时添加虚拟产品' => [
				'prod_vtid_vip_premium' => '铂金永久会员卡'
			],
			'促销活动虚拟产品' => [
				'prod_vtid_vip_11_11' => '双11福利会员卡'
			],
		];
		return $listed ? array_merge(['' => array_shift($vtids)], ...array_values($vtids)) : $vtids;
	}
	function form_prod(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('产品图片 / 虚拟ID');
		$form->field('prod', 'file', ['accept' => 'image/*']);
		$form->field('vtid', 'select', ['options' => $this->prod_vtids()]);

		$form->fieldset('产品名称 / 单价（元） / 数量');
		$form->field('name', 'text', ['placeholder' => '名称', 'required' => NULL]);
		$form->field('price', 'number', ['style' => 'width:8rem', 'min' => 0, 'placeholder' => '单价（元）', 'required' => NULL]);
		$form->field('count', 'number', ['style' => 'width:8rem', 'min' => 0, 'placeholder' => '数量', 'value' => 99999999, 'required' => NULL]);

		$form->fieldset('产品描述');
		$form->field('desc', 'text', ['style' => 'width:32rem', 'placeholder' => '描述']);

		$form->fieldset();
		$form->button('提交', 'submit');

		return $form;
	}
	function get_prods(int $page = 1)
	{
		$conds = [[]];




		$conds[0] = sprintf('%sORDER BY LEFT(vtid, 13) ASC,price ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->prods(...$conds)->paging($page), function($table, $value, $prod)
		{
			$table->row();

			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));
			$table->cell($value['hash']);
			$table->cell($prod[$value['vtid']] ?? '未知');
			$table->cell([number_format($value['price']), 'style' => 'text-align:right']);
			
			$table->cell()->append('a', [$value['name'], 'href' => "?control/prod-update,hash:{$value['hash']}"]);
			$table->cell([number_format($value['sales']), 'style' => 'text-align:right']);
			$table->cell([number_format($value['count']), 'style' => 'text-align:right']);
			

			$table->cell()->append('a', ['删除',
				'href' => "?control/prod,hash:{$value['hash']}",
				'data-method' => 'delete',
				'data-bind' => 'click',
				'data-dialog' => "删除 {$value['hash']} 确定？"
			]);

		}, $this->prod_vtids(TRUE));
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('创建时间', '修改时间', 'HASH', 'VTID', '单价（元）', '名称', '累计销量', '剩余数量', '删除');
		$table->header('产品 %d 项', $table->count());
		$table->bar->append('button', ['添加产品', 'onclick' => 'location.href="?control/prod-insert"']);

	}
	function delete_prod(string $hash)
	{
		if ($this->admin === FALSE)
		{
			$this->dialog('请联系管理员进行删除！');
			return;
		}
		if ($this->webapp->mysql->prods->delete('WHERE hash=?s LIMIT 1', $hash) === 1)
		{
			$this->goto();
			return;
		}
		$this->dialog('异常操作！');
	}
	function get_prod_insert()
	{
		$this->form_prod($this->main);
	}
	function post_prod_insert()
	{
		$hash = $this->webapp->random_hash(FALSE);
		if (count($uploadedfile = $this->webapp->request_uploadedfile('prod')) === 0
			&& $this->form_prod()->fetch($prod) && $this->webapp->mysql->prods->insert([
				'hash' => $hash,
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time,
				'sales' => 0,
				'vtid' => $prod['vtid'] ? $prod['vtid'] : NULL] + $prod)
			) {
			$this->webapp->response_location('?control/prods');
		}
		else
		{
			$this->main->append('h4', '产品插入失败！');
		}
	}
	function get_prod_update(string $hash)
	{
		if ($this->webapp->mysql->prods('WHERE hash=?s LIMIT 1', $hash)->fetch($prod))
		{
			$form = $this->form_prod($this->main);
			// $form->xml->fieldset->append('div', [
			// 	'style' => 'width:32rem;height:18rem;border:black 1px solid',
			// 	'data-cover' => "/news/{$ad['hash']}"
			// ]);
			$form->echo($prod);
		}
	}
	function post_prod_update(string $hash)
	{
		if ($this->form_prod()->fetch($prod) && $this->webapp->mysql->prods('WHERE hash=?s LIMIT 1', $hash)->update([
			'ctime' => $this->webapp->time
		] + $prod)) {
			$this->webapp->response_location('?control/prods');
		}
		else
		{
			$this->main->append('h4', '产品更新失败！');
		}
	}



	// //话题 & 评论
	// function get_comments(string $search = NULL, string $phash = NULL, int $page = 1)
	// {
	// 	$conds = [[]];
	// 	if ($search && trim($search, webapp::key) === '')
	// 	{
	// 		$conds[0][] = 'hash=?s';
	// 		$conds[] = $search;
	// 	}
	// 	if ($phash)
	// 	{
	// 		$conds[0][] = 'phash=?s';
	// 		$conds[] = $phash;
	// 	}
	// 	else
	// 	{
	// 		$conds[0][] = 'phash IS NOT NULL';
	// 	}
		
	// 	if ($type = $this->webapp->query['type'] ?? '')
	// 	{
	// 		$conds[0][] = 'type=?s';
	// 		$conds[] = $type;
	// 	}
	// 	if ($check = $this->webapp->query['check'] ?? '')
	// 	{
	// 		$conds[0][] = '`check`=?s';
	// 		$conds[] = $check;
	// 	}
	// 	if ($userid = $this->webapp->query['userid'] ?? '')
	// 	{
	// 		$conds[0][] = 'userid=?s';
	// 		$conds[] = $userid;
	// 	}

	// 	$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY `sort` DESC,`mtime` DESC';
	// 	$table = $this->main->table($this->webapp->mysql->comments(...$conds)->paging($page, 10), function($table, $value)
	// 	{
	// 		$table->row();
	// 		$table->cell()->append('a', ['删除',
	// 			'href' => "?control/comment-simple,hash:{$value['hash']}",
	// 			'style' => 'color:red',
	// 			'data-dialog' => '删除后无法恢复',
	// 			'data-method' => 'delete',
	// 			'data-bind' => 'click']);
	// 		$table->cell()->append('a', [$value['hash'], 'href' => "?control/comment,hash:{$value['hash']}"]);
	// 		$table->cell()->append('a', [$value['userid'], 'href' => "?control/comments,userid:{$value['userid']}"]);
	// 		$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
	// 		$table->cell(number_format($value['count']));
	// 		$table->cell(number_format($value['view']));
	// 		$table->cell()->append('a', [base::comment_type[$value['type']],
	// 			'href' => "?control/comments,search:{$value['phash']}", 'target' => '_blank']);
	// 		$table->cell([$value['sort'],
	// 			'data-src' => "?control/comment,hash:{$value['hash']}",
	// 			'data-method' => 'patch',
	// 			'data-dialog' => '{"sort":"number"}',
	// 			'data-value' => $value['sort'],
	// 			'data-bind' => 'click'
	// 		]);

	// 		$check = $table->cell();
	// 		if ($value['check'] === 'pending')
	// 		{
	// 			$check->append('a', ['允许',
	// 				'href' => "?control/comment,hash:{$value['hash']},field:check,value:allow",
	// 				'data-method' => 'patch',
	// 				'data-bind' => 'click'
	// 			]);
	// 			$check->append('span', ' | ');
	// 			$check->append('a', ['拒绝',
	// 				'href' => "?control/comment,hash:{$value['hash']},field:check,value:deny",
	// 				'data-method' => 'patch',
	// 				'data-bind' => 'click'
	// 			]);
	// 		}
	// 		else
	// 		{
	// 			$check->text($value['check']);
	// 		}

	// 		$table->row();
	// 		$contents = $table->cell(['colspan' => 9])->append('pre', ['style' => 'width:50rem;margin:0;line-height:1.4rem;white-space:pre-wrap;word-wrap:break-word']);
	// 		if ($value['type'] !== 'video' && $value['title'])
	// 		{
	// 			$contents->text($value['title']);
	// 			$contents->text("\n");
	// 		}
	// 		if ($value['content'])
	// 		{
	// 			$contents->text("\t{$value['content']}");
	// 		}

	// 		if ($value['images'])
	// 		{
	// 			$table->row();
	// 			$image = $table->cell(['colspan' => 9])->append('div', ['style' => 'display:flex;gap:.4rem;width:53rem;flex-wrap: wrap;']);
	// 			foreach ($this->webapp->mysql->images('WHERE hash IN(?S)', str_split($value['images'], 12)) as $img)
	// 			{
	// 				$cover = $image->append('div', [
	// 					'class' => 'cover',
	// 					'style' => 'width:10rem;height:10rem;display:inline-block'
	// 				]);
	// 				if ($img['sync'] === 'finished')
	// 				{
	// 					$cover->append('img', [
	// 						'loading' => 'lazy',
	// 						'src' => sprintf('?/imgs/%s/%s?mask0000000000', date('ym', $img['mtime']), $img['hash'])
	// 					]);
	// 				}
	// 				else
	// 				{
	// 					$cover->text('pending');
	// 				}
	// 			}
	// 		}
	// 	});
	// 	$table->fieldset('删除', 'HASH', '用户ID', '发布时间', '数量', '观看', '类型', '排序', '审核');
	// 	$table->header('评论 %s 项', $table->count());
	// 	$table->paging($this->webapp->at(['page' => '']));
	// 	$table->bar->append('button', ['添加分类', 'onclick' => 'location.href="?control/comment_class"']);
	// 	$table->bar->append('span', ['style' => 'margin-left:.6rem'])
	// 		->select(['' => '全部分类'] + $this->webapp->select_topics())
	// 		->setattr(['onchange' => 'g({phash:this.value||null})', 'style' => 'padding:.1rem'])->selected($phash);
	// 	$table->bar->append('span', ['style' => 'margin-left:.6rem'])
	// 		->select(['' => '全部类型'] + array_slice(base::comment_type, 1))
	// 		->setattr(['onchange' => 'g({type:this.value||null})', 'style' => 'padding:.1rem'])->selected($type);
	// 	$table->bar->append('span', ['style' => 'margin-left:.6rem'])
	// 		->select(['' => '全部状态', 'pending' => '等待审核', 'allow' => '通过审核', 'deny' => '未通过'])
	// 		->setattr(['onchange' => 'g({check:this.value||null})', 'style' => 'padding:.1rem'])->selected($check);

	// 	$table->bar->append('button', ['删除该分类和话题以及所有帖子和评论',
	// 		'style' => 'margin-left:.6rem',
	// 		'data-src' => "?control/comment,hash:{$phash}",
	// 		'data-method' => 'delete',
	// 		'data-dialog' => '删除后不可恢复',
	// 		'data-bind' => 'click'
	// 	]);
	// 	$table->bar->append('button', ['辅助UP主发布评论',
	// 		'style' => 'margin-left:.6rem',
	// 		'onclick' => 'location.href="?control/comment"'
	// 	]);
	// }
	// function post_comments(string $type)
	// {
	// 	$search = $this->webapp->request_content();
	// 	if ($type === 'reply')
	// 	{
	// 		$this->json(['comments' => []]);
	// 		return;
	// 	}
	// 	$this->json(['comments' => $type === 'video'
	// 		? $this->webapp->mysql->videos('WHERE sync="allow" AND name LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 10', "%{$search}%")
	// 			->column('name', 'hash')
	// 		: $this->webapp->mysql->comments('WHERE type=?s AND `check`="allow" AND title LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 10', $type, "%{$search}%")
	// 			->column('title', 'hash')]);
	// }
	// function form_comment(webapp_html $html = NULL):webapp_form
	// {
	// 	$form = new webapp_form($html ?? $this->webapp);

	// 	$form->fieldset('用户ID');
		
	// 	$form->field('userid', 'text', ['maxlength' => 10, 'oninput' => 'localStorage.setItem("comment_userid",this.value)', 'required' => NULL]);
	// 	$form->fieldset->append('script')->cdata('document.querySelector("form.webapp>fieldset>input[name=userid]").value=localStorage.getItem("comment_userid")');

	// 	$form->fieldset('类型 / 搜索');
	// 	$form->field('type', 'select', ['options' => ['reply' => '请选择类型'] + base::comment_type,
	// 		'onchange' => 'this.nextElementSibling.dataset.type=this.value;search_comment(this.nextElementSibling,this.parentElement.nextElementSibling)',
	// 		'required' => NULL]);
	// 	$form->field('phash', 'search', [
	// 		'data-type' => 'reply',
	// 		'data-action' => '?control/comments',
	// 		'placeholder' => '选择左边类型后输入关键字进行搜索选择',
	// 		'oninput' => 'search_comment(this,this.parentElement.nextElementSibling)',
	// 		'style' => 'width:24rem']);
	// 	$form->fieldset()->setattr('class', 'search_comment');

	// 	$form->fieldset('标题');
	// 	$form->field('title', 'text', ['placeholder' => '话题和评论必须要一个标题', 'style' => 'width:31rem']);

	// 	$form->fieldset('内容');
	// 	$form->field('content', 'textarea', ['rows' => 10, 'cols' => 50, 'required' => NULL]);

	// 	$form->fieldset('图片');
	// 	$form->field('images', 'textarea', ['rows' => 4, 'cols' => 36,
	// 		'placeholder' => '图片最多不超过10个','readonly' => NULL]);
	// 	$form->fieldset->append('label', '添加图片')->append('input', [
	// 		'type' => 'file',
	// 		'accept' => 'image/*',
	// 		'style' => 'display:none',
	// 		'data-uploadurl' => '?uploadimage',
	// 		'onchange' => 'upload_image(this,admin_comment_image)'
	// 	]);
	// 	$form->fieldset('视频');
	// 	$form->field('video', 'search', [
	// 		'data-action' => '?control/videos',
	// 		'placeholder' => '输入视频关键字进行搜索选择，勾选最多不超过100个视频',
	// 		'oninput' => 'search_videos(this,this.parentElement.nextElementSibling.firstElementChild)',
	// 		'style' => 'width:32rem']);
	// 	$form->fieldset()->setattr([
	// 		'class' => 'search_comment',
	// 		'style' => 'height:20rem'
	// 	])->append('ul');


	// 	$form->fieldset();
	// 	$form->button('提交', 'submit');

	// 	$form->xml['onsubmit'] = 'return admin_comment(this)';
	// 	return $form;
	// }
	// function post_videos(string $userid)
	// {
	// 	$search = $this->webapp->request_content();
	// 	$this->json(['videos' => $this->webapp->mysql
	// 		//->videos('WHERE userid=?s AND sync="allow" AND name LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 20', $userid, "%{$search}%")
	// 		->videos('WHERE sync="allow" AND name LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 20', "%{$search}%")
	// 		->column('name', 'hash')]);
	// }
	// function get_comment(string $hash = NULL)
	// {
	// 	$form = $this->form_comment($this->main);
	// 	if ($hash && $this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $hash)->fetch($comment))
	// 	{
	// 		$form->echo($comment);
	// 	}
	// }
	// function post_comment(string $hash = NULL)
	// {
	// 	$error = '无效内容！';
	// 	while ($this->form_comment()->fetch($comment))
	// 	{
	// 		$user = $this->webapp->user(trim($comment['userid']));
	// 		if ($user->id === NULL)
	// 		{
	// 			$error = '用户不存在！';
	// 			break;
	// 		}
	// 		if ($comment['type'] === 'video')
	// 		{
	// 			if ($this->webapp->user($comment['userid'])->comment_video($comment['phash'], $comment['content']) === FALSE)
	// 			{
	// 				$error = '视频评论失败！';
	// 				break;
	// 			}
	// 		}
	// 		else
	// 		{
	// 			if (empty($comment['images']))
	// 			{
	// 				$comment['images'] = NULL;
	// 			}
	// 			if (empty($comment['videos'] = join($_POST['videos'] ?? [])))
	// 			{
	// 				$comment['videos'] = NULL;
	// 			}
	// 			$type = match ($comment['type'])
	// 			{
	// 				'class' => 'topic',
	// 				'topic' => 'post',
	// 				default => 'reply'
	// 			};
	// 			if ($type === 'reply' || $user['uid'] === 0)
	// 			{
	// 				$error = '用户必须是UP主！';
	// 				break;
	// 			}
	// 			[$images, $videos] = match ($type)
	// 			{
	// 				'topic', 'post' => [$comment['images'], $comment['videos']],
	// 				default => [NULL, NULL]
	// 			};
	// 			if ($hash)
	// 			{
	// 				if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $hash)->update([
	// 					'ctime' => $this->webapp->time,
	// 					'title' => $comment['title'],
	// 					'content' => $comment['content'],
	// 					'images' => $images]) === FALSE) {
	// 					$error = '修改评论失败！';
	// 					break;
	// 				}
	// 			}
	// 			else
	// 			{
	// 				if ($user->comment($comment['phash'], $comment['content'], $type, $comment['title'], $images, $videos, TRUE) === FALSE)
	// 				{
	// 					$error = '发布评论失败！';
	// 					break;
	// 				}
	// 			}
	// 		}
	// 		return $this->goto('/comments');
	// 	}
	// 	$this->dialog($error);
	// }

	// function form_comment_class(webapp_html $html = NULL):webapp_form
	// {
	// 	$form = new webapp_form($html ?? $this->webapp);
	// 	$form->fieldset('标题 / 排序（越大越靠前）');
	// 	$form->field('title', 'text', ['maxlength' => 128, 'required' => NULL]);
	// 	$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);
	// 	$form->button('提交', 'submit');
	// 	return $form;
	// }
	// function get_comment_class()
	// {
	// 	$this->form_comment_class($this->main);
	// }
	// function post_comment_class()
	// {
	// 	if ($this->form_comment_class()->fetch($data)
	// 		&& $this->webapp->mysql->comments->insert([
	// 			'hash' => $this->webapp->random_hash(FALSE),
	// 			'mtime' => $this->webapp->time,
	// 			'ctime' => $this->webapp->time,
	// 			'type' => 'topic',
	// 			'check' => 'allow',
	// 			'count' => 0,
	// 			'content' => ''] + $data)) {
	// 		$this->webapp->response_location('?control/comments');
	// 		return;
	// 	}
	// 	$this->main->append('h4', '话题发布失败！');
	// }
	// function delete_comment(string $hash)
	// {
	// 	if ($this->admin && $this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $hash)->delete() === 1)
	// 	{
	// 		$count_topic = 0;
	// 		$count_post = 0;
	// 		$count_reply = 0;
	// 		foreach ($this->webapp->mysql->comments('WHERE phash=?s', $hash)->column('hash') as $topic_hash)
	// 		{
	// 			if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $topic_hash)->delete() === 1)
	// 			{
	// 				++$count_topic;
	// 				foreach ($this->webapp->mysql->comments('WHERE phash=?s', $topic_hash)->column('hash') as $post_hash)
	// 				{
	// 					if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $topic_hash)->delete() === 1)
	// 					{
	// 						++$count_post;
	// 						$count_reply += $this->webapp->mysql->comments('WHERE phash=?s', $post_hash)->delete();
	// 					}
	// 				}
	// 			}
	// 		}
	// 		$this->dialog("删除 {$count_topic} 个话题。\n删除 {$count_post} 个帖子。\n删除 {$count_reply} 个评论。");
	// 		$this->goto();
	// 		return;
	// 	}
	// 	$this->dialog('需要超级管理员权限或者删除失败！');
	// }
	// function delete_comment_simple(string $hash)
	// {
	// 	if ($this->webapp->mysql->comments('WHERE hash=?s AND type IN("post","reply") LIMIT 1', $hash)->delete() === 1)
	// 	{
	// 		$this->webapp->mysql->comments('WHERE phash=?s', $hash)->delete();
	// 		$this->goto();
	// 		return;
	// 	}
	// 	$this->dialog('只能删除帖子或者回复！');
	// }
	// function patch_comment(string $hash, string $field = NULL, string $value = NULL)
	// {
	// 	if ($input = $this->webapp->request_content())
	// 	{
	// 		$field = array_key_first($input);
	// 		$value = $input[$field];
	// 	}
	// 	if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $hash)->update('?a=?s', $field, $value) === 1)
	// 	{
	// 		$this->goto();
	// 		return;
	// 	}
	// 	$this->dialog('操作失败！');
	// }
	function get_channels(int $page = 1)
	{
		$conds = [[]];
		$dpurl = $this->webapp->fetch_configs('down_page');
		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->channels(...$conds)->paging($page), function($table, $value, $dpurl)
		{
			$table->row();
			$table->cell()->append('a', ['删除']);
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell()->append('a', [$value['hash'], 'href' => "?control/channel,hash:{$value['hash']}"]);
			$table->cell($value['type']);
			$table->cell($value['rate']);
			$table->cell($value['name']);
			$table->cell($value['url']);
			$table->cell("{$dpurl}{$value['hash']}");
		}, $dpurl);
		$table->fieldset('删除', '创建日期', '渠道ID', '类型', '比率', '名称', '地址', '落地页地址');
		$table->header('渠道');
		$table->bar->append('button', ['创建渠道', 'onclick' => 'location.href="?control/channel"']);
	}
	function form_channel(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('渠道ID / 密码 / 子渠道数量');
		$form->field('hash', 'text', ['pattern' => '^[0-9A-Za-z]{4}$', 'style' => 'width:6rem', 'placeholder' => '4位字母数字', 'required' => NULL]);
		$form->field('pwd', 'text', ['style' => 'width:8rem', 'maxlength' => 16, 'placeholder' => '渠道后台密码', 'required' => NULL]);
		$form->field('max', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset('名称 / 类型 / 比率（数据 = 实际 x 比率）');

		$form->field('name', 'text', ['style' => 'width:9rem', 'required' => NULL]);
		$form->field('type', 'select', ['options' => ['cpa' => 'CPA', 'cpc' => 'CPC', 'cpm' => 'CPM', 'cps' => 'CPS'], 'required' => NULL]);
		$form->field('rate', 'number', ['min' => 0.01, 'max' => 2, 'step' => 0.01, 'value' => 1, 'required' => NULL]);

		$form->fieldset('主页');
		$form->field('url', 'text', ['style' => 'width:20rem']);

		$form->fieldset();
		$form->button('提交', 'submit');
		$form->echo && $form->echo([
			'hash' => $hash = substr(preg_replace('/[\-\_]+/', '', $this->webapp->random_hash(TRUE)), -4),
			'pwd' => $this->webapp->random_int(100000, 999999),
			'name' => $hash
		]);
		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function get_channel(string $hash = NULL)
	{
		$form = $this->form_channel($this->main);
		if ($hash && $this->webapp->mysql->channels('WHERE hash=?s LIMIT 1', $hash)->fetch($channel))
		{
			$form['hash']->setattr(['readonly' => NULL]);
			$form->echo($channel);
		}
	}
	function post_channel(string $hash = NULL)
	{
		if ($this->form_channel()->fetch($channel, $error) === FALSE)
		{
			return $this->dialog($error);
		}
		if (is_string($hash))
		{
			unset($channel['hash']);
			if ($this->webapp->mysql->channels('WHERE hash=?s LIMIT 1', $hash)->update($channel) !== 1)
			{
				return $this->dialog('渠道更新失败！');
			}
		}
		else
		{
			if ($this->webapp->mysql->channels->insert([
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time
			] + $channel) === FALSE) {
				return $this->dialog('渠道创建失败！');
			}
		}
		$this->goto('/channels');
	}
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
	//配置
	function get_configs()
	{
		$this->form_configs($this->main)->echo($this->webapp->fetch_configs());
	}
	function post_configs()
	{
		if ($this->form_configs()->fetch($configs))
		{
			foreach ($configs as $key => $value)
			{
				$this->webapp->mysql->configs('WHERE `key`=?s LIMIT 1', $key)->update('`value`=?s', $value)
					|| $this->webapp->mysql->configs->insert(['key' => $key, 'value' => $value]);
			}
		}
		$this->webapp->clear_configs();
		$this->goto('/configs');
	}

	function get_reports(string $search = NULL,int $page = 0)
	{
		$conds = [[]];
		if ($search)
		{
			if (strlen($search) === 10 && trim($search, webapp::key) === '')
			{
				$conds[0][] = 'userid=?s';
				$conds[] = $search;
			}
			else
			{
				$search = urldecode($search);
				$conds[0][] = 'question LIKE ?s';
				$conds[] = "%{$search}%";
			}
		}
		if ($clientip = $this->webapp->query['clientip'] ?? '')
		{
			$conds[0][] = 'clientip=?s';
			$conds[] = $clientip;
		}
		if ($promise = $this->webapp->query['promise'] ?? '')
		{
			$conds[0][] = 'promise=?s';
			$conds[] = $promise;
		}

		$conds[0] = sprintf('%sORDER BY time DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->reports(...$conds)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['time']));
			$table->cell($value['hash']);
			$table->cell()->append('a', [$value['userid'], 'href' => "?control/reports,search:{$value['userid']}"]);
			$table->cell()->append('a', [$this->webapp->hexip($value['clientip']), 'href' => "?control/reports,clientip:{$value['clientip']}"]);

			///$table->cell($value['promise']);
			$cell = $table->cell();
			if ($value['promise'] === 'pending')
			{
				$cell->append('a', ['解决',
					'href' => "?control/report,promise:resolve,hash:{$value['hash']}",
					'data-method' => 'patch',
					'data-dialog' => '{"reply":"textarea"}',
					'data-bind' => 'click']);

				$cell->append('span', ' | ');

				$cell->append('a', ['拒绝',
					'href' => "?control/report,promise:reject,hash:{$value['hash']}",
					'data-method' => 'patch',
					'data-dialog' => '{"reply":"textarea"}',
					'data-bind' => 'click']);
			}
			else
			{
				$cell->text(['resolve' => '已解决', 'reject' => '已拒绝'][$value['promise']]);
			}


			$table->row();
			$cell = $table->cell(['colspan' => 5]);
			$cell->append('pre', [$value['question'], 'style' => 'margin:0']);
			if ($value['reply'])
			{
				$cell->append('hr');
				$cell->append('pre', [$value['reply'], 'style' => 'margin:0']);
			}
		});
		$table->fieldset('时间', 'HASH', '用户ID', 'IP', '状态 | 回复');
		$table->header('问题汇报 %d 项', $table->count());
		$table->paging($this->webapp->at(['page' => '']));

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'padding:2px;width:21rem',
			'placeholder' => '用户ID或问题描述',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value||null,page:null})'
		]);

		$table->bar->select(['' => '全部', 'pending' => '待办的', 'resolve' => '已解决', 'reject' => '已拒绝'])
			->setattr(['onchange' => 'g({promise:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($promise);

	}
	function patch_report(string $hash, string $promise)
	{
		$this->webapp->mysql->reports('WHERE hash=?s AND promise="pending" LIMIT 1', $hash)->update([
		 	'promise' => $promise, 'reply' => $this->webapp->request_content()['reply'] ?? '']) === 1
				? $this->goto() : $this->dialog('回复失败！');
	}

	//记录
	function patch_record(string $hash, string $userid)
	{
		$this->webapp->record($hash, TRUE) && ($this->admin || $this->uid === 'linb')
			? ($this->webapp->user_sync($userid) || $this->goto())
			: $this->dialog('回调记录失败！');
	}
	function get_record_recharge(string $type = NULL, int $page = 1)
	{
		$conds = [[]];
		if ($type)
		{
			$conds[0][] = 'type=?s';
			$conds[] = $type;
		}
		else
		{
			$conds[0][] = 'type IN("vip","coin","game")';
		}
		if ($userid = $this->webapp->query['userid'] ?? '')
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}
		if ($result = $this->webapp->query['result'] ?? '')
		{
			$conds[0][] = 'result=?s';
			$conds[] = $result;
		}

		if ($datefrom = $this->webapp->query['datefrom'] ?? '')
		{
			$conds[0][] = 'mtime>=?s';
			$conds[] = strtotime($datefrom);
		}
		if ($dateto = $this->webapp->query['dateto'] ?? '')
		{
			$conds[0][] = 'mtime<=?s';
			$conds[] = strtotime($dateto) + 68399;
		}

		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->records(...$conds)->paging($page), function($table, $value, $recordtype)
		{
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell($value['hash']);
			$table->cell()->append('a', [$value['userid'], 'href' => "?control/record-recharge,userid:{$value['userid']}"]);
			$table->cell($value['cid']);
			$table->cell([number_format($value['fee']), 'style' => 'text-align:right']);
			$table->cell($recordtype[$value['type']]);
			$table->cell([base::record_results[$value['result']], 'style' => match ($value['result'])
			{
				'success' => 'color:green',
				'failure' => 'color:red',
				default => 'color:blue'
			}]);
			$table->cell()->append('a', ['回调记录',
				'href' => "?control/record,hash:{$value['hash']},userid:{$value['userid']}",
				'data-method' => 'patch',
				'data-bind' => 'click',
				'data-dialog' => '回调记录将纳入统计，确定回调记录？']);

		}, $recordtype = ['vip' => '会员', 'coin' => '金币', 'game' => '游戏']);
		$table->fieldset('时间', 'HASH', '用户ID', '渠道ID', '金额', '类型', '结果', '回调记录');
		$table->header('用户充值 %d 项', $table->count());
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $userid,
			'style' => 'padding:2px;width:8rem',
			'placeholder' => '用户ID',
			'onkeydown' => 'event.keyCode==13&&g({userid:this.value||null,page:null})'
		]);
		$table->bar->select(['' => '全部'] + $recordtype)
			->setattr(['onchange' => 'g({type:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($type);
		$table->bar->select(['' => '状态'] + base::record_results)
			->setattr(['onchange' => 'g({result:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($result);

		$table->bar->append('input', ['type' => 'date',
			'value' => $datefrom,
			'style' => 'margin-left:.6rem;padding:.1rem',
			'onchange' => 'g({datefrom:this.value||null})']);
		$table->bar->append('input', ['type' => 'date',
			'value' => $dateto,
			'style' => 'margin:0 .6rem;padding:.1rem',
			'onchange' => 'g({dateto:this.value||null})']);
		$all = $this->webapp->mysql->records(...$conds)->select('SUM(fee)')->value() ?? 0;
		$table->bar->append('span', sprintf('总计：%s', number_format($all)));
	}
	function get_record_video(int $page = 1)
	{
		$conds = [['type="video"']];
		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->records(...$conds)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell($value['hash']);
			$table->cell($value['userid']);
			$table->cell($value['cid']);
			$table->cell($value['fee']);
		});
		$table->fieldset('时间', 'HASH', '用户ID', '渠道ID', '金币');
		$table->header('用户购买视频 %d 项', $table->count());
		$table->paging($this->webapp->at(['page' => '']));
		
		$table->bar->append('input', ['type' => 'date']);
		$table->bar->append('span', ' - ');
		$table->bar->append('input', ['type' => 'date']);
	}
	function get_record_exchange_balance(int $page = 1)
	{
		$conds = [['type="exchange" AND ext->>"$.vtid" = "user_exchange"']];
		if ($userid = $this->webapp->query['userid'] ?? '')
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}
		if ($result = $this->webapp->query['result'] ?? '')
		{
			$conds[0][] = 'result=?s';
			$conds[] = $result;
		}
		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$exchange = $this->webapp->mysql->records(...$conds)->paging($page);
		$table = $this->main->table($exchange, function($table, $value)
		{
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell($value['userid']);
			$table->cell(number_format($value['fee']));
			$table->cell(json_decode($value['ext'], TRUE)['trc']);

			$action = $table->cell();
			if ($value['result'] === 'pending')
			{
				$action->append('a', ['完成',
					'href' => "?control/record-exchange-balance,hash:{$value['hash']},result:success",
					'data-method' => 'patch',
					'data-bind' => 'click'
				]);
				$action->append('span', ' | ');
				$action->append('a', ['拒绝',
					'href' => "?control/record-exchange-balance,hash:{$value['hash']},result:failure",
					'data-method' => 'patch',
					'data-bind' => 'click'
				]);
			}
			else
			{
				$action->text(base::record_results[$value['result']]);
			}
		});
		$table->fieldset('创建时间', '用户ID', '提现', 'TRC', '状态');
		$table->header('余额提现');
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $userid,
			'style' => 'padding:2px;width:8rem',
			'placeholder' => '用户ID',
			'onkeydown' => 'event.keyCode==13&&g({userid:this.value||null,page:null})'
		]);
		$table->bar->select(['' => '全部'] + base::record_results)
			->setattr(['onchange' => 'g({result:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($result);
	}
	function patch_record_exchange_balance(string $hash, string $result)
	{
		$this->webapp->record($hash, $result === 'success')
			? $this->goto()
			: $this->dialog('操作失败！');

	}


	function get_record_exchange_game(int $page = 1)
	{
		$conds = [['type="exchange" AND ext->>"$.vtid" = "game_exchange"']];
		if ($userid = $this->webapp->query['userid'] ?? '')
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}
		if ($result = $this->webapp->query['result'] ?? '')
		{
			$conds[0][] = 'result=?s';
			$conds[] = $result;
		}
		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$exchange = $this->webapp->mysql->records(...$conds)->paging($page);
		$table = $this->main->table($exchange, function($table, $value)
		{
			$ext = json_decode($value['ext'], TRUE);
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell($value['hash']);
			$table->cell($value['userid']);
			$table->cell(number_format($value['fee']));


			$table->cell()->details('详细')->append('pre', json_encode($ext,
				JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));

			$action = $table->cell();
			if ($value['result'] === 'pending')
			{
				$action->append('a', ['允许提现',
					'href' => "?control/record-exchange-game,hash:{$value['hash']},result:success",
					'data-method' => 'patch',
					'data-bind' => 'click'
				]);
				$action->append('span', ' | ');
				$action->append('a', ['退回分数',
					'href' => "?control/record-exchange-game,hash:{$value['hash']},result:failure",
					'data-method' => 'patch',
					'data-bind' => 'click'
				]);
			}
			else
			{
				$action->text(base::record_results[$value['result']]);
			}
		});
		$table->fieldset('创建时间', 'HASH', '用户ID', '提现', '扩展数据', '状态');
		$table->header('游戏提现');
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $userid,
			'style' => 'padding:2px;width:8rem',
			'placeholder' => '用户ID',
			'onkeydown' => 'event.keyCode==13&&g({userid:this.value||null,page:null})'
		]);
		$table->bar->select(['' => '全部'] + base::record_results)
			->setattr(['onchange' => 'g({result:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($result);
		$table->bar->append('a', isset($this->webapp->query['balance'])
			? [sprintf('代理游戏余额：%s', number_format($this->webapp->game_balance())),
				'href' => $this->webapp->at(['balance' => FALSE]),
				'style' => 'margin-left:.6rem']
			: ['显示代理游戏余额',
				'href' => $this->webapp->at(['balance' => 1]),
				'style' => 'margin-left:.6rem']);
	}
	function patch_record_exchange_game(string $hash, string $result)
	{
		$this->webapp->record($hash, $result === 'success')
			? $this->goto()
			: $this->dialog('操作失败！');

		
		// $this->webapp->record($hash, $result === 'success')
		// 	? $this->goto()
		// 	: $this->dialog('操作失败！');

		//$this->webapp->record

		//$this->webapp->remote($this->webapp['app_sync_call'], 'sync_user', [$id]);

	}

}