<?php
class webapp_extend_mysql_admin_echo extends webapp_echo_admin
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->stylesheet('/webapp/extend/mysql_admin/echo.css');
		
		if ($this->init === FALSE)
		{
			$this->script(['src' => '/webapp/extend/mysql_admin/echo.js']);
		}

	}
	function get_home()
	{

	}
}