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
			$this->script(['src' => '/webapp/app/star/control.js']);
			$this->nav([
				['标签管理', '?control/tags'],
				['专题管理', '?control/subjects'],
				['视频管理', '?control/videos'],
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
	function goto(string $url):void
	{
		$this->json['goto'] = "?control{$url}";
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
			1 => '首页标签'
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
		$conds = [''];


		$conds[0] .= ' ORDER BY level ASC,sort DESC';
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
		$table->header('标签管理');
		$table->bar->append('button', ['添加标签', 'onclick' => 'location.href="?control/tag"']);
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

		$form->fieldset('专题名称');
		$form->field('name', 'text', ['required' => NULL]);

		$form->fieldset();
		$form->button('提交', 'submit');

		$form->xml['data-bind'] = 'submit';
		return $form;
	}
	function get_subjects(int $page = 1)
	{
		$conds = [''];


		$conds[0] .= ' ORDER BY tagid ASC,sort DESC';
		$table = $this->main->table($this->webapp->mysql->subjects(...$conds)->paging($page), function($table, $value, $tag)
		{
			$table->row();

			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['ctime']));
			$table->cell($value['hash']);
			$table->cell($value['sort']);
			$table->cell($tag[$value['tagid']]);
			$table->cell()->append('a', [$value['name'], 'href' => "?control/subject,hash:{$value['hash']}"]);

		}, $this->subject_tags());
		$table->paging($this->webapp->at(['page' => '']));

		$table->fieldset('创建时间', '修改时间', 'HASH', '排序', '标签', '名称');
		$table->header('专题管理');
		$table->bar->append('button', ['添加专题', 'onclick' => 'location.href="?control/subject"']);
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

		
		$conds[0] = $conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) : '';
		$conds[0] .= 'ORDER BY mtime DESC,ctime DESC';
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
		$table->header('视频管理');
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
	function get_users(int $page = 1)
	{

	}

	//========广告========
	function get_ads(int $page = 1)
	{

	}
}