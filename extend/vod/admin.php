<?php
class webapp_router_admin extends webapp_echo_admin
{
	public array $nav = [
		['首页', '?admin'],
		['广告', '?admin/ads'],
		['视频', '?admin/videos'],
		['用户', '?admin/users'],
	], $submenu = [];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($this->auth)
		{
			$this->stylesheet('/webapp/ext/vod/admin.css');
		}
	}


	function get_home()
	{
		$this->main->append('h2', 'Statistics are under development');
	}

	const ad_type = [
		0 => '开屏（全屏幕）',
		1 => '首次弹窗（半屏幕）',
		2 => '横幅',
		3 => '导航图标 1:1',
		4 => '轮播 21:9',

		// 3 => '播放视频 16:9',
		// 4 => '滑动视频（全屏幕）',
		// 3 => '游戏轮播',
		// 4 => '社区轮播',
		// 5 => '个人中心',
		// 6 => '弹窗广告',
	];
	function form_ad(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset->append('img', ['style' => 'width:30rem;height:30rem;object-fit:contain;border:1px solid black']);

		$form->fieldset('广告图片 / 展示位置');
		$form->field('ad', 'file', ['accept' => 'image/*', 'onchange' => '$.previewimage(this,document.querySelector("form>fieldset>img"))']);
		$form->field('seat', 'select', ['options' => static::ad_type, 'required' => NULL]);

		$form->fieldset('行为URL');
		$form->field('acturl', 'text', [
			'placeholder' => 'javascript 或者 url',
			'value' => 'javascript:;',
			'maxlength' => 255,
			'style' => 'width:40rem',
			'required' => NULL
		]);

		$form->fieldset('过期时间 / 权重（越大越几率越大） / 名称');
		$form->field('expire', 'date', ['value' => date('Y-m-t')], fn($v, $i) => $i ? strtotime($v) : date('Y-m-d', $v));
		$form->field('weight', 'number', ['min' => 0, 'max' => 255, 'value' => 1, 'required' => NULL]);
		$form->field('name', 'text');

		$form->fieldset();
		$form->button('提交', 'submit');

		return $form;
	}
	function post_ad(string $hash = NULL)
	{
		$this->json();
		if ($this->form_ad()->fetch($data))
		{
			if ($hash)
			{
				$this->webapp->nfs(0)->update_uploadedfile($hash, ['name' => $data['name'], 'extdata' => $data], 'ad', TRUE);
			}
			else
			{
				$this->webapp->nfs(0)->create_uploadedfile('ad', ['name' => $data['name'], 'extdata' => $data], TRUE);
			}
		}
	}
	function get_ad(string $hash = NULL)
	{
		$form = $this->form_ad($this->main);
		if ($hash && $this->webapp->nfs(0)->fetch($hash, $data, $extdata))
		{
			$form->xml->fieldset->img['src'] = $this->webapp->src($data, '#!');
			//$this->webapp->readorigin . $this->webapp->nfs(0)->filename($hash) . "?{$data['t1']}#!";
			$form->echo($extdata);
		}
	}
	function get_ads()
	{
		$ads = $this->webapp->nfs(0);
		
		$table = $this->main->table($ads, function($table, $value)
		{
			$table->row()['style'] = 'background-color:var(--webapp-hint)';
			$table->cell()->append('a', ['删除下面广告', 'href' => "?admin/ad,hash:{$value['hash']}", 'data-method' => 'delete', 'data-bind' => 'click']);
			$table->cell(['colspan' => 6])->append('a', ['修改下面信息', 'href' => "?admin/ad,hash:{$value['hash']}"]);

			$table->row();
			$table->cell(['rowspan' => 5])
				->append('img', ['loading' => 'lazy', 'src' => $this->webapp->src($value, '#!')]);

			$table->row();
			$table->cell('HASH');
			$table->cell($value['hash']);
			$table->cell('创建时间');
			$table->cell(date('Y-m-d\\TH:i:s', $value['t0']));
			$table->cell('修改时间');
			$table->cell(date('Y-m-d\\TH:i:s', $value['t1']));

			$extdata = json_decode($value['extdata'], TRUE);
			$table->row();
			$table->cell('位置');
			$table->cell(static::ad_type[$extdata['seat']] ?? NULL);
			$table->cell('展示权重');
			$table->cell($extdata['weight']);
			$table->cell('过期时间');
			$table->cell([date('Y-m-d\\TH:i:s', $extdata['expire']),
				'style' => $extdata['expire'] > $this->webapp->time ? 'color:green' : 'color:red']);

			$table->row();
			$table->cell('名称');
			$table->cell($value['name']);
			$table->cell('展示次数');
			$table->cell(number_format($value['views']));
			$table->cell('点击次数');
			$table->cell(number_format($value['likes']));

			$table->row();
			$table->cell('URL');
			$table->cell(['colspan' => 5])->append('a', [$extdata['acturl'], 'href' => $extdata['acturl']]);
		});
		$table->tbody['class'] = 'ads';
		$table->header('广告');
		//$table->fieldset('asdasd');


		$table->bar->append('button', ['创建', 'onclick' => 'location.assign("?admin/ad")']);
		$table->bar->select(['' => '全部类型'] + static::ad_type)->selected(1)['onchange'] = '$.at({type:this.value})';
		//$table->bar->select(static::ad_type)->selected(1)['onchange'] = '$.at({type:this.value})';

	}
	function get_videos(){}
	function get_users()
	{
		$this->main->append('h2', 'User system are under development');
	}
}