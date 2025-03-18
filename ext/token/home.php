<?php
require '../../webapp_stdio.php';
require 'bitcoin.php';
class webapp_router_home extends webapp_echo_admin
{
	public array $nav = [
		['Home', '?home/home'],
		['Bitcoin', '?home/bitcoin'],
		['Ethereum', '?home/ethereum'],
		['Logout', "javascript:location.reload(document.cookie='webapp=0');", 'style' => 'color:maroon']
	], $submenu = [
		'get_home' => [
			['Submenu-1', '?home/home'],
			['Submenu-2', '?home/home']
		]

	];
	function get_home()
	{
		$bitcoin = new webapp_token_bitcoin;
		//$ethereum = new webapp_token_ethereum;
		$data = [
			'Bitcoin' => $bitcoin->balance('1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa')
				+ $bitcoin->balance('3KsNB7QYeUdJCBV3TisXJxusvGyzqVnFnd'),
			'Ethereum' => NULL


		];
		$this->main->displayarray($data);
		

		

	}
	function get_bitcoin()
	{
		$this->main->append('iframe', [
			'width' => '100%',
			'style' => 'height:50rem',
			'src' => 'https://cn.investing.com/crypto/bitcoin'
		]);
	}
	function get_ethereum()
	{
		$this->main->append('iframe', [
			'width' => '100%',
			'style' => 'height:50rem',
			'src' => 'https://cn.investing.com/crypto/ethereum'
		]);
	}

}
new class extends webapp
{




};
