<?php
class webapp_router_news extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);


		$this->xml->head->link['href'] = '/webapp/app/star/news.css?' . $this->webapp->random_hash(TRUE);
		$this->footer[0] = NULL;
	}


	function add_top_nav()
	{
		$nav = $this->xml->body->append('nav');

		$nav->append('a', ['href' => '#'])->svg(['fill' => 'white'])->icon('markdown', 24);
		$nav->append('input', ['type' => 'search']);
		$nav->append('a', ['href' => '#'])->svg(['fill' => 'white'])->icon('search', 24);
		$nav->append('a', ['href' => '#'])->svg(['fill' => 'white'])->icon('person', 24);

	}


	function add_div_videos()
	{
		
	}


	function get_home()
	{
		$this->add_top_nav();

		$this->main->append('h1', 'test');
	}



}