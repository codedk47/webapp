<?php
class webapp_extend_demo_mask extends webapp_echo_masker
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		if ($this->init)
		{
		}
	}
	function get_home()
	{
		$this->main->append('h1', 'This is masker page');
	}
}