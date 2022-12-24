<?php
require __DIR__ . '/../webapp_stdio.php';
class webapp_router_query extends webapp_echo_json
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($webapp->mysql->connect_errno)
		{
			$webapp->break(fn() => 500);
		}
		else
		{
			// if ($referer = $webapp->request_referer())
			// {
			// 	$webapp->response_location($referer);
			// }
		}
	}
	function post_console()
	{
		$input = $this->webapp->request_content();
		if ($input['createdb'])
		{
			$this->webapp->mysql->createdb($input['createdb']);
		}
		//$this['post'] = $_POST;
	}
	function get_createtab()
	{
		$this->webapp->mysql->createtab('resources');
	}
	function post_insert(string $tab)
	{
		$tabname = $this->webapp->url64_decode($tab);
		
		$data = $this->webapp->form_field($tabname, $this->webapp);

		var_dump($tab, $tabname, $data);
	}
}
new class extends webapp
{
	function __construct()
	{
		//if ($this->init_admin_sign_in()) return;
		parent::__construct();
		[$this->mysql_hostname, $this->mysql_username, $this->mysql_password]
			= json_decode($this->request_cookie_decrypt('mysql_hostinfo') ?? 'NULL', TRUE)
				?? [$this['mysql_hostname'], $this['mysql_username'], $this['mysql_password']];
		$this->mysql_database = $this->request_cookie('mysql_database');
		$this->mysql_charset = $this->request_cookie('mysql_charset') ?? $this['mysql_charset'];
		if ($this->router === $this)
		{
			$this->app('webapp_echo_html')->title('MySQL Admin');
			$this->app->nav([
				['Home', '?'],
				['Console', '?console'],
				['Microsoft', [
					['Windows Server 2016', '#'],
					['Windows XP Profession', '#'],
					['Windows XP Home', '#'],
					['Else Windows', [
						['Windows 98', '#'],
						['Windows 2000', '#'],
						['Windows Me', '#']
					]]
				]],
				['User', '?users'],
				['Status 不要点', '?status'],
				['Variables 没完成', '?variables'],
				['Processlist', '?processlist'],
			]);
			if (in_array($this->method, ['get_home', 'post_home'], TRUE))
			{
				return;
			}
			
			if ($this->mysql->connect_errno)
			{
				return $this->break($this->get_home(...));
			}
		
			
			$this->app->aside->select(array_combine($this->charset, $this->charset))->setattr([
				'onchange' => 'location.replace(`?console/${this.value}`)',
				'class' => 'webapp-button'])->selected($this->mysql_charset);

			$ul = $this->app->aside->append('ul', ['class' => 'webapp-select']);
	
			
		
			
			foreach ($this->mysql('SHOW DATABASES') as $db)
			{
				$node = $ul->append('li');
				$node->append('a', [$db['Database'], 'href' => '?db/' . $this->url64_encode($db['Database'])]);
				if ($db['Database'] === $this->mysql_database)
				{
					$node = $node->append('ul', ['class' => 'webapp-select']);
					foreach ($this->mysql('SHOW TABLE STATUS') as $tab)
					{
						$node->append('li')->append('a', ["{$tab['Name']}[" . ($tab['Rows'] ?? 0) . ']', 'href' => '?tab/' . $this->url64_encode($tab['Name'])]);
					}
				}
			}
		}
	}

	function mysql(...$commands):webapp_mysql
	{
		if ($commands)
		{
			return ($this->mysql)(...$commands);
		}
		if (property_exists($this, 'mysql'))
		{
			return $this->mysql;
		}
		$mysql = new webapp_mysql($this->mysql_hostname, $this->mysql_username, $this->mysql_password, $this->mysql_database);
		if (in_array($this->mysql_charset, $this->charset = $mysql->characterset()->column('Charset'), TRUE))
		{
			$mysql->set_charset($this->mysql_charset);
		}
		return $mysql;
	}
	function datatypes():array
	{
		return array_combine($datatype = $this->mysql->datatypes(), $datatype);
	}
	function form_field(string $tabname, webapp|webapp_html $context = NULL, string $action = NULL):array|webapp_form
	{
		$form = new webapp_form($context, $action);
		$form->legend('Field');

		$form->fieldset('Field / Comment');
		$form->field('field', 'text', ['placeholder' => 'Type name', 'required' => NULL]);
		$form->field('comment', 'text', ['placeholder' => 'Type comment']);

		$form->fieldset('Type / Length');
		$form->field('type', 'select', [
			'options' => $this->datatypes
		]);
		$form->field('length', 'text', ['placeholder' => 'Type max length or enum set']);

		$form->fieldset('Attribute / Collation');
		$form->field('attr', 'select', [
			'options' => [
				'none', 'binary', 'unsigned', 'unsigned zerofill'
			]
		]);
		$collation = $form->field('collation', 'select', ['options' => ['' => 'none']]);
		

		// $collation = [];
		// foreach ($this->mysql('SHOW COLLATION') as $row)
		// {
		// 	$collation[$row['Charset']][] = $row['Collation'];
		// }



		$form->fieldset('Null / Default');
		$form->field('null', 'checkbox', [
			'options' => ['Yes' => 'Allow null value']
		]);
		$form->field('default', 'text', ['placeholder' => 'Type default value']);

		$form->fieldset('Extra / After');
		$form->field('extra', 'checkbox', [
			'options' => ['auto_increment' => 'Auto increment']
		]);
		$after = $form->field('after', 'select', ['options' => ['' => 'none']]);
		

		$form->fieldset();
		$form->button('Submit', 'submit');
		$form->button('Reset', 'reset');
		if ($form->echo)
		{
			foreach ($this->mysql->collation() as $row)
			{
				$optgroup = ($p = $collation->xpath("optgroup[@label='{$row['Charset']}']"))
					? $p[0] : $collation->append('optgroup', ['label' => $row['Charset']]);
				$optgroup->append('option', [$row['Collation'], 'value' => $row['Collation']]);

			}
			foreach ($this->mysql('SHOW FIELDS FROM ?a', $tabname) as $row)
			{
				$after->append('option', ["{$row['Field']}:{$row['Type']}", 'value' => $row['Field']]);
			}

			
		}
		else
		{
			$form->novalidate();
		}
		
		return $form;
	}
	function form_table(string $tabname, webapp|webapp_html $context = NULL, string $action = NULL):array|webapp_form
	{
		$form = new webapp_form($context, $action);
		$form->legend($tabname);
		foreach ($this->mysql('SHOW FULL FIELDS FROM ?a', $tabname) as $row)
		{
			$form->fieldset($row['Field']);
			preg_match('/(\w+)(?:\((\d+)\)(?:\s(\w+))?)?/', $row['Type'], $type);
			if (isset($type[2]) && is_numeric($type[2]))
			{
				$attr['maxlength'] = $type[2];
			}


			$attr = [];
			if ($row['Comment'])
			{
				$attr['placeholder'] = $row['Comment'];
			}
			if ($row['Null'] === 'NO')
			{
				$attr['required'] = NULL;
			}
			

			$form->field($row['Field'], match ($type[1])
			{
				'tinyint' => 'number',
				'text' => 'textarea',
				default => 'text'
			}, $attr);
			//rint_r($row);
		}
		$form->fieldset();
		
		$form->button('Insert', 'submit');
		$form->button('Reset', 'reset');
		
		return $form();
	}

	function post_home()
	{
		$this->response_cookie_encrypt('mysql_hostinfo', json_encode(array_values($this->request_content()), JSON_UNESCAPED_UNICODE));
		$this->response_cookie('mysql_database');
		$this->response_location('?console');
	}
	function get_home()
	{
		
		$form = $this->app->main->form('?home');
		$form->fieldset('MySQL Host');
		$form->field('hostname', 'text', ['value' => $this->mysql_hostname]);
		$form->fieldset('Username');
		$form->field('username', 'text', ['value' => $this->mysql_username]);
		$form->fieldset('Password');
		$form->field('password', 'text', ['value' => $this->mysql_password]);
		$form->fieldset();
		$form->button('Connect to MySQL', 'submit');
		$form->xml['class'] = 'webapp-inline';
	}
	function get_console(string $charset = NULL)
	{
		if (is_string($charset))
		{
			$this->response_cookie('mysql_charset', $charset);
			$this->response_location($this->request_referer() ?? '?console');
			return;
		}
		$form = $this->app->main->form('?query/console');
		$form->fieldset('Create');
		$form->field('createdb', 'text');
		$form->button('Create Database', 'submit');
		
		$form->fieldset();
		$form->field('command', 'textarea');
		$form->fieldset();
		$form->button('Query', 'submit');


		
		//$form->fieldset();
		//$form->field('uploadfile', 'file', ['multiple' => NULL]);
		
	}
	function get_db(string $database = NULL)
	{
		if (is_string($database))
		{
			$this->response_cookie('mysql_database', $this->url64_decode($database));
			$this->response_location('?db');
			return 302;
		}
		$table = $this->app->main->table($this->mysql('SHOW TABLE STATUS')->result($fields), function(array $row, webapp $webapp)
		{
			$this->row();
			$this->cell([
				['a', $row['Name'], 'href' => '?tab/' . $webapp->url64_encode($row['Name'])],
				['span', $row['Comment']]
			], 'iter');
			$this->cell(['span',[
				
			]], );
			$tr = &$this->tbody->tr[];
			$td = &$tr->td[];

			$td->append('span')->append('a', [$row['Name'], 'href' => '?tab/' . $webapp->url64_encode($row['Name'])]);
			$td->span[] = $row['Comment'];

			$td = &$tr->td[];
			//$td->span[] = $tab['Collation'];
			$td->span[] = "{$row['Engine']}:{$row['Version']}";
			$td->span[] = "{$row['Row_format']}:{$row['Rows']}";

			$td = &$tr->td[];
			$td->span[] = "{$row['Data_length']}/{$row['Data_free']}";
			$td->span[] = "{$row['Index_length']}/{$row['Avg_row_length']}";
			

			$td = &$tr->td[];
			$td->span[] = $row['Create_time'] ?? '-';
			$td->span[] = $row['Update_time'] ?? '-';

			$td = &$tr->td[];
			$td->span[] = $row['Check_time'] ?? '-';
			$td->span[] = $row['Checksum'] ?? '-';

			$td = &$tr->td[];
			$td->span[] = $row['Create_options'] ?? '-';
			$td->span[] = $row['Auto_increment'] ?? '-';
		}, $this);

		$table->header($this->mysql_database);

		$table->bar->append('a', ['Create Table', 'href' => '?query/createtab']);

		
		$fieldset = $table->fieldset();

		$fieldset->append('td')->appends('span', ['Name', 'Comment']);
		$fieldset->append('td')->appends('span', ['Engine:Version', 'Row format:Rows']);
		$fieldset->append('td')->appends('span', ['Data length/Data free', 'Index length/Avg row length']);
		$fieldset->append('td')->appends('span', ['Create time', 'Update time']);
		$fieldset->append('td')->appends('span', ['Check time', 'Checksum']);
		$fieldset->append('td')->appends('span', ['Create options', 'Auto increment']);
		$fieldset->append('td')->appends('span', ['Name', 'Comment']);


		$table->footer($this->mysql('SHOW CREATE DATABASE ?a', $this->mysql_database)->value(1));
		
		$table->xml['class'] .= '-grid';


		$table = $this->app->main->table(['123','456']);
		
	}
	
	function get_tab(string $name)
	{
		$tabname = $this->url64_decode($name);
		$table = $this->app->main->table($this->mysql('SHOW FULL FIELDS FROM ?a', $tabname), function(array $row)
		{
			$this->row();
	
			$this->cell()->append('a', ['Editor', 'href' => '#']);

			
			$cell = $this->cell();
			$cell->append('a', [$row['Field'], 'href' => '#']);
			if ($row['Comment'])
			{
				$cell['style'] = 'color: gray';
				$cell->text("({$row['Comment']})");
			}

			$this->row->appends('td', [
				$row['Type'],
				$row['Collation'],
				$row['Null'],
				$row['Key'],
				$row['Default'],
				$row['Extra'],
				$row['Privileges']
			]);
		});

		$table->fieldset('Function', 'Field', 'Type', 'Collation', 'Null', 'Key', 'Default', 'Extra', 'Privileges');


		$table->header($tabname);

		
		
		
		
		$a = $table->bar;
		$a->append('a', ['View data', 'href' => '?data/' . $name, 'class'=> 'primary']);
		$a->append('a', ['Insert data', 'href' => '#']);
		$a->append('a', ['Append field', 'href' => '?editfield/' . $name]);
		//$a->append('input');
		$a->append('a', ['Rename table', 'href' => '#', 'class'=> 'danger']);
		$a->append('a', ['Truncate table', 'href' => '#', 'class'=> 'danger']);
		$a->append('a', ['Drop table', 'href' => '#', 'class'=> 'danger']);
		
		
		
	

		
		
		//$a->append('button', ['View Data']);
		// $table->cond();
		// $table->bar->button('Append Field');
		// $table->bar->field('asd', 'text');
		
		// $table->bar->button('vvvv');

		// $table->bar->button('dwdawd');
		// $table->bar->button('wdwdwdwd');

		

		

		$table->footer()->details('Create table')->append('code', [
			$this->mysql('SHOW CREATE TABLE ?a', $tabname)->value(1),
			'class' => 'webapp-codeblock'
		]);
		$table->xml['class'] = 'webapp';

		

		$table = $this->app->main->table($this->mysql('SHOW INDEX FROM ?a', $tabname)->result($fields), function(array $data)
		{
			$this->row();
			$this->cell([['a', 'Delete', 'href' => '#']], 'iter');
			$this->cells($data);
		});
		$table->fieldset('Function', ...$fields);
		$table->xml['style'] = 'margin-top: var(--webapp-gap)';
	}
	function get_data(string $name, int $page = 1, int $rows = 40)
	{
		$tabname = $this->url64_decode($name);
		$datatab = $this->mysql->{$tabname};

		
		
		$table = $this->app->main->table($datatab->paging($page, $rows)->result($fields));
		$table->xml['class'] = 'webapp';
		$table->header($tabname);
		
		$table->cond([
			'qweqwe' => 'aaaaa',
			'1eqwe' => 'bbbbbb',
			'1eqwea' => 'cccccc'
		]);
		
		//$table->bar->append('input');
		$table->bar->append('a', ['Insert data', 'href' => '#']);
		$table->bar->append('a', ['Backto table', 'href' => '#']);
		$table->bar->append('a', ['Delete all', 'href' => '#', 'class' => 'danger']);

		$table->fieldset(...$fields);

		

		

	}
	function get_editfield(string $name)
	{
		$this->form_field($this->url64_decode($name), $this->app->main);
	}
	function get_editor(string $name)
	{
		$tabname = $this->url64_decode($name);
		$this->form_field($tabname, $this->app->main, '?query/insert,tab:' . $name);
	}

	function get_select(string $fullname)
	{
	

		$datatable = $this->mysql->{$this->selectdb($fullname)};
		$table = $this->app->section->table($datatable('order by help_keyword_id asc')->paging($this->page), function(array $val, string $fullname, string $primary):void
		{
			$tr = &$this->tbody->tr[];
			$tr->append('td')->append('a', ['Edit', 'href' => "?editor/{$fullname},primary:{$primary},key:{$val[$primary]}"]);
			foreach ($val as $col)
			{
				$tr->td[] = $col;
			}
		}, $fullname, $datatable->primary);
		$table->paging('?select/mysql--help_keyword,page:');
	}
	function form_datatable_build(string $fullname, webapp_html_xml $node = NULL, string $action = NULL):webapp_html_form
	{
		$form = $this->formdata($node, $action);
		//$form = new webapp_html_form($this, $node, $action);
		foreach ($this->mysql->list('describe ?a', $this->selectdb($fullname)) as $info)
		{
			$form->fieldset($info['Field']);
			preg_match('/\w+(?:\((\d+)\)(?:\s(\w+))?)?/', $info['Type'], $type);
			switch ($type[0])
			{
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
				case 'float':
				case 'double':
				case 'decimal':
					$form->field($info['Field'], 'number');
					break;
				case 'DATE':
				case 'TIME':
				case 'YEAR':
				case 'DATETIME':
				case 'TIMESTAMP':
					$form->field($info['Field'], 'text');
					break;
				default:
					$form->field($info['Field'], 'text');
			}
		}
		$form->fieldset();
		$form->button('Insert', 'submit');
		$form->button('Update', 'submit');
		return $form;
	}

	function post_users(){
		print_r($_POST);
	}
	function get_users()
	{
		$this->app->main->select([
			'ddd' => '卡仕达和爱时代拉',
			'aaaa' => '哈可是当我安徽省电话',
			'wwwww' => '哦i的期望都收到阿萨的',
			'gggg' => '静安寺点卡实打实'
		], FALSE, 'adsdasd', '请选择')->selected('ddd', 'aaaa', 'gggg', null)['class'] = 'webapp-button-menu';

		// $this->app->main->append('option', ['value' => 'dd']);
		
		$this->app->main->append('hr');

		$form = $this->app->main->form();
		//$form->legend('0000000000000000000000000000000000000000000000000000000000000000');

		$form->fieldset();
		$form->field('asd', 'webapp-select', [
		'data-placeholder' => 'aaa',
		'data-multiple' => NULL,
		'options' => [
			'ddd' => '卡仕达和爱时代拉',
			'aaaa' => '哈可是当我安徽省电话',
			'wwwww' => '哦i的期望都收到阿萨的',
			'gggg' => '静安寺点卡实打实'
		]]);
		$form->field('dda', 'text');
		//$form->field('add', 'text');

		$form->fieldset();

		$form->field('www', 'checkbox', [

			'options' => [
				'ddd' => '卡仕达和爱时代拉',
				'aaaa' => '哈可是当我安徽省电话',
				'wwwww' => '哦i的期望都收到阿萨的',
				'gggg' => '静安寺点卡实打实'
		]]);
		$form->fieldset();

		$form->button('提交', 'submit');
		$form->button('清楚把',);
		$form->button('静安寺安徽爱时代就垃圾啊看');

		$form->fieldset();
		$form->field('dda1', 'text');
		$form->field('add2', 'text');
		//$form->field('dda3', 'text');
		//$form->field('add4', 'text');

		$form->fieldset();
		$form->button('测试');

		$this->app->main->append('b', '2222');
		$this->app->main->append('input', ['type'=>'text', 'value' => 'ssss', 'class'=>'webapp-input']);
		$this->app->main->append('button', ['Hjasdw Lkdasd', 'class'=>'webapp-button']);
	}
	function get_processlist(string $id = NULL)
	{
		if (is_numeric($id))
		{
			$this->response_location('?processlist');
			$this->mysql->kill(intval($id));
			return 302;
		}
		$table = $this->app->main->table($this->mysql->processlist()->result($fields), function(array $data)
		{
			$this->row();
			//$this->cell(['a', 'Kill', 'href' => '?processlist/' . $data['Id']]);
			//$this->row->append('td')->append('a', ['Kill', 'href' => '?processlist/' . $data['Id']]);
			$this->cell([['a', 'Kill', 'href' => '?processlist/' . $data['Id']]], 'iter');
			$this->cells($data);
		});
		$table->fieldset('Kill', ...$fields);
		$table->header('Processlist');


		$table->xml['class'] = 'webapp-even-hover';
	}
};