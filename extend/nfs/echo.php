<?php
class webapp_extend_nfs_echo extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp, 'nfs', 'get_home');
		if ($webapp instanceof webapp_extend_nfs)
		{
			$this->nav([
				$this-> auth
					? ['Sign Out', "?{$this->routename}/admin"]
					: ['Administrator', "?{$this->routename}/admin"]
			]);
		}
		else
		{
			$webapp->break($this->ignore(...));
		}
	}

	function ignore()
	{
		$this->title('Ignore');
		$this->main->append('h2', 'WebApp Must extend NFS');
		return 500;
	}
	function authenticate(...$params):array
	{
		return $this->webapp->admin(...$params);
	}
	function get_asd()
	{

	}


	function get_home(int $sort = 0, int $page = 1, string $search = NULL)
	{
		$table = $this->main->table();
		$table->fieldset('Hash', 'Sort', 'Type', 'Insert Time', 'Update Time', 'Size', 'Views', 'Likes', 'Shares', 'Name', 'Node');
		$table->header('Explorer');
		//$table->bar->append('input', ['type' => 'number', 'min' => 0, 'mix' => 255, 'value' => $sort]);
		$table->bar->select(range(0, 255));
		$table->bar->append('input', ['type' => 'search', 'value' => $search]);
		$table->bar->append('a', ['Create folder here', 'href' => '#']);
		$table->bar->append('a', ['Upload file here', 'href' => '#', 'class' => 'default']);
	}
}