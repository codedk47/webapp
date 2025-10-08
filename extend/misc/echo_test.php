<?php
class webapp_extend_misc_echo_test extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->nav([
			['Home', [
				['Back to current home', "?{$this->routename}"],
				['Back to webapp home', '?']
			]],
		]);
	}
	function post_home()
	{
	}
	function get_home()
	{
	}
}