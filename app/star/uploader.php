<?php
class webapp_router_uploader extends webapp_echo_masker
{
	private readonly array $users;
	private readonly user $user;
	//private readonly webapp_echo_html|webapp_echo_json $echo;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Uploader');
		if ($this->initiated || isset($this->users, $this->user) === FALSE)
		{
			return;
		}
		$this->link_resources($this->webapp['app_resorigins']);
		$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/app/star/base.css']);

		$this->script(['src' => '/webapp/res/js/uploader.js', 'data-key' => bin2hex(random_bytes(8))]);
		$this->script(['src' => '/webapp/app/star/base.js']);

		$this->script(['src' => '/webapp/app/star/uploader.js']);
		$nav = $this->nav([
			['用户信息', '?uploader/home'],
			['视频列表', '?uploader/videos'],
			['图片列表', '?uploader/images'],
			['导出异常影片', '?uploader/exceptions'],
			//['评论', '?uploader/comments'],
			['记录', [
				['提现', '?uploader/record-exchanges']
			]],
			['注销登录', 'javascript:masker.authorization(null).then(()=>location.reload());', 'style' => 'color:maroon']
		]);
		if ($this->users)
		{
			$nav->ul->insert('li', 'first')->setattr(['style' => 'margin-left:1rem'])->select($this->users)->selected($this->user->id)
				->setattr(['onchange' => 'location.reload(document.cookie=`userid=${this.value}`)']);
		}
	}
	function authorization($uid, $pwd):array
	{
		if ($this->webapp->mysql->uploaders('WHERE uid=?s AND pwd=?s LIMIT 1', $uid, $pwd)->fetch())
		{

			if ($this->users = $this->webapp->mysql->users('WHERE uid=?i', $uid)->column('nickname', 'id'))
			{
				$this->user = user::from_id($this->webapp,
					isset($this->users[$userid = $this->webapp->request_cookie('userid')]) ? $userid : array_keys($this->users)[0]);
			}
			return [$uid, $pwd];
		}
		return [];
	}
	function get_home()
	{
		if ($this->user->id)
		{
			$form = $this->main->form();
			$signature = $this->webapp->encrypt((string)$this->user);
			$form->fieldset['style'] = join(';', [
				'width:18rem',
				'height:18rem',
				'border:.1rem solid black',
				'display:block',
				"background:url({$this->webapp->request_entry}?qrcode/{$signature}) center center / 90% no-repeat white"
			]);

			$form->fieldset();
			$form->button('下载该账号凭证（从手机上传登录）', 'button', [
				'onclick' => 'alert("上线开放")'
			]);

			$form->fieldset()->append('label', [
				'style' => sprintf('width:18rem;height:18rem;background-image:url(?%s)', $this->user->fid()),
				'class' => 'uploaderface',
			])->append('input', [
				'type' => 'file',
				'accept' => 'image/*',
				'data-key' => bin2hex($this->webapp->random(8)),
				'data-uploadurl' => "?uploader/change-face",
				'onchange' => 'uploader.upload_image(this,this.parentNode)'
			]);

			$form->fieldset('用户昵称：');
			$form->field('nickname', 'text');
			$form->button('修改', 'button', [
				'data-action' => '?uploader/change_nickname',
				'onclick' => 'uploader.change_nickname(this)'
			]);

			$form->fieldset('提现余额：');
			$form->field('balance', 'number', ['disabled' => NULL]);
			$form->button('提现', 'button', [
				'onclick' => 'location.href = "?uploader/exchange"'
			]);
			$form->echo($this->user->getArrayCopy());
		}
	}
	function patch_change_face()
	{
		$hash = $this->webapp->time33hash($this->webapp->hashtime33($this->user->id));
		$this->json(['result' => is_string($key = $this->webapp->request_header('Mask-Key'))
			&& file_put_contents("{$this->webapp['face_savedir']}/{$hash}",
				hex2bin($key) . $this->webapp->request_content('binary')) !== FALSE
			&& $this->user->change_fid(0)]);
	}
	function patch_change_nickname()
	{
		$this->json(['result' => $this->user->change_nickname($this->webapp->request_content())]);
	}
	function form_exchange(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('USDT / TRC');
		$form->field('trc', 'text', [
			'pattern' => '^T[0-9a-zA-Z]{33}$',
			'placeholder' => '钱包地址',
			'style' => 'width:30rem',
			'required' => NULL]);
		$form->fieldset('输入金额');
		$form->field('fee', 'number', [
			'value' => $this->user['balance'],
			'max' => $this->user['balance'],
			'min' => 100,
			'placeholder' => '最低提现金额不得低于 100 元',
			'style' => 'width:20rem',
			'required' => NULL]);
		$form->button('提交提现', 'submit', ['style' => 'width:10rem']);
		$form->xml['onsubmit'] = 'return uploader.form_value(this)';
		return $form;
	}
	function post_exchange()
	{
		$this->json($this->form_exchange()->fetch($transfer)
			&& $this->user->exchange($transfer)
				? ['goto' => '?uploader/record-exchanges']
				: ['dialog' => '提现失败！']);
	}
	function get_exchange()
	{
		$form = $this->form_exchange($this->main);



	}
	function get_record_exchanges(int $page = 1)
	{
		$exchange = $this->webapp->mysql->records('WHERE userid=?s AND type="exchange" ORDER BY mtime DESC', $this->user->id)->paging($page);
		$table = $this->main->table($exchange, function($table, $value)
		{
			$table->row();
			$table->cell(date('Y-m-d\\TH:i:s', $value['mtime']));
			$table->cell(number_format($value['fee']));
			$table->cell(json_decode($value['ext'], TRUE)['trc']);
			$table->cell(base::record_results[$value['result']]);
		});
		$table->fieldset('创建时间', '提现', 'TRC', '状态');
		$table->header('提现记录');
		$table->paging($this->webapp->at(['page' => '']));
	}

	function get_videos(string $search = NULL, int $page = 1)
	{
		$this->script(['src' => '/webapp/res/js/hls.min.js']);
		$this->script(['src' => '/webapp/res/js/video.js']);
		if ($this->user->id === NULL) return 401;
		$conds = [[]];
		if (isset($this->users[$userid = $this->webapp->query['userid'] ?? '']))
		{
			$conds[0][] = 'userid=?s';
			$conds[] = $userid;
		}
		else
		{
			$conds[0][] = 'userid IN(?S)';
			$conds[] = array_keys($this->users);
		}
		if (is_string($search))
		{
			$search = urldecode($search);
			if (in_array(strlen($search), [4, 12], TRUE) && trim($search, webapp::key) === '')
			{
				$conds[0][] = strlen($search) === 4 ? 'FIND_IN_SET(?s,tags)' : 'hash=?s';
				$conds[] = $search;
			}
			else
			{
				$conds[0][] = 'name LIKE ?s';
				$conds[] = "%{$search}%";
			}
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
		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY ' . match ($sort = $this->webapp->query['sort'] ?? '')
		{
			'view-desc' => '`view` DESC',
			'like-desc' => '`like` DESC',
			'sales-desc' => '`sales` DESC',
			default => '`mtime` DESC'
		} . ',hash ASC';

		$tags = $this->webapp->mysql->tags->column('name', 'hash');
		$table = $this->main->table($this->webapp->mysql->videos(...$conds)->paging($page, 10), function($table, $value, $tags, $goto)
		{
			$ym = date('ym', $value['mtime']);

			$table->row()['class'] = 'info';
			$usernode = $table->cell();
			$usernode->append('span', '用户：');
			$usernode->select($this->users)->setattr([
				'style' => 'padding:2px',
				'data-action' => "?uploader/change-video-user,hash:{$value['hash']}",
				'onchange' => 'uploader.change_video_user(this)'
			])->selected($value['userid']);
			$table->cell(['colspan' => 8])->append('a', ['信息（点击修改下面信息）', 'href' => "?uploader/video,hash:{$value['hash']},goto:{$goto}"]);

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
			if ($value['sync'] !== 'exception')
			{
				$syncnode->append('span', ' | ');
				$syncnode->append('a', ['设为异常',
					'href' => 'javascript:;',
					'style' => 'color:maroon',
					'data-action' => "?uploader/video-exception,hash:{$value['hash']}",
					'onclick' => 'confirm("设为异常后不可撤销！")&&uploader.video_patch(this.dataset.action)'
				]);
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
					$tagnode->append('a', [$tags[$tag], 'href' => "?uploader/videos,search:{$tag}"]);
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

		}, $tags, $this->webapp->url64_encode($this->webapp->at([],'?uploader/videos')));
		$table->paging($this->webapp->at(['page' => '']));
		$table->fieldset('封面（预览视频）', '信息');
		$table->header('视频 %d 项', $table->count());
		unset($table->xml->tbody->tr[0]);
		$table->bar->append('button', ['上传视频', 'onclick' => 'location.href = "?uploader/uploading"']);
		$table->bar->select(['' => '全部用户'] + $this->users)
			->setattr(['onchange' => 'g({userid:this.value||null})', 'style' => 'margin-left:.6rem;padding:.1rem'])
			->selected($userid);
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'style' => 'margin-left:.6rem;padding:2px;width:26rem',
			'placeholder' => '请输入视频HASH、标签HASH、关键字按【Enter】进行搜索。',
			'onkeydown' => 'event.keyCode==13&&g({search:this.value?urlencode(this.value):null,page:null})'
		]);
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
		$table->bar['style'] = 'white-space:nowrap';
	}
	function get_video(string $hash, string $goto = NULL)
	{
		//$goto ??= $this->webapp->url64_encode('?uploader/videos');
		$form = $this->webapp->form_video($this->main, $hash);
		$form->xml['action'] .= ",goto:{$goto},up:1";

	}
	function get_uploading()
	{
		$table = $this->main->table();
		$table->fieldset('HASH', '大小（字节）', '类型', '名称', '进度');
		$table->header('上传视频（上传中请不要切换页面，直到上传完成。）');
		$table->bar->append('button', ['开始上传',
			'style' => 'margin-right:.6rem;color:maroon',
			'onclick' => 'uploader.uploading(document.querySelector("main>table.webapp:first-child"))'
		]);
		$table->bar->append('input', [
			'type' => 'file',
			'accept' => 'video/mp4',
			'data-uploadurl' => "?uploading/{$this->user}",
			'onchange' => 'uploader.uploadlist(this.files,document.querySelector("main>table.webapp:first-child>tbody"))',
			'multiple' => NULL
		]);
		$table->footer('建议单次上传保持在1~5个视频，单个视频不得大于2G');
	}
	function patch_video_exception(string $hash)
	{
		$this->json(['result' => $this->user->id
			&& $this->webapp->mysql->videos('WHERE hash=?s AND userid IN(?S) LIMIT 1',
				$hash, array_keys($this->users))->update('sync="exception"') === 1]);
	}
	function patch_change_video_user(string $hash)
	{
		$this->json(['result' => $this->user->id
			&& strlen($userid = $this->webapp->request_content()) === 10
			&& trim($userid, webapp::key) === ''
			&& $this->webapp->mysql->videos('WHERE hash=?s AND userid IN(?S) LIMIT 1', $hash, array_keys($this->users))
				->update('userid=?s', $userid) === 1]);
	}
	function get_exceptions()
	{
		$form = $this->main->form();
		$form->fieldset->text('复制保存下列异常影片，等待重新上传！（这个功能是暂时的，以后将被更好的操作取代）');
		$form->fieldset();
		$exception = $form->field('exception', 'textarea', [
			'style' => 'background:whitesmoke;font:1rem var(--webapp-font-monospace)',
			'onfocus' => 'this.select()',
			'rows' => 40, 'cols' => 100, 'readonly' => NULL
		]);
		foreach ($this->webapp->mysql->videos('WHERE sync="exception" AND userid IN(?S)', array_keys($this->users)) as $video)
		{
			$exception->text("{$video['hash']} - {$video['name']}\n");
		}
	}

	function get_images()
	{

	}

	function get_comments(int $page = 1)
	{
		$conds = [['userid=?s'], $this->user->id];
		if ($check = $this->webapp->query['check'] ?? '')
		{
			$conds[0][] = '`check`=?s';
			$conds[] = $check;
		}

		$conds[0] = ($conds[0] ? 'WHERE ' . join(' AND ', $conds[0]) . ' ' : '') . 'ORDER BY ctime DESC,hash ASC';
		$table = $this->main->table($this->webapp->mysql->comments(...$conds)->paging($page), function($table, $topic)
		{
			$table->row();
			$table->cell($topic['hash']);
			$table->cell(date('Y-m-d\\TH:i:s', $topic['mtime']));
			$table->cell(base::comment_type[$topic['type']]);
			$table->cell(number_format($topic['count']));
			$table->cell($topic['check']);
			$action = $table->cell();
			if ($topic['type'] === 'topic')
			{
				$action->append('a', ['发布帖子', 'href' => "?uploader/comment,type:post,phash:{$topic['hash']}"]);
			}
			else
			{
				if ($topic['type'] === 'video')
				{
					//$action->append('span', '视频评论');
				}
				else
				{
					$action->append('a', ['回复帖子', 'href' => $topic['type'] === 'post'
						? "?uploader/comment,type:reply,phash:{$topic['hash']}"
						: "?uploader/comment,type:reply,phash:{$topic['phash']}"]);
				}
			}

			$table->row();
			$contents = $table->cell(['colspan' => 6])->append('pre', ['style' => 'margin:0;line-height:1.4rem;']);
			if ($topic['type'] !== 'video' && $topic['title'])
			{
				$contents->text($topic['title']);
				$contents->text("\n");
			}
			if ($topic['content'])
			{
				$contents->text("\t{$topic['content']}");
			}


		});
		$table->fieldset('HASH', '发布时间', '类型', '回复数', '状态', '回复');
		$table->paging($this->webapp->at(['page' => '']));
		$table->header('话题、帖子、回复');
		
		$table->bar->append('button', ['发布话题', 'onclick' => 'location.href = "?uploader/comment"']);
		$table->bar->append('span', ['style' => 'margin-left:.6rem'])
			->select(['' => '全部状态', 'pending' => '等待审核', 'allow' => '通过审核', 'deny' => '未通过'])
			->setattr(['onchange' => 'g({check:this.value||null})', 'style' => 'padding:.1rem'])->selected($check);


	}
	function form_comment(string $type, webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		$form->fieldset('上级 / 标题');
		if ($type === 'topic')
		{
			$form->field('phash', 'select', ['options' => $this->webapp->select_topics(), 'required' => NULL]);
		}
		else
		{
			$form->field('phash', 'text', ['value' => $this->webapp->query['phash'] ?? NULL, 'readonly' => NULL]);
		}
		$form->field('title', 'text', ['placeholder' => '标题', 'style' => 'width:30rem', 'required' => NULL]);

		$form->fieldset('内容');
		$form->field('content', 'textarea', [
			'placeholder' => '内容',
			'rows' => 10,
			'cols' => 70,
			'required' => NULL]);
		$form->fieldset();



		$form->fieldset();
		$form->button('提交', 'submit');
		$form->xml['onsubmit'] = 'return uploader.form_value(this)';
		return $form;
	}
	function get_comment(string $type = 'topic')
	{
		$this->form_comment($type, $this->main);
	}
	function post_comment(string $type = 'topic')
	{
		$json = $this->json([]);
		if ($this->form_comment($type)->fetch($topic)
			&& $this->user->comment($topic['phash'], $topic['content'], $type, $topic['title'])) {
			$json['goto'] = '?uploader/comments';
			return;
		}
		$json['dialog'] = '发布失败！';
	}

}