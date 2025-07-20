<?php
class webapp_extend_demo_admin extends webapp_echo_admin
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($this->init)
		{
		}
		if ($this->auth)
		{
		}
	}
	function get_home()
	{
		$this->main->append('h1', 'This admin masker page');
	}
}