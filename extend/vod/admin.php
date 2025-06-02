<?php
class webapp_extend_vod_admin extends webapp_echo_admin
{
	public array $nav = [
		['首页', '?admin'],
		['渠道', '?admin/channels'],
		['广告', '?admin/ads'],
		['分类', '?admin/classifies'],
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
	public array $initallow = ['post_notice', 'post_video_recommends'];
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


	function post_notice():int
	{
		$this->webapp->redis->set('notice', $this->webapp->request_content('text/plain'));
		return $this->echo_no_content();
	}
	function get_home()
	{
		$cond = $this->webapp->cond();
		$cond_d0 = $cond->query('d0', 'SUBSTR(dcid,1,8)>=?s', fn($v) => str_replace('-', '', $v), date('Y-m-d'));
		$cond_d1 = $cond->query('d1', 'SUBSTR(dcid,1,8)<=?s', fn($v) => str_replace('-', '', $v), $cond_d0);
		$cond_cid = $cond->query('cid', 'dcid LIKE ?s', fn($v) => "%{$v}");


		//$cond->merge('ORDER BY rate ASC, cid ASC');


		$channels = $this->webapp->mysql->channels->column('name', 'cid');
		$table = $this->main->table($cond($this->webapp->mysql->records), function($table, $value, $channels)
		{
			$table->row();
			$table->cell(substr($value['dcid'], 0, 8));
			$table->cell($channels[$cid = substr($value['dcid'], -4)] ?? $cid);
			$table->cell(number_format($value['init']));

			$table->cell(number_format($value['ic']));
			$table->cell(number_format($value['iu']));
			$table->cell(number_format($value['pv']));
			$table->cell(number_format($value['pc']));
			$table->cell(number_format($value['ac']));
			$table->cell(number_format($value['vw']));
			$table->cell(number_format($value['signup']));
			$table->cell(number_format($value['signin']));
			$table->cell(number_format($value['oi']));
			$table->cell(number_format($value['op']));
		}, $channels);
		$table->fieldset('日期', '渠道名称', '初始化', '进入统计', '进入唯一', '推广展示', '推广点击', '广告点击', '视频观看', '用户注册', '用户登入', '订单发起', '订单支付');
		$table->header('数据统计');
		$table->bar->append('input', ['type' => 'date', 'value' => $cond_d0, 'onchange' => '$.at({d0:this.value||null})']);
		$table->bar->append('span', ' - ');
		$table->bar->append('input', ['type' => 'date', 'value' => $cond_d1, 'onchange' => '$.at({d1:this.value||null})']);
		$table->bar->append('span', ' - ');
		$table->bar->select(['' => '全部渠道'] + $channels)->setattr(['onchange' => '$.at({cid:this.value||null})'])->selected($cond_cid);


		$table->bar->append('textarea', [is_string($notice = $this->webapp->redis->get('notice')) ? $notice : NULL, 'rows' => 1, 'cols' => 60, 'style' => 'vertical-align:bottom']);
		$table->bar->append('button', ['更新公告', 'onclick' => 'navigator.sendBeacon("?admin/notice", this.previousElementSibling.value)&&alert("提交成功")']);
	}
	#--------------------------------渠道--------------------------------
	function form_channel(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('ID / 名称');
		$form->field('cid', 'text', ['pattern' => '^[0-9A-Za-z]{4}$', 'placeholder' => '唯一ID', 'required' => NULL]);
		$form->field('name', 'text', ['maxlength' => 16, 'placeholder' => '名称', 'required' => NULL]);

		$form->fieldset('密码 / 比率');
		$form->field('pwd', 'text', ['maxlength' => 16, 'placeholder' => '密码', 'required' => NULL]);
		$form->field('rate', 'number', ['min' => 0, 'max' => 99.9999, 'step' => 0.0001, 'placeholder' => '展示比率']);

		$form->fieldset();
		$form->button('提交', 'submit');

		$form->xml['onsubmit'] = 'return !$(this).action()';
		return $form;
	}
	function post_channel(string $cid = NULL)
	{
		$this->json();
		$form = $this->form_channel();
		if ($cid)
		{
			unset($form['cid']);
		}
		if ($form->fetch($data))
		{
			if ($cid
				? $this->webapp->mysql->channels('WHERE cid=?s LIMIT 1', $cid)->update($data)
				: $this->webapp->mysql->channels->insert(['time' => date('Y-m-d H:i:s')] + $data)) {
				$this->echo->redirect('?admin/channels');
			}
			else
			{
				$this->echo->message($cid ? '修改失败！' : '创建失败！');
			}
		}
	}
	function get_channel(string $cid = NULL)
	{
		$form = $this->form_channel($this->main);
		if ($this->webapp->mysql->channels('WHERE cid=?s LIMIT 1', (string)$cid)->fetch($data))
		{
			$form['cid']->setattr('disabled');
			$form->echo($data);
		}
		else
		{
			$form->echo([
				'cid' => bin2hex(random_bytes(2)),
				'pwd' => bin2hex(random_bytes(8)),
				'rate' => 1
			]);
		}
	}
	function delete_channel(string $cid = NULL)
	{
		$this->json();
		$this->webapp->mysql->channels->delete('WHERE cid=?s LIMIT 1', $cid) === 1
			? $this->echo->refresh()
			: $this->echo->message("删除渠道 {$cid} 失败！");
	}
	function get_channels(int $page = 1)
	{
		$cond = $this->webapp->cond();
		$cond_search = urldecode($cond->query('search', 'name LIKE ?s', fn($v) => '%' . urldecode($v) . '%') ?? '');
		$cond->merge('ORDER BY rate ASC, cid ASC');
		$table = $this->main->table($cond($this->webapp->mysql->channels)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell()->append('a', ['删除',
				'href' => "?admin/channel,cid:{$value['cid']}",
				'onclick' => 'return !$(this).action()',
				'data-method' => 'delete',
				'data-confirm' => '删除渠道后不可恢复'
			]);
			$table->cell()->append('a', [$value['cid'], 'href' => "?admin/channel,cid:{$value['cid']}"]);
			$table->cell($value['name']);
			$table->cell($value['rate']);
			$table->cell($value['time']);
		});
		$table->fieldset('删除', 'CID(编辑)', '名称', '比率(ASC)', '创建时间');
		$table->header('找到 %d 个渠道', $table->count());
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('button', ['创建', 'onclick' => 'location.assign("?admin/channel")']);
		$table->bar->append('input', ['type' => 'search', 'value' => $cond_search, 'onkeypress' => 'event.keyCode===13&&$.at({search:this.value||null})']);
		$table->bar->append('span', ['删除 NULL 渠道会导致无法记录丢失渠道ID的数据！', 'style' => 'padding-left:1rem;color:brown']);
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

		$form->xml['onsubmit'] = 'return !$(this).action()';
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
				$this->echo->message($hash ? '修改失败！' : '创建失败，新广告必须上传图片！');
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
	function delete_ad(string $hash)
	{
		$this->json();
		$this->webapp->nfs_ads->delete_data($hash)
			? $this->echo->refresh()
			: $this->echo->message("删除广告 {$hash} 失败！");
	}
	function get_ads(int $page = 1)
	{
		$cond = $this->webapp->cond();
		$cond_seat = $cond->query('seat', 'extdata->"$.seat"=?s');
		$cond->merge('ORDER BY extdata->"$.weight" DESC, hash ASC');
		$table = $this->main->table($cond($this->webapp->nfs_ads)->paging($page, 8), function($table, $value)
		{
			$table->row()['class'] = 'title';
			$table->cell()->append('a', ['删除这个广告',
				'href' => "?admin/ad,hash:{$value['hash']}",
				'onclick' => 'return !$(this).action()',
				'data-method' => 'delete'
			]);
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
	const classify_display_styles = [
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
	const classify_fetch_methods = [
		'intersect' => '标签HASH交集',
		'union' => '标签HASH并集',
		'starts' => '标题开始关键词',
		'ends' => '标题结尾关键词',
		'contains' => '标题包含关键词'
	];
	function form_classify(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset->setattr(['注意：该分类是视频的唯一分类，一个视频不能同时属于2个分类！', 'style' => 'color:brown']);
	
		$form->fieldset('专题名称 / 等级 / 排序 / 展示样式');
		$form->field('name', 'text', ['placeholder' => '名称', 'required' => NULL]);
		


		$form->field('level', 'number', ['value' => 0, 'min' => 0, 'max' => 255, 'placeholder' => '等级', 'required' => NULL]);
		$form->field('sorting', 'number', ['value' => 0, 'min' => 0, 'max' => 255, 'placeholder' => '排序', 'required' => NULL]);
		$form->field('style', 'select', ['options' => static::classify_display_styles, 'required' => NULL]);


		$form->fieldset('数据来源');
		$form->field('method', 'select', ['options' => static::classify_fetch_methods]);
		$form->field('values', 'text', ['placeholder' => '多个值请用 "," 间隔', 'style' => 'width:21rem']);

		$form->fieldset();
		$form->button('提交', 'submit');

		$form->xml['onsubmit'] = 'return !$(this).action()';
		return $form;
	}
	function post_classify(string $hash = NULL)
	{
		$this->json();
		if ($this->form_classify()->fetch($data))
		{
			$data = ['name' => $data['name'], 'extdata' => $data];
			unset($data['extdata']['name']);
			if ($hash
				? $this->webapp->nfs_classifies->update($hash, $data)
				: $this->webapp->nfs_classifies->create($data)) {
				$this->echo->redirect("?admin/classifies");
			}
			else
			{
				$this->echo->message($hash ? '修改失败！' : '创建失败！');
			}
		}
	}
	function get_classify(string $hash = NULL)
	{
		$form = $this->form_classify($this->main);
		if ($hash && $this->webapp->nfs_classifies->fetch($hash, $data))
		{
			$form->echo($data);
		}
	}
	function delete_classify(string $hash)
	{
		$this->json();
		$this->webapp->nfs_classifies->delete($hash)
			? $this->echo->refresh()
			: $this->echo->message("删除分类 {$hash} 失败！");
	}
	function get_classifies(int $page = 1)
	{
		$cond = $this->webapp->cond();

		//print_r($cond);
		$cond->merge('ORDER BY extdata->"$.sorting" DESC, hash ASC');
		$table = $this->main->table($cond($this->webapp->nfs_classifies)->paging($page), function($table, $value)
		{
			$table->row();
			$table->cell()->append('a', ['删除',
				'href' => "?admin/classify,hash:{$value['hash']}",
				'onclick' => 'return !$(this).action()',
				'data-method' => 'delete'
			]);
			$table->cell()->append('a', [$value['hash'], 'href' => "?admin/classify,hash:{$value['hash']}"]);
			$table->cell(date('Y-m-d\\TH:i:s', $value['t0']));
			$table->cell(date('Y-m-d\\TH:i:s', $value['t1']));
			$table->cell($value['name']);
			$table->cell($value['level']);
			$table->cell($value['sorting']);
			$table->cell(static::classify_display_styles[$value['style']]);
			$table->cell(sprintf("%s({$value['values']})", static::classify_fetch_methods[$value['method']]));
		});
		
		$table->header('找到 %d 个分类', $table->count());
		$table->fieldset('删除', 'HASH(编辑)', '创建时间', '修改时间', '名称', '等级', '排序', '样式', '数据来源');
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('button', ['创建', 'onclick' => 'location.assign("?admin/classify")']);
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

		$form->xml['onsubmit'] = 'return !$(this).action()';
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
				$this->echo->message('更新视频失败！');
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
	function delete_video(string $hash)
	{
		$this->json();
		$this->webapp->video_delete($hash)
			? $this->echo->refresh()
			: $this->echo->message("删除视频 {$hash} 失败！");
	}

	function post_video_recommends():int
	{
		$this->webapp->redis->set('recommendvideos', $this->webapp->request_content('text/plain'));
		return $this->echo_no_content();
	}
	function get_videos(int $page = 1)
	{
		$this->script(['src' => '/webapp/static/js/hls.min.js']);
		$this->script(['src' => '/webapp/static/js/video.js']);

		$cond = $this->webapp->cond();


		if (isset($this->webapp->query['search']))
		{
			$cond_search = $this->webapp->is_long_hash($hash = $this->webapp->query['search'])
				? $cond->query('search', 'hash=?s')
				: urldecode($cond->query('search', 'name LIKE ?s', fn($v) => '%' . urldecode($v) . '%') ?? '');
		}
		else
		{
			$cond_search = NULL;
		}

		$cond->merge('ORDER BY t1 DESC, hash ASC');

		$table = $this->main->table($cond($this->webapp->nfs_videos)->paging($page, 10), function($table, $value)
		{
			$table->row()['class'] = 'title';


			$table->cell()->append('a', ['删除这个视频',
				'href' => "?admin/video,hash:{$value['hash']}",
				'onclick' => 'return !$(this).action()',
				'data-method' => 'delete'
			]);
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
		$table->bar->append('input', ['type' => 'search', 'value' => $cond_search, 'onkeypress' => 'event.keyCode===13&&$.at({search:this.value||null,page:null})']);

		$table->bar->append('textarea', [is_string($video_recommends = $this->webapp->redis->get('recommendvideos')) ? $video_recommends : NULL, 'rows' => 1, 'cols' => 14, 'style' => 'vertical-align:bottom']);
		$table->bar->append('button', ['更新推荐', 'onclick' => 'navigator.sendBeacon("?admin/video-recommends", this.previousElementSibling.value)&&alert("提交成功")']);

	}

	#--------------------------------用户--------------------------------
	function get_users()
	{
		$this->main->append('h2', '正在开发..');
	}


}