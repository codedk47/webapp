<?php
class webapp_nfs implements IteratorAggregate
{
	
	// function __construct(public readonly webapp $webapp, public readonly int $sort)
	// {

	// }
	// function count():int
	// {
	// 	$this->webapp->mysql
	// }
	function getIterator():mysqli_result
	{
		//return $this->webapp->mysql->nfs('WHERE sort=?i', $this->sort)
	}
	function file():ArrayObject
	{

	}
	function storage(string|array $hash, string $name, array $json = NULL, int $options = JSON_UNESCAPED_UNICODE):ArrayObject
	{
		return new class extends ArrayObject
		{


		};
		$data = is_string($hash)
			? [
				'hash' => $hash,
				'sort' => $this->sort,
				'flag' => 0,
				'size' => 0,
				'name' => $name,
				'json' => $json === NULL ? NULL : json_encode($json, $options)
			]
			: [

			]
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

	function nfs(int $sort = 0):webapp_nfs
	{
		return $this->nfs[$sort &= 0xff] ??= new webapp_nfs($this, $sort);
	}
	function get_view(int $sort = 0)
	{
		$this->app('webapp_echo_xml');

		foreach ($this->mysql->videos->paging(1, 20) as $file)
		{
			$this->app->xml->append('file', [
				'hash' => $file['hash'],
				'size' => $file['size'],
				'name' => $file['name']
			])->cdata((string)$file['extdata']);
			
			
		}
	}
	//function delete_file()
}