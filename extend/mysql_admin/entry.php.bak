<?php
require '../../webapp_stdio.php';
new class extends webapp
{
	private readonly array $connect;
	function __construct()
	{
		parent::__construct(['admin_cookie' => 'mysql']);
		if ($this->auth)
		{
			$this->connect = is_string($host = $this->request_cookie_decrypt('mysql_connect'))
				? json_decode($host, TRUE) : [$this['mysql_hostname'], $this['mysql_username'], $this['mysql_password']];
			if (in_array($this->method, ['get_home', 'post_home']) === FALSE && $this->mysql->connect_errno)
			{
				$this->response_location('?');
				$this->response_status(302);
				return;
			}
		}
	}
	function authenticate()
	{
		return $this->admin(...func_get_args());
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
		$mysql = new webapp_mysql(...$this->connect);
		if ($mysql->connect_errno === 0)
		{
			$mysql->select_db(in_array(
				$this->database = $this->request_cookie('mysql_database') ?? $this['mysql_database'],
				$this->databases = $mysql->show('DATABASES')->column('Database'), TRUE)
					? $this->database : $this->database = 'mysql');
			if (in_array(
				$this->charset = $this->request_cookie('mysql_charset') ?? $this['mysql_charset'],
				$this->charsets = $mysql->characterset()->column('Charset'), TRUE)) {
				$mysql->set_charset($this->charset);
			}
		}
		return $this($mysql);
	}
	function html():webapp_echo_html
	{
		$this->echo_html('MySQL');
		$this->echo->script(['src' => '/webapp/static/js/wa.js']);
		$this->echo->nav([
			['Home', '?'],
			['Console', '?console'],
			// ['Microsoft', [
			// 	['Windows Server 2016', '#'],
			// 	['Windows XP Profession', '#'],
			// 	['Windows XP Home', '#'],
			// 	['Else Windows', [
			// 		['Windows 98', '#'],
			// 		['Windows 2000', '#'],
			// 		['Windows Me', '#']
			// 	]]
			// ]],
			['Users', '?users'],
			['Variables', '?variables'],
			['Status', '?status'],
			['Processlist', '?processlist'],
		]);
		$this->echo->xml->head->append('style', <<<STYLE
			@import url(/webapp/static/fonts/titilliumweb/import.css);
			:root{
				--webapp-font-default: titilliumweb;
			}
		STYLE);

		if ($this->method !== 'get_home')
		{
			$this->echo->aside->select(array_combine($this->charsets, $this->charsets))->setattr([
				'onchange' => 'location.replace(`?console/${this.value}`)',
				'class' => 'webapp-button'])->selected($this->charset);

			$ul = $this->echo->aside->append('ul', ['class' => 'webapp-select']);
			foreach ($this->databases as $name)
			{
				$node = $ul->append('li');
				$node->append('a', [$name, 'href' => '?database/' . $this->url64_encode($name), 'data-bind' => 'click']);
				if ($name === $this->database)
				{
					$node = $node->append('ul', ['class' => 'webapp-select']);
					foreach ($this->tables(TRUE) as $table)
					{
						$node->append('li')->append('a', ["{$table['Name']}[{$table['Rows']}]", 'href' => '?table/' . $this->url64_encode($table['Name'])]);
					}
				}
			}
		}

		return $this->echo;
	}
	function json():webapp_echo_json
	{
		return $this->echo_json();
	}
	function goto(string $url):void
	{
		$this->echo['goto'] = $url;
	}
	function tables(bool $status):array
	{
		return $status ? $this->mysql->show('TABLE STATUS')->all() : array_column($this->mysql->show('TABLES')->all(MYSQLI_NUM), 0);
	}

	function form_host(webapp|webapp_html $context):webapp_form
	{
		$form = new webapp_form($context, '?');
		$form->fieldset('MySQL Host');
		$form->field('hostname', 'text');
		$form->fieldset('Username');
		$form->field('username', 'text');
		$form->fieldset('Password');
		$form->field('password', 'text');
		$form->fieldset();
		$form->button('Connect to MySQL', 'submit');
		return $form;
	}
	function post_home()
	{
		if ($this->form_host($this)->fetch($connect))
		{
			$this->response_cookie_encrypt('mysql_connect', json_encode(array_values($connect), JSON_UNESCAPED_UNICODE));
		}
		$this->response_refresh(0, '?console');
	}
	function get_home()
	{
		$this->form_host($this->html->main)->echo(array_combine(['hostname', 'username', 'password'], $this->connect));
	}

	function form_console(webapp|webapp_html $context):webapp_form
	{
		$form = new webapp_form($context);
		$form->xml['data-bind'] = 'submit';
		$form->fieldset('Create');
		$form->field('createdb', 'text');
		$form->fieldset();
		$form->field('command', 'textarea');
		$form->fieldset();
		$form->button('Query', 'submit');
		return $form;
	}
	function post_console()
	{
		$this->echo_json();
		if ($this->form_console($this)->fetch($console))
		{
			if (strlen($console['createdb'])
				&& $this->mysql->real_query('CREATE DATABASE ?a', $console['createdb'])
				&& $this->mysql->select_db($console['createdb'])) {
				$this->response_cookie('mysql_database', $console['createdb']);
				$this->goto('?database');
			}
			if (strlen($console['command']))
			{
				$this->mysql->real_query($console['command']);
			}
		}
	}
	function get_console(string $charset = NULL)
	{
		if (is_string($charset))
		{
			$this->response_cookie('mysql_charset', $charset);
			$this->response_location($this->request_referer('?conosle'));
			return 302;
		}
		$this->form_console($this->html->main);
	}

	function delete_database()
	{
		$this->echo_json();
		$this->mysql->real_query('DROP DATABASE ?a', $this->database)
			&& $this->goto('?database');
	}
	function patch_database()
	{
		$this->echo_json();
		is_array($data = $this->request_content())
			&& array_key_exists('tablename', $data)
			&& is_string($data['tablename'])
			&& strlen($data['tablename'])
			&& $this->mysql->real_query('CREATE TABLE ?a(`hash` char(12) NOT NULL, PRIMARY KEY (`hash`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $data['tablename'])
			&& $this->goto('?table/' . $this->url64_encode($data['tablename']));
	}

	//截断表（全部）
	function patch_truncate(string $name = NULL)
	{
		$this->goto(is_string($name) ? "?table/{$name}" : '?database');
		foreach (is_string($name)
			? (is_string($tablename = $this->url64_decode($name)) ? [$tablename] : [])
			: $this->tables(FALSE) as $tablename) {
			$this->mysql->real_query('TRUNCATE TABLE ?a', $tablename);
		}
	}
	//删除表（全部）
	function delete_table(string $name = NULL)
	{
		$this->goto('?database');
		foreach (is_string($name)
			? (is_string($tablename = $this->url64_decode($name)) ? [$tablename] : [])
			: $this->tables(FALSE) as $tablename) {
			$this->mysql->real_query('DROP TABLE ?a', $tablename);
		}
	}

	function patch_table(string $name)
	{
		$this->goto();
		$tablename = $this->url64_decode($name);
		foreach ($this->request_content() as $field => $value)
		{
			if ($field === 'rename')
			{
				$this->echo['goto'] = '?table/' . $this->url64_encode($value);
				$this->mysql->real_query('ALTER TABLE ?a ?? ?a', $tablename, $field, $value);
			}
			else
			{
				$this->mysql->real_query('ALTER TABLE ?a ?? ?s', $tablename, $field, $value);
			}
		}
	}

	function patch_rename(string $table, string $field = NULL)
	{

	}


	function get_database(string $name = NULL)
	{
		if (is_string($name))
		{
			$this->echo_json();
			if (is_string($database = $this->url64_decode($name)) && $this->mysql->select_db($database))
			{
				$this->response_cookie('mysql_database', $database);
				$this->goto('?database');
			}
			return;
		}


		$table = $this->html->main->table($this->tables(TRUE), function(webapp_table $table, array $field, array $fields)
		{
			$tablename = $this->url64_encode($field['Name']);
			$table->row();
			// $cell = $table->cell();
			// $cell->append('a', ['Delete', 'href' => "?table/{$tablename}"]);
			// $cell->append('a', ['Comment', 'href' => '#']);

			$cell = $table->cell();
			$cell->append('a', [$field['Name'], 'href' => "?table/{$tablename}"]);
			$cell->append('span', [$field['Comment'],
				'data-method' => 'patch',
				'data-src' => "?table/{$tablename}",
				'data-dialog' => '{"comment":"textarea"}',
				'data-bind' => 'click'
			]);

			$cell = $table->cell();
			$cell->append('span', "{$field['Engine']} {$field['Version']}");
			$cell->append('span', $field['Collation']);
			foreach ($fields as $name)
			{
				$cell = $table->cell();
				$cell->append('span', $field[$name[0]]);
				$cell->append('span', $field[$name[1]]);
			}
			//$table->cell()->append('a', [$field['Name'], 'href' => '?table/' . $this->url64_encode($field['Name'])]);
		}, $fields = [
			//,
			['Row_format', 'Rows'],
			
			['Data_length', 'Data_free'],
			['Create_time', 'Update_time'],
			['Check_time', 'Checksum'],
			['Create_options', 'Auto_increment']

		]);

		$table->xml['class'] = 'webapp-multiline';
		$fieldset = $table->fieldset();
		foreach ([['Name', 'Comment'], ['Engine', 'Collation'], ...$fields] as $name)
		{
			$td = $fieldset->append('td');
			$td->append('span', $name[0]);
			$td->append('span', $name[1]);
		}


		

		
		$table->header($this->database);
		$table->bar->append('a', ['Create table', 'href' => '?database',
			'data-bind' => 'click',
			'data-method' => 'patch',
			'data-dialog' => '{"tablename":"text"}'
		]);

		$table->bar->append('a', ['Truncate all table', 'href' => "?truncate",
			'style' => 'color:darkred',
			'data-bind' => 'click',
			'data-method' => 'patch',
			'data-dialog' => 'Truncate all table cannot undo'
		]);

		$table->bar->append('a', ['Drop all table', 'href' => '?table',
			'style' => 'color:darkred',
			'data-bind' => 'click',
			'data-method' => 'delete',
			'data-dialog' => 'Drop all table'
		]);


		$table->bar->append('a', ['Drop database', 'href' => '?database',
			'style' => 'color:darkred',
			'data-bind' => 'click',
			'data-method' => 'delete',
			'data-dialog' => 'Drop database'
		]);
		
		$table->footer($this->mysql->show('CREATE DATABASE ?a', $this->database)->value(1));
	}



	function get_table(string $name, int $page = 0)
	{
		if ($page > 0)
		{
			$model = $this->mysql->table($this->url64_decode($name));
			if ($primary = $model->primary)
			{
				$table = $this->html->main->table($model->paging($page)->result($fields), function(webapp_table $table, array $data, string $name, string $primary)
				{
					$table->row();
					$cell = $table->cell();
					$primary = $this->url64_encode($data[$primary]);
	
					$cell->append('a', ['Delete', 'href' => "?data/{$name},primary:{$primary}",
						'data-bind' => 'click',
						'data-method' => 'delete',
						'data-dialog' => 'Delete data cannot undo'
					]);
					$cell->append('span', ' | ');
					$cell->append('a', ['Update', 'href' => "?data/{$name},primary:{$primary}"]);
					foreach ($data as $cell)
					{
						$cell === NULL ? $table->cell() : $table->cell()->append('span', htmlspecialchars($cell));
					}
				}, $name, $model->primary);
			

				$table->fieldset(...['Function', ...$fields]);
			}
			else
			{
				$table = $this->html->main->table($model->paging($page)->result($fields), function(webapp_table $table, array $data, string $name)
				{
					$table->row();
					foreach ($data as $cell)
					{
						$cell === NULL ? $table->cell() : $table->cell()->append('span', htmlspecialchars($cell));
					}
				}, $name);
				$table->fieldset(...$fields);
			}
			$table->header($model->tablename);
			$table->paging($this->at(['page' => '']));
			$table->bar->append('input', [
				'type' => 'search'
			]);
			$table->tbody['class'] = 'viewdata';
			return;
		}





		$table = $this->html->main->table($this->mysql->show('FULL FIELDS FROM ?a', $tablename = $this->url64_decode($name))->result($fields), function(webapp_table $table, array $field, string $name)
		{
			$fieldname = $this->url64_encode($field['Field']);
			$table->row();
			$cell = $table->cell();
			$cell->append('a', ['De', 'href' => "?field/{$name},field:{$fieldname}",
				'title' => 'Delete',
				'data-bind' => 'click',
				'data-method' => 'delete',
				'data-dialog' => 'Drop field cannot undo'
			]);

			


			$cell->append('span', ' | ');
			$cell->append('a', ['Ed', 'href' => "?field/{$name},field:{$fieldname}", 'title' => 'Editor']);
			$cell->append('span', ' | ');
			$cell->append('a', ['In',
				'href' => "?alter/{$name},field:{$fieldname},type:index",
				'title' => 'Index',
				'data-bind' => 'click',
				'data-method' => 'post'
			]);
			$cell->append('span', ' | ');
			$cell->append('a', ['Un',
				'href' => "?alter/{$name},field:{$fieldname},type:unique",
				'title' => 'Unique',
				'data-bind' => 'click',
				'data-method' => 'post'
			]);
			$cell->append('span', ' | ');
			$cell->append('a', ['Pr',
				'href' => "?alter/{$name},field:{$fieldname},type:primary",
				'title' => 'Primary',
				'data-bind' => 'click',
				'data-method' => 'post'
			]);

			$table->cell($field['Comment']);

			
			$table->cells(array_slice($field, 0, -1));
		}, $name);



		
		$table->fieldset(...['Function', 'Comment', ...array_slice($fields, 0, -1)]);

		$table->footer(['style' => 'text-align:left'])->details('Show create table')->append('pre', $create = $this->mysql->show('CREATE TABLE ?a', $tablename)->value(1));
		$table->header($tablename);
		$table->bar->append('a', ['View data', 'href' => "?table/{$name},page:1", 'class' => 'default']);
		$table->bar->append('a', ['Insert data', 'href' => "?data/{$name}", 'class' => 'primary']);
		$table->bar->append('a', ['Append field', 'href' => "?field/{$name}"]);

		$table->bar->select($this->mysql->show('ENGINES')->column('Engine', 'Engine'))->setattr([
			'data-src' => "?engine/{$name}",
			'data-bind' => 'change',
			'data-method' => 'patch',

		])->selected(preg_match('/ENGINE\=(\w+)/', $create, $engines) ? $engines[1] : '');
		$table->bar->select(array_combine($this->charsets, $this->charsets))->setattr([
			'data-src' => "?charsets/{$name}",
			'data-bind' => 'change',
			'data-method' => 'patch',

		])->selected(preg_match('/CHARSET\=(\w+)/', $create, $charsets) ? $charsets[1] : '');
		// foreach ($this->mysql->show('ENGINES') as $engine)
		// {
		// 	//if ($engine['Engine'])
		// 	$engines->append('option', [
		// 		$engine['Engine'],
		// 		'label' => 'asdasd',
		// 		'value' => $engine['Engine']

		// 	]);//[$engine['Engine']] = 
		// }




		$table->bar->append('a', ['Rename table', 'href' => "?table/{$name}",
			'data-bind' => 'click',
			'data-method' => 'patch',
			'data-dialog' => '{"rename":"text"}'
		]);

		$table->bar->append('a', ['Truncate table', 'href' => "?truncate/{$name}",
			'class' => 'danger',
			'data-bind' => 'click',
			'data-dialog' => 'Truncate table cannot undo'
		]);

		$table->bar->append('a', ['Drop table', 'href' => "?table/{$name}",
			'class' => 'danger',
			'data-bind' => 'click',
			'data-method' => 'delete',
			'data-dialog' => 'Drop table cannot undo'
		]);

		$table->xml['style'] = 'margin-bottom:1rem';
		
		$table = $this->echo->main->table($this->mysql->show('INDEX FROM ?a', $tablename)->result($fields), function(webapp_table $table, array $field, string $name)
		{
			$table->row();
			$table->cell()->append('a', ['Delete',
				'href' => sprintf('?alter/%s,field:%s', $name, $this->url64_encode($field['Column_name'])),
				'data-bind' => 'click',
				'data-method' => 'delete',
				'data-dialog' => 'Drop table cannot undo'
			]);

			$table->cells(array_values($field));
		}, $name);

		$table->fieldset('Delete', ...$fields);
	}


	//表单字段
	function form_field(webapp|webapp_html $context, string $table, string $field = NULL):webapp_form
	{
		return new class($context, $this, $table, $field) extends webapp_form
		{
			public readonly ?string $table, $field;
			function __construct(webapp|webapp_html $context, webapp $webapp, string $table, ?string $field)
			{
				parent::__construct($context);

				$this->table = $webapp->url64_decode($table);
				$this->field = $field ? $webapp->url64_decode($field) : $field;

				$this->fieldset('Field / Comment');
				$this->field('field', 'text', ['placeholder' => 'Type field name', 'required' => NULL]);
				$this->field('comment', 'text', ['placeholder' => 'Type field comment']);

				$this->fieldset('Type / Length');
				$this->field('type', 'select', ['required' => NULL,
					'options' => array_combine($datatype = $webapp->mysql->datatypes(), $datatype)]);
				$this->field('length', 'text', ['placeholder' => 'Type max length or enum set']);

				$this->fieldset('Null / Default');
				$this->field('null', 'select', ['required' => NULL,
					'options' => array_combine($null = ['NOT NULL', 'NULL'], $null)]);
				$this->field('default', 'text', ['placeholder' => 'Type default value']);

				$this->fieldset('Attribute / After');
				$this->field('attribute', 'select', [
					'options' => ['' => 'none'] + array_combine($attribute = ['binary', 'unsigned', 'unsigned zerofill'], $attribute)]);
				$this->field('after', 'select', [
					'options' => ['' => 'default', '.' => 'at top of table'] + array_combine($after = $webapp->mysql('DESC ?a', $this->table)->column('Field'), $after)]);

				$this->fieldset('Extra / Collation');
				$this->field('extra', 'select', ['required' => NULL,
					'options' => array_combine($extra = ['none', 'auto_increment'], $extra)]);
				$this->field('collation', 'select', ['placeholder' => 'Type default value',
					'options' => ['' => 'default'] + $webapp->mysql->collation()->group('Charset', 'Collation', 'Collation')]);

				$this->fieldset();
				$this->button('Submit', 'submit');

				$this->xml['data-bind'] = 'submit';
			}
			function input(?array &$commands):bool
			{
				if ($this->fetch($field))
				{
					$commands = ['ALTER TABLE ?a ', $this->table];
					if ($this->field)
					{
						$commands[0] .= 'CHANGE COLUMN ?a ?a';
						$commands[] = $this->field;
						$commands[] = $field['field'];
					}
					else
					{
						$commands[0] .= 'ADD ?a';
						$commands[] = $field['field'];
					}
					$commands[0] .= sprintf(' %s%s%s%s %s', $field['type'],
						$field['length'] ? "({$field['length']})" : '',
						$field['attribute'] ? " {$field['attribute']}" : '',
						$field['collation'] ? " COLLATE {$field['collation']}" : '',
						$field['null']);
					if ($field['default'])
					{
						$commands[0] .= ' DEFAULT ?s';
						$commands[] = $field['default'];
					}
					// if ($values['extra'])
					// {
					// 	$fields[] = sql_field_extra($values['extra']);
					// }
					if ($field['comment'])
					{
						$commands[0] .= ' COMMENT ?s';
						$commands[] = $field['comment'];
					}
					if ($field['after'])
					{
						$commands[0] .= $field['after'] === '.' ? ' first' : ' after ?a';
						$commands[] = $field['after'];
					}
					//print_r($commands);
			
					//var_dump( $this->mysql->format(...$commands) );
			
					//$this->mysql->real_query(...$commands);
					
					//var_dump($field);
					return TRUE;
				}
				return FALSE;
			}
			function output()
			{
				if ($this->field
					&& $this->webapp->mysql->show('FULL FIELDS FROM ?a LIKE ?s', $this->table, $this->field)->fetch($fields)
					&& preg_match('/^([a-z]+)(?:\(([^\)]+)\))?(?: (.+))?/', $fields['Type'], $types, PREG_UNMATCHED_AS_NULL)) {
					$this->xml['method'] = 'patch';
					$this->echo([
						'field' => $fields['Field'],
						'type' => $types[1],
						'length' => $types[2],
						'collation' => $fields['Collation'],
						'null' => $fields['Null'] === 'NO' ? 'NOT NULL' : 'NULL',
						'attribute' => $types[3],
						'default' => $fields['Default'],
						'comment' => $fields['Comment']
					]);
				}
				else
				{
					$this['type']->selected('tinyint');
				}
			}
		};
	}
	//创建字段
	function post_field(string $table)
	{
		$this->echo_json();
		$this->form_field($this, $table)->input($commands)
			&& $this->mysql->real_query(...$commands)
			&& $this->goto("?table/{$table}");
	}
	//删除字段
	function delete_field(string $table, string $field)
	{
		$this->echo_json();
		$this->mysql->real_query('ALTER TABLE ?a DROP ?a', $this->url64_decode($table), $this->url64_decode($field))
			&& $this->goto("?table/{$table}");
	}
	//修改字段
	function patch_field(string $table, string $field)
	{
		$this->echo_json();
		$this->form_field($this, $table, $field)->input($commands)
			&& $this->mysql->real_query(...$commands)
			&& $this->goto("?table/{$table}");
	}
	//查看字段
	function get_field(string $table, string $field = NULL)
	{
		$this->form_field($this->html->main, $table, $field)->output();
	}
	

	function post_alter(string $table, string $field, string $type)
	{
		$this->echo_json();
		if ($this->mysql->real_query(...['ALTER TABLE ?a ADD ' . match ($type)
		{
			'index' => 'INDEX(?a)',
			'unique' => 'UNIQUE(?a)',
			default => 'PRIMARY KEY(?a)'
		}, $this->url64_decode($table), $this->url64_decode($field)])) {

			$this->goto("?table/{$table}");
		}
	}
	function delete_alter(string $table, string $field)
	{
		$this->echo_json();
		if ($this->mysql->real_query('ALTER TABLE ?a DROP INDEX ?a',
			$this->url64_decode($table), $this->url64_decode($field))) {
			$this->goto("?table/{$table}");
		}
	}





	function form_data(webapp|webapp_html $context, string $table):webapp_form
	{
		$form = new webapp_form($context);
		$form->xml['data-bind'] = 'submit';
		$form->fieldset->legend = $form->table = $this->url64_decode($table);

		foreach ($this->mysql->show('FULL FIELDS FROM ?a', $form->table) as $field)
		{
			$form->fieldset($field['Field']);

			preg_match('/(\w+(?:\sunsigned)?)(?:\(([^)]+)\))?/', $field['Type'], $pattern);
			[$type, $attr] = match ($pattern[1])
			{
				'tinyint' => ['number', ['min' => -128, 'max' => 127]],
				'smallint' => ['number', ['min' => -32768, 'max' => 32767]],
				'mediumint' => ['number', ['min' => -8388608, 'max' => 8388607]],
				'int' => ['number', ['min' => -2147483648, 'max' => 2147483647]],
				'bigint' => ['number', ['min' => PHP_INT_MIN, 'max' => PHP_INT_MAX]],
				'float',
				'double' => ['number', ['step' => 0.0001, 'min' => PHP_INT_MIN, 'max' => PHP_INT_MAX]],

				'tinyint unsigned' => ['number', ['min' => 0, 'max' => 255]],
				'smallint unsigned' => ['number', ['min' => 0, 'max' => 65535]],
				'mediumint unsigned' => ['number', ['min' => 0, 'max' => 16777215]],
				'int unsigned' => ['number', ['min' => 0, 'max' => 4294967295]],
				'bigint unsigned' => ['number', ['min' => 0]],
				'float unsigned',
				'double unsigned' => ['number', ['step' => 0.0001, 'min' => 0]],

				'date' => ['date', []],
				'set' => ['checkbox', ['placeholder' => 'asd', 'options' => array_combine($values = explode("','", substr($pattern[2], 1, -1)), $values), 'multiple' => NULL]],
				'enum' => ['select', ['placeholder' => 'asd', 'options' => array_combine($values = explode("','", substr($pattern[2], 1, -1)), $values)]],

				'text' => ['textarea', ['rows' => 8]],
				'json' => ['textarea', ['rows' => 21]],

				default => ['textarea', isset($pattern[2]) ? ['maxlength' => $pattern[2]] : []]
			};
			if ($field['Comment'])
			{
				$attr['placeholder'] = $field['Comment'];
			}
			if ($field['Default'])
			{
				$attr['value'] = $field['Default'];
			}
			// if ($field['Null'] === 'NO')
			// {
			// 	$attr['required'] = NULL;
			// }
			$form->field($field['Field'], $type, $attr, $pattern[1] === 'json'
				? fn($v, $i) => $i ? $v : ($v ? json_encode(json_decode($v, TRUE), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '')
				: NULL);
		}
		$form->fieldset();
		$form->button('Submit', 'submit');

		return $form;
	}
	//增加数据
	function post_data(string $table)
	{
		$this->echo_json();
		$form = $this->form_data($this, $table);
		if ($form->fetch($data))
		{
			$model = $this->mysql->table($form->table);
			if ($model->insert($data))
			{
				$this->goto(sprintf('?data/%s,primary:%s', $table, $this->url64_encode($data[$model->primary])));
			}
		}
	}
	//删除数据
	function delete_data(string $table, string $primary)
	{
		$this->echo_json();
		$model = $this->mysql->table($this->url64_decode($table));
		$model->delete('WHERE ?a=?s LIMIT 1', $model->primary, $this->url64_decode($primary)) === 1
			&& $this->goto();
	}
	//修改数据
	function patch_data(string $table, string $primary)
	{
		$this->echo_json();
		$form = $this->form_data($this, $table);
		if ($form->fetch($data))
		{
			$model = $this->mysql->table($form->table);
			$model('WHERE ?a=?s LIMIT 1', $model->primary, $this->url64_decode($primary))->update($data) === 1
				&& $this->goto(sprintf('?data/%s,primary:%s', $table, $this->url64_encode($data[$model->primary])));
		}
	}
	//查看数据
	function get_data(string $table)
	{
		$form = $this->form_data($this->html->main, $table);
		if (isset($this->query['primary'])
			&& is_string($primary = $this->mysql->table($form->table)->primary)
			&& $this->mysql->table($form->table)('WHERE ?a=?s LIMIT 1', $primary, $this->url64_decode($this->query['primary']))->fetch($data)) {
			$form->xml['method'] = 'patch';
			$form->echo($data);
		}
	}




	function get_users(int $page = 1)
	{
		// $this->mysql->select_db('mysql');
		// $table = $this->html->main->table($this->mysql->user->select(['user', 'host', 'plugin'])->paging($page));
		$table = $this->html->main->table($this->mysql('SELECT user,host,plugin FROM mysql.user'));
		$table->fieldset('user', 'host', 'plugin');
		$table->header('Users');
	}
	function get_variables(string $like = NULL)
	{
		$search = is_string($like) ? $this->url64_decode($like) : '%';
		$table = $this->html->main->table($this->mysql->show('VARIABLES LIKE ?s', $search)->result($fields));
		$table->fieldset(...$fields);
		$table->header('Variables');
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'onkeydown' => 'event.keyCode==13&&location.replace(`?variables${this.value?`/${url64_encode(this.value)}`:""}`)'
		]);
	}
	function get_status(string $like = NULL)
	{
		$search = is_string($like) ? $this->url64_decode($like) : '%';
		$table = $this->html->main->table($this->mysql->show('STATUS LIKE ?s', $search)->result($fields));
		$table->fieldset(...$fields);
		$table->header('Status');
		$table->bar->append('input', [
			'type' => 'search',
			'value' => $search,
			'onkeydown' => 'event.keyCode==13&&location.replace(`?status${this.value?`/${url64_encode(this.value)}`:""}`)'
		]);
	}
	function get_processlist(int $id = NULL)
	{
		if (is_int($id))
		{
			$this->echo_json();
			$this->mysql->kill($id);
			$this->response_location('?processlist');
			return 302;
		}
		$table = $this->html->main->table($this->mysql->processlist()->result($fields), function(webapp_table $table, array $process)
		{
			$table->row();
			$table->cell()->append('a', ['kill', 'href' => "?processlist/{$process['Id']}"]);
			$table->cells($process);
		});
		$table->fieldset(...['kill', ...$fields]);
		$table->header('Processlist');
	}
};