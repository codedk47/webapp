<?php
class webapp_ext_nfs_base extends webapp
{
	function __construct(array $config = [], webapp_io $io = new webapp_stdio)
	{
		parent::__construct($config, $io);
	}


	
}

[
	'file' => [
		'sort' => 0,
		'root' => 'd:/save'
	]
];