<?php
class webapp_account
{
	const
	tablename = 'accounts',
	records = 'account_records',
	favorites = 'account_favorites';

	private array $data;
	function __construct(public readonly webapp $webapp)
	{

	}
	function sign_in(string $id, $pwd):bool
	{
		//$this->webapp->mysql->
	}


	function create(array $accountinfo = []):array
	{
		$accountinfo = [
			'id' => $this->webapp->random_hash(TRUE),
			't0' => $this->webapp->time(),
			't1' => 0,
			't2' => 0,
			'ip' => $this->webapp->iphex('0.0.0.0'),
			'balances' => '{}',
			'freezes' => '{}'
		] + $accountinfo;


		// $id = $this->webapp->random_hash(TRUE);
		// $time = $this->webapp->time();

		// var_dump( $this->webapp->mysql->{static::tablename}->insert([
		// 	'id' => $id,
		// 	't0' => $time,
		// 	't1' => $time,
		// 	'ip' => $this->webapp->iphex('0.0.0.0')
		// ]) );

		return $accountinfo;
	}



	static function createtable(webapp_mysql $mysql):bool
	{
		return $mysql->real_query(<<<'SQL'
		CREATE TABLE ?a (
			`id` char(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`t0` bigint unsigned NOT NULL COMMENT 'create time',
			`t1` bigint unsigned NOT NULL COMMENT 'expire time',
			`t2` bigint unsigned NOT NULL COMMENT 'signin time',
			`ip` char(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'signin hexip',
			`balances` json NOT NULL,
			`freezes` json NOT NULL,
			`extdata` json NOT NULL,
			`phone` bigint unsigned DEFAULT NULL,
			`email` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
			`uid` varchar(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
			`pwd` varchar(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
		SQL, static::tablename);
	}





}