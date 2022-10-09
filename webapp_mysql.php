<?php
declare(strict_types=1);
class webapp_mysql extends mysqli implements IteratorAggregate
{
	public array $errors = [];
	function __construct(string $hostname = 'p:127.0.0.1:3306', string $username = 'root', string $password = NULL, string $database = NULL, private string $maptable = 'webapp_maptable_')
	{
		try
		{
			//ini_set('mysqli.reconnect', TRUE);
			mysqli_report(MYSQLI_REPORT_STRICT);
			parent::__construct();
			parent::options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
			//parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
			parent::real_connect($hostname, $username, $password, $database, flags: MYSQLI_CLIENT_FOUND_ROWS | MYSQLI_CLIENT_INTERACTIVE);
			//parent::ping();
		}
		catch (mysqli_sql_exception)
		{
			$this->errors[] = $this->connect_error;
		}
	}
	function __destruct()
	{
		//$this->close();
	}
	function __get(string $name):webapp_mysql_table
	{
		return $this->{$name} = class_exists($tablename = $this->maptable . $name, FALSE) ? new $tablename($this) : $this->table($name);
	}
	function __call(string $tablename, array $conditionals):webapp_mysql_table
	{
		return ($this->{$tablename})(...$conditionals);
	}
	function __invoke(...$commands):static
	{
		$this->real_query(...$commands);
		return $this;
	}
	function getIterator():mysqli_result
	{
		return $this->store_result();
	}
	function object(string $class = 'stdClass', array $constructor_args = []):object
	{
		return $this->use_result()->fetch_object($class, $constructor_args) ?? new $class;
	}
	function array(int $mode = MYSQLI_ASSOC):array
	{
		return $this->use_result()->fetch_array($mode) ?? [];
	}
	function fetch(?array &$data, int $mode = MYSQLI_ASSOC):bool
	{
		return boolval($data = $this->array($mode));
	}
	function value(int $index = 0):NULL|int|float|string
	{
		return $this->array(MYSQLI_NUM)[$index] ?? NULL;
	}
	function all(int $mode = MYSQLI_ASSOC):array
	{
		return $this->use_result()->fetch_all($mode);
	}
	function column(string $key, string $index = NULL):array
	{
		return array_column($this->all(), $key, $index);
	}
	private function quote(string $name):string
	{
		return '`' . addcslashes($name, '`') . '`';
	}
	private function escape(string $value):string
	{
		return '\'' . $this->real_escape_string($value) . '\'';
	}
	private function iterator(iterable $contents):string
	{
		$query = [];
		foreach ($contents as $key => $value)
		{
			$query[] = $this->quote($key) . '=' . match (get_debug_type($value))
			{
				'null' => 'NULL',
				'bool' => intval($value),
				'int', 'float' => $value,
				default => $this->escape($value)
			};
		}
		return join(',', $query);
	}
	function format(string $command, iterable|string|int|float|bool ...$values):string
	{
		$offset = 0;
		$buffer = [];
		$length = strlen($command);
		foreach ($values as $value)
		{
			if (($pos = strpos($command, '?', $offset)) !== FALSE)
			{
				$buffer[] = substr($command, $offset, $pos - $offset) . match ($command[$pos + 1])
				{
					'i' => intval($value),
					'f' => floatval($value),
					'a' => $this->quote((string)$value),
					's' => $this->escape((string)$value),
					'v' => $this->iterator($value),
					'?', 'A', 'S' => is_iterable($value) ? join(',', array_map([$this, $command[$pos + 1] === 'A' ? 'quote' : 'escape'],
						is_array($value) ? $value : iterator_to_array($value))) : (string)$value,
					default => substr($command, $pos, 2)
				};
				$offset = $pos + 2;
				continue;
			}
			break;
		}
		if ($offset < $length)
		{
			$buffer[] = substr($command, $offset);
		}
		return join($buffer);
		// if ($values)
		// {
		// 	$index = 0;
		// 	$offset = 0;
		// 	$buffer = [];
		// 	$length = strlen($query);
		// 	while (($pos = strpos($query, '?', $offset)) !== FALSE && $pos < $length && array_key_exists($index, $values))
		// 	{
		// 		$buffer[] = substr($query, $offset, $pos - $offset) . match ($query[$pos + 1])
		// 		{
		// 			'i' => intval($values[$index++]),
		// 			'f' => floatval($values[$index++]),
		// 			'a' => $this->quote($values[$index++]),
		// 			's' => $this->escape($values[$index++]),
		// 			'v' => $this->iterator($values[$index++]),
		// 			'?', 'A', 'S' => is_iterable($values[$index]) ? join(',', array_map([$this, $query[$pos + 1] === 'A' ? 'quote' : 'escape'],
		// 				is_array($values[$index]) ? $values[$index++] : iterator_to_array($values[$index++]))) : (string)$values[$index++],
		// 			default => substr($query, $pos, 2)
		// 		};
		// 		$offset = $pos + 2;
		// 	}
		// 	if ($offset < $length)
		// 	{
		// 		$buffer[] = substr($query, $offset);
		// 	}
		// 	return join($buffer);
		// }
		// return $query;
	}
	function real_query(string $command, ...$values):bool
	{
		if (parent::real_query($this->format($command, ...$values)))
		{
			return TRUE;
		}
		$this->errors[] = $this->error;
		return FALSE;
	}
	function kill(int $pid = NULL):bool
	{
		return parent::kill($pid ?? $this->thread_id);
	}
	function syncable():bool
	{
		return boolval($this('SELECT @@autocommit')->value());
	}
	function sync(callable $submit, ...$params):bool
	{
		return $this->autocommit(FALSE) && [($success = $submit(...$params) && $this->commit())
			|| $this->rollback(), $this->autocommit(TRUE)] ? $success : FALSE;
		// if ($this->autocommit(FALSE))
		// {
		// 	if ($submit->call($this, ...$params))
		// 	{
		// 		$this->commit();
		// 		$this->autocommit(TRUE);
		// 		return TRUE;
		// 	}
		// 	$this->rollback();
		// 	$this->autocommit(TRUE);
		// }
		// return FALSE;
	}
	function cond(array $fieldinfo, array $cond):ArrayObject
	{
		return new class($this, $fieldinfo, $cond) extends ArrayObject implements Stringable
		{
			const query = [
				'eq' => '?a=?s',
				'ne' => '?a!=?s',
				'gt' => '?a>?s',
				'ge' => '?a>=?s',
				'lt' => '?a<?s',
				'le' => '?a<=?s',
				'lk' => '?a LIKE ?s',
				'nl' => '?a NOT LIKE ?s',
				'in' => '?a IN(?S)',
				'ni' => '?a NOT IN(?S)'
			];
			private array $where = [], $merge = [], $append;
			function __construct(private webapp_mysql $mysql, array $fieldinfo, array $cond)
			{
				$this->append = $cond;
				parent::__construct($fieldinfo);
			}
			function __toString():string
			{
				$where = $this->where;
				$allow = (array)$this;
				foreach ($this->append as $fieldinfo => $value)
				{
					preg_match('/(\w+)\.(eq|ne|gt|ge|lt|le|lk|nl|in|ni)/', $fieldinfo, $required);
					if (array_key_exists($required[1], $allow))
					{
						$where[] = $value === NULL
							? $this->mysql->sprintf('?a ?? NULL', $required[1], $required[2] === 'ne' ? 'IS NOT' : 'IS')
							: $this->mysql->sprintf(self::query[$required[2]], $required[1], in_array($required[2], ['in', 'ni']) ? explode(',', $value) : $value);
					}
				}
				$cond = [];
				if ($where)
				{
					$cond[] = 'WHERE';
					$cond[] = join(' AND ', $where);
				}
				if ($this->merge)
				{
					$cond[] = join(' ', $this->merge);
				}
				return join(' ', $cond);
			}
			function __invoke(string $tablename):webapp_mysql_table
			{
				return $this->mysql->{$tablename}($this);
			}
			function __call(string $name, array $params)
			{
				if (count($params))
				{
					switch ($name)
					{
						case 'eq':
						case 'ne':
						case 'gt':
						case 'ge':
						case 'lt':
						case 'le':
						case 'lk':
						case 'nl':
						case 'in':
						case 'ni':
							return $this->append["{$params[0]}.{$name}"] ?? NULL;
						case 'where':
						case 'merge':
							$this->{$name}[] = $this->mysql->sprintf(...$params);
							return $this;
					}
				}
			}
		};
	}
	function result(&$fields = NULL, bool $detailed = FALSE):iterable
	{
		$result = $this->getIterator();
		$fields = $detailed ? $result->fetch_fields() : array_column($result->fetch_fields(), 'name');
		return $result;
	}
	function createdb(string $dbname):bool
	{
		return $this->real_query('CREATE DATABASE ?a', $dbname);
	}
	function createtab(string $tabname):bool
	{
		return $this->real_query('CREATE TABLE ?a(`hash` char(12) NOT NULL, PRIMARY KEY (`hash`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $tabname);
	}
	function table(string $name):webapp_mysql_table
	{
		return new class($this, $name) extends webapp_mysql_table
		{
			function __construct(webapp_mysql $mysql, string $name)
			{
				unset($this->primary);
				$this->tablename = $name;
				parent::__construct($mysql);
			}
		};
	}
	function datatypes():array
	{
		return $this('SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS GROUP BY DATA_TYPE ORDER BY DATA_TYPE ASC')->column('DATA_TYPE');
	}
	function show(mixed ...$commands):static
	{
		return $this('SHOW ' . $this->format(...$commands));
	}
	function status():array
	{
		return $this->show('status')->column('Value', 'Variable_name');
	}
	function tablestatus():static
	{
		return $this->show('TABLE STATUS');
	}
	function characterset():static
	{
		return $this->show('CHARACTER SET');
	}
	function collation():static
	{
		return $this->show('COLLATION');
	}
	function processlist():static
	{
		return $this->show('PROCESSLIST');
	}
	function exists_table(string $name):bool
	{
		foreach ($this->tablestatus() as $table)
		{
			if ($table['Name'] === $name)
			{
				return TRUE;
			}
		}
		return FALSE;
	}
}
abstract class webapp_mysql_table implements IteratorAggregate, Countable, Stringable
{
	public array $paging = [];
	private string $cond = '', $fields = '*';
	protected ?string $tablename, $primary;
	protected ?mysqli_result $lastresult;
	function __construct(protected readonly webapp_mysql $mysql)
	{
	}
	function __get(string $name):?string
	{
		return match ($name)
		{
			'tablename' => $this->tablename,
			'primary' => $this->primary =
				($this->mysql)('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'PRI')->array()['Key'] ??
				($this->mysql)('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'UNI')->value()['Key'] ?? NULL,
			'create' => ($this->mysql)('SHOW CREATE TABLE ?a', $this->tablename)->value(1),
			default => 1.1
		};
	}
	function __invoke(mixed ...$conditionals):static
	{
		if ($conditionals)
		{
			$this->cond = $this->mysql->format(...$conditionals);
		}
		return $this;
	}
	function __toString():string
	{
		$cond = $this->cond;
		$this->fields = '*';
		$this->cond = '';
		return strlen($cond) ? ' ' . $cond : $cond;
	}
	function count(string &$cond = NULL):int
	{
		$cond = $this->cond;
		return intval(($this->mysql)('SELECT SQL_NO_CACHE COUNT(1) FROM ?a?? LIMIT 1', $this->tablename, (string)$this)->value());
	}
	function getIterator():webapp_mysql
	{
		return ($this->mysql)('SELECT ?? FROM ?a??', $this->fields, $this->tablename, (string)$this);
	}
	function result(array|string $fields = '*'):mysqli_result
	{
		$result = $this->select($fields)->getIterator()->getIterator();
		$result->paging = $this->paging;
		return $result;
		// $result = $this->getIterator()->getIterator();
		// $fields = $detailed ? $result->fetch_fields() : array_column($result->fetch_fields(), 'name');
		// $result->paging = $this->paging;
		// return $result;
	}
	function object(string $class = 'stdClass', array $constructor_args = []):object
	{
		return $this->getIterator()->object($class, $constructor_args);
	}
	function array(int $mode = MYSQLI_ASSOC):array
	{
		return $this->getIterator()->array($mode);
	}
	function fetch(?array &$data, int $mode = MYSQLI_ASSOC):bool
	{
		return $this->getIterator()->fetch($data, $mode);
	}
	function value(int $index = 0):NULL|int|float|string
	{
		return $this->array(MYSQLI_NUM)[$index] ?? NULL;
	}
	function all(int $mode = MYSQLI_ASSOC):array
	{
		return $this->getIterator()->all($mode);
	}
	// function reduce(callable $callback, $initial = NULL)
	// {
	// 	foreach ($this as $value)
	// 	{
	// 		$initial = $callback($initial, $value);
	// 	}
	// 	return $initial;
	// }
	function column(string|int ...$keys):array
	{
		if (count($keys) < 3)
		{
			return array_column($this->select($keys)->all(), ...$keys);
		}
		$values = [];
		$index = end($keys);
		foreach ($this->select($keys) as $value)
		{
			$key = $value[$index];
			unset($value[$index]);
			$values[$key] = $value;
		}
		return $values;
	}



	// function &tablename():string
	// {
	// 	return $this->tablename;
	// }
	// function &primary():string
	// {
	// 	if (property_exists($this, 'primary') === FALSE)
	// 	{
	// 		$this->primary =
	// 			$this->mysql->row('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'PRI')['Field'] ??
	// 			$this->mysql->row('SHOW FIELDS FROM ?a WHERE ?a=?s', $this->tablename, 'Key', 'UNI')['Field'] ?? NULL;
	// 	}
	// 	return $this->primary;
	// }


	function append():int
	{
		$this->insert(...func_get_args());
		return $this->mysql->insert_id;
	}
	function insert(iterable|string $data):bool
	{
		return ($this->mysql)('INSERT INTO ?a SET ??', $this->tablename, $this->mysql->format(...is_iterable($data) ? ['?v', $data] : func_get_args()))->affected_rows === 1;
	}
	function delete(mixed ...$conditionals):int
	{
		return ($this->mysql)('DELETE FROM ?a??', $this->tablename, (string)($conditionals ? $this(...$conditionals) : $this))->affected_rows;
	}
	function update(iterable|string $data):int
	{
		return ($this->mysql)('UPDATE ?a SET ????', $this->tablename, $this->mysql->format(...is_iterable($data) ? ['?v', $data] : func_get_args()), (string)$this)->affected_rows;
	}
	function select(array|string $fields):static
	{
		$this->fields = $this->mysql->format('?A', $fields);
		return $this;
	}
	function paging(int $index, int $rows = 21):static
	{
		$fields = $this->fields;
		$this->paging['count'] = $this->count($this->paging['cond']);
		$this->paging['max'] = ceil($this->paging['count'] / $rows);
		$this->paging['index'] = max(1, min($index, $this->paging['max']));
		$this->paging['skip'] = ($this->paging['index'] - 1) * $rows;
		$this->paging['rows'] = $rows;
		$this->fields = $fields;
		return $this(join(' ', [$this->paging['cond'], 'LIMIT', "{$this->paging['skip']},{$rows}"]));
	}
	// function column(string ...$keys):array
	// {
	// 	return array_column($this->select($keys)->all, ...$keys);
	// }

	// function target($mixed = NULL):webapp_mysql_target
	// {
	// 	return new $this->targetname($this, $mixed);
	// }
	function dataincr(array $data, array $incr)
	{
		
	}
	function statmonth(string $y_m, string $group, string $day, array $fields, ...$extra)
	{
		$days = range(0, date('t', strtotime($y_m)));

		// $stat = [];
		// foreach ($days as $i)
		// {
		// 	$stat[] = "day{$i}";
		// 	foreach ([$fields] as $field)
		// 	{
		// 		$stat[] = "{$field}{$i}";
		// 	}
		// }
		// print_r($stat);
		// return;



		$data = [];
		$calc = [];
		$more = [];

		foreach ($days as $i)
		{
			$daydata = [$i
				? $this->mysql->format('COUNT(IF(??=?i,1,NULL))AS?a', $day, $i, "\${$i}")
				: $this->mysql->format('COUNT(1)AS?a', "\${$i}")];
			$daycalc = [$this->mysql->format('IFNULL(SUM(?a),0)AS?a', "\${$i}", "\${$i}")];
			$daymore = [$this->mysql->format('SUM(?a)AS?a', "\${$i}", "\${$i}")];

			
			foreach ($fields as $index => $field)
			{
				
				$daydata[] = $this->mysql->format('??AS?a', str_replace(['{day}'], [$i], $field), "\${$index}\${$i}");
				$daycalc[] = $this->mysql->format('IFNULL(SUM(?a),0)AS?a', "\${$index}\${$i}", "\${$index}\${$i}");
				$daymore[] = $this->mysql->format('SUM(?a)AS?a', "\${$index}\${$i}", "\${$index}\${$i}");
			}
			
			$data[] = join(',', $daydata);
			$calc[] = join(',', $daycalc);
			$more[] = join(',', $daymore);
		}

		$s = $this->mysql->format(join(' ', ['WITH t AS (SELECT ?a,?? FROM ?a?? GROUP BY ?a)',
				'SELECT NULL as ?a,?? FROM t',
				'UNION ALL',
				'SELECT ?a,?? FROM t GROUP BY ?a??'
			]),
			$group, join(",\n", $data), $this->tablename, (string)$this, $group,
			$group, join(",\n", $calc),
			$group, join(",\n", $more), $group,
			$extra ? ' '. $this->mysql->format(...$extra) : '');
		//var_dump($s);
		return ($this->mysql)($s);
		

		
		// PRINT_R(($this->mysql)($s)->all() );
		
	
		// print_r($s);
	}
	function fieldinfo()
	{
		return ($this->mysql)('SHOW FULL COLUMNS FROM ?a', $this->tablename);
	}
	function rename(string $name):bool
	{
		if ($this->mysql->real_query('ALTER TABLE ?a RENAME TO ?a', $this->tablename, $name))
		{
			$this->tablename = $name;
			return TRUE;
		}
		return FALSE;
	}
	function truncate():bool
	{
		return $this->mysql->real_query('TRUNCATE TABLE ?a', $this->tablename);
	}
	function drop():bool
	{
		return $this->mysql->real_query('DROP TABLE ?a', $this->tablename);
	}
	//field
	// function fieldformat(string $fieldname, string $type, ):string
	// {

	// }
	// function fieldappend(string $fieldname):bool
	// {
	// 	return $this->mysql->real_query('DROP TABLE ?a', $this->tablename);
	// }
	// function fieldchange(string $fieldname)
	// {
	// 	return $this->mysql->real_query('ALTER TABLE ?a CHANGE COLUMN ?a old json NULL', $this->tablename, $fieldname);
	// }
	// function fieldremove(string $fieldname):bool
	// {
	// 	return $this->mysql->real_query('ALTER TABLE ?a DROP COLUMN ?a', $this->tablename, $fieldname);	
	// }
}