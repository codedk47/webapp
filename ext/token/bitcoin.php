<?php
class webapp_token_bitcoin extends webapp_client_http
{
	function __construct()
	{
		parent::__construct('https://blockchain.info/');

	}
	//https://www.blockchain.com/explorer/api/blockchain_api
	function api(string $path):?array
	{
		$this->request('GET', $path);

		return $this->content();
	}
	function block(string $hash = '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f')
	{
		return $this->api("/rawblock/{$hash}");
	}

	function latestblock()
	{
		return $this->api('/latestblock');

	}

	function balance(string $address)
	{
		return $this->api("/balance?active={$address}");
	}

	function unconfirmed()
	{
		return $this->api('/unconfirmed-transactions?format=json');
	}
}
