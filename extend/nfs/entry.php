<?php
require __DIR__ . '/../webapp_stdio.php';
require __DIR__ . '/../webapp_nfas.php';
new class extends webapp_nfas
{
	function __construct()
	{
		if ($this->init_admin_sign_in()) return;
		if ($this->mysql->connect_errno
			|| $this->mysql->exists_table(static::tablename) === FALSE
			|| $this->init() === FALSE) {
			return $this->response_status(500);
		}
	}
	function get_home(string $hash = NULL, int $page = 1)
	{
		if ($node = $this->node($hash))
		{
			$this->app('webapp_echo_html')->title($node['name']);
			$ul = $this->app->main->append('ul');
			foreach ($this->nodeitem($node['hash'], $page) as $item)
			{
				$ul->append('li')->append('a', [$item['name'], 'href' => "?home/{$item['hash']}"]);
			}

			return;
		}
		return 404;
	}
	function get_node(string $hash = NULL, int $page = 1)
	{
		if ($node = $this->node($hash))
		{
			$this->app('webapp_echo_json', []);
			foreach ($this->iterator([$node], $this->nodeitem($node['hash'], $page)) as $item)
			{
				$this->app[] = [
					'hash' => $item['hash'],
					'size' => $item['size'],
					'time' => $item['time'],
					'type' => $item['type'],
					'name' => $item['name']
				];
			}
			return;
		}
		return 404;
	}
	function get_echo(string $hash)
	{
		
		print_r($this->file($hash));
	}
	function post_admin()
	{
		if ($admin = webapp_echo_html::form_sign_in($this))
		{
			var_dump($admin);
			// if ($this->admin($signature = $this->signature($admin['username'], $input['password'])))
			// 			{
			// 				$this->response_refresh(0);
			// 				$this->response_cookie($this['admin_cookie'], $this->app['signature'] = $signature);
			// 			}
			// 			else
			// 			{
			// 				$this->app['errors'][] = 'Sign in failed';
			// 			}
		}
		
	}
	function post_test()
	{
		//print_r($this->storage_localfile('C:\Users\mac\Desktop\sql.txt'));
		print_r( $this->storage_localfolder('C:/Users/mac/Desktop/test') );
		//print_r( $this->storage_uploadedfile('upfiles', 10) );
	}
	function get_test()
	{
		$this->app('webapp_echo_html')->title('uptest');
		$form = $this->app->main->form();

		$form->field('upfiles', 'file', ['multiple' => NULL]);

		$form->button('upload', 'submit');
	}
	function get_open(string $hash)
	{
		$this->echo($hash);
	}
};