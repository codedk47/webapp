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
			$this->script(['src' => '/webapp/res/js/loader.js']);
			$this->script(['src' => '/webapp/app/star/control.js', 'data-origin' => $this->webapp['app_resorigins'][0]]);
			$this->nav([
				['标签管理', '?control/tags'],
				['专题管理', '?control/subjects'],
				['视频管理', '?control/videos'],
				['产品管理', '?control/prods'],
				['用户管理', '?control/users'],
				['广告管理', '?control/ads'],
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




	function get_home()
	{
	}

	//========标签========
	function tag_level():array
	{
		return [
			1 => '首页标签',
			2 => '自定义标签'
		];
	}
	function form_tag(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('标签级别 / 排序（越大越靠前）');
		$form->field('level', 'select', ['options' => $this->tag_level(), 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset('标签名称');
		$form->field('name', 'text', ['required' => NULL]);

		$form->fieldset();
		$form->button('提交', 'submit');

		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function get_tags(int $page = 1)
	{
		$conds = [[]];
		if ($level = $this->webapp->query['level'] ?? '')
		{
			$conds[0][] = 'level=?s';
			$conds[] = $level;
		}

		$conds[0] = sprintf('%sORDER BY level ASC,sort DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->tags(...$conds)->paging($page), function($table, $value, $level)
		{
			$table->row();

			
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));
			$table->cell($value['hash']);
			$table->cell($level[$value['level']]);
			$table->cell($value['sort']);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/tag,hash:{$value['hash']}"]);
			



		}, $this->tag_level());
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('创建时间', '修改时间', 'HASH', '级别', '排序', '名称');
		$table->header('标签 %d 项', $table->count());
		$table->bar->append('button', ['添加标签', 'onclick' => 'location.href="?control/tag"']);

		$table->bar->append('span', ['style' => 'margin:0 .6rem'])
			->select(['' => '全部级别'] + $this->tag_level())
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
			'mtime' => $this->webapp->time,
			'ctime' => $this->webapp->time] + $tag)) {
			$this->goto('/tags');
		}
		else
		{
			$this->dialog('标签添加失败！');
		}
	}
	function patch_tag(string $hash)
	{
		if ($this->form_tag()->fetch($tag)
			&& $this->webapp->mysql->tags('WHERE hash=?s LIMIT 1', $hash)->update([
			'ctime' => $this->webapp->time] + $tag)) {
			$this->goto('/tags');
		}
		else
		{
			$this->dialog('标签修改失败！');
		}
	}


	//========专题========
	function subject_style():array
	{
		return [
			1 => '全大图',
			2 => '全小图',
			5 => '5宫格'
		];
	}
	function subject_tags():array
	{
		return $this->webapp->mysql->tags('WHERE level=1')->column('name', 'hash');
	}
	function form_subject(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('专题标签 / 排序（越大越靠前）');

		$form->field('tagid', 'select', ['options' => $this->subject_tags(), 'required' => NULL]);
		$form->field('sort', 'number', ['min' => 0, 'max' => 255, 'value' => 0, 'required' => NULL]);

		$form->fieldset('专题名称 / 展示样式');
		$form->field('name', 'text', ['required' => NULL]);
		$form->field('style', 'select', ['options' => $this->subject_style(), 'required' => NULL]);

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

		$conds[0] = sprintf('%sORDER BY tagid ASC,sort DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->subjects(...$conds)->paging($page), function($table, $value, $tag, $style)
		{
			$table->row();

			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));
			$table->cell($value['hash']);
			$table->cell($value['sort']);
			$table->cell($tag[$value['tagid']]);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/subject,hash:{$value['hash']}"]);
			$table->cell($style[$value['style']] ?? $value['style']);

		}, $this->subject_tags(), $this->subject_style());
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('创建时间', '修改时间', 'HASH', '排序', '标签', '名称', '展示样式');
		$table->header('专题 %d 项', $table->count());
		$table->bar->append('button', ['添加专题', 'onclick' => 'location.href="?control/subject"']);


		
		$table->bar->append('span', ['style' => 'margin:0 .6rem'])
			->select(['' => '全部', ...$this->webapp->mysql->tags->column('name', 'hash')])
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


	//========产品========
	function prod_vtids():array
	{
		return [
			'' => '实体产品',
			'prod_vtid_vip100' => '会员卡100元'
		];
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


		$conds[0] = sprintf('%sORDER BY ctime DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
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

		}, $this->prod_vtids());
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
		if ($this->form_prod()->fetch($prod) && $this->webapp->mysql->prods('WHERE hash=?s LIMIT 1', $hash)->update($prod))
		{
			$this->webapp->response_location('?control/prods');
		}
		else
		{
			$this->main->append('h4', '产品更新失败！');
		}
	}



	//========视频========
	function get_videos(string $search = NULL, int $page = 1)
	{
		$conds = [[]];
		if (is_string($search))
		{
			$search = urldecode($search);
			if (trim($search, webapp::key) === '' && in_array($len = strlen($search), [10, 12], TRUE))
			{
				$conds[0][] = $len === 10 ? 'userid=?s' : 'hash=?s';
				$conds[] = $search;
			}
			else
			{
				$conds[0][] = 'name LIKE ?s';
				$conds[] = "%{$search}%";
			}
		}

		
		$conds[0] = sprintf('%sORDER BY mtime DESC,ctime DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->videos(...$conds)->paging($page, 10), function($table, $value)
		{
			$ym = date('ym', $value['mtime']);

			$table->row()['style'] = 'background-color:var(--webapp-hint)';
			$table->cell('封面');
			$table->cell('信息');
			$table->cell('操作');

			$table->row();
			$table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144'])->append('div', [
				'style' => 'height:100%;border:black 1px solid;box-shadow: 0 0 .4rem black;',
				'data-cover' => "/{$ym}/{$value['hash']}/cover"
			]);




			$table->row();
			$table->cell(sprintf('HASH：%s， 创建时间：%s， 修改时间：%s',
				$value['hash'],
				date('Y-m-d\\TH:i:s', $value['mtime']),
				date('Y-m-d\\TH:i:s', $value['ctime'])));
			$table->cell()->append('button', ['扩展按钮']);

			$table->row();
			$table->cell(sprintf('上传用户：%s， 大小：%s',
				$value['userid'],
				date('Y-m-d\\TH:i:s', $value['ctime'])));
			$table->cell()->append('button', ['扩展按钮']);

			$table->row();
			$table->cell('asd');
			$table->cell()->append('button', ['扩展按钮']);


			$table->row();
			$table->cell()->append('a', [htmlentities($value['name']), 'href' => "#"]);
			$table->cell()->append('button', ['扩展按钮']);




		});
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('封面', '信息', '操作');
		$table->header('视频 %d 项', $table->count());
		unset($table->xml->tbody->tr[0]);

		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'width:24rem',
			'placeholder' => '请输入视频HASH、用户ID、键字按【Enter】进行搜索。',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
		//$table->bar->append('button', ['添加专题', 'onclick' => 'location.href="?control/subject"']);
	}

	//========用户========
	function form_user(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset();
		$form->button('提交', 'submit');

		$form->xml['data-bind'] = 'submit';
		return $form;
	}


	function get_users(int $page = 1)
	{
		$conds = [[]];


		$conds[0] = sprintf('%sORDER BY time DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->users(...$conds)->paging($page), function($table, $value)
		{
			$table->row();

			
			$table->cell(date('Y-m-d', $value['time']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['lasttime']));
			$table->cell($this->webapp->hexip($value['lastip']));
			$table->cell()->append('a', [$value['id'], 'href' => '#']);
			$table->cell($value['cid']);


			$table->cell($value['device']);
			$table->cell($value['tid']);
			$table->cell($value['did']);


			$table->cell($value['nickname']);
			$table->cell(number_format($value['balance']));
			$table->cell(date('Y-m-d', $value['expire']));
			$table->cell(number_format($value['coin']));
			//$table->cell($value['nickname']);


			




			// $table->cell($value['sort']);
			// $table->cell()->append('a', [$value['name'], 'href' => "?control/tag,hash:{$value['hash']}"]);
			



		});
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('注册日期', '最后登录日期', '最后登录IP', 'ID', '渠道ID', '设备类型', '绑定手机', '设备ID', '昵称', '余额', '会员到期', '金币');
		$table->header('用户 %d 项', $table->count());
	}

	//========广告========
	function ad_seats():array
	{
		return [
			0 => '开屏广告',
			1 => '首页轮播',
			255 => '待定分类'
		];
	}
	function form_ad(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('广告图片');

		$form->field('ad', 'file', ['accept' => 'image/*']);

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


		$conds[0] = sprintf('%sORDER BY seat ASC,weight DESC', $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '');
		$table = $this->main->table($this->webapp->mysql->ads(...$conds)->paging($page), function($table, $value, $seat)
		{
			$table->row()['style'] = 'background-color:var(--webapp-hint)';
			$table->cell('封面');
			$table->cell('字段');
			$table->cell('信息');
			$table->cell('操作');

			$table->row();
			$table->cell(['rowspan' => 5, 'width' => '256', 'height' => '144'])->append('div', [
				'style' => 'height:100%;border:black 1px solid;box-shadow: 0 0 .4rem black;',
				'data-cover' => "/news/{$value['hash']}"
			]);




			$table->row();
			$table->cell('展示位置');
			$table->cell([$seat[$value['seat']], 'style' => 'min-width:20rem']);
			$table->cell()->append('button', ['修改信息', 'onclick' => "location.href='?control/ad-update,hash:{$value['hash']}'"]);

			$table->row();
			$table->cell('权重');
			$table->cell($value['weight']);
			$table->cell()->append('button', ['扩展按钮']);

			$table->row();
			$table->cell('展示');
			$table->cell($value['display']);
			$table->cell()->append('button', ['扩展按钮']);


			$table->row();
			$table->cell('行为URL');
			$table->cell(['colspan' => 2])->append('a', [$value['acturl'], 'href' => $value['acturl']]);


		}, $this->ad_seats());
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('封面', '字段', '信息', '操作');
		$table->header('广告 %d 项', $table->count());
		unset($table->xml->tbody->tr[0]);

		$table->bar->append('button', ['添加广告', 'onclick' => 'location.href="?control/ad-insert"']);
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
				'ctime' => $this->webapp->time] + $ad)
			&& $uploadedfile->maskfile("{$this->webapp['rootdir_ad']}/{$hash}")) {
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
			$form->xml->fieldset->append('div', [
				'style' => 'width:32rem;height:18rem;border:black 1px solid',
				'data-cover' => "/news/{$ad['hash']}"
			]);
			$form->echo($ad);
		}
	}
	function post_ad_update(string $hash)
	{
		if ($this->form_ad()->fetch($ad) && $this->webapp->mysql->ads('WHERE hash=?s LIMIT 1', $hash)->update($ad))
		{
			$this->webapp->response_location('?control/ads');
		}
		else
		{
			$this->main->append('h4', '广告更新失败！');
		}
	}
}