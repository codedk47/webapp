<?php
require '../../webapp_stdio.php';
new class extends webapp
{
	function __construct()
	{
		parent::__construct();
		//if ($this->allow()) return;

		//empty($this->auth) && $this->break($this->get_home(...));


		// if (empty($this->auth))
		// {
		// 	//$this->echo_html('asd')->auth();
		// }

	}

	function authenticate()
	{
		return $this->admin(...func_get_args());
		//var_dump(func_get_args());
		return [];
	}



	function get_home()
	{
		$this->echo_html('asd');

		
	
	}
	function get_hhh()
	{}
};