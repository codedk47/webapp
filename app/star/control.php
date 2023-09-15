<?php
class webapp_router_control extends webapp_echo_html
{
	//private readonly array $signinfo;
	private array $json = [];
	private readonly bool $admin;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Control');
		if ($signinfo = $webapp->authorize($webapp->request_cookie($webapp['admin_cookie']), $this->sign_in_auth(...)))
		{
			$this->admin = $signinfo['is_admin'];

			$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/star/base.css']);
			$this->script(['src' => '/webapp/res/js/loader.js']);
			$this->script([
				'src' => '/webapp/app/star/base.js',
				'data-key' => bin2hex(random_bytes(8)),
				'data-origin' => end($this->webapp['app_resorigins'])]);
			$this->nav([
				['数据', '?control/home'],
				['标签 & 分类', '?control/tags'],
				['专题', '?control/subjects'],
				['上传账号', '?control/uploaders'],
				['用户', '?control/users'],
				
				['视频', '?control/videos'],
				['产品', '?control/prods'],
				['广告', '?control/ads'],
				['记录', [
					['上传的图片', '?control/images'],
					// ['充值VIP', '?control'],
					// ['充值金币', '?control'],
					['购买影片', '?control/record-video'],
					['余额提现', '?control/record-exchange-balance'],
					['游戏提现', '?control/record-exchange-game']
				]],
				['评论', '?control/comments'],
				['渠道', '?control/channels'],
				['注销登录', "javascript:top.location.reload(document.cookie='webapp=0');", 'style' => 'color:maroon']
			]);
		}
		else
		{
			$this->admin = FALSE;
			$webapp->method === 'post_sign_in' || $webapp->break($this->sign_in_page(...));
		}
	}
	function __toString():string
	{
		return $this->json ? (string)($this->webapp)(new webapp_echo_json($this->webapp, $this->json)) : parent::__toString();
	}
	function sign_in_auth(string $uid, string $pwd):array
	{
		if ($uid === $this->webapp['admin_username'] && $pwd === $this->webapp['admin_password'])
		{
			return ['uid' => $uid, 'is_admin' => TRUE];
		}
		if (array_key_exists($uid, $this->webapp['admin_users']) && $pwd === $this->webapp['admin_users'][$uid])
		{
			return ['uid' => $uid, 'is_admin' => FALSE];
		}
		return [];
	}
	function sign_in_page()
	{
		webapp_echo_html::form_sign_in($this->main)->xml['action'] = '?control/sign-in';
		return 401;
	}
	function post_sign_in()
	{
		$this->webapp->response_location($this->webapp->request_referer('?control'));
		if (webapp_echo_html::form_sign_in($this->webapp)->fetch($admin)
			&& $this->webapp->authorize($signature = $this->webapp->signature(
				$admin['username'], $admin['password']), $this->sign_in_auth(...))) {
			$this->webapp->response_cookie($this->webapp['admin_cookie'], $signature);
			return 200;
		}
		return 401;
	}
	function goto(string $url = NULL):void
	{
		$this->json['goto'] = $url === NULL ? NULL : "?control{$url}";
	}
	function dialog(string $msg):void
	{
		$this->json['dialog'] = $msg;
	}




	function get_home(string $datefrom = '')
	{
		if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $datefrom))
		{
			$datefrom = date('Y-m-d');
		}
		$statistics = [
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
			'recharge',
			'recharge_new',
			'recharge_old',
			'recharge_coin',
			'recharge_vip',
			'recharge_vip_new',
			'order',
			'order_ok',
			'order_ios',
			'order_ios_ok',
			'order_android',
			'order_android_ok'
		];
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

		$logs = $this->webapp->mysql('SELECT cid,??,JSON_ARRAY(??) AS hourdata FROM recordlog WHERE date=?s GROUP BY cid',
			join(',', $sum), join(',', $merge), $datefrom);
		$table = $this->main->table($logs, function($table, $log, $statistics)
		{
			$node = [$table->row()];
			$table->cell([$log['cid'], 'rowspan' => 24]);
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
			$table->cell(['日活', 'rowspan' => 3]);
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
			$table->cell(['充值', 'rowspan' => 6]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('新充值');
			$node[] = $table->row();
			$table->cell('老充值');
			$node[] = $table->row();
			$table->cell('金币');
			$node[] = $table->row();
			$table->cell('VIP总');
			$node[] = $table->row();
			$table->cell('VIP新');
			$node[] = $table->row();
			$table->cell(['订单', 'rowspan' => 6]);
			$table->cell('总计');
			$node[] = $table->row();
			$table->cell('成功');
			$node[] = $table->row();
			$table->cell('苹果总计');
			$node[] = $table->row();
			$table->cell('苹果成功');
			$node[] = $table->row();
			$table->cell('安卓总计');
			$node[] = $table->row();
			$table->cell('安卓成功');
			$hourdata = json_decode($log['hourdata'], TRUE);
			foreach ($statistics as $i => $field)
			{
				$node[$i]->append('td', number_format($log[$field]));
				foreach (range(0, 23) as $hour)
				{
					$node[$i]->append('td', number_format($hourdata[$hour][$field]));
				}
			}
		}, $statistics);
		$table->xml['class'] .= '-statistics';
		$table->fieldset('渠道', '分类', '详细', '总计', ...range(0, 23));
		$table->header('数据统计');
		$table->bar->append('input', ['type' => 'date', 'value' => $datefrom, 'onchange' => 'g({datefrom:this.value||null})']);
		//$table->bar->append('input', ['type' => 'date', 'value' => $datefrom]);

	}

	//========标签========
	function tag_types():array
	{
		return $this->webapp->mysql->tags('WHERE phash IS NULL ORDER BY sort DESC,hash ASC')->column('name', 'hash');
	}
	function form_tag(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('标签名称 / 归属分类 / 排序（越大越靠前）');
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('phash', 'select', ['options' => ['' => '分类', ...$this->tag_types()]]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

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
		if ($phash = $this->webapp->query['phash'] ?? '')
		{
			$conds[0][] = 'phash=?s';
			$conds[] = $phash;
		}
		$conds[0] = sprintf('%sORDER BY phash ASC,sort DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');

		$tag_types = $this->tag_types();
		$table = $this->main->table($this->webapp->mysql->tags(...$conds)->paging($page), function($table, $value, $types)
		{
			$table->row();
			$table->cell()->append('a', ['删除', 'href' => "?control/tag,hash:{$value['hash']}", 'data-method' => 'delete', 'data-bind' => 'click']);
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));
			$table->cell($value['hash']);
			$table->cell($types[$value['phash']] ?? '分类');
			$table->cell($value['sort']);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/tag,hash:{$value['hash']}"]);
			
		}, $tag_types);
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('删除', '创建时间', '修改时间', 'HASH', '级别', '排序', '名称');
		$table->header('标签 %d 项', $table->count());
		$table->bar->append('button', ['添加标签或分类', 'onclick' => 'location.href="?control/tag"']);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px',
			'placeholder' => '关键字【Enter】搜索',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->append('span', ['style' => 'margin:0 .6rem'])
			->select(['' => '全部标签'] + $tag_types)
			->setattr(['onchange' => 'g({phash:this.value||null})', 'style' => 'padding:.1rem'])->selected($phash);
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
				'phash' => strlen($tag['phash']) === 4 ? $tag['phash'] : NULL,
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time] + $tag)) {
			$this->goto('/tags');
		}
		else
		{
			$this->dialog('标签添加失败！');
		}
	}
	function delete_tag(string $hash)
	{
		$this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->delete() === 1
			? $this->goto('/tags')
			: $this->dialog('标签删除失败！');
	}
	function patch_tag(string $hash)
	{
		if ($this->form_tag()->fetch($tag)
			&& $this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->update([
				'phash' => strlen($tag['phash']) === 4 ? $tag['phash'] : NULL,
				'ctime' => $this->webapp->time] + $tag)) {
			$this->goto('/tags');
		}
		else
		{
			$this->dialog('标签修改失败！');
		}
	}


	//========专题========
	function subject_styles():array
	{
		return [
			1 => '全大图',
			2 => '全小图',
			5 => '5宫格',
			7 => '个人'
		];
	}
	function subject_fetch_methods():array
	{
		return [
			'tags' => '分类（标签HASH交集）',
			'words' => '关键词（标题包含关键词）',
			'uploader' => 'UP主（用户ID并集）'
		];
	}
	function form_subject(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('专题分类 / 排序（越大越靠前）');

		$form->field('tagid', 'select', ['options' => $this->tag_types(), 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset('专题名称 / 展示样式');
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('style', 'select', ['options' => $this->subject_styles(), 'required' => NULL]);

		$form->fieldset('数据来源');
		$form->field('fetch_method', 'select', ['options' => $this->subject_fetch_methods()]);
		$form->field('fetch_values', 'text', ['placeholder' => '多个值请用 "," 间隔', 'required' => NULL]);

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
		if ($tagid = $this->webapp->query['tagid'] ?? '')
		{
			$conds[0][] = 'tagid=?s';
			$conds[] = $tagid;
		}

		$conds[0] = sprintf('%sORDER BY tagid ASC,sort DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$tag_types = $this->tag_types();
		$subject_styles = $this->subject_styles();
		$table = $this->main->table($this->webapp->mysql->subjects(...$conds)->paging($page), function($table, $value, $tags, $styles)
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
			$table->cell($tags[$value['tagid']]);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/subject,hash:{$value['hash']}"]);
			$table->cell($styles[$value['style']] ?? $value['style']);

			$table->cell()->append('a', ["{$value['fetch_method']}({$value['fetch_values']})",
				'href' => "?control/videos,subject:{$value['hash']}"]);

		}, $tag_types, $subject_styles);
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('删除', '创建时间', '修改时间', 'HASH', '排序', '标签', '名称', '展示样式', '数据来源');
		$table->header('专题 %d 项', $table->count());
		$table->bar->append('button', ['添加专题', 'onclick' => 'location.href="?control/subject"']);

		$table->bar->append('span', ['style' => 'margin:0 .6rem'])
			->select(['' => '全部分类', ...$this->webapp->mysql->tags('WHERE phash IS NULL')->column('name', 'hash')])
			->setattr(['onchange' => 'g({tagid:this.value||null})', 'style' => 'padding:.1rem'])->selected($tagid);
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
			$this->goto('/subjects');
		}
		else
		{
			$this->dialog('专题添加失败！');
		}
	}
	function delete_subject(string $hash)
	{
		$this->webapp->mysql->subjects('WHERE hash=?s LIMIT 1', $hash)->delete() === 1
			? $this->goto('/subjects')
			: $this->dialog('专题删除失败！');
	}
	function patch_subject(string $hash)
	{
		if ($this->form_subject()->fetch($subject)
			&& $this->webapp->mysql->subjects('WHERE hash=?s LIMIT 1', $hash)->update([
			'ctime' => $this->webapp->time] + $subject)) {
			$this->goto('/subjects');
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
	function form_user(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('会员到期 / 金币余额 / 绑定UP主后台上传ID（0非UP主）');
		$form->field('expire', 'date', [], fn($v, $i)=>$i?strtotime($v):date('Y-m-d', $v));
		$form->field('coin', 'number', ['min' => 0]);
		$form->field('uid', 'number', ['min' => 0, 'max' => 65535]);

		$form->button('更新', 'submit');

		$form->xml['method'] = 'patch';
		$form->xml['data-bind'] = 'submit';
		return $form;
	}


	function get_users(int $page = 1)
	{
		$conds = [[]];
		if (strlen($uid = $this->webapp->query['uid'] ?? ''))
		{
			if (intval($uid) === -1)
			{
				$conds[0][] = 'uid!=0';
			}
			else
			{
				$conds[0][] = 'uid=?i';
				$conds[] = $uid;
			}
		}
		if ($search = $this->webapp->query['search'] ?? '')
		{
			if (strlen($search) === 10 && trim($search, webapp::key) === '')
			{
				$conds[0][] = 'id=?s';
				$conds[] = $search;
			}
		}

		$conds[0] = sprintf('%sORDER BY id,mtime DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->users(...$conds)->paging($page), function($table, $value)
		{
			$table->row();

			$table->cell($value['date']);
			$table->cell(date('Y-m-d\\TH:i:s', $value['lasttime']));
			$table->cell($this->webapp->hexip($value['lastip']));
			$table->cell()->append('a', [$value['id'], 'href' => "?control/user,id:{$value['id']}"]);
			$table->cell($value['cid']);

			$table->cell($value['device']);
			$table->cell($value['tid']);
			$table->cell($value['did']);

			$table->cell($value['nickname']);
			$table->cell(number_format($value['video_num']));
			$table->cell(number_format($value['balance']));
			$table->cell(date('Y-m-d', $value['expire']));
			$table->cell(number_format($value['coin']));

		});
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('注册日期', '最后登录日期', '最后登录IP', 'ID', '渠道ID', '设备类型', '绑定手机', '设备ID', '昵称', '影片数', '余额', '会员到期', '金币');
		$table->header('用户 %d 项', $table->count());

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $uid,
			'style' => 'width:10rem',
			'placeholder' => 'UP后台ID，-1 全部',
			'onkeydown' => 'event.keyCode==13&&g({uid:this.value||null,page:null})'
		]);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem',
			'placeholder' => '用户信息按【Enter】搜索',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);


	}
	function get_user(string $id)
	{
		if ($this->webapp->mysql->users('WHERE id=?s LIMIT 1', $id)->fetch($user))
		{
			$form = $this->form_user($this->main);
			$form->xml->fieldset[0] = $this->webapp->signature($user['id'], $user['cid']);
			$form->echo($user);
		}
	}

	function patch_user(string $id)
	{
		if ($this->form_user()->fetch($user) && $this->webapp->mysql->users('WHERE id=?s LIMIT 1', $id)->update([
			'ctime' => $this->webapp->time
		] + $user)) {
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
			$content = $figures->append('div');
			$content->append('div', $value['sync'] === 'pending'
				? ['pending']
				: ['data-cover' => "/imgs/{$ym}/{$value['hash']}?{$value['ctime']}"]);
			$content->append('div', $value['hash']);

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
		$this->script(['src' => '/webapp/res/js/player.js']);
		$conds = [[]];
		if (is_string($search))
		{
			$search = urldecode($search);
			if (trim($search, webapp::key))
			{
				$conds[0][] = 'name LIKE ?s';
				$conds[] = "%{$search}%";
			}
			else
			{
				$conds[0][] = strlen($search) === 4 ? 'FIND_IN_SET(?s,tags)' : 'hash=?s';
				$conds[] = $search;
			}
		}
		if ($userid = $this->webapp->query['userid'] ?? '')
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}
		if ($tag = $this->webapp->query['tag'] ?? '')
		{
			$conds[0][] = 'FIND_IN_SET(?s,tags)';
			$conds[] = $tag;
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
			$conds[0][] = 'type=?s';
			$conds[] = $type;
		}

		if ($subject = $this->webapp->query['subject'] ?? '')
		{
			$conds[0][] = 'FIND_IN_SET(?s,subjects)';
			$conds[] = $subject;
		}
		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY ' . match ($sort = $this->webapp->query['sort'] ?? '')
		{
			'view-desc' => '`view` DESC',
			'like-desc' => '`like` DESC',
			'sales-desc' => '`sales` DESC',
			default => '`ctime` DESC'
		}. ',hash ASC';
		$tags = $this->webapp->mysql->tags->column('name', 'hash');
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
				$cover->append('div', [
					'id' => "v{$value['hash']}",
					'data-cover' => "/{$ym}/{$value['hash']}/cover?{$value['ctime']}",
					'data-playm3u8' => "/{$ym}/{$value['hash']}/play",
					'onclick' => "view_video(this.dataset, {$value['preview']})"
				]);
				$title['onclick'] = "view_video(document.querySelector('div#v{$value['hash']}').dataset)";
			}
			else
			{
				$cover[0] = 'Pending';
			}

		}, $tags);
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('封面（预览视频）', '信息');
		$table->header('视频 %d 项', $table->count());
		unset($table->xml->tbody->tr[0]);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $userid,
			'style' => 'padding:2px;width:8rem',
			'placeholder' => '用户ID',
			'onkeydown' => 'event.keyCode==13&&g({userid:this.value||null,page:null})'
		]);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px;width:24rem',
			'placeholder' => '请输入视频HASH、标签HASH、关键字按【Enter】进行搜索。',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		$table->bar->select(['' => '全部分类'] + $this->tag_types())
			->setattr(['onchange' => 'g({tag:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($tag);
		$table->bar->select(['' => '全部状态', 'uploading' => '正在上传'] + base::video_sync)
			->setattr(['onchange' => 'g({sync:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($sync);
		$table->bar->select(['' => '要求', 'vip' => '会员', 'free' => '免费', 'coin' => '金币'])
			->setattr(['onchange' => 'g({require:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($require);
		$table->bar->select(['' => '全部类型'] + base::video_type)
			->setattr(['onchange' => 'g({type:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($type);
		$table->bar->select(['' => '默认排序（最后修改）',
			'view-desc' => '观看（降序）',
			'like-desc' => '点赞（降序）',
			'sales-desc' => '销量（降序）'])
			->setattr(['onchange' => 'g({sort:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($sort);
		$table->bar->append('button', ['所有完成视频通过审核',
			'style' => 'margin-left:.6rem',
			'data-src' => '?control/video-all-finished-to-allow',
			'data-method' => 'patch',
			'data-bind' => 'click'
		]);
		$table->bar->append('button', ['清理异常',
			'style' => 'margin-left:.6rem',
			'onclick' => 'location.href="?control/video-exception-clear"'
		]);
		$table->bar['style'] = 'white-space:nowrap';
	}
	function get_video(string $hash)
	{
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
			'会员' => [
				'prod_vtid_vip50' => '会员卡 50元',
				'prod_vtid_vip100' => '会员卡 100元',
				'prod_vtid_vip200' => '会员卡 200元',
				'prod_vtid_vip300' => '会员卡 300元',
				'prod_vtid_vip500' => '会员卡 500元'
			],
			'金币' => [
				'prod_vtid_coin50' => '金币 50个',
				'prod_vtid_coin100' => '金币 100个',
				'prod_vtid_coin200' => '金币 200个',
				'prod_vtid_coin300' => '金币 300个',
				'prod_vtid_coin500' => '金币 500个'
			],
			'游戏' => [
				'prod_vtid_game100' => '游戏 100元',
				'prod_vtid_game300' => '游戏 300元',
				'prod_vtid_game500' => '游戏 500元',
				'prod_vtid_game800' => '游戏 800元',
				'prod_vtid_game1000' => '游戏 1000元',
				'prod_vtid_game2000' => '游戏 2000元',
				'prod_vtid_game3000' => '游戏 3000元',
				'prod_vtid_game5000' => '游戏 5000元',
				'prod_vtid_game10000' => '游戏 10000元'
			]
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
			$table->cell([number_format($value['sales']), 'style' => 'text-align:right']);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/prod-update,hash:{$value['hash']}"]);
			$table->cell([number_format($value['count']), 'style' => 'text-align:right']);
			$table->cell([number_format($value['price']), 'style' => 'text-align:right']);

			$table->cell()->append('a', ['删除',
				'href' => "?control/prod,hash:{$value['hash']}",
				'data-method' => 'delete',
				'data-bind' => 'click',
				'data-dialog' => "删除 {$value['hash']} 确定？"
			]);

		}, $this->prod_vtids(TRUE));
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('创建时间', '修改时间', 'HASH', 'VTID', '累计销量', '名称', '剩余数量', '单价（元）', '删除');
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


	//========广告========
	function ad_seats():array
	{
		return [
			0 => '开屏广告',
			1 => '首页轮播',
			2 => '中间轮播',
			3 => '游戏轮播',
			4 => '待定',
			5 => '个人中心'
			//255 => '待定分类'
		];
	}
	function ad_displays():array
	{
		return [
			'hide' => '隐藏（不展示）',
			'show' => '显示（展示中）'
		];
	}
	function form_ad(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset->append('div', [
			'class' => 'cover',
			'style' => 'width:32rem;height:18rem;background-size:contain'
		]);

		$form->fieldset('广告图片');
		$form->field('ad', 'file', ['accept' => 'image/*', 'onchange' => 'cover_preview(this,document.querySelector("div.cover"))']);
		$form->field('display', 'select', ['options' => $this->ad_displays()]);

		$form->fieldset('展示位置 / 权重（越大越几率越大）');

		$form->field('seat', 'select', ['options' => $this->ad_seats(), 'required' => NULL]);
		$form->field('weight', 'number', ['min' => 0, 'max' => 255, 'value' => 1, 'required' => NULL]);

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
		$table = $this->main->table($this->webapp->mysql->ads(...$conds)->paging($page), function($table, $value, $seats, $displays)
		{
			$table->row()['style'] = 'background-color:var(--webapp-hint)';
			$table->cell('封面');
			$table->cell(['信息', 'colspan' => 6]);

			$table->row();
			$cover = $table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144', 'class' => 'cover'])->append('div');
			if ($value['change'] === 'sync')
			{
				$cover[0] = '等待同步...';
			}
			else
			{
				$cover['style'] = 'background-size:contain';
				$cover['data-cover'] = "/news/{$value['hash']}?{$value['ctime']}";
			}

			$table->row();
			$table->cell('HASH');
			$table->cell($value['hash']);
			$table->cell('创建时间');
			$table->cell(date('Y-m-d\\H:i:s', $value['mtime']));
			$table->cell('修改时间');
			$table->cell(date('Y-m-d\\H:i:s', $value['ctime']));

			$table->row();
			$table->cell('位置');
			$table->cell($seats[$value['seat']]);
			$table->cell('展示权重');
			$table->cell($value['weight']);
			$table->cell('是否展示');
			$table->cell($displays[$value['display']]);

			$table->row();
			$table->cell('行为URL');
			$table->cell(['colspan' => 6])->append('a', [$value['acturl'], 'href' => $value['acturl']]);

			$table->row();
			$table->cell('功能');
			$td = $table->cell(['colspan' => 5]);
			// $td->append('button', ['新窗口打开改行为URL', 'onclick' => "location.href='?control/ad-update,hash:{$value['hash']}'"]);
			// $td->append('span', ' | ');
			$td->append('button', ['修改信息', 'onclick' => "location.href='?control/ad-update,hash:{$value['hash']}'"]);

		}, $seats = $this->ad_seats(), $displays = $this->ad_displays());
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('封面', '字段', '信息');
		$table->header('广告 %d 项', $table->count());
		unset($table->xml->tbody->tr[0]);

		$table->bar->append('button', ['添加广告', 'onclick' => 'location.href="?control/ad-insert"']);
		$table->bar->select(['' => '所有位置'] + $seats)
			->setattr(['onchange' => 'g({seat:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($seat);
		$table->bar->select(['' => '所有展示'] + $displays)
			->setattr(['onchange' => 'g({display:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($display);
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
				'change' => 'sync'] + $ad)
			&& $uploadedfile->maskfile("{$this->webapp['ad_savedir']}/{$hash}")) {
			$this->webapp->response_location('?control/ads');
		}
		else
		{
			$this->main->append('h4', '广告插入失败！');
		}
	}
	function get_ad_update(string $hash)
	{
		if ($this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->fetch($ad))
		{
			$form = $this->form_ad($this->main);
			$form->xml->fieldset->div['data-cover'] = "/news/{$ad['hash']}?{$ad['ctime']}";
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

	//话题 & 评论
	function get_comments(string $search = NULL, string $phash = NULL, int $page = 1)
	{
		$conds = [[]];
		if ($phash)
		{
			if ($phash === 'video')
			{
				$conds[0][] = 'type=?s';
			}
			else
			{
				$conds[0][] = 'phash=?s';
			}
			$conds[] = $phash;
		}
		else
		{
			$conds[0][] = 'phash IS NOT NULL';
		}
		if ($check = $this->webapp->query['check'] ?? '')
		{
			$conds[0][] = '`check`=?s';
			$conds[] = $check;
		}
		if ($userid = $this->webapp->query['userid'] ?? '')
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}

		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY `sort` DESC,`mtime` DESC';
		$table = $this->main->table($this->webapp->mysql->comments(...$conds)->paging($page, 10), function($table, $value)
		{
			$table->row();
			$table->cell($value['hash']);
			$table->cell()->append('a', [$value['userid'], 'href' => "?control/comments,userid:{$value['userid']}"]);
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(number_format($value['count']));
			$table->cell(base::comment_type[$value['type']]);
			$table->cell([$value['sort'],
				'data-src' => "?control/comment,hash:{$value['hash']}",
				'data-method' => 'patch',
				'data-dialog' => '{"sort":"number"}',
				'data-value' => $value['sort'],
				'data-bind' => 'click'
			]);

			$check = $table->cell();
			if ($value['check'] === 'pending')
			{
				$check->append('a', ['允许',
					'href' => "?control/comment,hash:{$value['hash']},field:check,value:allow",
					'data-method' => 'patch',
					'data-bind' => 'click'
				]);
				$check->append('span', ' | ');
				$check->append('a', ['拒绝',
					'href' => "?control/comment,hash:{$value['hash']},field:check,value:deny",
					'data-method' => 'patch',
					'data-bind' => 'click'
				]);
			}
			else
			{
				$check->text($value['check']);
			}

			$table->row();
			$contents = $table->cell(['colspan' => 7])->append('pre', ['style' => 'width:60rem;margin:0;line-height:1.4rem;white-space:pre-wrap;word-wrap: break-word;']);
			if ($value['type'] !== 'video' && $value['title'])
			{
				$contents->text($value['title']);
				$contents->text("\n");
			}
			if ($value['content'])
			{
				$contents->text("\t{$value['content']}");
			}

		});
		$table->fieldset('HASH', '用户ID', '发布时间', '数量', '类型', '排序', '审核');
		$table->header('评论 %s 项', $table->count());
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('button', ['添加分类', 'onclick' => 'location.href="?control/comment_class"']);
		$table->bar->append('span', ['style' => 'margin-left:.6rem'])
			->select(['' => '全部分类', 'video' => '影片评论'] + $this->webapp->select_topics())
			->setattr(['onchange' => 'g({phash:this.value||null})', 'style' => 'padding:.1rem'])->selected($phash);
		$table->bar->append('span', ['style' => 'margin-left:.6rem'])
			->select(['' => '全部状态', 'pending' => '等待审核', 'allow' => '通过审核', 'deny' => '未通过'])
			->setattr(['onchange' => 'g({check:this.value||null})', 'style' => 'padding:.1rem'])->selected($check);

		$table->bar->append('button', ['删除该分类和话题以及所有帖子和评论',
			'style' => 'margin-left:.6rem',
			'data-src' => "?control/comment,hash:{$phash}",
			'data-method' => 'delete',
			'data-dialog' => '删除后不可恢复',
			'data-bind' => 'click'
		]);
		$table->bar->append('button', ['辅助UP主发布评论',
			'style' => 'margin-left:.6rem',
			'onclick' => 'location.href="?control/comment"'
		]);
	}
	function post_comments(string $type)
	{
		$search = $this->webapp->request_content();
		if ($type === 'reply')
		{
			$this->json['comments'] = [];
			return;
		}
		$this->json['comments'] = $type === 'video'
			? $this->webapp->mysql->videos('WHERE sync="allow" AND name LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 10', "%{$search}%")
				->column('name', 'hash')
			: $this->webapp->mysql->comments('WHERE type=?s AND `check`="allow" AND title LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 10', $type, "%{$search}%")
				->column('title', 'hash');
	}
	function form_comment(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('用户ID');
		
		$form->field('userid', 'text', ['maxlength' => 10, 'oninput' => 'localStorage.setItem("comment_userid",this.value)', 'required' => NULL]);
		$form->fieldset->append('script')->cdata('document.querySelector("form.webapp>fieldset>input[name=userid]").value=localStorage.getItem("comment_userid")');

		$form->fieldset('类型 / 搜索');
		$form->field('type', 'select', ['options' => ['reply' => '请选择类型'] + base::comment_type,
			'onchange' => 'this.nextElementSibling.dataset.type=this.value;search_comment(this.nextElementSibling,this.parentElement.nextElementSibling)',
			'required' => NULL]);
		$form->field('phash', 'search', [
			'data-type' => 'reply',
			'data-action' => '?control/comments',
			'placeholder' => '选择左边类型后输入关键字进行搜索选择',
			'oninput' => 'search_comment(this,this.parentElement.nextElementSibling)',
			'style' => 'width:24rem']);
		$form->fieldset()->setattr('class', 'search_comment');

		$form->fieldset('标题');
		$form->field('title', 'text', ['placeholder' => '话题和评论必须要一个标题', 'style' => 'width:31rem']);

		$form->fieldset('内容');
		$form->field('content', 'textarea', ['rows' => 10, 'cols' => 50, 'required' => NULL]);

		$form->fieldset('图片');
		$form->field('images', 'textarea', ['rows' => 4, 'cols' => 36,
			'placeholder' => '图片最多不超过10个','readonly' => NULL]);
		$form->fieldset->append('label', '添加图片')->append('input', [
			'type' => 'file',
			'accept' => 'image/*',
			'style' => 'display:none',
			'data-uploadurl' => '?uploadimage',
			'onchange' => 'upload_image(this,admin_comment_image)'
		]);
		$form->fieldset('视频');
		$form->field('video', 'search', [
			'data-action' => '?control/videos',
			'placeholder' => '输入视频关键字进行搜索选择，勾选最多不超过100个视频',
			'oninput' => 'search_videos(this,this.parentElement.nextElementSibling.firstElementChild)',
			'style' => 'width:32rem']);
		$form->fieldset()->setattr([
			'class' => 'search_comment',
			'style' => 'height:20rem'
		])->append('ul');


		$form->fieldset();
		$form->button('提交', 'submit');

		$form->xml['onsubmit'] = 'return admin_comment(this)';
		return $form;
	}
	function post_videos(string $userid)
	{
		$search = $this->webapp->request_content();
		$this->json['videos'] = $this->webapp->mysql
			//->videos('WHERE userid=?s AND sync="allow" AND name LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 20', $userid, "%{$search}%")
			->videos('WHERE sync="allow" AND name LIKE ?s ORDER BY mtime DESC,hash ASC LIMIT 20', "%{$search}%")
			->column('name', 'hash');
	}
	function get_comment()
	{
		$this->form_comment($this->main);
	}
	function post_comment()
	{
		$error = '无效内容！';
		while ($this->form_comment()->fetch($comment))
		{
			if ($comment['type'] === 'video')
			{
				if ($this->webapp->user($comment['userid'])->comment_video($comment['phash'], $comment['content']) === FALSE)
				{
					$error = '视频评论失败！';
					break;
				}
			}
			else
			{
				if (empty($comment['images']))
				{
					$comment['images'] = NULL;
				}
				if (empty($comment['videos'] = join($_POST['videos'] ?? [])))
				{
					$comment['videos'] = NULL;
				}
				$type = match ($comment['type'])
				{
					'class' => 'topic',
					'topic' => 'post',
					default => 'reply'
				};
				[$images, $videos] = match ($type)
				{
					'topic' => [NULL, $comment['videos']],
					'post' => [$comment['images'], $comment['videos']],
					default => [NULL, NULL]
				};
				if ($this->webapp->user($comment['userid'])->comment($comment['phash'],
					$comment['content'], $type, $comment['title'], $images, $videos, TRUE) === FALSE) {
					$error = '社区评论失败！';
					break;
				}
			}
			return $this->goto('/comments');
		}
		$this->dialog($error);
	}

	function form_comment_class(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('标题 / 排序（越大越靠前）');
		$form->field('title', 'text', ['maxlength' => 128, 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);
		$form->button('提交', 'submit');
		return $form;
	}
	function get_comment_class()
	{
		$this->form_comment_class($this->main);
	}
	function post_comment_class()
	{
		if ($this->form_comment_class()->fetch($data)
			&& $this->webapp->mysql->comments->insert([
				'hash' => $this->webapp->random_hash(FALSE),
				'mtime' => $this->webapp->time,
				'ctime' => $this->webapp->time,
				'type' => 'topic',
				'check' => 'allow',
				'count' => 0,
				'content' => ''] + $data)) {
			$this->webapp->response_location('?control/comments');
			return;
		}
		$this->main->append('h4', '话题发布失败！');
	}
	function delete_comment(string $hash)
	{
		if ($this->admin && $this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $hash)->delete() === 1)
		{
			$count_topic = 0;
			$count_post = 0;
			$count_reply = 0;
			foreach ($this->webapp->mysql->comments('WHERE phash=?s', $hash)->column('hash') as $topic_hash)
			{
				if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $topic_hash)->delete() === 1)
				{
					++$count_topic;
					foreach ($this->webapp->mysql->comments('WHERE phash=?s', $topic_hash)->column('hash') as $post_hash)
					{
						if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $topic_hash)->delete() === 1)
						{
							++$count_post;
							$count_reply += $this->webapp->mysql->comments('WHERE phash=?s', $post_hash)->delete();
						}
					}
				}
			}
			$this->dialog("删除 {$count_topic} 个话题。\n删除 {$count_post} 个帖子。\n删除 {$count_reply} 个评论。");
			$this->goto();
			return;
		}
		$this->dialog('需要超级管理员权限或者删除失败！');
	}
	function patch_comment(string $hash, string $field = NULL, string $value = NULL)
	{
		if ($input = $this->webapp->request_content())
		{
			$field = array_key_first($input);
			$value = $input[$field];
		}
		if ($this->webapp->mysql->comments('WHERE hash=?s LIMIT 1', $hash)->update('?a=?s', $field, $value) === 1)
		{
			$this->goto();
			return;
		}
		$this->dialog('操作失败！');
	}
	function get_channels(int $page = 1)
	{
		$conds = [[]];
		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->channels(...$conds)->paging($page), function($table, $value)
		{
			$table->row();
			// $table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			// $table->cell($value['userid']);
			// $table->cell($value['cid']);
			// $table->cell($value['fee']);
			// $table->cell($value['result']);
		});
		$table->fieldset('创建日期', '渠道ID');
		$table->header('渠道');
		$table->bar->append('button', ['创建渠道', 'onclick' => 'location.href="?control/channel"']);



	}
	function form_channel(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('渠道ID / 密码 ');
		$form->field('hash', 'text', ['pattern' => '^[\w\-]{4}$', 'required' => NULL]);


		$form->field('pwd', 'text', ['maxlength' => 16, 'required' => NULL]);

		$form->field('name', 'text', ['required' => NULL]);

		$form->button('提交', 'submit');
		return $form;
	}
	function get_channel()
	{
		$this->form_channel($this->main);
	}
	function get_record_video(int $page = 1)
	{
		$conds = [['type="video"']];
		$conds[0] = sprintf('%sORDER BY mtime DESC,hash ASC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->records(...$conds)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell($value['userid']);
			$table->cell($value['cid']);
			$table->cell($value['fee']);
			$table->cell($value['result']);
		});
		$table->fieldset('时间', '用户ID', '用户渠道ID', '费用', '结果');
		$table->header('用户购买视频 %d 项', $table->count());
		$table->bar->append('input', ['type' => 'date']);
		$table->bar->append('span', ' - ');
		$table->bar->append('input', ['type' => 'date']);
	}
	function get_record_exchange_balance(int $page = 1)
	{
		$conds = [['type="exchange"']];
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
		$table->header('提现记录');
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
		
	}
}