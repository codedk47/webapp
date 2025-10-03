<?php
class webapp_extend_mysql_admin_echo extends webapp_echo_admin
{
	private readonly array $connect;
	private readonly string $charset, $database;
	private readonly webapp_mysql $mysql;
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->stylesheet('/webapp/extend/mysql_admin/echo.css');
		
		if ($this->init === FALSE)
		{
			$this->script(['src' => '/webapp/extend/mysql_admin/echo.js']);
		}
		if ($this->auth)
		{
			$this->connect = is_string($connect = $webapp->request_cookie_decrypt('mysql-connect'))
				? json_decode($connect, TRUE) : [$webapp['mysql_hostname'], $webapp['mysql_username'], $webapp['mysql_password']];
			$this->charset = $webapp->request_cookie('mysql-charset') ?? $webapp['mysql_charset'];
			$this->database = $webapp->request_cookie('mysql-database') ?? $webapp['mysql_database'];
			if (in_array($webapp->method, ['get_home', 'post_home'], TRUE) === FALSE)
			{
				$this->mysql = $webapp(new webapp_mysql(...$this->connect));
				if ($this->mysql->connect_errno)
				{
					$webapp->break($this->get_home(...));
				}
				else
				{
					$this->nav([
						['Home', "?{$this->routename}"],
						['Console', "?{$this->routename}/console"],
						// ['Users', "?{$this->routename}/users"],
						// ['Variables', "?{$this->routename}/variables"],
						// ['Status', "?{$this->routename}/status"],
						// ['Processlist', "?{$this->routename}/processlist"],
					]);
					$charset = $this->mysql->characterset()->column('Charset');
					$node = $this->aside->select(array_combine($charset, $charset))->setattr([
						'data-action' => "?{$this->routename},command:charset",
						'onchange' => '$.action(this)'
					]);
					if (in_array($this->charset, $charset, TRUE))
					{
						$this->mysql->set_charset($this->charset);
						$node->selected($this->charset);
					}
					$node = $this->aside->append('ul', ['class' => 'webapp-listmenu']);
					foreach ($this->mysql->show('DATABASES')->column('Database') as $database)
					{
						$dbnode = $node->append('li')->details($database);
						$dbnode->summary->setattr([
							'onclick' => 'return $.action(this)',
							'data-action' => "?{$this->routename},command:database",
							'data-body' => $database]);
						if ($database === $this->database && $this->mysql->select_db($this->database))
						{
							$dbnode->setattr('open');
							$dbnode = $dbnode->append('ul');
							foreach ($this->mysql->show('TABLE STATUS') as $table)
							{
								$dbnode->append('li')->append('a', ["{$table['Name']}:{$table['Rows']}",
									'href' => "?{$this->routename}/table,name:" . $webapp->url64_encode($table['Name'])]);
							}
						}
					}
				}
			}
		}
	}
	function form_connect(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return $.action(this)';
		$form->fieldset('Hostname');
		$form->field('hostname', 'text', ['required' => NULL]);
		$form->fieldset('Username');
		$form->field('username', 'text', ['required' => NULL]);
		$form->fieldset('Password');
		$form->field('password', 'text');
		$form->fieldset();
		$form->button('Connect to MySQL', 'submit');
		return $form;
	}
	function post_home(string $command = NULL)
	{
		$this->json();
		switch ($command)
		{
			case 'charset':
			case 'database':
				$this->webapp->response_cookie("mysql-{$command}", $this->input());
				$this->echo->refresh($command === 'database' ? "?{$this->routename}/tables" : NULL);
				break;
			default:
				if ($this->form_connect()->fetch($input))
				{
					$connect = array_values($input);
					$mysql = new webapp_mysql(...$connect);
					if ($mysql->connect_errno)
					{
						$this->echo->error($mysql->connect_error);
					}
					else
					{
						$this->webapp->response_cookie_encrypt('mysql-connect', json_encode($connect, JSON_UNESCAPED_UNICODE));
						$this->echo->redirect("?{$this->routename}/console");
					}
				}
		}
	}
	function get_home()
	{
		$this->form_connect($this->main)->echo(array_combine(['hostname', 'username', 'password'], $this->connect));
	}
	function form_console(webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->xml['onsubmit'] = 'return $.action(this)';

		$form->field('database', 'text', ['placeholder' => 'Create database name']);
		$form->button('Execute', 'submit');
		$form->fieldset();
		$form->field('command', 'textarea', ['rows' => 16, 'cols' => 64, 'placeholder' => 'The command will act on the created database context']);

		// $form->fieldset('Hostname');
		// $form->field('hostname', 'text', ['required' => NULL]);
		// $form->fieldset('Username');
		// $form->field('username', 'text', ['required' => NULL]);
		// $form->fieldset('Password');
		// $form->field('password', 'text');
		// $form->fieldset();
		// $form->button('Connect to MySQL', 'submit');
		return $form;
	}
	function post_console()
	{
		$this->json();
		if ($this->form_console()->fetch($input))
		{
			strlen($input['database'])
				&& $this->mysql->real_query('CREATE DATABASE ?a', $input['database'])
				&& $this->mysql->select_db($input['database'])
				&& $this->webapp->response_cookie('mysql-database', $input['database']);
			strlen($input['command']) && $this->mysql->real_query($input['command']);
			$this->mysql->errno === 0 && $this->echo->refresh();
		}
	}
	function get_console()
	{
		$this->form_console($this->main);
	}
	function post_tables()
	{
		$this->json();

		var_dump($this->input());
	}
	function get_tables()
	{
		//print_r( $this->mysql->show('TABLE STATUS')->all() );
		$table = $this->main->table($this->mysql->show('TABLE STATUS'), function($table, $value)
		{
			$table->row();
			$table->cell([$value['Comment'],
				'onclick' => '$.action(this)',
				'data-prompt' => "Comment:text:{$value['Comment']}"
			]);
			$table->cell()->append('a', ["{$value['Name']}:{$value['Rows']}",
				'href' => "?{$this->routename}/table,name:" . $this->webapp->url64_encode($value['Name'])]);
			$table->cell($value['Engine']);
			$table->cell($value['Collation']);
			$table->cell($value['Row_format']);
			$table->cell($value['Create_time']);
			$table->cell($value['Update_time']);
		});
		$table->fieldset('Comment', 'Name:Rows', 'Engine', 'Collation', 'Row_format', 'Create_time', 'Update_time');
		$table->header($this->database);
		$table->footer($this->mysql->show('CREATE DATABASE ?a', $this->database)->value(1));
		$table->bar->append('a', ['Create table',
			'href' => "?{$this->routename}/table",
			'onclick' => 'return $.action(this)',
			'class' => 'primary',
			'data-prompt' => 'Type table name:text'
		]);
		$table->bar->append('a', ['Truncate all table',
			'href' => "?{$this->routename}/tables",
			'class' => 'danger',
			'onclick' => 'return $.action(this)',
			'data-body' => 'truncate',
			'data-confirm' => "Truncate all table ?"
		]);
		$table->bar->append('a', ['Drop all table',
			'href' => "?{$this->routename}/tables",
			'class' => 'danger',
			'onclick' => 'return $.action(this)',
			'data-body' => 'drop',
			'data-confirm' => "Drop all table ?"
		]);
		$table->bar->append('a', ['Drop database',
			'href' => "?{$this->routename}/database",
			'class' => 'danger',
			'onclick' => 'return $.action(this)',
			'data-method' => 'delete',
			'data-confirm' => "Delete \"{$this->database}\" database ?"
		]);
	}
	function delete_database()
	{
		$this->json();
		if ($this->mysql->real_query('DROP DATABASE ?a', $this->database))
		{
			$this->echo->redirect("?{$this->routename}/console");
		}
	}


	function form_field(string $tablename, webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);
		$form->fieldset('Field / Comment');
		$form->field('field', 'text', ['placeholder' => 'Type field name', 'required' => NULL]);
		$form->field('comment', 'text', ['placeholder' => 'Type field comment']);

		$form->fieldset('Type / Length');
		$form->field('type', 'select', ['required' => NULL,
			'options' => array_combine($datatype = $this->mysql->datatypes(), $datatype)]);
		$form->field('length', 'text', ['placeholder' => 'Type max length or enum set']);

		$form->fieldset('Null / Default');
		$form->field('null', 'select', ['required' => NULL,
			'options' => array_combine($null = ['NOT NULL', 'NULL'], $null)]);
		$form->field('default', 'text', ['placeholder' => 'Type default value']);

		$form->fieldset('Attribute / After');
		$form->field('attribute', 'select', [
			'options' => ['' => 'none'] + array_combine($attribute = ['binary', 'unsigned', 'unsigned zerofill'], $attribute)]);
		$form->field('after', 'select', [
			'options' => ['' => 'default', '.' => 'at top of table'] + array_combine($after = (($this->mysql)('DESC ?a', $tablename))->column('Field'), $after)]);

		$form->fieldset('Extra / Collation');
		$form->field('extra', 'select', ['required' => NULL,
			'options' => array_combine($extra = ['none', 'auto_increment'], $extra)]);
		$form->field('collation', 'select', ['placeholder' => 'Type default value',
			'options' => ['' => 'default'] + $this->mysql->collation()->group('Charset', 'Collation', 'Collation')]);

		$form->fieldset();
		$form->button('Close', 'button', ['value' => 'close']);
		$form->button('Reset', 'reset');
		$form->button('Submit', 'submit');

		return $form;
	}
	function form_field_query(string $tablename, string $fieldname = NULL):bool
	{
		if ($this->form_field($tablename)->fetch($input))
		{
			$sql = ['ALTER TABLE ?a ', $tablename];
			if ($fieldname)
			{
				$sql[0] .= 'CHANGE COLUMN ?a ?a';
				$sql[] = $fieldname;
				$sql[] = $input['field'];
			}
			else
			{
				$sql[0] .= 'ADD ?a';
				$sql[] = $input['field'];
			}
			$sql[0] .= sprintf(' %s%s%s%s %s', $input['type'],
				$input['length'] ? "({$input['length']})" : '',
				$input['attribute'] ? " {$input['attribute']}" : '',
				$input['collation'] ? " COLLATE {$input['collation']}" : '',
				$input['null']);
			if ($input['default'])
			{
				$sql[0] .= ' DEFAULT ?s';
				$sql[] = $input['default'];
			}
			if ($input['extra'])
			{
				//$sql[] = sql_field_extra($input['extra']);
			}
			if ($input['comment'])
			{
				$sql[0] .= ' COMMENT ?s';
				$sql[] = $input['comment'];
			}
			if ($input['after'])
			{
				$sql[0] .= $input['after'] === '.' ? ' first' : ' after ?a';
				$sql[] = $input['after'];
			}
			return $this->mysql->real_query(...$sql);
		}
		return FALSE;
	}


	function post_table(string $name = NULL, string $field = NULL)
	{
		$this->json();
		if ($name === NULL)
		{
			$this->mysql->real_query(<<<'SQL'
			CREATE TABLE ?a(
				`hash` char(12) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
				PRIMARY KEY (`hash`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
			SQL, $input = $this->input())
				&& $this->echo->redirect("?{$this->routename}/table,name:" . $this->webapp->url64_encode($input));
		}
		else
		{
			$tablename = $this->webapp->url64_decode($name);
			if ($field === NULL)
			{
				$this->form_field_query($tablename) && $this->echo->refresh();
			}
			else
			{
				$fieldname = $this->webapp->url64_decode($field);
				if ($this->webapp->request_content_length())
				{
					$this->form_field_query($tablename, $fieldname) && $this->echo->refresh();
				}
				else
				{
					$form = $this->form_field($tablename, $this->main);
					$this->mysql->show('FULL FIELDS FROM ?a LIKE ?s', $tablename, $fieldname)->fetch($data)
						&& preg_match('/^([a-z]+)(?:\(([^\)]+)\))?(?: (.+))?/', $data['Type'], $type, PREG_UNMATCHED_AS_NULL)
						&& $form->echo([
							'field' => $data['Field'],
							'type' => $type[1],
							'length' => $type[2],
							'collation' => $data['Collation'],
							'null' => $data['Null'] === 'NO' ? 'NOT NULL' : 'NULL',
							'attribute' => $type[3],
							'default' => $data['Default'],
							'comment' => $data['Comment']
						]);
					$this->echo->prompt($form);
					$this->echo->continue("?{$this->routename}/table,name:{$name},field:{$field}", 'post');
				}
			}
		}
	}
	function delete_table(string $name)
	{
		$this->json();
		if ($this->mysql->real_query('DROP TABLE ?a', $this->webapp->url64_decode($name)))
		{
			$this->echo->redirect("?{$this->routename}/tables");
		}
	}
	function patch_table(string $name, string $command = NULL)
	{
		$this->json();
		$sql = [];
		$input = $this->input();
		$redirect = NULL;


		$command = match ($command)
		{
			'engine' => 'ENGINE=?s',
			'index' => 'ADD INDEX(?a)',
			'unique' => 'ADD UNIQUE(?a)',
			'primary' => 'ADD PRIMARY KEY(?a)',
			'dropfield' => 'DROP ?a',
			'dropindex' => 'DROP INDEX ?a',
			default => ['RENAME ?a', "?{$this->routename}/table,name:" . $this->webapp->url64_encode($input)][0]
		};
		if ($this->mysql->real_query("ALTER TABLE ?a {$command}", $this->webapp->url64_decode($name), $this->input()))
		{
			$this->echo->refresh($redirect);
		}
	}

	function get_table(string $name)
	{
		$tablename = $this->webapp->url64_decode($name);
		$this->form_data($tablename, $this->template('form_data'));
		$this->form_field($tablename, $this->template('form_field'));
		$table = $this->main->table($this->mysql->show('FULL FIELDS FROM ?a', $tablename)->result($fields), function($table, $value, $name)
		{
			$field = $this->webapp->url64_encode($value['Field']);
			$table->row();
			$table->cell()->details_popup($value['Field'], [
				['Modify', "?{$this->routename}/table,name:{$name},field:{$field}",
					'onclick' => 'return $.action(this)', 'data-method' => 'post'],
				['Index', "?{$this->routename}/table,name:{$name},command:index",
					'onclick' => 'return $.action(this)', 'data-method' => 'patch', 'data-body' => $value['Field']],
				['Unique', "?{$this->routename}/table,name:{$name},command:unique",
					'onclick' => 'return $.action(this)', 'data-method' => 'patch', 'data-body' => $value['Field']],
				['Primary', "?{$this->routename}/table,name:{$name},command:primary",
					'onclick' => 'return $.action(this)', 'data-method' => 'patch', 'data-body' => $value['Field']],
				['Delete', "?{$this->routename}/table,name:{$name},command:dropfield", 'class' => 'danger',
					'onclick' => 'return $.action(this)', 'data-method' => 'patch', 'data-body' => $value['Field'],
					'data-confirm' => "Drop \"{$value['Field']}\" field ?"]
			]);
			$table->cell($value['Comment']);
			$table->cell($value['Type']);
			$table->cell($value['Collation']);
			$table->cell($value['Null']);
			$table->cell($value['Key']);
			$table->cell($value['Default']);
			$table->cell($value['Extra']);
			$table->cell($value['Privileges']);
		}, $name);
		$table->fieldset('Field', 'Comment', ...array_slice($fields, 1, -1));
		$table->header($tablename);
		$table->footer()->details('Show create table')->append('pre', $create = $this->mysql->show('CREATE TABLE ?a', $tablename)->value(1));
		$table->bar->append('a', ['View data', 'href' => "?{$this->routename}/data,name:{$name}", 'class' => 'default']);
		$table->bar->append('a', ['Insert data', '#', 'class' => 'primary',

			'onclick' => 'return $.action(this)',
			'data-prompt' => "#form_data"
		]);

		$table->bar->append('a', ['Append field',
			'href' => "?{$this->routename}/table,name:{$name}",
			'onclick' => 'return $.action(this)',
			'data-prompt' => '#form_field'
		]);
		$table->bar->append('a', ['Rename table',
			'href' => "?{$this->routename}/table,name:{$name},command:rename",
			'onclick' => 'return $.action(this)',
			'data-method' => 'patch',
			'data-prompt' => "New table name:text:{$tablename}"
		]);
		$table->bar->select($this->mysql->show('ENGINES')->column('Engine', 'Engine'))->setattr([
			'onchange' => '$.action(this)',
			'data-method' => 'patch',
			'data-action' => "?{$this->routename}/table,name:{$name},command:engine"
		])->selected(preg_match('/ENGINE\=(\w+)/', $create, $engines) ? $engines[1] : '');

		$table->bar->append('a', ['Truncate table',
			'href' => "?{$this->routename}/table,name:{$name},command:truncate",
			'class' => 'danger',
			'onclick' => 'return $.action(this)',
			'data-method' => 'patch',
			'data-confirm' => "Clean \"{$tablename}\" table ?"
		]);
		$table->bar->append('a', ['Drop table',
			'href' => "?{$this->routename}/table,name:{$name}",
			'class' => 'danger',
			'onclick' => 'return $.action(this)',
			'data-method' => 'delete',
			'data-confirm' => "Delete \"{$tablename}\" table ?"
		]);
		$table->xml['style'] = 'margin-bottom:var(--webapp-gap)';

		$table = $this->main->table($this->mysql->show('INDEX FROM ?a', $tablename)->result($fields), function($table, $value, $name)
		{
			$table->row();
			$table->cell()->append('a', ['Delete',
				'href' => "?{$this->routename}/table,name:{$name},command:dropindex",
				'class' => 'danger',
				'onclick' => 'return $.action(this)',
				'data-method' => 'patch',
				'data-body' => $value['Key_name'],
				'data-confirm' => "Drop \"{$value['Key_name']}\" index ?"
			]);
			$table->cells(array_values($value));
		}, $name);
		$table->fieldset('Delete', ...$fields);
	}

	function form_data(string $tablename, webapp_html $html = NULL):webapp_form
	{
		$form = new webapp_form($html ?? $this->webapp);

		foreach ($this->mysql->show('FULL FIELDS FROM ?a', $tablename) as $data)
		{
			$form->fieldset($data['Field']);
			preg_match('/(\w+(?:\sunsigned)?)(?:\(([^)]+)\))?/', $data['Type'], $pattern);
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
				'datetime' => ['datetime-local', []],
				'set' => ['checkbox', ['options' => array_combine($values = explode("','", substr($pattern[2], 1, -1)), $values), 'multiple' => NULL]],
				'enum' => ['radio', ['options' => array_combine($values = explode("','", substr($pattern[2], 1, -1)), $values)]],

				'text' => ['textarea', ['rows' => 8]],
				'json' => ['textarea', ['rows' => 21]],

				default => ['textarea', isset($pattern[2]) ? ['maxlength' => $pattern[2]] : []]
			};
			if ($data['Comment'])
			{
				$attr['placeholder'] = $data['Comment'];
			}
			if ($data['Default'])
			{
				$attr['value'] = $data['Default'];
			}
			$form->field($data['Field'], $type, $attr);
		}

		$form->fieldset();
		$form->button('Submit', 'submit');
		return $form;
	}

	function patch_data(string $name)
	{
		$this->json();
		$this->mysql->real_query('SELECT * FROM ?a ??', $this->webapp->url64_decode($name), $input = $this->input())
			&& $this->echo->redirect("?{$this->routename}/data,name:{$name},cond:" . $this->webapp->encrypt($input));
	}
	function delete_data(string $name)
	{
		$this->json();
		$datatable = $this->mysql->table($this->webapp->url64_decode($name));
		$datatable->delete('WHERE ?a=?s LIMIT 1', $datatable->primary, $this->input()) === 1 && $this->echo->refresh();
	}
	function get_data(string $name, string $cond = NULL, int $page = 1)
	{
		$datatable = $this->mysql->table($tablename = $this->webapp->url64_decode($name));
		is_string($cond = $this->webapp->decrypt($cond)) && $datatable($cond);
		$table = $this->main->table($datatable->paging($page)->result($fields), function($table, $value, $name, $primary)
		{
			$table->row();
			foreach ($value as $k => $v)
			{
				$k === $primary ? $table->cell()->details_popup($v, [
					['Update', '#'],
					['Delete', "?{$this->routename}/data,name:{$name}",
						'class' => 'danger',
						'onclick' => 'return $.action(this)',
						'data-body' => $v,
						'data-method' => 'delete',
						'data-confirm' => "Delete {$primary}:{$v} data ?"
					]
				]) : $table->cell($v);
			}

		}, $name, $datatable->primary);
		$table->fieldset(...$fields);
		$table->header($tablename);
		$table->paging(['page' => '']);
		$table->bar->append('a', ['Back table', 'href' => "?{$this->routename}/table,name:{$name}", 'class' => 'default']);
		$table->bar->append('a', ['Insert data', 'href' => '#', 'class' => 'primary',

			'onclick' => 'return $.action(this)',
			'data-prompt' => "#form_data"
		]);
		$table->bar->append('input', ['type' => 'search',
			'value' => $cond,
			'style' => 'flex-grow:1',
			'placeholder' => 'Where ... group by ... order by',
			'onkeydown' => 'event.keyCode===13&&$.action(this)',
			'data-action' => "?{$this->routename}/data,name:{$name}",
			'data-method' => 'patch'
		]);
		
		
		
	}

}