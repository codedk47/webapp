<?php
class webapp_nfs implements Stringable, Countable
{
	
	function __construct(public readonly webapp $webapp, public readonly int $sort)
	{

	}
	// function count():int
	// {
	// 	$this->webapp->mysql
	// }
	function storage(string $hash)
	{

	}
	function storage_uploadfile(int $index, string $rename = NULL):bool
	{
	}
	function storage_localfile(string $filename, string $rename = NULL):bool
	{
	}
	function storage_netfile(string $url, string $rename = NULL):bool
	{}
}
class webapp_ext_nfs_base extends webapp
{
	private array $nfs = [];
	function __construct(array $config = [], webapp_io $io = new webapp_stdio)
	{
		parent::__construct($config, $io);
	}

	function nfs(int $sort):webapp_nfs
	{
		return $this->nfs[$sort &= 0xff] ??= new webapp_nfs($this, $sort);
	}
	
}