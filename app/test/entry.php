<?php
require '../../webapp_stdio.php';

new class extends webapp
{
	function __construct()
	{
		parent::__construct();
	}



	function get_home()
	{
		$this->echo_json([2 => 3, 'jj' => '你好']);



		
	}
};