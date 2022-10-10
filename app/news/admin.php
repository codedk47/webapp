<?php
class webapp_router_admin extends webapp_echo_html
{
	function __construct(interfaces $webapp)
	{
		parent::__construct($webapp);
		if (empty($this->admin()))
		{
			if (str_ends_with($webapp->method, 't_home'))
			{
				if ($webapp->method === 'post_home'
					&& webapp_echo_html::form_sign_in($webapp)->fetch($admin)
					&& $this->admin($signature = $webapp->signature($admin['username'], $admin['password']))) {
					$webapp->response_cookie($webapp['admin_cookie'], $signature);
					$webapp->response_refresh(0);
				}
				else
				{
					webapp_echo_html::form_sign_in($this->main);
				}
				return $webapp->response_status(200);
			}
			$this->main->setattr(['Unauthorized', 'style' => 'font-size:2rem']);
			return $webapp->response_status(401);
		}
		$this->xml->head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/news/admin.css']);
		$this->xml->head->append('script', ['src' => '/webapp/app/news/admin.js']);
		$nav = $this->nav([
			['Home', '?admin'],
			['Tags', '?admin/tags'],
			['Resources', '?admin/resources'],
			['Accounts', '?admin/accounts'],
			['Ads', '?admin/ads'],
			['Extensions', [
				['Reports（通告、报告，合并通用）', '?admin/reports'],
				['Web Socket Chat System（在线客服）', '?admin/wschat'],
				['Comments（评论记录，暂时没用）', '?admin/comments'],
				['Set Tags（设置首页标签，包含哪些点播）', '?admin/settags'],
				['Set Vods（设置点播集合，类型资源）', '?admin/setvods'],
				['Stat Bills（统计账单，包括资源购买）', '?admin/statbills'],
				['Orderstat（订单统计，可以查看支付情况）', '?admin/orderstat'],
				['Orders（支付中心，订单数据，超级管理）', '?admin/orders'],
				['Payaisle（支付通道，设置修改，超级管理）', '?admin/payaisle'],
				['Unitsets（单位设置，开设需要后台的单位）', '?admin/unitsets'],
				['Unitcost（单位成本，统计计算单位费用）', '?admin/unitcost'],
				['Runstatus（服务器状态，轻点）', '?admin/runstatus']
			]]
		]);
		if ($webapp->admin[2])
		{
			$this->title($this->webapp->site);
			$nav->ul->insert('li', 'first')->setattr(['style' => 'margin-left:1rem'])->select($this->webapp['app_site'])->selected($this->webapp->site)->setattr(['onchange' => 'location.reload(document.cookie=`app_site=${this.value}`)']);
			$nav->ul->append('li')->append('a', ['Admin', 'href' => '?admin/admin']);
		}
		else
		{
			$this->title('Admin');
			$nav->ul->append('li')->append('a', ['Setpwd', 'href' => '?admin/setpwd']);
		}
		$nav->ul->append('li')->append('a', ['Logout', 'href' => "javascript:void(document.cookie='{$webapp['admin_cookie']}=0',location.href='?admin');", 'style' => 'color:darkred']);
	}
	function admin(?string $signature = NULL)
	{
		if (func_num_args() === 0)
		{
			$signature = $this->webapp->request_cookie($this->webapp['admin_cookie']);
		}
		if ($admin = $this->webapp->admin($signature))
		{
			$admin[2] = TRUE;
			$this->webapp->admin = $admin;
			return $admin;
		}
		return $this->webapp->authorize($signature, function(string $uid, string $pwd, int $st):array
		{
			if ($st > $this->webapp->time(-$this->webapp['admin_expire'])
				&& ($admin = $this->webapp->mysql->admin('WHERE uid=?s AND pwd=?s LIMIT 1', $uid, $pwd)->array())
				&& $this->webapp->mysql->admin('WHERE uid=?s LIMIT 1', $uid)
					->update(['lasttime' => $this->webapp->time, 'lastip' => $this->webapp->clientiphex])) {
					$this->webapp->site = $admin['site'];
					$this->webapp->admin = [$admin['uid'], $admin['pwd'], FALSE];
					return $admin;
			}
			return [];
		});
	}
	function warn(string $text)
	{
		$this->main->append('h4', $text);
	}
	function okay(string $goto = NULL):int
	{
		$this->webapp->response_location($goto
			?? $this->webapp->request_referer('?'));
		return 302;
	}
	function adminlists():array
	{
		$users = [$this->webapp['admin_username'] => "admin(超级管理)"];
		foreach ($this->webapp->mysql->admin as $admin)
		{
			$users[$admin['uid']] = "{$admin['uid']}({$admin['name']})";
		}
		return $users;
	}
	//统计
	function post_home()
	{
	}
	function get_home(string $ym = '', string $unit = NULL, string $type = NULL, string $admin = NULL)
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode('-', $ym = date('Y-m'));
		if ($unit)
		{
			$hours = range(0, 23);
			$table = $this->main->table($this->webapp->mysql->unitstats('WHERE left(date,7)=?s AND unit=?s ORDER BY date DESC', $ym, $unit), function($table, $stat)
			{
				$t1 = $table->tbody->append('tr');
				$t2 = $table->tbody->append('tr');
				$t3 = $table->tbody->append('tr');
				$t4 = $table->tbody->append('tr');
				$t5 = $table->tbody->append('tr');
				$t6 = $table->tbody->append('tr');

				$t1->append('td', [$stat['date'], 'rowspan' => 6]);

				$t1->append('td', '浏览');
				$t2->append('td', '独立');
				$t3->append('td', '登录');
				$t4->append('td', '注册');
				$t5->append('td', '下载');
				$t6->append('td', '激活');

				$t1->append('td', number_format($stat['pv']));
				$t2->append('td', number_format($stat['ua']));
				$t3->append('td', number_format($stat['lu']));
				$t4->append('td', number_format($stat['ru']));
				$t5->append('td', number_format($stat['dc']));
				$t6->append('td', number_format($stat['ia']));

				foreach (json_decode($stat['details'], TRUE) as $details)
				{
					$t1->append('td', number_format($details['pv']));
					$t2->append('td', number_format($details['ua']));
					$t3->append('td', number_format($details['lu']));
					$t4->append('td', number_format($details['ru']));
					$t5->append('td', number_format($details['dc']));
					$t6->append('td', number_format($details['ia']));
				}
			});
			$table->fieldset('日期', '统计', '总和', ...$hours);
			$table->header($unit)->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);
			$table->xml['class'] = 'webapp-stateven';
			return;
		}

		$days = range(1, date('t', strtotime($ym)));

		$unitorders = [NULL => array_fill(0, 32, ['count'=> 0, 'fee' => 0])];
		foreach ($this->webapp->mysql('SELECT orders.day AS day,orders.order_fee AS fee,accounts.unit AS unit FROM orders INNER JOIN (accounts) ON (accounts.uid=orders.notify_url) WHERE orders.pay_user=?i AND orders.status!="unpay" AND tym=?i', $this->webapp->site, $y.$m) as $order)
		{
			++$unitorders[NULL][0]['count'];
			$unitorders[NULL][0]['fee'] += $order['fee'];
			++$unitorders[NULL][$order['day']]['count'];
			$unitorders[NULL][$order['day']]['fee'] += $order['fee'];
			if (array_key_exists($order['unit'], $unitorders) === FALSE)
			{
				$unitorders[$order['unit']] = array_fill(0, 32, ['count'=> 0, 'fee' => 0]);
			}
			++$unitorders[$order['unit']][0]['count'];
			$unitorders[$order['unit']][0]['fee'] += $order['fee'];
			++$unitorders[$order['unit']][$order['day']]['count'];
			$unitorders[$order['unit']][$order['day']]['fee'] += $order['fee'];
		}

		$cond = ['WHERE left(date,7)=?s', $ym];

		if ($type || $admin)
		{
			$unitcond = ['WHERE 1'];
			if ($type)
			{
				$unitcond[0] .= ' AND type=?s';
				$unitcond[] = $type;
			}
			if ($admin)
			{
				$unitcond[0] .= ' AND admin=?s';
				$unitcond[] = $admin;
			}
			if ($units = $this->webapp->mysql->unitsets(...$unitcond)->column('unit'))
			{
				$cond[0] .= ' AND unit IN(?S)';
				$cond[] = $units;
			}
			else
			{
				$cond[0] .= ' AND 0';
			}
		}

		$stat = $this->webapp->mysql->unitstats(...$cond)->statmonth($ym, 'unit', 'right(date,2)', [
			'SUM(IF({day}=0 OR right(date,2)={day},pv,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ua,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},lu,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ru,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},dc,0))',
			'SUM(IF({day}=0 OR right(date,2)={day},ia,0))',
		], 'ORDER BY $1$0 DESC LIMIT 10');

		$table = $this->main->table($stat, function($table, $stat, $days, $ym, $unitorders)
		{

			$t1 = $table->tbody->append('tr');
			$t2 = $table->tbody->append('tr');
			$t3 = $table->tbody->append('tr');
			$t4 = $table->tbody->append('tr');
			$t5 = $table->tbody->append('tr');
			$t6 = $table->tbody->append('tr');
			$t7 = $table->tbody->append('tr');
			$t8 = $table->tbody->append('tr');
			if ($stat['unit'])
			{
				$t1->append('td', ['rowspan' => 8])->append('a', [$stat['unit'], 'href' => "?admin,ym:{$ym},unit:{$stat['unit']}"]);
			}
			else
			{
				$t1->append('td', ['汇总', 'rowspan' => 8]);
			}

			$t1->append('td', '浏览');
			$t2->append('td', '独立');
			$t3->append('td', '登录');
			$t4->append('td', '注册');
			// $t5->append('td', '下载');
			// $t6->append('td', '激活');
			// $t7->append('td', '订单');
			// $t8->append('td', '金额');

			$t5->append('td', '点击量');
			$t6->append('td', '下载量');
			$t7->append('td', '订单数');
			$t8->append('td', '订单金额');

			$t1->append('td', number_format($stat['$0$0']));
			$t2->append('td', number_format($stat['$1$0']));
			$t3->append('td', number_format($stat['$2$0']));
			$t4->append('td', number_format($stat['$3$0']));
			$t5->append('td', number_format($stat['$4$0']));
			$t6->append('td', number_format($stat['$5$0']));
			if (isset($unitorders[$stat['unit']]))
			{
				$t7->append('td', number_format($unitorders[$stat['unit']][0]['count']));
				$t8->append('td', number_format($unitorders[$stat['unit']][0]['fee'] * 0.01));
			}
			else
			{
				$t7->append('td', 0);
				$t8->append('td', 0);
			}

			foreach ($days as $i)
			{
				$t1->append('td', number_format($stat["\$0\${$i}"]));
				$t2->append('td', number_format($stat["\$1\${$i}"]));
				$t3->append('td', number_format($stat["\$2\${$i}"]));
				$t4->append('td', number_format($stat["\$3\${$i}"]));
				$t5->append('td', number_format($stat["\$4\${$i}"]));
				$t6->append('td', number_format($stat["\$5\${$i}"]));
				if (isset($unitorders[$stat['unit']]))
				{
					$t7->append('td', number_format($unitorders[$stat['unit']][$i]['count']));
					$t8->append('td', number_format($unitorders[$stat['unit']][$i]['fee'] * 0.01));
				}
				else
				{
					$t7->append('td', 0);
					$t8->append('td', 0);
				}
			}

		}, $days, $ym, $unitorders);
		$table->fieldset('单位', '统计', '总和', ...$days);
		$header = $table->header('');
		$header->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);
		$header->select(['' => '全部类型', 'cpc' => 'CPC', 'cpa' => 'CPA', 'cps' => 'CPS', 'cpm' => 'CPM'])
			->setattr(['onchange' => 'g({type:this.value===""?null:this.value})'])->selected($type);
		$header->select(['' => '全部账号'] + $this->adminlists())
			->setattr(['onchange' => 'g({admin:this.value===""?null:this.value})'])->selected($admin);
		$table->xml['class'] = 'webapp-stateven';
	}
	//标签
	function form_tag($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('name / level / count / click');
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('level', 'number', ['style' => 'width:8rem', 'min' => 0, 'required' => NULL]);
		$form->field('count', 'number', ['style' => 'width:8rem', 'min' => 0, 'required' => NULL]);
		$form->field('click', 'number', ['style' => 'width:8rem', 'min' => 0, 'required' => NULL]);

		$form->fieldset('seat');
		$form->field('seat', 'text', ['style' => 'width:40rem']);

		$form->fieldset('alias');
		$form->field('alias', 'text', ['style' => 'width:40rem', 'required' => NULL]);

		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}
	function post_tag_create()
	{
		if ($this->webapp->admin[2]
			&& $this->form_tag($this->webapp)->fetch($tag)
			&& $this->webapp->mysql->tags->insert($tag += ['hash' => substr($this->webapp->randhash(TRUE), 6), 'time' => $this->webapp->time])
			&& $this->webapp->call('saveTag', $this->webapp->tag_xml($tag))) {
			return $this->okay("?admin/tags,search:{$tag['hash']}");
		}
		$this->warn($this->webapp->admin[2] ? '标签创建失败！' : '需要全局管理权限！');
	}
	function get_tag_create()
	{
		$this->form_tag($this->main)->echo([
			'level' => 0,
			'count' => 0,
			'click' => 0
		]);
	}
	function get_tag_delete(string $hash)
	{
		if ($this->webapp->mysql->resources('WHERE FIND_IN_SET(?s,tags)', $hash)->count())
		{
			return $this->warn('该标签存在资源，无法删除！');
		}
		if ($this->webapp->admin[2]
			&& $this->webapp->call('delTag', $hash)
			&& $this->webapp->mysql->tags->delete('WHERE hash=?s', $hash)) {
			return $this->okay("?admin/tags");
		}
		$this->warn($this->webapp->admin[2] ? '标签删除失败！' : '需要全局管理权限！');
	}
	function post_tag_update(string $hash)
	{
		$tag = $this->webapp->mysql->tags('where hash=?s', $hash)->array();
		if ($tag
			&& $this->webapp->admin[2]
			&& $this->form_tag($this->webapp)->fetch($tag)
			&& $this->webapp->mysql->tags('where hash=?s', $hash)->update($tag)
			&& $this->webapp->call('saveTag', $this->webapp->tag_xml($tag))) {
			return $this->okay("?admin/tags,search:{$hash}");
		}
		$this->warn($this->webapp->admin[2] ? '标签更新失败！' : '需要全局管理权限！');
	}
	function get_tag_update(string $hash)
	{
		$this->form_tag($this->main)->echo($this->webapp->mysql->tags('where hash=?s', $hash)->array());
	}
	function get_tags(string $search = NULL, int $page = 1)
	{
		$cond = [];
		if (is_string($search))
		{
			strlen($search) === 4 && trim($search, webapp::key) === ''
				? array_push($cond, 'where hash=?s ??', $search)
				: array_push($cond, 'where name=?s or alias like ?s ??', $search = urldecode($search), "%{$search}%");
		}
		else
		{
			$cond[] = '??';
		}
		$cond[] = 'ORDER BY level ASC,count DESC,click DESC';
		$table = $this->main->table($this->webapp->mysql->tags(...$cond)->paging($page), function($table, $tag)
		{
			$table->row();
			$table->cell()->append('a', ['❌',
				'href' => "?admin/tag-delete,hash:{$tag['hash']}",
				'onclick' => 'return confirm(`Delete Tag ${this.dataset.name}`)',
				'data-name' => $tag['name']]);
			$table->cell()->append('a', [$tag['hash'], 'href' => "?admin/tag-update,hash:{$tag['hash']}"]);
			$table->cell(date('Y-m-d H:i:s', $tag['time']));
			$table->cell($tag['level']);
			$table->cell(number_format($tag['count']));
			$table->cell(number_format($tag['click']));
			$table->cell($tag['seat']);
			$table->cell()->append('a', [$tag['name'], 'href' => "?admin/resources,tag:{$tag['hash']}"]);
			$table->cell($tag['alias']);
		});
		$table->fieldset('❌', 'hash', 'time', 'level', 'count', 'click', 'seat', 'name', 'alias');
		$table->header('Found %s item', number_format($table->count()));
		$table->button('Create Tag', ['onclick' => 'location.href="?admin/tag-create"']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	//资源
	function form_resource($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('封面图片 / 类型 / 预览');
		$form->field('piccover', 'file');
		$form->field('type', 'select', ['options' => $this->webapp['app_restype']]);
		$form->field('preview_start', 'time', ['value' => '00:00:00', 'step' => 1]);
		$form->field('preview_end', 'time', ['value' => '00:00:10', 'step' => 1]);

		$form->button('观看预览')['onclick'] = 'g({preview:btoa(`${this.previousElementSibling.previousElementSibling.value},${this.previousElementSibling.value}`)})';
		$form->button('观看完整')['onclick'] = 'g({preview:null})';

		$form->fieldset('name / actors');
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->field('actors', 'text', ['value' => '素人', 'required' => NULL]);

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

		$form->fieldset('require(下架：-2、会员：-1、免费：0、金币)');
		$form->field('require', 'number', ['min' => -2, 'required' => NULL]);
		$form->fieldset();
		$form->button('Update Resource', 'submit');
		return $form;
	}
	function post_resource_update(string $hash)
	{
		if ($this->form_resource($this->webapp)->fetch($resource)
			&& $this->webapp->mysql->resources('WHERE hash=?s AND sync="finished" LIMIT 1', $hash)->array()
			&& $this->webapp->resource_update($hash, $resource)
			&& ($resource = $this->webapp->mysql->resources('WHERE hash=?s LIMIT 1', $hash)->array())
			&& $this->webapp->call('saveRes', $this->webapp->resource_xml($resource))) {
			$sync = 'finished';
			if ($piccover = $this->webapp->request_uploadedfile('piccover', 1)[0] ?? [])
			{
				//这里可能要加资源归属权后才能修改图片
				if (is_object($object = webapp_client_http::open("{$this->webapp['app_resdomain']}/?updatecover/{$hash}", [
					'autoretry' => 2,
					'headers' => [
						'Authorization' => 'Bearer ' . $this->webapp->signature($this->webapp['admin_username'], $this->webapp['admin_password'])
					],
					'type' => 'application/octet-stream',
					'data' => file_get_contents($piccover['file'])
				])->content()) && isset($object->resource)) {
				$sync = 'waiting';
				};
			}
			return $this->okay("?admin/resources,sync:{$sync},search:{$hash}");
		}
		$this->warn('资源更新失败，请确认资源同步状态完成！');
	}
	function get_resource_update(string $hash)
	{
		if ($resource = $this->webapp->resource_get($hash))
		{
			$resource['preview_start'] = date('H:i:s', $preview_start = ($resource['preview'] >> 16) + 57600);
			$resource['preview_end'] = date('H:i:s', ($resource['preview'] & 0xffff) + $preview_start);

			if ($resource['sync'] === 'finished')
			{
				$this->xml->head->append('script', ['src' => '/webapp/app/news/hls.min.js']);
				$this->xml->head->append('script', ['src' => '/webapp/app/news/wplayer.js']);
				$playvideo = $this->main->append('webapp-video', [
					'style' => 'display:block;width:854px;height:480px',
					'data-load' => sprintf("{$this->webapp['app_resoutput']}%s/{$resource['hash']}", date('ym', $resource['time'])),
					'muted' => NULL,
					'autoplay' => NULL,
					'controls' => NULL
				]);
				if (isset($this->webapp->query['preview']))
				{
					[$resource['preview_start'], $resource['preview_end']] = explode(',', base64_decode($this->webapp->query['preview']));
					$preview_start = explode(':', $resource['preview_start']);
					$preview_start = mktime(8 + $preview_start[0], $preview_start[1], $preview_start[2], 1, 1, 1970);
					$preview_end = explode(':', $resource['preview_end']);
					$preview_end = mktime(8 + $preview_end[0], $preview_end[1], $preview_end[2], 1, 1, 1970);
					if ($preview_end >= $preview_start)
					{
						$playvideo['data-preview'] = sprintf('%d,%d', $preview_start, $preview_end - $preview_start);
					}
				}
				
			}
			$form = $this->form_resource($this->main);

			$form->echo($resource);
		}
	}
	function post_resource_upload()
	{
		return $this->okay("?admin/resource-upload");
	}
	function get_resource_delete(string $hash)
	{
		if ($this->webapp->resource_delete($hash)
			&& $this->webapp->call('delRes', $hash)) {
			return $this->okay();
		}
		$this->warn('资源删除失败！');
	}
	function get_resource_upload()
	{
		$dl = $this->main->append('dl')->setattr(['class' => 'mrp']);
		$dt = $dl->append('dt');
		$dt->append('button', ['添加上传任务', 'onclick' => 'mrp(this)']);

		$details = $dt->details('')->setattr(['open' => NULL]);
		$details->summary->progress();
		$this->webapp->form_resourceupload($details);

	}
	function get_resources(string $search = NULL, int $page = 1)
	{
		$cond = ['WHERE FIND_IN_SET(?s,site) AND sync=?s', $this->webapp->site,
			$sync = $this->webapp->query['sync'] ?? 'finished'];
		if (is_string($search))
		{
			$cond[0] .= ' AND (hash=?s or data->>\'$."?i".name\' like ?s)';
			array_push($cond, $search = urldecode($search), $this->webapp->site, "%{$search}%");
		}
		if (strlen($tag = $this->webapp->query['tag'] ?? ''))
		{
			$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
			$cond[] = $tag;
		}
		if (strlen($type = $this->webapp->query['type'] ?? ''))
		{
			$cond[0] .= ' AND type=?s';
			$cond[] = $type;
		}
		if (strlen($require = $this->webapp->query['require'] ?? ''))
		{
			$cond[0] .= ' AND data->>\'$."?i".require\'??';
			$cond[] = $this->webapp->site;
			$cond[] = match ($require)
			{
				'closed' => '=-2',
				'member' => '=-1',
				'free' => '=0',
				'play' => '>0',
				default => '>-3'
			};
		}

		$cond[0] .= match ($sort = $this->webapp->query['sort'] ?? '')
		{
			'favorite' => $this->webapp->mysql->format(' ORDER BY CAST(data->>\'$."?i".favorite\' AS UNSIGNED) DESC', $this->webapp->site),
			'view' => $this->webapp->mysql->format(' ORDER BY CAST(data->>\'$."?i".view\' AS UNSIGNED) DESC', $this->webapp->site),
			'like' => $this->webapp->mysql->format(' ORDER BY CAST(data->>\'$."?i".like\' AS UNSIGNED) DESC', $this->webapp->site),
			default => ' ORDER BY time DESC'
		};

		$table = $this->main->table($this->webapp->mysql->resources(...$cond)->paging($page), function($table, $res, $type)
		{
			$table->row();
			$table->cell(['width' => 'width:100%;'])->append('a', ['❌',
				'href' => "?admin/resource-delete,hash:{$res['hash']}",
				'onclick' => 'return confirm(`Delete Resource ${this.dataset.hash}`)',
				'data-hash' => $res['hash']]);
			$table->cell()->append('a', [$res['hash'], 'href' => "?admin/resource-update,hash:{$res['hash']}"]);
			$table->cell(date('Y-m-d\\TH:i:s', $res['time']));
			$table->cell(sprintf('%s - %s',
				date('G:i:s', $start = ($res['preview'] >> 16) + 57600),
				date('G:i:s', ($res['preview'] & 0xffff) + $start)));
			$table->cell(date('G:i:s', $res['duration'] + 57600));
			$table->cell($type[$res['type']]);
			$data = json_decode($res['data'], TRUE)[$this->webapp->site] ?? [];
			$table->cell([-2 => '下架', -1 => '会员', 0 => '免费'][$require = $data['require'] ?? 0] ?? $require);
			$table->cell(number_format($data['favorite']));
			$table->cell(number_format($data['view']));
			$table->cell(number_format($data['like']));
			$table->cell()->append('div', [
				'style' => 'width:30rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis'
			])->append('a', [$data['name'], 'href' => "?admin/resource-update,hash:{$res['hash']}"]);
			$table->cell()->append('a', ['❓', 'href' => '#', 'download' => "{$res['hash']}.jpg",
				'data-cover' => sprintf("{$this->webapp['app_resoutput']}%s/{$res['hash']}/cover", date('ym', $res['time']))]);
			//$table->cell()->append('a', ['下载预览', 'href' => "{$this->webapp['app_resdomain']}?resourcepreview/{$res['hash']}"]);
		}, $this->webapp['app_restype']);
		$table->fieldset('❌', 'hash', 'time', 'preview', 'duration', 'type', 'require', 'favorite', 'view', 'like', 'name', '❓');
		$table->header('Found %s item', number_format($table->count()));
		$table->button('Upload Resource', ['onclick' => 'location.href="?admin/resource-upload"']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->bar->select(['' => '全部标签'] + $this->webapp->selecttags())->setattr(['onchange' => 'g({tag:this.value===""?null:this.value})'])->selected($tag);
		$table->bar->select(['' => '全部类型'] + $this->webapp['app_restype'])->setattr(['onchange' => 'g({type:this.value===""?null:this.value})'])->selected($type);
		$table->bar->select([
			'' => '任何要求',
			'closed' => '下架',
			'member' => '会员',
			'free' => '免费',
			'play' => '收费'
		])->setattr(['onchange' => 'g({require:this.value||null})'])->selected($require);
		$table->bar->select([
			'' => '最新上传',
			'favorite' => '最多收藏',
			'view' => '最多观看',
			'like' => '最多点赞'
		])->setattr(['onchange' => 'g({sort:this.value||null})'])->selected($sort);
		$table->bar->select([
			'finished' => '完成',
			'waiting' => '等待',
			'exception' => '异常'
		])->setattr(['onchange' => 'g({sync:this.value})'])->selected($sync);
		$table->paging($this->webapp->at(['page' => '']));
		$this->main->append('script')->cdata(<<<'JS'
document.querySelectorAll('a[data-cover]').forEach(node =>
{
	node.onmouseenter = function(event)
	{
		if (!this.img)
		{
			this.img = new Image;
			loader(this.dataset.cover, null, 'application/octet-stream').then(blob => this.img.src = this.href = URL.createObjectURL(blob));
			this.img.style.cssText = 'position:absolute;border:.1rem solid black;max-width:300px';
			this.parentNode.parentNode.appendChild(this.img);
		}
		else
		{
			this.img.style.display = 'block';
		}
		this.img.style.top = event.pageY > window.outerHeight * 0.5
			? `${this.getBoundingClientRect().bottom - this.img.offsetHeight - this.offsetHeight}px`
			: `${this.getBoundingClientRect().bottom}px`;
	}
	node.onmouseleave = function()
	{
		this.img.style.display = 'none';
	}
});
JS);
	}
	//账户
	function form_account($ctx):webapp_form
	{
		$form = new webapp_form($ctx);

		$form->fieldset('name / password');
		$form->field('name', 'text');
		$form->field('pwd', 'text');

		$form->fieldset('expire / balance');
		$form->field('expire', 'date', [],
			fn($v, $i)=>$i?strtotime($v):date('Y-m-d', $v));
		$form->field('balance', 'number', ['min' => 0]);

		$form->fieldset('favorite');
		$form->field('favorite', 'textarea', ['cols' => 120, 'rows' => 6]);

		$form->fieldset('history');
		$form->field('history', 'textarea', ['cols' => 120, 'rows' => 6]);

		$form->fieldset();
		$form->button('Update Account', 'submit');
		return $form;
	}
	function post_account_update(string $uid)
	{
		$acc = $this->webapp->mysql->accounts('where site=?i and uid=?s', $this->webapp->site, $uid)->array();
		if ($acc
			&& $this->form_account($this->webapp)->fetch($acc)
			&& $this->webapp->mysql->accounts('where site=?i and uid=?s', $this->webapp->site, $uid)->update($acc)
			&& $this->webapp->call('saveUser', $this->webapp->account_xml($acc))) {
			return $this->okay('?admin/accounts');
		}
		$this->warn('账户更新失败！');
	}
	function get_account_update(string $uid)
	{
		if ($this->webapp->mysql->accounts('where site=?i and uid=?s', $this->webapp->site, $uid)->fetch($account))
		{
			$form = $this->form_account($this->main);
			$form->xml->fieldset->legend = $this->webapp->signature($account['uid'], $account['pwd']);
			$form->echo($account);
		}
		
	}
	function get_accounts($search = NULL, int $page = 1)
	{
		$cond = ['where site=?i', $this->webapp->site];
		if ($uint = $this->webapp->query['unit'] ?? '')
		{
			$cond[0] .= ' and unit=?s';
			$cond[] = $uint;
		}
		if ($date = $this->webapp->query['date'] ?? '')
		{
			$cond[0] .= ' and date=?s';
			$cond[] = $date;
		}
		if (is_string($search))
		{
			if (is_numeric($search))
			{
				$cond[0] .= ' and phone=?s';
				$cond[] = $search;
			}
			else
			{
				if (strlen($search) === 10 && trim($search, webapp::key) === '')
				{
					$cond[0] .= ' and uid=?s';
					$cond[] = $search;
				}
				else
				{
					$cond[0] .= ' and (uid like ?s or name like ?s)';
					array_push($cond, '%' . ($search = urldecode($search)) . '%', "%{$search}%");
				}
			}
		}
		$cond[0] .= ' order by time desc';
		$table = $this->main->table($this->webapp->mysql->accounts(...$cond)->paging($page), function($table, $acc)
		{
			$table->row();
			$table->cell()->append('a', [$acc['uid'], 'href' => "?admin/account-update,uid:{$acc['uid']}"]);
			$table->cell($acc['date']);
			$table->cell(date('Y-m-d', $acc['expire']));
			$table->cell(number_format($acc['balance']));
			$table->cell(date('Y-m-d\\TH:i:s', $acc['lasttime']));
			$table->cell($this->webapp->hexip($acc['lastip']));
			$table->cell($acc['device']);
			$table->cell($acc['unit']);
			if ($acc['code'])
			{
				$table->cell()->append('a', [$acc['code'], 'href' => "?admin/accounts,search:{$acc['code']}"]);
			}
			else
			{
				$table->cell();
			}
			$table->cell($acc['did']);
			$table->cell($acc['phone']);
			$table->cell($acc['name']);
		});
		$table->fieldset('账号(uid)', '注册日期(date)', '会员过期(expire)', '余额(balance)', '最后登录时间(lasttime)', '最后登录IP(lastip)', '设备类型(device)', '单位(unit)', '邀请账号(code)', '设备ID(did)', '绑定手机(phone)', '名称(name)');
		$table->header('Found %s item', number_format($table->count()));
		$table->bar->select(['' => '全部单位', '0000' => '内部单位'] + $this->webapp->mysql->unitsets->column('name', 'unit'))
			->setattr(['onchange' => 'g({unit:this.value?this.value:null})'])->selected($uint);
		$table->bar->append('input', ['type' => 'date', 'value' => "{$date}", 'onchange' => 'g({date:this.value})']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	//广告
	function get_ads(string $search = NULL)
	{
		$cond = ['where site=?i', $this->webapp->site];
		if ($search)
		{
			$cond[0] .= ' and hash=?s';
			$cond[] = $search;
		}
		$cond[0] .= ' order by time desc';
		$table = $this->main->table($this->webapp->mysql->ads(...$cond), function($table, $ad, $week, $auth)
		{
			$table->row();
			$table->cell()->append('a', ['❌',
				'href' => "{$this->webapp['app_resdomain']}?deletead/{$ad['hash']}",
				'onclick' => 'return confirm(`Delete Ad ${this.dataset.name}`) && anchor(this)',
				'data-auth' => $auth, 'data-name' => $ad['name']]);
			$table->cell()->append('a', [$ad['hash'], 'href' => "?admin/ad-update,hash:{$ad['hash']}"]);
			$table->cell($ad['name']);
			$table->cell($ad['seat']);
			$table->cell(date('Y-m-d\TH:i:s', $ad['timestart']) . ' - ' . date('Y-m-d\TH:i:s', $ad['timeend']));
			$table->cell($ad['weekset'] ? join(',', array_map(fn($v)=>"周{$week[$v]}", explode(',', $ad['weekset']))) : '时间段');
			$table->cell($ad['count']);
			$table->cell(number_format($ad['click']));
			$table->cell(number_format($ad['view']));
			$table->cell()->append('a', [$ad['goto'], 'href' => $ad['goto'], 'target' => 'ad']);
		},
		['日', '一', '二', '三', '四', '五', '六'],
		$this->webapp->signature($this->webapp['admin_username'], $this->webapp['admin_password'], (string)$this->webapp->site));
		$table->fieldset('❌', 'hash', 'name', 'seat', 'timestart - timeend', 'weekset', 'count', 'click', 'view', 'goto');
		$table->header('Found ' . $this->webapp->mysql->ads->count() . ' item');
		$table->bar->append('button', ['Create Ad', 'onclick' => 'location.href="?admin/ad-create"']);
	}
	function get_ad_create()
	{
		$this->webapp->form_ad($this->main);
	}
	function get_ad_update(string $hash)
	{
		if ($this->webapp->mysql->ads('where site=?i and hash=?s', $this->webapp->site, $hash)->fetch($ad))
		{
			$this->webapp->form_ad($this->main, $hash)->echo($ad)->xml->fieldset->setattr([
				'style' => 'display:block;width:480px;height:280px;border:.1rem solid black;',
				'data-src' => "{$this->webapp['app_resoutput']}news/{$ad['hash']}"
			])->append('script')->cdata('const pic = document.querySelector("fieldset[data-src]");
			loader(pic.dataset.src,null,"application/octet-stream").then(b=>pic.style.background=`center/contain no-repeat url(${URL.createObjectURL(b)}) silver`)');
			
		}
	}
	//================Extensions================
	//报告
	function form_report($ctx, string $hash = NULL):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('describe');
		$form->field('describe', 'textarea', ['cols' => 78, 'rows' => 8, 'placeholder' => '通知内容', 'required' => NULL]);
		if ($hash)
		{
			$form->fieldset('promise / content');
			$form->field('promise', 'select', ['options' => [
				'resolved' => '已解决',
				'rejected' => '以拒绝'
			]]);
			$form->field('content', 'text', ['style' => 'width:640px', 'placeholder' => '回复内容']);
		}
		$form->fieldset();
		$form->button('Notification', 'submit');
		return $form;
	}
	function post_report_create()
	{
		if ($this->form_report($this->webapp)->fetch($data)
			&& $this->webapp->mysql->reports->insert($data += [
				'hash' => $this->webapp->randhash(TRUE),
				'site' => $this->webapp->site,
				'time' => $this->webapp->time,
				'ip' => $this->webapp->clientiphex,
				'promise' => 'resolved',
				'account' => NULL])
			&& $this->webapp->call('saveRep', $this->webapp->report_xml($data))) {
			return $this->okay('?admin/reports');
		}
		$this->warn('通告发布失败！');
	}
	function get_report_create()
	{
		$this->form_report($this->main);
	}
	function post_report(string $hash)
	{
		if ($this->form_report($this->webapp, $hash)->fetch($data)
			&& $this->webapp->mysql->reports('WHERE site=?i AND hash=?s', $this->webapp->site, $hash)
				->update('`promise`=?s,`describe`=CONCAT(`describe`,?s)', $data['promise'], strlen($data['content']) ? "\n{$data['content']}" : '')
			&& $this->webapp->call('saveRep', $this->webapp->report_xml(
				$this->webapp->mysql->reports('WHERE site=?i AND hash=?s', $this->webapp->site, $hash)->array()))) {
	
			return $this->okay('?admin/reports');
		}
		$this->warn('通告更新失败！');
	}
	function get_report(string $hash)
	{
		$this->form_report($this->main, $hash)->echo($this->webapp->mysql
			->reports('WHERE site=?i AND hash=?s', $this->webapp->site, $hash)->array());
	}
	function get_reports(string $search = NULL, int $page = 1)
	{
		$cond = ['where site=?i', $this->webapp->site];
		if (is_string($search))
		{
			if (strlen($search) === 10 && trim($search, webapp::key) === '')
			{
				$cond[0] .= ' and account=?s';
				$cond[] = $search;
			}
			else
			{
				$search = urldecode($search);
				$cond[0] .= ' and `describe` like ?s';
				$cond[] = "%{$search}%";
			}
		}
		$cond[0] .= ' order by time desc';
		$table = $this->main->table($this->webapp->mysql->reports(...$cond)->paging($page), function($table, $rep)
		{
			$table->row();
			if ($rep['promise'] === 'waiting')
			{
				$table->cell()->append('a', [$rep['promise'], 'href' => "?admin/report,hash:{$rep['hash']}"]);
			}
			else
			{
				$table->cell([$rep['promise'], 'style' => [
					'resolved' => 'color:green',
					'rejected' => 'color:red'][$rep['promise']]]);
			}
			
			// $table->cell()->append('a', [$rep['promise'],
			// 	'href' => "?admin/resolve,hash:{$rep['hash']}",
			// 	'style' => "color:red"]);
			
			$table->cell(date('Y-m-d\\TH:i:s', $rep['time']));
			$table->cell($this->webapp->hexip($rep['ip']));
			if ($rep['account'])
			{
				$table->cell()->append('a', [$rep['account'], 'href' => "?admin/accounts,search:{$rep['account']}"]);
			}
			else
			{
				$table->cell('管理通告');
			}
			$table->cell($rep['describe']);
		});
		$table->fieldset('promise', 'time', 'ip', 'account', 'describe');
		$table->header('Reports, Found %s item', number_format($table->count()));
		$table->button('Create Report', ['onclick' => 'location.href="?admin/report-create"']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	//聊天
	function get_wschat()
	{
		//if ($this->webapp->admin[2] === FALSE) return $this->warn('目前测试试用阶段！');
		$this->aside['class'] = 'wschatusers';
		$this->aside->append('dl');
		$form = $this->main->form();
		$form->fieldset();
		$form->field('to', 'text', ['placeholder' => 'To']);
		$form->field('message', 'text', ['placeholder' => 'Message']);
		$form->button('Send', 'submit');
		$form->xml['class'] = 'webapp-wschat';
		$form->xml['onsubmit'] = 'return false';
		$form->xml['data-ws'] = 'wss://wschat.fasdfasd.com/' . $this->webapp->request_cookie($this->webapp['admin_cookie']);
		$this->main->append('script')->cdata("wschatinit(document.querySelector('aside>dl'),document.querySelector('form'))");
	}
	//评论
	function get_comments(string $search = NULL, int $page = 1)
	{
		$cond = ['WHERE site=?i', $this->webapp->site];
		$cond[0] .= ' ORDER BY time DESC';
		$table = $this->main->table($this->webapp->mysql->comments(...$cond)->paging($page, 12), function($table, $comm)
		{
			$table->row();
			$table->cell()->append('a', ['❌', 'href' => "#"]);
			$table->cell(date('Y-m-d\\Th:i:s', $comm['time']));
			$table->cell()->append('a', [$comm['resource'], 'href' => "?admin/resources,search:{$comm['resource']}"]);
			$table->cell()->append('a', [$comm['account'], 'href' => "?admin/accounts,search:{$comm['account']}"]);
			$table->cell()->append('a', ['✅', 'href' => "#"]);
			$table->row();
			$table->cell(['colspan' => 5])->append('pre', [$comm['content'], 'style' => 'margin:0']);

		});
		$table->fieldset('❌', 'time', 'resource', 'account', '✅');
		$table->header('Found %d item', $table->count());
		$table->bar->select([
			'' => '全部评论',
			'等待审核',
			'审核通过'
		])->setattr(['onchange' => 'g({status:this.value?this.value:null})'])->selected($this->webapp->query['status'] ?? '');
		$table->paging($this->webapp->at(['page' => '']));
	}
	//合集标签
	function form_settag($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('sort / name');
		$form->field('sort', 'number', ['value' => 0, 'min' => 0, 'required' => NULL]);
		$form->field('name', 'text', ['required' => NULL]);
		
		$form->fieldset('vods');

		$vods = [];
		$type = ['long' => '长', 'short' => '短', 'live' => '直', 'movie' => '电'];
		foreach ($this->webapp->mysql->setvods('WHERE site=?i ORDER BY type ASC,sort ASC,time DESC', $this->webapp->site) as $vod)
		{
			$vods[$vod['hash']] = "{$type[$vod['type']]}:{$vod['name']}";
		}
		$form->field('vods', 'checkbox', ['options' => $vods], 
			fn($v,$i)=>$i?join($v):str_split($v,12))['class'] = 'mo';

		$form->fieldset('ads');
		$ads = $this->webapp->mysql->ads('WHERE site=?i ORDER BY time DESC', $this->webapp->site)->column('name', 'hash');
		$form->field('ads', 'checkbox', ['options' => $ads], fn($v,$i)=>$i?join($v):str_split($v,12))['class'] = 'mo';
		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}
	function post_settag_create()
	{
		if ($this->form_settag($this->webapp)->fetch($data)
			&& $this->webapp->mysql->settags->insert($data += [
				'hash' => $this->webapp->randhash(TRUE),
				'site' => $this->webapp->site,
				'time' => $this->webapp->time])
			&& $this->webapp->call('saveSettag', $this->webapp->settag_xml($data))) {
			return $this->okay('?admin/settags');
		}
		$this->warn('合集标签创建失败！');
	}
	function get_settag_create()
	{
		$this->form_settag($this->main);
	}
	function get_settag_delete(string $hash)
	{
		if ($this->webapp->call('delSettag', $hash)
			&& $this->webapp->mysql->settags->delete('WHERE site=?s AND hash=?s', $this->webapp->site, $hash)) {
			return $this->okay('?admin/settags');
		}
		$this->warn('合集标签删除失败！');
	}
	function post_settag_update(string $hash)
	{
		if ($this->form_settag($this->webapp)->fetch($data)
			&& $this->webapp->mysql->settags('WHERE site=?s AND hash=?s LIMIT 1', $this->webapp->site, $hash)->update($data)
			&& ($newdata = $this->webapp->mysql->settags('WHERE site=?s AND hash=?s LIMIT 1', $this->webapp->site, $hash)->array())
			&& $this->webapp->call('saveSettag', $this->webapp->settag_xml($newdata))) {
			return $this->okay('?admin/settags');
		}
		$this->warn('合集标签更新失败！');
	}
	function get_settag_update(string $hash)
	{
		if ($data = $this->webapp->mysql->settags('WHERE hash=?s LIMIT 1', $hash)->array())
		{
			$this->form_settag($this->main)->echo($data);
		}
	}
	function get_settags()
	{
		$count = 0;
		$table = $this->main->table($this->webapp->mysql->settags(
			'WHERE site=?i ORDER BY sort ASC', $this->webapp->site), function($table, $tag) use(&$count)
		{
			++$count;
			$table->row();
			$table->cell()->append('a', ['❌',
				'href' => "?admin/settag-delete,hash:{$tag['hash']}",
				'onclick' => 'return confirm(`Delete Settag ${this.dataset.name}`)',
				'data-name' => $tag['name']]);
			$table->cell()->append('a', [$tag['hash'], 'href' => "?admin/settag-update,hash:{$tag['hash']}"]);
			$table->cell(date('Y-m-d H:i:s', $tag['time']));
			$table->cell($tag['sort']);
			$table->cell($tag['name']);
			$table->cell($tag['ads'] ? floor(strlen($tag['ads']) / 12) : 0);
			$table->cell($tag['vods'] ? floor(strlen($tag['vods']) / 12) : 0);
		});
		$table->fieldset('❌', 'hash', 'time', 'sort', 'name', 'ads', 'VOD');
		$table->header('Found %d item', $count);
		$table->button('Create Set Tag', ['onclick' => 'location.href="?admin/settag-create"']);
	}
	//合集资源
	function get_setvod_create()
	{
		$this->webapp->form_setvod($this->main)->xml->fieldset[1]->input['required'] = NULL;
	}
	function get_setvod_delete(string $hash)
	{
		if ($this->webapp->call('delSetvod', $hash)
			&& $this->webapp->mysql->setvods->delete('WHERE site=?s AND hash=?s', $this->webapp->site, $hash)) {
			return $this->okay('?admin/setvods');
		}
		$this->warn('合集资源删除失败！');
	}
	function get_setvod_update(string $hash, string $type)
	{
		if ($data = $this->webapp->mysql->setvods('WHERE hash=?s LIMIT 1', $hash)->array())
		{
			$this->webapp->form_setvod($this->main, $hash, $type)->echo($data)->xml->fieldset->setattr([
				'style' => 'display:block;width:480px;height:280px;border:.1rem solid black;',
				'data-src' => "{$this->webapp['app_resoutput']}vods/{$data['hash']}"
			])->append('script')->cdata('const pic = document.querySelector("fieldset[data-src]");
			loader(pic.dataset.src,null,"application/octet-stream").then(b=>pic.style.background=`center/contain no-repeat url(${URL.createObjectURL(b)}) silver`)');
		}
	}
	function get_setvods(string $type = NULL, string $tags = NULL, string $search = NULL)
	{
		$settags = [];
		$seltags = [];
		foreach ($this->webapp->mysql->settags('ORDER BY sort ASC,time DESC') as $settag)
		{
			$settags[$settag['hash']] = [
				'name' => $seltags[$settag['hash']] = $settag['name'],
				'vods' => $settag['vods'] ? str_split($settag['vods'], 12) :[]
			];
		}
		$cond = ['WHERE site=?i', $this->webapp->site];
		if (is_string($type) && strlen($type))
		{
			$cond[0] .= ' AND type=?s';
			$cond[] = $type;
		}
		if (is_string($search) && strlen($search))
		{
			$search = urldecode($search);
			$cond[0] .= ' AND (name LIKE ?s OR `describe` LIKE ?s)';
			$cond[] = "%{$search}%";
			$cond[] = "%{$search}%";
		}
		$cond[0] .= ' ORDER BY sort ASC,view DESC';
		$count = 0;
		$table = $this->main->table($this->webapp->mysql->setvods(...$cond), function($table, $vod, $type, $viewtype, $settags, $tags) use(&$count)
		{
			if ($tags)
			{
				if (in_array($vod['hash'], $tags, TRUE) === FALSE)
				{
					return;
				}
			}
			++$count;
			$table->row();
			$table->cell()->append('a', ['❌',
				'href' => "?admin/setvod-delete,hash:{$vod['hash']}",
				'onclick' => 'return confirm(`Delete Setvod ${this.dataset.name}`)',
				'data-name' => $vod['name']]);
			$table->cell()->append('a', [$vod['hash'], 'href' => "?admin/setvod-update,hash:{$vod['hash']},type:{$vod['type']}"]);
			$table->cell(date('Y-m-d H:i:s', $vod['time']));
			$table->cell($vod['view']);
			$table->cell($vod['sort']);
			$table->cell($type[$vod['type']]);
			$table->cell($viewtype[$vod['viewtype']]);
			if ($vod['ad'])
			{
				$table->cell()->append('a', [$vod['ad'], 'href' => "?admin/ads,search:{$vod['ad']}"]);
			}
			else
			{
				$table->cell('无广告');
			}
			$table->cell($vod['resources'] ? floor(strlen($vod['resources']) / 12) : 0);
			$table->cell($vod['name']);
			$table->cell($vod['describe']);

			$node = $table->cell(['class' => 'tdlinkspan']);
			foreach ($settags as $hash => $settag)
			{
				if (in_array($vod['hash'], $settag['vods']))
				{
					$node->append('a', [$settag['name'], 'href' => "?admin/settag-update,hash:{$hash}"]);
				}
			}
			if (count($node->children()) === 0)
			{
				$node->append('a', ['未设置 ', 'href' => '?admin/settag-create', 'style' => 'color:red']);
			}
		}, $this->webapp['app_restype'], ['双联', '横中滑动', '大一偶小', '横小滑动', '竖小', '大横图'], $settags, array_key_exists($tags, $settags) ? $settags[$tags]['vods'] : []);
		$table->fieldset('❌', 'hash', 'time', 'view', 'sort', 'type', 'viewtype', 'ad', 'RES', 'name', 'describe', '首页标签');
		$table->header('Found %d item', $count);
		$table->button('Create Set Vod', ['onclick' => 'location.href="?admin/setvod-create"']);
		$table->bar->select(['' => '全部'] + $this->webapp['app_restype'])->setattr(['onchange' => 'g({type:this.value===""?null:this.value})'])->selected($type);
		$table->bar->select(['' => '全部'] + $seltags)->setattr(['onchange' => 'g({tags:this.value===""?null:this.value})'])->selected($tags);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
	}
	//账单统计
	function get_statbills(string $type = 'undef', string $ym = '', int $top = 10)
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode('-', $ym = date('Y-m'));
		$tops = ['10' => 'TOP 10', '20' => 'TOP 20', '50' => 'TOP 50', '100' => 'TOP 100'];
		$days = range(1, date('t', strtotime($ym)));

		$stat = $this->webapp->mysql->bills('WHERE tym=?i AND type=?s', "{$y}{$m}", $type)->statmonth($ym, 'describe', 'day', [
			'SUM(IF({day}=0 OR day={day},fee,0))',
			// 'COUNT(IF((!{day} OR day={day}) AND status!="unpay",1,NULL))',
			// 'SUM(IF((!{day} OR day={day}) AND status!="unpay",order_fee,0))',
			// 'SUM(IF((!{day} OR day={day}) AND status!="unpay",actual_fee,0))',
			// 'COUNT(IF((!{day} OR day={day}) AND status="notified",1,NULL))'
		], 'ORDER BY $0 DESC LIMIT ?i', array_key_exists($top, $tops) ? $top : 10);

		//print_r($stat->all());

		//return;

		$table = $this->main->table($stat, function($table, $bill, $days)
		{
			$t1 = $table->tbody->append('tr');
			$t2 = $table->tbody->append('tr');
			if ($bill['describe'] && preg_match('/^[0-9A-Z]{12}$/', $bill['describe']))
			{
				$t1->append('td', ['rowspan' => 2])->append('a', [$bill['describe'], 'href' => "?admin/resource-update,hash:{$bill['describe']}"]);
			}
			else
			{
				$t1->append('td', [$bill['describe'] ?? '汇总', 'rowspan' => 2]);
				
			}

			$t1->append('td', '购买');
			$t2->append('td', '收入');

			$t1->append('td', number_format($bill['$0']));
			$t2->append('td', number_format($bill['$0$0']));

			foreach ($days as $i)
			{
				$t1->append('td', number_format($bill["\${$i}"]));
				$t2->append('td', number_format($bill["\$0\${$i}"]));
			}
		}, $days);
		$table->fieldset('描述（资源）', '统计', '总和', ...$days);
		$table->header('统计账单');
		$table->xml['class'] = 'webapp-stateven';

		$table->bar->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);
		$table->bar->select(['undef' => '未定义'] + $this->webapp['app_restype'])->setattr(['onchange' => 'g({type:this.value===""?null:this.value})'])->selected($type);
		$table->bar->select($tops)->setattr(['onchange' => 'g({top:this.value})'])->selected($top);

	}
	//订单
	function get_orderstat(string $ym = '')
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode('-', $ym = date('Y-m'));
		$days = range(1, date('t', strtotime($ym)));

		$stat = $this->webapp->mysql->orders('WHERE tym=?i', "{$y}{$m}")->statmonth($ym, 'pay_name', 'day', [
			'COUNT(IF(({day}=0 OR day={day}) AND status="unpay",1,NULL))',
			'COUNT(IF(({day}=0 OR day={day}) AND status!="unpay",1,NULL))',
			'SUM(IF({day}=0 OR day={day},order_fee,0))',
			'SUM(IF(({day}=0 OR day={day}) AND status!="unpay",actual_fee,0))'
		], 'ORDER BY `$1$0` DESC');

		$table = $this->main->table($stat, function($table, $order, $days)
		{
			$t1 = $table->tbody->append('tr');
			$t2 = $table->tbody->append('tr');
			$t3 = $table->tbody->append('tr');
			$t4 = $table->tbody->append('tr');
			$t5 = $table->tbody->append('tr');
			$t6 = $table->tbody->append('tr');

			$t1->append('td', [$order['pay_name'] ?? '汇总', 'rowspan' => 6]);

			$t1->append('td', '订单总数');
			$t2->append('td', '未付款单');
			$t3->append('td', '已付款单');
			$t4->append('td', '订单金额');
			$t5->append('td', '成交金额');
			$t6->append('td', '支付几率');

			$t1->append('td', number_format($order['$0']));
			$t2->append('td', number_format($order['$0$0']));
			$t3->append('td', number_format($order['$1$0']));
			$t4->append('td', number_format($order['$2$0'] * 0.01, 2));
			$t5->append('td', number_format($order['$3$0'] * 0.01, 2));
			$t6->append('td', sprintf('%0.1f%%', $order['$0'] ? $order['$1$0'] / $order['$0'] * 100 : 0));

			foreach ($days as $i)
			{
				$t1->append('td', number_format($order["\${$i}"]));
				$t2->append('td', number_format($order["\$0\${$i}"]));
				$t3->append('td', number_format($payed = $order["\$1\${$i}"]));
				$t4->append('td', number_format($order["\$2\${$i}"] * 0.01, 2));
				$t5->append('td', number_format($order["\$3\${$i}"] * 0.01, 2));
				$t6->append('td', sprintf('%0.1f%%', $order["\${$i}"] ? $order["\$1\${$i}"] / $order["\${$i}"] * 100 : 0));
				//$t6->append('td', number_format($payed - $order["\$4\${$i}"]));
			}
		}, $days);
		$table->fieldset('渠道', '统计', '总和', ...$days);
		$table->header('订单统计');
		$table->xml['class'] = 'webapp-stateven';

		$table->bar->append('input', ['type' => 'month', 'value' => "{$ym}", 'onchange' => 'g({ym:this.value})']);
	}
	function get_orders(string $search = NULL, int $page = 1)
	{
		$cond = ['WHERE 1'];
		if ($pay_name = $this->webapp->query['pn'] ?? '')
		{
			$cond[0] .= ' AND pay_name=?s';
			$cond[] = $pay_name;
		}
		if ($date = $this->webapp->query['date'] ?? '')
		{
			$cond[0] .= ' AND tym=?i AND day=?i';
			$cond[] = substr($date, 0, 4) . substr($date, 5, 2);
			$cond[] = substr($date, -2);
		}
		if ($status = $this->webapp->query['status'] ?? '')
		{
			$cond[0] .= ' AND status=?s';
			$cond[] = $status;
		}
		if ($search)
		{
			$cond[0] .= strlen($search) === 10 ? ' AND notify_url=?s' : ' AND hash=?s';
			$cond[] = $search;
		}
		$cond[0] .= ' ORDER BY time DESC';
		$table = $this->main->table($this->webapp->mysql->orders(...$cond)->paging($page, 21), function($table, $order, $status)
		{
			$table->row();
			$table->cell($order['hash']);
			$table->cell(date('Y-m-d\\TH:i:s', $order['time']));
			$table->cell(date('Y-m-d\\TH:i:s', $order['last']));
			$table->cell([$order['status'], 'style' => "color:{$status[$order['status']]}"]);
			$table->cell(number_format($order['actual_fee'] * 0.01, 2));
			$table->cell(number_format($order['order_fee'] * 0.01, 2));
			$table->cell($order['pay_user']);
			$table->cell("{$order['pay_name']}@{$order['pay_type']}");
			$table->cell($order['order_no']);
			$table->cell($order['trade_no']);
			$table->cell()->append('a', [$order['notify_url'],
				'href' => "?admin/ordernotify,hash:{$order['hash']}",
				'onclick' => 'return confirm(this.dataset.notifyurl)',
				'data-notifyurl' => $order['notify_url']]);
			
		}, ['unpay' => 'red', 'payed' => 'blue', 'notified' => 'green']);
		$table->fieldset('我方订单', '创建时间', '最后更新', '状态', '实际支付', '订单价格', '商户', '平台@类型', '订单（内部产品）', '对方订单', '回调地址');
		$table->header('找到 %s 个订单数据', number_format($table->count()));
		$table->bar->select(['' => '全部平台'] + $this->webapp->mysql->payaisle('ORDER BY sort ASC')->column('name', 'code'))->setattr(['onchange' => 'g({pn:this.value})'])->selected($pay_name);
		$table->bar->select([
			'' => '全部状态',
			'notified' => 'notified',
			'unpay' => 'unpay',
			'payed' => 'payed'
		])->setattr(['onchange' => 'g({status:this.value})'])->selected($status);
		$table->bar->append('input', ['type' => 'date', 'value' => "{$date}", 'onchange' => 'g({date:this.value})']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	function get_ordernotify(string $hash)
	{
		do
		{
			if ($this->webapp->admin[2] === FALSE)
			{
				$error = '需要超级管理员权限';
				break;
			}
			$error = '订单不存在';
			if ($this->webapp->mysql->orders('WHERE hash=?s LIMIT 1', $hash)->fetch($order) === FALSE)
			{
				break;
			}
			if (is_numeric($order['pay_user']) && strlen($order['notify_url']) === 10)
			{
				//内部通知
				if (preg_match('/(E|B)(\d{8})(\d+)/', $order['order_no'], $goods)
					&& $this->webapp->mysql->sync(fn() =>
						$this->webapp->mysql->accounts('WHERE uid=?s LIMIT 1', $order['notify_url'])->update(...match ($goods[1])
						{
							'E' => ['expire=IF(expire>?i,expire,?i)+?i', $this->webapp->time, $this->webapp->time, $goods[3]],
							'B' => ['balance=balance+?i', $goods[3]],
							default => []
						})
						&& $this->webapp->mysql->bills->insert([
							'hash' => $this->webapp->randhash(TRUE),
							'site' => $order['pay_user'],
							'time' => $this->webapp->time,
							'type' => 'undef',
							'tym' => date('Ym', $this->webapp->time),
							'day' => date('d', $this->webapp->time),
							'fee' => intval($order['order_fee'] * 0.01),
							'account' => $order['notify_url'],
							'describe' => match ($goods[1])
							{
								'E' => sprintf('购买会员: %d天', $goods[3] / 86400),
								'B' => sprintf('购买金币: %d个', $goods[3]),
								default => '??'
							}])
						&& is_numeric($this->webapp->site = $order['pay_user'])
						&& $this->webapp->call('saveUser', $this->webapp->account_xml($this->webapp->mysql
							->accounts('WHERE uid=?s LIMIT 1', $order['notify_url'])->array()))
						&& ($order['status'] === 'payed' ? $this->webapp->mysql
							->orders('WHERE hash=?s LIMIT 1', $order['hash'])->update('status="notified"') : TRUE)
					)) {
					return $this->okay("?admin/orders,search:{$order['hash']}");
				}
				$error = '内部通知失败！';
				break;
			}
			else
			{
				//外部通知
				if (webapp_router_pay::callback($order, $result, $response))
				{
					return $this->okay("?admin/orders,search:{$order['hash']}");
				}
				else
				{
					$responses = [];
					foreach ($response as $name => $value)
					{
						$responses[] = $name ? "{$name}: {$value}" : $value;
					}
					return $this->main->append('pre', join("\n", $responses) . "\n\n{$result}");
					//echo 123;
				}
				//var_dump(webapp_router_pay::callback($order, $result));
			}
			$error = '外部通知失败！';
		} while (0);
		$this->warn($error);
	}
	//支付
	function form_payaisle($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->field('name', 'text', ['maxlength' => 16, 'placeholder' => '支付通道名称', 'style' => 'width:8rem', 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 255, 'style' => 'width:4rem', 'required' => NULL]);
		$form->field('code', 'text', ['minlength' => 2, 'maxlength' => 2, 'placeholder' => '??', 'style' => 'width:2rem', 'required' => NULL]);
		$form->button('Set', 'submit');
		$form->fieldset();
		$form->field('type', 'textarea', [
			'placeholder' => 'type@name:open[,type@name:open]',
			'cols' => 60,
			'rows' => 8,
			'pattern' => '[01]#\w+\[\d+(\,\d+)*\][^\r]+(\r\n[01]#\w+\[\d+(\,\d+)*\][^\r]+)*',
			'required' => NULL
		]);
		return $form;
	}
	function post_payaisle(string $code = NULL)
	{
		if ($this->form_payaisle($this->webapp)->fetch($data)
			&& class_exists("webapp_pay_{$data['code']}", FALSE)
			&& ($code
			? $this->webapp->mysql->payaisle('WHERE code=?s LIMIT 1', $code)->update([
				'sort' => $data['sort'],
				'name' => $data['name'],
				'type' => $data['type']])
			: $this->webapp->mysql->payaisle->insert([
				'time' => $this->webapp->time,
				'keep' => 'off'] + $data))) {
			return $this->okay('?admin/payaisle');
		}
		$this->warn('支付通道添加失败');
	}
	function get_payaisle(string $code = NULL, string $onoff = NULL)
	{
		//if ($this->webapp->admin[2] === FALSE) return $this->warn('需要灰常牛逼的全局超级管理员才可以使用！');
		$form = $this->form_payaisle($this->main);
		$form->xml['style'] = 'display:block;margin:1rem 0';
		if ($code || $onoff)
		{
			$form->echo($this->webapp->mysql->payaisle('WHERE code=?s LIMIT 1', $code ?? $onoff)->array());
			if ($onoff)
			{
				$this->webapp->mysql->payaisle('WHERE code=?s LIMIT 1', $onoff)->update('keep=IF(keep="on","off","on")');
			}
		}

		$orderstat = [
			'24h' => [],
			'15m' => []
		];
		$t24 = $this->webapp->time(-86400);
		$t15 = $t24 + (86400 - 900);
		foreach ($this->webapp->mysql->orders('where time>=?i', $t24) as $order)
		{
			$name = "{$order['pay_name']}{$order['pay_type']}";
			if (isset($orderstat['24h'][$name]))
			{
				$orderstat['24h'][$name][0] += 1;
				$orderstat['24h'][$name][1] += intval($order['status'] != 'unpay');
			}
			else
			{
				$orderstat['24h'][$name] = [1, intval($order['status'] != 'unpay')];
			}
			if ($order['time'] > $t15)
			{
				if (isset($orderstat['15m'][$name]))
				{
					$orderstat['15m'][$name][0] += 1;
					$orderstat['15m'][$name][1] += intval($order['status'] != 'unpay');
				}
				else
				{
					$orderstat['15m'][$name] = [1, intval($order['status'] != 'unpay')];
				}
			}
		}
		$table = $this->main->table($this->webapp->mysql->payaisle('ORDER BY sort ASC'), function($table, $pay, $orderstat)
		{
			preg_match_all('/\d#([^\[]+)/', $pay['type'], $aisle);
			$stat = ['24h' => [], '15m' => []];
			foreach ($aisle[1] as $name)
			{
				$name = "{$pay['code']}{$name}";
				$stat['24h'][] = sprintf('%.02f%%', isset($orderstat['24h'][$name]) ? $orderstat['24h'][$name][1] / $orderstat['24h'][$name][0] * 100 : 0);
				$stat['15m'][] = sprintf('%.02f%%', isset($orderstat['15m'][$name]) ? $orderstat['15m'][$name][1] / $orderstat['15m'][$name][0] * 100 : 0);
			}
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $pay['time']));
			$table->cell($pay['name']);
			$table->cell($pay['sort']);
			$table->cell($pay['code']);
			$table->cell(join("\n", $stat['24h']));
			$table->cell(join("\n", $stat['15m']));
			$table->cell()->append('a', [$pay['type'], 'href' => "?admin/payaisle,code:{$pay['code']}"]);
			$table->cell()->append('a', [$pay['keep'], 'href' => "?admin/payaisle,onoff:{$pay['code']}"]);
		}, $orderstat);
		$table->fieldset('创建时间', '名称', '排序', '代码', '24h成功', '15m成功', '类型', 'on/off');
	}
	//运行
	function get_runstatus()
	{
		$table = $this->main->table();
		$table->fieldset('Name', 'Value');
		$table->header('Front app running status');
		$table->xml->setattr(['style' => 'margin-right:1rem']);
		$sync = $this->webapp->sync();
		if (is_object($status = $sync->goto("{$sync->path}?pull/runstatus")->content()))
		{
			foreach ($status->getattr() as $name => $value)
			{
				$table->row();
				$table->cell($name);
				$table->cell($value);
			}
		}

		if ($this->webapp->admin[2])
		{
			$table = $this->main->table();
			$table->fieldset('Name', 'Value');
			$table->header('Data synchronize running status');
			$table->row();
			$table->cell('os_http_connections');
			$table->cell(intval(shell_exec('netstat -ano | find ":80" /c')));
			
			foreach ($this->webapp->mysql('SELECT * FROM performance_schema.GLOBAL_STATUS WHERE VARIABLE_NAME IN(?S)', [
				//'Aborted_clients',
				//'Aborted_connects',//接到MySQL服务器失败的次数
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
				$table->row();
				$table->cell('mysql_' . strtolower($stat['VARIABLE_NAME']));
				$table->cell($stat['VARIABLE_VALUE']);
			}
		}
	}
	//================Extensions================
	//Admin
	function form_admin($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('uid / pwd / name');
		$form->field('uid', 'number', ['min' => 1000, 'required' => NULL]);
		$form->field('pwd', 'text', ['required' => NULL]);
		$form->field('name', 'text', ['required' => NULL]);
		if ($form->echo)
		{
			$form->echo([
				'uid' => random_int(1000, 9999),
				'pwd' => random_int(100000, 999999)
			]);
		}
		$form->fieldset();
		$form->button('Create Administrator', 'submit');
		return $form;
	}
	function post_admin_create()
	{
		if ($this->form_admin($this->webapp)->fetch($admin)
			&& $this->webapp->mysql->admin->insert([
				'site' => $this->webapp->site,
				'time' => $this->webapp->time,
				'lasttime' => $this->webapp->time,
				'lastip' => $this->webapp->iphex('127.0.0.1')
			] + $admin)) {
			return $this->okay('?admin/admin');
		}
		$this->warn('管理员创建失败！');
	}
	function get_admin_create()
	{
		$this->form_admin($this->main);
	}
	function get_admin_delete(string $uid)
	{
		$this->webapp->mysql->admin->delete('WHERE uid=?s LIMIT 1', $uid);
		$this->okay('?admin/admin');
	}
	function get_admin(int $page = 1)
	{
		$cond = ['WHERE site=?i', $this->webapp->site];
		$table = $this->main->table($this->webapp->mysql->admin(...$cond)->paging($page), function($table, $admin)
		{
			$table->row();
			$table->cell()->append('a', ['❌',
				'href' => "?admin/admin-delete,uid:{$admin['uid']}",
				'onclick' => 'return confirm(`Delete Admin ${this.dataset.uid}`)',
				'data-uid' => $admin['uid']]);
			$table->cell("{$admin['uid']}:{$admin['pwd']}");
			$table->cell($admin['name']);
			$table->cell(date('Y-m-d\\TH:i:s', $admin['time']));
			$table->cell(date('Y-m-d\\TH:i:s', $admin['lasttime']));
			$table->cell($this->webapp->hexip($admin['lastip']));
		});
		$table->fieldset('❌', 'uid:pwd', 'name', 'time', 'lasttime', 'lastip' );
		$table->header('Found ' . $table->count() . ' item');
		$table->bar->append('button', ['Create Admin', 'onclick' => 'location.href="?admin/admin-create"']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	//单位
	function form_unitset($ctx):webapp_form
	{
		$form = new webapp_form($ctx);

		$form->fieldset('unit / code / name');
		$form->field('unit', 'text', ['placeholder' => '单位编码4位字母数字组合', 'pattern' => '\w{4}', 'maxlength' => 4, 'required' => NULL]);
		$form->field('code', 'number', ['value' => random_int(100000, 999999), 'min' => 100000, 'max' => 999999, 'required' => NULL]);
		$form->field('name', 'text', ['placeholder' => '单位名字描述', 'maxlength' => 128, 'required' => NULL]);

		$form->fieldset('type / rate / price');
		$form->field('type', 'select', ['options' => [
			'cpc' => 'CPC',
			'cpa' => 'CPA',
			'cps' => 'CPS',
			'cpm' => 'CPM'
		], 'required' => NULL]);
		$form->field('rate', 'number', ['value' => 1, 'min' => 0.1, 'max' => 1, 'step' => 0.01, 'style' => 'width:14rem', 'required' => NULL]);
		$form->field('price', 'number', ['value' => 0, 'step' => 0.01, 'style' => 'width:14rem', 'required' => NULL]);

		$form->fieldset('owns');
		$unit = $this->webapp->mysql->unitsets('WHERE site=?i ORDER BY time DESC', $this->webapp->site)->column('unit', 'unit');

		$form->field('owns', 'checkbox', ['options' => $unit], 
			fn($v,$i)=>$i?join($v):str_split($v,4))['class'] = 'mo';

		$form->fieldset();
		$form->button('Submit', 'submit');

		return $form;
	}
	function post_unitset(string $unit = NULL)
	{
		if ($this->form_unitset($this->webapp)->fetch($data) && ($unit
			? $this->webapp->mysql->unitsets('WHERE unit=?s LIMIT 1', $unit)->update($data)
			: $this->webapp->mysql->unitsets->insert([
				'site' => $this->webapp->site,
				'time' => $this->webapp->time,
				'admin' => $this->webapp->admin[0]] + $data))) {
			return $this->okay('?admin/unitsets');
		}
		$this->warn('创建失败！');
	}
	function get_unitset_delete(string $unit)
	{
		$this->webapp->mysql->unitsets->delete('WHERE unit=?s LIMIT 1', $unit)
			? $this->okay('?admin/unitsets')
			: $this->warn('删除失败！');
	}
	function get_unitset(string $unit = NULL)
	{
		$this->form_unitset($this->main)->echo($unit ? $this->webapp->mysql->unitsets('WHERE unit=?s LIMIT 1', $unit)->array() : []);
	}
	function get_unitsets(string $search = NULL, int $page = 1)
	{
		$cond = ['WHERE site=?i', $this->webapp->site];

		$cond[0] = ' ORDER BY time DESC';

		$table = $this->main->table($this->webapp->mysql->unitsets(...$cond)->paging($page), function($table, $unit, $admin)
		{
			$table->row();
			$table->cell()->append('a', ['❌',
				'href' => "?admin/unitset-delete,unit:{$unit['unit']}",
				'onclick' => 'return confirm(`Delete Admin ${this.dataset.unit}`)',
				'data-unit' => $unit['unit']]);
			$table->cell(date('Y-m-d\\TH:i:s', $unit['time']));
			$table->cell("{$unit['unit']}:{$unit['code']}");
			$table->cell($unit['type']);
			$table->cell($unit['rate']);
			$table->cell($unit['price']);
			$table->cell()->append('a', [$unit['name'], 'href' => "?admin/unitset,unit:{$unit['unit']}"]);
			$table->cell($admin[$unit['admin']] ?? $unit['admin']);
			$table->cell("https://ichigua.net/{$unit['unit']}");
		}, $this->adminlists());
		$table->fieldset('❌', 'time', 'unit:code', 'type', 'rate', 'price', 'name', 'admin', '推广链接');
		$table->header('Found ' . $table->count() . ' item');
		$table->bar->append('button', ['Create Unit', 'onclick' => 'location.href="?admin/unitset"']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	function get_unitcost(string $start = NULL, string $end = NULL)
	{
		$start ??= date('Y-m-d', mktime(0, 0, 0, day:1));
		$end ??= date('Y-m-d');

		$cond = ['WHERE site=?i AND date>=?s AND date<=?s', $this->webapp->site, $start, $end];

		$cond[0] .= ' GROUP BY unit ORDER BY pv DESC';
		
		

		$price = $this->webapp->mysql->unitsets->column('price', 'unit');

		$types = $this->webapp->mysql->unitsets->column('type', 'unit');

		$order = $this->webapp->mysql(<<<SQL
SELECT SUM(orders.order_fee) AS fee,accounts.unit AS unit
FROM orders INNER JOIN (accounts) ON (accounts.uid=orders.notify_url)
WHERE orders.pay_user=?i AND orders.status!="unpay"
AND concat(left(orders.tym,4),"-",right(orders.tym,2),"-",lpad(orders.day,2,0))>=?s
AND concat(left(orders.tym,4),"-",right(orders.tym,2),"-",lpad(orders.day,2,0))<=?s
GROUP BY unit ORDER BY fee DESC
SQL, $this->webapp->site, $start, $end)->column('fee', 'unit');



		$fake = [];
		$fakes = [
			'pv' => 0,
			'ua' => 0,
			'lu' => 0,
			'ru' => 0,
			'dc' => 0,
			'ia' => 0,
			'count' => 0,
			'order' => 0
		];
		foreach ($this->webapp->mysql->unitrates(...$cond)->select('unit,SUM(pv) pv,SUM(ua) ua,SUM(lu) lu,SUM(ru) ru,SUM(dc) dc,SUM(ia) ia') as $row)
		{
			$fake[$row['unit']] = $row;
			foreach ($row as $k => $v)
			{
				if ($k === 'unit') continue;
				$fakes[$k] += $v;
			}
		}

		$count = [
			'pv' => 0,
			'ua' => 0,
			'lu' => 0,
			'ru' => 0,
			'dc' => 0,
			'ia' => 0,
			'count' => 0,
			'order' => 0
		];
		$stat = $this->webapp->mysql->unitstats(...$cond)->select('unit,SUM(pv) pv,SUM(ua) ua,SUM(lu) lu,SUM(ru) ru,SUM(dc) dc,SUM(ia) ia');
		
		$table = $this->main->table($stat, function($table, $stat, $types, $price, $order, $fake) use(&$count, &$fakes)
		{
			$count['pv'] += $stat['pv'];
			$count['ua'] += $stat['ua'];
			$count['lu'] += $stat['lu'];
			$count['ru'] += $stat['ru'];
			$count['dc'] += $stat['dc'];
			$count['ia'] += $stat['ia'];

			$table->row();
			$table->cell($stat['unit']);
			$table->cell($types[$stat['unit']] ?? 'cpa');
			$table->cell(number_format($p = $price[$stat['unit']] ?? 0, 2));
			$table->cell(number_format($stat['pv']));
			$table->cell(number_format($stat['ua']));
			$table->cell(number_format($stat['lu']));
			$table->cell(number_format($stat['ru']));
			$table->cell(number_format($stat['dc']));
			$table->cell(number_format($stat['ia']));
			$table->cell(number_format(($d = $order[$stat['unit']] ?? 0) * 0.01, 2));
			$table->cell(number_format($c = $stat['ia'] * $p, 2));
			$count['order'] += $d;
			$count['count'] += $c;

			if (isset($fake[$stat['unit']]))
			{
				$table->cell(number_format($fake[$stat['unit']]['pv']));
				$table->cell(number_format($fake[$stat['unit']]['ua']));
				$table->cell(number_format($fake[$stat['unit']]['lu']));
				$table->cell(number_format($fake[$stat['unit']]['ru']));
				$table->cell(number_format($fake[$stat['unit']]['dc']));
				$table->cell(number_format($fake[$stat['unit']]['ia']));
				$table->cell('-');
				$table->cell(number_format($c = $fake[$stat['unit']]['ia'] * $p, 2));
			}
			else
			{
				$table->cell('-');
				$table->cell('-');
				$table->cell('-');
				$table->cell('-');
				$table->cell('-');
				$table->cell('-');
				$table->cell('-');
				$table->cell('-');
				$c = 0;
			}

			$fakes['count'] += $c;


		}, $types, $price, $order, $fake);
		$table->fieldset('单位', '类型', '单价', '浏览', '独立', '登录', '注册', '下载', '激活', '充值', '结算(激活x单价)',
			'浏览(假)', '独立(假)', '登录(假)', '注册(假)', '下载(假)', '激活(假)', '充值(假)', '结算(激活x单价)(假)');
		$table->row()['style'] = 'background:lightblue';
		$table->cell(['合计', 'colspan' => 3]);

		$table->cell(number_format($count['pv']));
		$table->cell(number_format($count['ua']));
		$table->cell(number_format($count['lu']));
		$table->cell(number_format($count['ru']));
		$table->cell(number_format($count['dc']));
		$table->cell(number_format($count['ia']));
		
		$table->cell(number_format($count['order'] * 0.01, 2));
		$table->cell(number_format($count['count'], 2));

		$table->cell(number_format($fakes['pv']));
		$table->cell(number_format($fakes['ua']));
		$table->cell(number_format($fakes['lu']));
		$table->cell(number_format($fakes['ru']));
		$table->cell(number_format($fakes['dc']));
		$table->cell(number_format($fakes['ia']));
		$table->cell('-');
		$table->cell(number_format($fakes['count'], 2));

		$table->header('单位成本结算');
		$table->bar->append('input', ['type' => 'date', 'value' => $start, 'onchange' => 'g({start:this.value})']);
		$table->bar->append('span', ' - ');
		$table->bar->append('input', ['type' => 'date', 'value' => $end, 'onchange' => 'g({end:this.value})']);
		$table->xml['class'] = 'webapp-stat';
	}
	//密码
	function form_setpwd($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('Old Password');
		$form->field('old', 'password', ['required' => NULL]);

		$form->fieldset('New Password');
		$form->field('new', 'password', ['required' => NULL]);

		$form->fieldset('Confirm Password');
		$form->field('ack', 'password', ['required' => NULL]);

		$form->fieldset();
		$form->button('Change Password', 'submit');

		return $form;
	}
	function post_setpwd()
	{
		if ($this->form_setpwd($this->webapp)->fetch($pwd))
		{
			if ($pwd['new'] === $pwd['ack'])
			{
				if ($pwd['old'] === $this->webapp->admin[1])
				{
					if ($this->webapp->mysql->admin('WHERE uid=?s LIMIT 1', $this->webapp->admin[0])->update('pwd=?s', $pwd['new']))
					{
						return $this->okay('?admin');
					}
					$this->warn('新密码设置失败！');
				}
				else $this->warn('老密码不正确！');
			}
			else $this->warn('新密码不一致！');
			$this->form_setpwd($this->main)->echo($pwd);
		}
	}
	function get_setpwd()
	{
		$this->form_setpwd($this->main);
	}
}