<?php
class webapp_router_admin extends webapp_echo_admin
{
	public array $nav = [
		['首页', '?admin'],
		['渠道', '?admin/channels'],
		['广告', '?admin/ads'],
		['分类', '?admin/types'],
		['视频', '?admin/videos'],
		// ['标签', '?admin/tags'],
		// ['演员', '?admin/actors'],
		// ['专题', '?admin/subjects'],
		// ['视频扩展', [
		// 	['标签', '?admin/tags'],
		// 	['演员', '?admin/actors'],
		// 	['专题', '?admin/subjects'],
		// ]],
		// ['用户', '?admin/users']
	], $submenu = [];
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($this->init)
		{
			$this->webapp->origin($this);
		}
		if ($this->auth)
		{
			$this->stylesheet('/webapp/extend/vod/admin.css');
		}
	}


	function get_home()
	{
		$this->main->append('h2', '正在开发..');
	}
	#--------------------------------渠道--------------------------------
	function get_channels()
	{
		$this->main->append('h2', '正在开发..');
	}
	#--------------------------------广告--------------------------------
	const ad_seat = [
		0 => '开屏（全屏幕）',
		1 => '首次弹窗（半屏幕）',
		2 => '横幅 840x108 = 7.8:1',
		3 => '导航图标 360x360 = 1:1',
		4 => '轮播 840x360 = 21:9',
		5 => '播放视频 840x473 = 16:9',

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
		$form->field('seat', 'select', ['options' => static::ad_seat, 'required' => NULL]);

		$form->fieldset('跳转网址');
		$form->field('jumpurl', 'text', [
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

		//$form->xml['onsubmit'] = 'return $(this).action()';
		return $form;
	}
	function post_ad(string $hash = NULL)
	{
		$this->json();
		if ($this->form_ad()->fetch($data))
		{
			$data = ['name' => $data['name'], 'extdata' => $data];
			unset($data['extdata']['name']);
			if ($hash
				? $this->webapp->nfs_ads->update_uploadedfile($hash, $data, 'ad', TRUE)
				: $this->webapp->nfs_ads->create_uploadedfile('ad', $data, TRUE)) {
				$this->echo->redirect("?admin/ads,seat:{$data['extdata']['seat']}");
			}
			else
			{
				$this->echo->message($hash ? '修改失败！' : '创建失败！');
			}
		}
	}
	function get_ad(string $hash = NULL)
	{
		$form = $this->form_ad($this->main);
		if ($hash && $this->webapp->nfs_ads->fetch($hash, $data))
		{
			$form->xml->fieldset->img['src'] = $this->webapp->src($data);
			$form->echo($data);
		}
	}
	function get_ads(int $page = 1)
	{
		$cond = $this->webapp->cond();
		$cond_seat = $cond->query('seat', 'extdata->"$.seat"=?s');
		$cond->append('ORDER BY extdata->"$.weight" DESC, hash ASC');


		$table = $this->main->table($cond($this->webapp->nfs_ads)->paging($page, 8), function($table, $value)
		{
			$table->row()['class'] = 'title';
			$table->cell()->append('a', ['删除这个广告', 'href' => "?admin/ad,hash:{$value['hash']}", 'data-method' => 'delete']);
			$table->cell(['colspan' => 6])->append('a', [$value['jumpurl'], 'href' => $value['jumpurl'], 'target' => '_blank']);
			
			$table->row();
			$table->cell(['rowspan' => 5, 'class' => 'cover', 'style' => 'min-width:235px'])->figure($value['src']);

			$table->row();
			$table->cell('HASH');
			$table->cell()->append('a', [$value['hash'], 'href' => "?admin/ad,hash:{$value['hash']}"]);
			$table->cell('创建');
			$table->cell(date('Y-m-d\\TH:i:s', $value['t0']));
			$table->cell('修改');
			$table->cell(date('Y-m-d\\TH:i:s', $value['t1']));

			$table->row();
			$table->cell('位置');
			$table->cell(static::ad_seat[$value['seat']] ?? NULL);
			$table->cell('权重');
			$table->cell($value['weight']);
			$table->cell('到期');
			$table->cell([date('Y-m-d\\TH:i:s', $value['expire']),
				'style' => $value['expire'] > $this->webapp->time ? 'color:green' : 'color:red']);

			$table->row();
			$table->cell('展示');
			$table->cell(number_format($value['views']));
			$table->cell('点击');
			$table->cell(number_format($value['likes']));
			$table->cell('大小');
			$table->cell(number_format($value['size']));

			$table->row();
			$table->cell('名称');
			$table->cell([$value['name'], 'colspan' => 5]);
		});
		$table->header('找到 %d 个广告', $table->count());
		$table->paging($this->webapp->at(['page' => '']));


		$table->bar->append('button', ['创建', 'onclick' => 'location.assign("?admin/ad")']);
		$table->bar->select(['' => '全部类型'] + static::ad_seat)->selected($cond_seat)['onchange'] = '$.at({seat:this.value||null})';
		//$table->bar->select(static::ad_type)->selected(1)['onchange'] = '$.at({type:this.value})';

	}
	#--------------------------------分类--------------------------------
	const type_display_styles = [
		1 => '1 横版（大）',
		2 => '2 横版（小）',
		3 => '3 竖版',
		4 => '4 竖版（单排滑动）',
		5 => '5 横版（单排滑动）',
		6 => '6 横版（先大后小）',
		7 => '7 横版（右侧封面）',
		8 => '8 横版（右侧封面单排滑动）',
		9 => '9 横版（右侧封面先大后小）'
	];
	const type_fetch_methods = [
		'intersect' => '标签HASH交集',
		'union' => '标签HASH并集',
		'starts' => '标题开始关键词',
		'ends' => '标题结尾关键词',
		'contains' => '标题包含关键词'
	];
	function form_type(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset->setattr(['注意：该分类是视频的唯一分类，一个视频不能同时属于2个分类！', 'style' => 'color:brown']);
	
		$form->fieldset('专题名称 / 等级 / 排序 / 展示样式');
		$form->field('name', 'text', ['placeholder' => '名称', 'required' => NULL]);
		


		$form->field('level', 'number', ['value' => 0, 'min' => 0, 'max' => 255, 'placeholder' => '等级', 'required' => NULL]);
		$form->field('sorting', 'number', ['value' => 0, 'min' => 0, 'max' => 255, 'placeholder' => '排序', 'required' => NULL]);
		$form->field('style', 'select', ['options' => static::type_display_styles, 'required' => NULL]);


		$form->fieldset('数据来源');
		$form->field('method', 'select', ['options' => static::type_fetch_methods]);
		$form->field('values', 'text', ['placeholder' => '多个值请用 "," 间隔', 'style' => 'width:21rem']);

		$form->fieldset();
		$form->button('提交', 'submit');
		return $form;
	}
	function post_type(string $hash = NULL)
	{
		$this->json();
		if ($this->form_type()->fetch($data))
		{
			$name = $data['name'];
			unset($data['name']);
			if ($hash
				? $this->webapp->nfs_classify->update($hash, ['name' => $name, 'extdata' => $data])
				: $this->webapp->nfs_classify->create_tree($name, $data)) {
				$this->echo->redirect("?admin/types");
			}
			else
			{
				$this->echo->message($hash ? '修改失败！' : '创建失败！');
			}
		}
	}
	function get_type(string $hash = NULL)
	{
		$form = $this->form_type($this->main);
		if ($hash && $this->webapp->nfs_classify->fetch($hash, $data))
		{
			$form->echo($data);
		}
	}
	function get_types(int $page = 1)
	{
		$cond = $this->webapp->cond('`type`=0');

		//print_r($cond);
		$cond->append('ORDER BY extdata->"$.sorting" DESC, hash ASC');
		$table = $this->main->table($cond($this->webapp->nfs_classify)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell()->append('a', ['删除', 'href' => '#']);
			$table->cell()->append('a', [$value['hash'], 'href' => "?admin/type,hash:{$value['hash']}"]);
			$table->cell(date('Y-m-d\\TH:i:s', $value['t0']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['t1']));
			$table->cell($value['name']);
			$table->cell($value['level']);
			$table->cell($value['sorting']);
			$table->cell(static::type_display_styles[$value['style']]);
			$table->cell(sprintf("%s({$value['values']})", static::type_fetch_methods[$value['method']]));
		});
		
		$table->header('找到 %d 个分类', $table->count());
		$table->fieldset('删除', 'HASH(编辑)', '创建时间', '修改时间', '名称', '等级', '排序', '样式', '数据来源');
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('button', ['创建', 'onclick' => 'location.assign("?admin/type")']);
	}
	#--------------------------------视频--------------------------------
	function form_video(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset['class'] = 'cover';
		$form->fieldset['style'] = 'width:30rem';

		$form->fieldset->figure('about:blank');

		$form->fieldset('影片封面');
		$form->field('cover', 'file', ['accept' => 'image/*']);

		$form->fieldset('影片名称');
		$form->field('name', 'textarea', ['style' => 'width:60rem', 'rows' => 3, 'required' => NULL]);

		$form->fieldset('要求：会员:-1、免费:0、金币>0，预览时间段 / 海报封面');
		$form->field('require', 'number', [
			'value' => 0,
			'min' => -1,
			'style' => 'width:13rem',
			'placeholder' => '要求',
			'required' => NULL
		]);

		$form->field('poster', 'number', ['placeholder' => '海报封面']);
		// $form->fieldset('标签集 / 演员集');
		// $form->field('tags', 'text', ['placeholder' => '暂时不可用']);
		// $form->field('actors', 'text', ['placeholder' => '暂时不可用']);

		$form->fieldset();
		$form->button('更新视频', 'submit');
		return $form;
	}
	function post_video(string $hash)
	{
		$this->json();
		if ($this->form_video()->fetch($data))
		{
			if ($this->webapp->video_update($hash, $data, 'cover'))
			{
				$this->echo->redirect("?admin/videos");
			}
			else
			{
				$this->echo->message('更新失败！');
			}
		}
	}
	function get_video(string $hash)
	{
		$form = $this->form_video($this->main);
		if ($this->webapp->nfs_videos->fetch($hash, $data))
		{
			$form->xml->fieldset->figure->img['src'] = $data['poster'];

			$data['poster'] = preg_match('/(\d+)\.cover$/', $data['poster'], $poster) ? $poster[0] : 0;

			$form->echo($data);
		}
	}
	function get_videos(int $page = 1)
	{
		$this->script(['src' => '/webapp/static/js/hls.min.js']);
		$this->script(['src' => '/webapp/static/js/video.js']);

		$cond = $this->webapp->cond();

		$cond->append('ORDER BY t1 DESC, hash ASC');


		$table = $this->main->table($cond($this->webapp->nfs_videos)->paging($page, 10), function($table, $value)
		{
			$table->row()['class'] = 'title';
			$table->cell()->append('a', ['删除这个视频', 'href' => 'javascript:;']);
			$table->cell([htmlentities($value['name']), 'colspan' => 8]);

			$table->row();
			$table->cell(['rowspan' => 6, 'class' => 'cover', 'style' => 'min-width:312px'])->figure($value['poster']);

			$table->row();
			$table->cell('HASH');
			$table->cell()->append('a', [$value['hash'], 'href' => "?admin/video,hash:{$value['hash']}"]);
			$table->cell('创建');
			$table->cell(date('Y-m-d\TH:i:s', $value['t0']));
			$table->cell('更新');
			$table->cell(date('Y-m-d\TH:i:s', $value['t1']));
			$table->cell('时长');
			$table->cell($this->webapp->format_duration($value['size']));


			$table->row();
			$table->cell('要求');
			$table->cell(match (intval($value['require']))
			{
				-2 => '下架', -1 => '会员', 0 => '免费',
				default => "{$value['require']} 金币"
			});
			$table->cell('观看');
			$table->cell(number_format($value['views']));
			$table->cell('喜欢');
			$table->cell(number_format($value['likes']));
			$table->cell('分享');
			$table->cell(number_format($value['shares']));

			$table->row();
			$table->cell('标签');
			$table->cell(['colspan' => 7]);
	
			$table->row();
			$table->cell('演员');
			$table->cell(['colspan' => 7]);

			$table->row();
			$table->cell('专题');
			$table->cell(['colspan' => 7]);

		});
		
		$table->header('找到 %d 个视频', $table->count());
		$table->paging($this->webapp->at(['page' => '']));



	}

	#--------------------------------用户--------------------------------
	function get_users()
	{
		$this->main->append('h2', '正在开发..');
	}


	function get_a()
	{
		echo '1';
	}
}