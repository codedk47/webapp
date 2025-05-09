<?php
require '../../webapp_stdio.php';
class webapp_router_mask extends webapp_echo_masker
{

	function get_home()
	{
		$this->main->append('h1', 'hi this masker masker!!');

		$this->main->append('hr');
		$this->main->append('a', ['test-1', 'href' => '?mask/test1']);
		$this->main->append('hr');
		$this->main->append('a', ['test-2', 'href' => '?mask/test2']);
		$this->aside->append('img', ['src' => 'http://ec2-13-113-139-96.ap-northeast-1.compute.amazonaws.com/photo#!', 'width' => 128, 'height' => 128]);
	}

	function get_test1()
	{
		$this->main->append('h1', 'hi this masker test 1');
		$this->main->append('hr');
		$this->main->append('a', ['test-2', 'href' => '?mask/test2']);
		$this->aside->append('img', ['src' => '/photo#!32323', 'width' => 128, 'height' => 128]);
	}

	function get_test2()
	{
		$this->main->append('h1', 'hi this masker test 2');
		$this->main->append('hr');
		$this->main->append('a', ['test-1', 'href' => '?mask/test1']);
		$this->aside->append('img', ['src' => '/photo#!123123', 'width' => 128, 'height' => 128]);
	}

};
new class extends webapp
{
	function __construct()
	{
		parent::__construct();



		//$this->maskfile('D:/wmhp/work/photo.jpg', 'D:/wmhp/work/photo.jpg.mask', $key, TRUE);

		//if ($this->allow()) return;

		//empty($this->auth) && $this->break($this->get_home(...));


		// if (empty($this->auth))
		// {
		// 	//$this->echo_html('asd')->auth();
		// }

	}

	// function authenticate()
	// {
	// 	return $this->admin(...func_get_args());
	// 	//var_dump(func_get_args());
	// 	return [];
	// }



	function get_home()
	{
		$this->echo_html('asd');

		
	
	}
	function get_hhh()
	{}
};