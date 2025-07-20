<?php
class webapp_extend_demo_home extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Welcome');
	}
	function get_home()
	{
		$this->main->append('h1', 'Hello World!');
	}
}