<?php
class webapp_router_news extends webapp_echo_html
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