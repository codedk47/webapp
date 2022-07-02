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
		$this->xml->head->append('script', ['src' => '/webapp/app/news/admin.min.js']);
		$nav = $this->nav([
			['Home', '?admin'],
			['Tags', '?admin/tags'],
			['Resources', '?admin/resources'],
			['Accounts', '?admin/accounts'],
			['Ads', '?admin/ads'],
			['Extensions', [
				['Reports（通告，报告，合并通用 ψ(｀∇´)ψ）', '?admin/reports'],
				['Comments（暂时没用）', '?admin/comments'],
				['Set Tags', '?admin/settags'],
				['Set Vods', '?admin/setvods'],
				['Runstatus', '?admin/runstatus']
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
	//统计
	function post_home()
	{
	}
	function get_home(string $ym = '')
	{
		[$y, $m] = preg_match('/^\d{4}(?=\-(\d{2}))/', $ym, $pattren) ? $pattren : explode(',', date('Y,m'));
		$fields = [
			'pv' => ['页面访问', '#F2D7D5'],
			'ua' => ['唯一地址', '#EBDEF0'],
			'lu' => ['登录用户', '#D4E6F1'],
			'ru' => ['注册用户', '#D4EFDF'],
			'dc' => ['下载数量', '#FCF3CF'],
			'ia' => ['激活数量', '#FDEBD0'],
			'oc' => ['订单数量', '#FAE5D3'],
			'op' => ['支付数量', '#F6DDCC'],
			'ov' => ['订单金额', '#F2F3F4'],
			'oi' => ['支付金额', '#E5E8E8']
		];
		$stats = ['汇总' => [$types = ['pv' => 0, 'ua' => 0, 'lu' => 0, 'ru' => 0, 'dc' => 0, 'ia' => 0, 'oc' => 0, 'op' => 0, 'ov' => 0, 'oi' => 0]]];
		foreach ($this->webapp->mysql->unitstats('where site=?i and year=?s and month=?s order by oi desc', $this->webapp->site, $y, $m) as $stat)
		{
			$stats['汇总'][$stat['day']] ??= $types;
			$stats[$stat['unit']][0] ??= $types;
			foreach ($stats[$stat['unit']][$stat['day']] = [
				'pv' => $stat['pv'],
				'ua' => $stat['ua'],
				'lu' => $stat['lu'],
				'ru' => $stat['ru'],
				'dc' => $stat['dc'],
				'ia' => $stat['ia'],
				'oc' => $stat['oc'],
				'op' => $stat['op'],
				'ov' => $stat['ov'],
				'oi' => $stat['oi']] as $k => $v) {
				$stats['汇总'][0][$k] += $v;
				$stats['汇总'][$stat['day']][$k] += $v;
				$stats[$stat['unit']][0][$k] += $v;
			}
		}
		// print_r($stats);
		// return;
		$t = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
		$table = $this->main->table();
		$table->fieldset('单位', '统计', '总计', ...range(1, $t));
		$table->header->append('input', ['type' => 'month', 'value' => "{$y}-{$m}", 'onchange' => 'g({ym:this.value})']);
		foreach ($stats as $unit => $stat)
		{
			$row = $table->row();
			//$table->cell([$unit, 'rowspan' => 11])
			$row->append('td', [$unit, 'rowspan' => 11, 'style' => 'background:silver']);
			$node = [];
			foreach ($fields as $name => $ctx)
			{
				$row = $table->row()->setattr(['style' => "background:{$ctx[1]}"]);
				$row->append('td', $ctx[0]);
				for ($i = 0; $i <= $t; ++$i)
				{
					$node[$i][$name] = $row->append('td', 0);
				}
			}
			foreach ($stat as $day => $value)
			{
				foreach ($value as $field => $count)
				{
					$node[$day][$field][0] = number_format($count);
				}
			}
		}
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
		$cond[] = 'order by level asc,click desc,count desc';
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
			$table->cell()->append('a', [$tag['name'], 'href' => "?admin/resources,search:{$tag['hash']}"]);
			$table->cell($tag['alias']);
		});
		$table->fieldset('❌', 'hash', 'time', 'level', 'count', 'click', 'seat', 'name', 'alias');
		$table->header('Found %d item', $table->count());
		$table->button('Create Tag', ['onclick' => 'location.href="?admin/tag-create"']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
	}
	//资源
	function form_resource($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('封面图片');
		$form->field('piccover', 'file');
		$form->field('type', 'select', ['options' => $this->webapp['app_restype']]);
		$form->fieldset('name / actors');
		$form->field('name', 'text', ['style' => 'width:42rem', 'required' => NULL]);
		$form->field('actors', 'text', ['value' => '素人', 'required' => NULL]);
		$form->fieldset('tags');
		$tags = $this->webapp->mysql->tags('ORDER BY time ASC')->column('name', 'hash');
		$form->field('tags', 'checkbox', ['options' => $tags], 
			fn($v,$i)=>$i?join(',',$v):explode(',',$v))['class'] = 'restag';
		$form->fieldset('require(下架：-2、会员：-1、免费：0、金币)');
		$form->field('require', 'number', ['min' => -1, 'required' => NULL]);
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
			if ($resource['sync'] === 'finished')
			{
				$this->xml->head->append('script', ['src' => '/webapp/app/news/hls.min.js']);
				$this->xml->head->append('script', ['src' => '/webapp/app/news/wplayer.js']);
				$this->main->append('webapp-video', [
					'style' => 'display:block;width:854px;height:480px',
					'data-load' => sprintf("{$this->webapp['app_resoutput']}%s/{$resource['hash']}", date('ym', $resource['time'])),
					'muted' => NULL,
					'autoplay' => NULL,
					'controls' => NULL
				]);
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
		$this->webapp->form_resourceupload($this->main);
	}
	function get_resources(string $search = NULL, int $page = 1)
	{
		$cond = ['WHERE FIND_IN_SET(?s,site) AND sync=?s', $this->webapp->site,
			$sync = $this->webapp->query['sync'] ?? 'finished'];
		if (is_string($search))
		{
			if (strlen($search) === 4 && trim($search, webapp::key) === '')
			{
				$cond[0] .= ' AND FIND_IN_SET(?s,tags)';
				$cond[] = $search;
			}
			else
			{
				$cond[0] .= ' AND (hash=?s or data->>\'$."?i".name\' like ?s)';
				array_push($cond, $search = urldecode($search), $this->webapp->site, "%{$search}%");
			}
		}
		if (strlen($type = $this->webapp->query['type'] ?? ''))
		{
			$cond[0] .= ' AND type=?s';
			$cond[] = $type;
		}
		
		$cond[0] .= ' ORDER BY time DESC';
		$table = $this->main->table($this->webapp->mysql->resources(...$cond)->paging($page), function($table, $res, $type)
		{
			$table->row();
			$table->cell(['width' => 'width:100%;'])->append('a', ['❌',
				'href' => "?admin/resource-delete,hash:{$res['hash']}",
				'onclick' => 'return confirm(`Delete Resource ${this.dataset.hash}`)',
				'data-hash' => $res['hash']
			]);
			$table->cell()->append('a', [$res['hash'], 'href' => "?admin/resource-update,hash:{$res['hash']}"]);
			$table->cell(date('Y-m-d', $res['time']));
			$table->cell(date('G:i:s', $res['duration'] + 57600));
			$table->cell($type[$res['type']]);
			$data = json_decode($res['data'], TRUE)[$this->webapp->site] ?? [];
			$table->cell([-2 => '下架', -1 => '会员', 0 => '免费'][$require = $data['require'] ?? 0] ?? $require);
			$table->cell(number_format($data['favorite']));
			$table->cell(number_format($data['view']));
			$table->cell(number_format($data['like']));
			$table->cell()->append('div', [
				'style' => 'width:30rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'
			])->append('a', [$data['name'], 'href' => "?admin/resource-update,hash:{$res['hash']}"]);
		}, $this->webapp['app_restype']);
		$table->fieldset('❌', 'hash', 'time', 'duration', 'type', 'require', 'favorite', 'view', 'like', 'name');
		$table->header('Found %d item', $table->count());
		$table->button('Upload Resource', ['onclick' => 'location.href="?admin/resource-upload"']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->bar->select(['' => '全部'] + $this->webapp['app_restype'])->setattr(['onchange' => 'g({type:this.value===""?null:this.value})'])->selected($type);
		$table->bar->select([
			'finished' => '完成',
			'waiting' => '等待',
			'exception' => '异常'
		])->setattr(['onchange' => 'g({sync:this.value})'])->selected($sync);
		$table->paging($this->webapp->at(['page' => '']));
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
		$this->form_account($this->main)->echo($this->webapp->mysql->accounts('where site=?i and uid=?s', $this->webapp->site, $uid)->array());
	}
	function get_accounts($search = NULL, int $page = 1)
	{
		$cond = ['where site=?i', $this->webapp->site];
		if (is_string($search))
		{
			$cond[0] .= is_numeric($search) ? ' and phone=?s' : ' and uid=?s';
			$cond[] = $search;
		}
		$cond[0] .= ' order by time desc';
		$table = $this->main->table($this->webapp->mysql->accounts(...$cond)->paging($page), function($table, $acc)
		{
			$table->row();
			$table->cell()->append('a', [$acc['uid'], 'href' => "?admin/account-update,uid:{$acc['uid']}"]);
			$table->cell(date('Y-m-d', $acc['time']));
			$table->cell(date('Y-m-d', $acc['expire']));
			$table->cell(number_format($acc['balance']));
			$table->cell(date('Y-m-d', $acc['lasttime']));
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
			$table->cell($acc['phone']);
			$table->cell($acc['name']);
		});
		$table->fieldset('uid', 'time', 'expire', 'balance', 'lasttime', 'lastip', 'device', 'unit', 'code', 'phone', 'name');
		$table->header('Found %d item', $table->count());
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
				'data-src' => "{$this->webapp['app_resoutput']}/news/{$ad['hash']}"
			])->append('script')->cdata('const pic = document.querySelector("fieldset[data-src]");
			loader(pic.dataset.src,null,"application/octet-stream").then(b=>pic.style.background=`center/contain no-repeat url(${URL.createObjectURL(b)}) silver`)');
			
		}
	}
	//================Extensions================
	//报告
	function form_report($ctx):webapp_form
	{
		$form = new webapp_form($ctx);
		$form->fieldset('describe');
		$form->field('describe', 'text', ['style' => 'width:740px', 'placeholder' => '通知内容', 'required' => NULL]);
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
			// $table->cell()->append('a', [$rep['promise'],
			// 	'href' => "?admin/resolve,hash:{$rep['hash']}",
			// 	'style' => "color:red"]);
			$table->cell($rep['promise']);
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
		$table->header('Reports, Found %d item', $table->count());
		$table->button('Create Report', ['onclick' => 'location.href="?admin/report-create"']);
		$table->search(['value' => $search, 'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})']);
		$table->paging($this->webapp->at(['page' => '']));
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
		$tags = $this->webapp->mysql->setvods('WHERE site=?i ORDER BY time DESC', $this->webapp->site)->column('name', 'hash');
		$form->field('vods', 'checkbox', ['options' => $tags], 
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
	function form_setvod($ctx)
	{
		$form = new webapp_form($ctx);
		$form->fieldset('name / sort / type / viewtype / ad');
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);
		$form->field('type', 'select', ['options' => $this->webapp['app_restype'], 'required' => NULL]);
		$form->field('viewtype', 'select', ['options' => ['双联', '横中滑动', '大一偶小', '横小滑动', '竖小'], 'required' => NULL]);
		$form->field('ad', 'select', ['options' => ['' => '请选择展示广告']
			+ $this->webapp->mysql->ads('WHERE site=?i ORDER BY time DESC', $this->webapp->site)->column('name', 'hash')]);

		$form->fieldset('describe');
		$form->field('describe', 'text', ['style' => 'width:60rem', 'placeholder' => '合集描述', 'required' => NULL]);

		$form->fieldset('tags');
		$tags = $this->webapp->mysql->tags('ORDER BY time ASC', $this->webapp->site)->column('name', 'hash');
		$form->field('tags', 'checkbox', ['options' => $tags], 
			fn($v,$i)=>$i?join($v):str_split($v,4))['class'] = 'restag';

		$form->fieldset('resources');
		$form->field('resources', 'text', [
			'style' => 'width:60rem',
			'placeholder' => '请输入展示的资源哈希用逗号间隔',
			'pattern' => '[0-9A-Z]{12}(,[0-9A-Z]{12})*',
			'required' => NULL
		], fn($v,$i)=>$i?join(explode(',',$v)):join(',',str_split($v,12)));

		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}
	function post_setvod_create()
	{
		if ($this->form_setvod($this->webapp)->fetch($data)
			&& $this->webapp->mysql->setvods->insert($data += [
				'hash' => $this->webapp->randhash(),
				'site' => $this->webapp->site,
				'time' => $this->webapp->time,
				'view' => 0])
			&& $this->webapp->call('saveSetvod', $this->webapp->setvod_xml($data))) {
			return $this->okay('?admin/setvods');
		}
		$this->warn('合集资源创建失败！');
	}
	function get_setvod_create()
	{
		$this->form_setvod($this->main);
	}
	function get_setvod_delete(string $hash)
	{
		if ($this->webapp->call('delSetvod', $hash)
			&& $this->webapp->mysql->setvods->delete('WHERE site=?s AND hash=?s', $this->webapp->site, $hash)) {
			return $this->okay('?admin/setvods');
		}
		$this->warn('合集资源删除失败！');
	}
	function post_setvod_update(string $hash, string $type = NULL)
	{
		if ($this->form_setvod($this->webapp)->fetch($data)
			&& $this->webapp->mysql->setvods('WHERE hash=?s LIMIT 1', $hash)->update($data)
			&& ($newdata = $this->webapp->mysql->setvods('WHERE hash=?s LIMIT 1', $hash)->array())
			&& $this->webapp->call('saveSetvod', $this->webapp->setvod_xml($newdata))) {
			return $this->okay("?admin/setvods,type:{$type}");
		}
		$this->warn('合集资源更新失败！');
	}
	function get_setvod_update(string $hash)
	{
		if ($data = $this->webapp->mysql->setvods('WHERE hash=?s LIMIT 1', $hash)->array())
		{
			$this->form_setvod($this->main)->echo($data);
		}
	}
	function get_setvods(string $type = NULL)
	{
		$cond = ['WHERE site=?i', $this->webapp->site];
		if (is_string($type) && strlen($type))
		{
			$cond[0] .= ' AND type=?s';
			$cond[] = $type;
		}
		$cond[0] .= ' ORDER BY view DESC';
		$count = 0;
		$table = $this->main->table($this->webapp->mysql->setvods(...$cond), function($table, $vod, $type, $viewtype) use(&$count)
		{
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
		}, $this->webapp['app_restype'], ['双联', '横中滑动', '大一偶小', '横小滑动', '竖小']);
		$table->fieldset('❌', 'hash', 'time', 'view', 'sort', 'type', 'viewtype', 'ad', 'RES', 'name', 'describe');
		$table->header('Found %d item', $count);
		$table->button('Create Set Vod', ['onclick' => 'location.href="?admin/setvod-create"']);
		$table->bar->select(['' => '全部'] + $this->webapp['app_restype'])->setattr(['onchange' => 'g({type:this.value===""?null:this.value})'])->selected($type);
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