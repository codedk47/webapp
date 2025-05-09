<?php
class webapp_router_home extends webapp_echo_masker
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		var_dump('webapp_router_home');
	}
	
	function get_home()
	{
	}




}