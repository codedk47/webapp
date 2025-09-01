<?php
class webapp_account
{
	const tablename = 'account';
	private array $data;
	function __construct(public readonly webapp $webapp)
	{
		
	}
	function sign_in(string $id, $pwd):bool
	{
		//$this->webapp->mysql->
	}


	function create():bool
	{
		$id = $this->webapp->random_hash(TRUE);
		$time = $this->webapp->time();

		var_dump( $this->webapp->mysql->{static::tablename}->insert([
			'id' => $id,
			't0' => $time,
			't1' => $time,
			'ip' => $this->webapp->iphex('0.0.0.0')
		]) );

		return false;
	}



	static function createtable(webapp_mysql $mysql):bool
	{
		return $mysql->real_query(<<<'SQL'
		CREATE TABLE CREATE TABLE `account` (
			`id` char(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
		SQL, static::tablename);
	}





}