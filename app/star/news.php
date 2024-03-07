<?php
class webapp_router_home extends webapp_echo_html
{
    function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
    }
    function get_home()
    {
        $this->main->append('h1', 'news');
    }
}