<?php
class webapp_extend_nfs_echo extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp, 'nfs', 'get_home');
		$this->stylesheet('/webapp/extend/nfs/echo.css');
		$this->script(['src' => '/webapp/extend/nfs/echo.js']);
		$this->title('NFS');
		if ($webapp instanceof webapp_extend_nfs)
		{
			$this->nav([
				['Home', '?'],
				['Explorer', "?{$this->routename}"],
				['Administrator', "?{$this->routename}/admin"]
			]);
			$webapp->mysql->table_exists($webapp::tablename) || $webapp::createtable($webapp->mysql);
		}
		else
		{
			$webapp->break(function()
			{
				$this->main->append('h2', 'WebApp Must extend NFS');
				return 500;
			});
		}
	}
	function authenticate(...$params):array
	{
		return $this->webapp->admin(...$params);
	}
	function get_admin()
	{
		$this->main->append('button', ['Sign out Administrator', 'onclick' => '$.delete_cookie_reload("nfs")', 'class' => 'primary']);
	}
	function get_home(int $sort = 0, int $page = 1, string $search = NULL, string $node = NULL)
	{
		$backtotop = "?{$this->routename},sort:{$sort}";
		$cond = $this->webapp->cond('`sort`=?i', $sort);
		if ($node = $cond->query('node', '`node`=?s'))
		{
			if ($this->webapp->nfs($sort, 0)->fetch($node, $data))
			{
				$this->title($data['name']);
				if ($data['node'])
				{
					$backtotop .= ",node:{$data['node']}";
				}
			}
		}
		else
		{
			
			$cond->append('`node` IS NULL');
		}

	
		$cond->merge('ORDER BY type ASC,hash ASC');
		$table = $this->main->table($cond($this->webapp->mysql->{$this->webapp::tablename})->paging($page, 20), function($table, $value, $type)
		{
			$table->row();
			$table->cell($value['hash']);
			$table->cell($type[$value['type']] ?? "mixed({$value['type']})");
			$table->cell(date('Y-m-d\TH:i:s', $value['t0']));
			$table->cell(date('Y-m-d\TH:i:s', $value['t1']));
			$table->cell($value['type'] ? $this->webapp->formatsize($value['size']) : '-');
			$table->cell($value['views']);
			$table->cell($value['likes']);
			$table->cell($value['shares']);

			



			$table->cell()->details_button_popup($value['name'], [
				...match ((int)$value['type'])
				{
					0 => [
						['Into node', "?{$this->routename},sort:{$value['sort']},node:{$value['hash']}"]
					],
					1 => [
						['Access object URL', strstr($this->webapp->src($value), '#', TRUE), 'target' => '_blank'],
						['Open with', [
							['Text reader', '#'],
							['Image viewer', '#'],
							['Video playback', '#']
						]],
						...$this->auth ? [
							['Update file', "?{$this->routename}/update,sort:{$value['sort']},type:{$value['type']},hash:{$value['hash']}",
								'data-prompt' => "Select the file to replace:file",
								'onclick' => 'return $.action(this)'
							],
							['Move to', "?{$this->routename}/moveto,sort:{$value['sort']},type:{$value['type']},hash:{$value['hash']}",
								'data-prompt' => "Node hash(empty is root node):text:{$value['node']}",
								'onclick' => 'return $.action(this)']
						] : []
					],
					default => [
						['Copy object URL', strstr($this->webapp->src($value), '#', TRUE), 
							'onclick' => 'return !$.copytoclipboard(this.href)']
					]
				},
				...$this->auth ? [
					['Rename', "?{$this->routename}/rename,sort:{$value['sort']},type:{$value['type']},hash:{$value['hash']}",
						'data-prompt' => "New name:text:{$value['name']}",
						'onclick' => 'return $.action(this)'],
					['Delete', "?{$this->routename}/delete,sort:{$value['sort']},type:{$value['type']}",
						'class' => 'danger',
						'data-confirm' => 'Delete cannot be undo',
						'data-body' => $value['hash'],
						'onclick' => 'return $.action(this)']
				] : []
			]);

		}, ['Node', 'File']);
		$table->fieldset('Hash', 'Type', 'Insert Time', 'Update Time', 'Size', 'Views', 'Likes', 'Shares', 'Name');
		$table->header('Explorer');
		$table->paging($this->webapp->at(['page' => '']));
		$table->bar->append('a', ['Back to top', 'href' => $backtotop, 'class' => 'default']);
		$table->bar->append('input', ['type' => 'number', 'min' => 0, 'max' => 255, 'value' => $sort, 'placeholder' => 'Sort', 'required' => NULL]);
		$table->bar->append('input', ['type' => 'search', 'placeholder' => 'Type keyword search', 'value' => $search]);
		if ($this->auth)
		{
			$table->bar->append('a', ['Upload file',
			'href' => "?{$this->routename}/uploadfile,sort:{$sort},node:{$node}",
			'onclick' => 'return $.action(this)',
			'data-prompt' => '#form_uploadfile'
			]);
			$table->bar->append('a', ['Create folder',
				'href' => "?{$this->routename}/createnode,sort:{$sort},node:{$node}",
				'onclick' => 'return $.action(this)',
				'data-prompt' => '#form_createnode'
			]);
		}



		// $table->bar->append('a', ['Dialog test', 'href' => "?{$this->routename}/asd",

		// 	// 'data-message' => '准备开始 Action',
		// 	// 'data-warning' => '这是一个警告！！',
		// 	// 'data-confirm' => '确定要开始?',
		// 	'data-prompt' => '请输入一个日期',
		// 	'onclick' => 'return $.action(this)'
		// ]);
	
		// $table->bar->append('a', ['Copy test', 'href' => "javascript:;", 'onclick' => '$.copytoclipboard(this.href)']);
	
		$this->form_uploadfile($this->template('form_uploadfile'));
		$this->form_createnode($this->template('form_createnode'));
	}
	function post_update(int $sort, int $type, string $hash)
	{
		$this->json();
		if ($this->webapp->nfs($sort, $type)->replace_inputedfile($hash))
		{
			$this->echo->refresh();
		}
		else
		{
			$this->echo->error('Update file failure');
		}
	}
	function post_moveto(int $sort, int $type, string $hash)
	{
		$this->json();
		if ($this->webapp->nfs($sort, $type)->moveto($hash, empty($node = $this->input()) ? NULL : $node))
		{
			$this->echo->refresh();
		}
		else
		{
			$this->echo->error('Move to node failure');
		}
	}
	function post_rename(int $sort, int $type, string $hash)
	{
		$this->json();
		if ($this->webapp->nfs($sort, $type)->rename($hash, $this->input()))
		{
			$this->echo->refresh();
		}
		else
		{
			$this->echo->error('Rename failure');
		}
	}
	function post_delete(int $sort, int $type)
	{
		$this->json();
		if ($this->webapp->nfs($sort, $type)->delete($this->input()))
		{
			$this->echo->refresh();
		}
		else
		{
			$this->echo->error("Delete failure\nIf type is node keep node must empty");
		}
	}
	function form_uploadfile(webapp_html $node = NULL)
	{
		$form = new webapp_form($node ?? $this->webapp);
		$form->progress();//->setattr(['value' => 0.47]);
		$form->fieldset('File / Submit');
		$form->field('file', 'file', ['onchange' => 'this.form.name.value=this.files.length?this.files[0].name:null', 'required' => NULL]);
		$form->button('Uploadfile', 'submit');
		$form->fieldset('Name');
		$form->field('name', 'text', ['placeholder' => 'File name', 'required' => NULL]);
		$form->fieldset('Extdata');
		$form->field('extdata', 'textarea', ['placeholder' => 'JSON String', 'rows' => 8]);
		$form->fieldset();
		$form->button('Close', 'button', ['value' => 'close']);
		return $form;
	}
	function post_uploadfile(int $sort, string $node)
	{
		$this->json();
		if ($this->form_uploadfile()->fetch($data))
		{
			if ($this->webapp->nfs($sort, 0)->fetch($node, $node))
			{
				$data['node'] = $node['hash'];
			}
			if (empty($data['extdata']))
			{
				unset($data['extdata']);
			}
			if ($hash = $this->webapp->nfs($sort, 1)->create_uploadedfile('file', $data))
			{
				$this->echo->message("File {$hash} creaded");
				$this->echo->refresh();
			}
			else
			{
				$this->echo->error('Upload file failure');
			}
		}
	}
	function form_createnode(webapp_html $node = NULL)
	{
		$form = new webapp_form($node ?? $this->webapp);
		$form->field('name', 'text', ['placeholder' => 'Folder name', 'required' => NULL]);
		$form->button('Create Folder', 'submit');
		$form->fieldset('Extdata');
		$form->field('extdata', 'textarea', ['placeholder' => 'JSON String', 'rows' => 8]);
		$form->fieldset();
		$form->button('Close', 'button', ['value' => 'close']);
		return $form;
	}
	function post_createnode(int $sort, string $node)
	{
		$this->json();
		$nfs = $this->webapp->nfs($sort, 0);
		if ($this->form_createnode()->fetch($data))
		{
			if ($nfs->fetch($node, $node))
			{
				$data['node'] = $node['hash'];
			}
			if (empty($data['extdata']))
			{
				unset($data['extdata']);
			}
			if ($nfs->create($data))
			{
				$this->echo->refresh();
			}
			else
			{
				$this->echo->error('Create folder failure');
			}
		}
	}
	function post_asd()
	{
		

		$this->json();
		$this->echo->continue('?nfs/aaa', 'post', ['a' => 1]);
		$this->echo->prompt('dddddd:number');
		//$this->echo->prompt($this->form_create_folder($this->main));
		// $this->echo->error('dwdawdawd');
		// //$this->echo->message(['title' => 'dwdwd', 'content' => $this->webapp->request_content(), 'accept' => '确定']);

		// $this->echo->prompt('adasdwdwd:date');

		//var_dump($this->webapp->request_content());
		//$this->echo->refresh();
	}








}