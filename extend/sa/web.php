<?php
interface sa_web_client
{
	function __invoke(string $domain):array;
	function create(string $domain, string|array $config):bool;
	function creates(array $domains):array;
	function delete(string $domain):bool;
	function deletes(array $domains):array;
	function cert_set(string $domain, string $cer, string $key):bool;
	function cert_get(string $domain, &$cer = NULL, &$key = NULL):bool;
}
class sa_web_bt extends webapp_client_http implements sa_web_client, IteratorAggregate
{
	function __construct(private readonly string $api, private readonly string $key)
	{
		parent::__construct($api, ['timeout' => 60, 'autoretry' => 2]);
	}
	final function api($path, array $body = [], &$message = NULL):bool
	{
		$time = time();
		return $this->request('POST', "/v2{$path}", [
				'request_token'	=> md5($time . md5($this->key)),
				'request_time' => $time
			] + $body, 'application/x-www-form-urlencoded')
		&& is_array($content = $this->content())
		&& is_array($message = $content['message'] ?? $content)
		&& isset($content['status'])
		&& $content['status'] === 0;
	}
	// private function format(array $data)
	// {
	// 	return [
	// 		'id' => $data['id'],
	// 		'domain' => $data['name'],
	// 		'root' => $data['path']
	// 	];
	// }
	function __invoke(string $domain):array
	{
		return $this->api('/data?action=getData',
			['table' => 'sites', 'limit' => 1, 'search' => $domain], $message)
			&& isset($message['data'][0]['name'])
			&& $message['data'][0]['name'] === $domain ? $message['data'][0] : [];
	}
	function getIterator():Traversable
	{
		for ($i = 1; $this->api('/data?action=getData',
			['table' => 'sites', 'limit' => 100, 'order' => 'addtime asc', 'p' => $i], $message)
			&& $message['data']; ++$i) {
			foreach ($message['data'] as $data)
			{
				yield $data;
			}
		}
	}
	function create(string $domain, string|array $config):bool
	{
		return $this->api('/site?action=AddSite', is_string($config) ? [
			'webname' => sprintf('{"domain":"%s","domainlist":[],"count":0}', $domain),
			'type' => 'PHP',
			'port' => 80,
			'ps' => '',
			'path' => $config,
			'type_id' => 0,
			'version' => '00',
			'ftp' => 'false',
			'sql' => 'false',
			'set_ssl' => 0,
			'is_create_default_file' => 'false'
		] : $config);
	}
	function creates(array $domains):array
	{
		$success = [];
		foreach ($domains as $domain => $value)
		{
			$this->create($domain, $value) && $success[] = $domain;
		}
		return $success;
	}
	function delete(string $domain):bool
	{
		return count($site = $this($domain))
			&& $this->api('/site?action=DeleteSite', ['id' => $site['id'], 'webname' => $site['name']]);
	}
	function deletes(array $domains):array
	{
		return array_filter($domains, $this->delete(...));
	}
	function cert_set(string $domain, string $cer, string $key):bool
	{
		return $this->api('/site?action=SetSSL', ['type' => 1, 'siteName' => $domain, 'csr' => $cer, 'key' => $key], $message)
			&& $this->api('/site?action=HttpToHttps', ['siteName' => $domain]);
	}
	function cert_get(string $domain, &$cer = NULL, &$key = NULL):bool
	{
		return $this->api('/site?action=GetSSL', ['siteName' => $domain], $message)
			&& is_string($cer = $message['csr'])
			&& is_string($key = $message['key']);
	}
}
class sa_web_aa extends sa_web_bt
{
	// function cert_deploy(string $hash, string $domain):bool
	// {
	// 	$content = http_build_query([
	// 		'request_time' => $time = time(),
	// 		'request_token'	=> md5($time . md5($this->key)),
	// 		'hash' => $hash,
	// 		'domains' => "[\"{$domain}\"]",
	// 		'append' => 1]);
	// 	$content = file_get_contents(rtrim($this->api, '/') . '/v2/ssl_domain?action=cert_deploy_sites',
	// 		FALSE, stream_context_create(['http' => [
	// 			'method' => 'POST',
	// 			'header' => join("\r\n", [
	// 				'Content-Type: application/x-www-form-urlencoded',
	// 				'Content-Length: ' . strlen($content)]),
	// 			'content' => $content,
	// 			'timeout' => 60
	// 		]]
	// 	));
	// 	return json_validate($content)
	// 		&& array_key_exists('status', $content = json_decode($content, TRUE))
	// 		&& $content['status'] === 0;
	// }
	// function cert_deploy(string $hash, string $domain):bool
	// {
	// 	return $this->api('/ssl_domain?action=cert_deploy_sites', [
	// 		'hash' => $message['hash'],
	// 		'domains' => "[\"{$domain}\"]",
	// 		'append' => 1]);
	// }
	// function cert_set(string $domain, string $cer, string $key):bool
	// {
	// 	return $this->api('/ssl_domain?action=upload_cert', ['key' => $key, 'cert' => $cer], $message)
	// 	 	&& $this->cert_deploy($message['hash'], $domain)
	// 	 	&& $this->api('/site?action=HttpToHttps', ['siteName' => $domain]);
	// }
}
class sa_web implements IteratorAggregate
{
	private array $clients = [];
	private sa_web_client $client;
	function __construct(private readonly array $accounts)
	{
	}
	function use(string $web, string $user):?sa_web_client
	{
		if (isset($this->accounts[$web][$user]))
		{
			return $this->client = $clients[$web][$user]
				??= new ("sa_web_{$web}")(...$this->accounts[$web][$user]);
		}
		return NULL;
	}
	function getIterator():Traversable
	{
		foreach ($this->accounts as $web => $users)
		{
			foreach ($users as $user => $params)
			{
				$this->use($web, $user);
				yield $web => $user;
			}
		}
	}
	function __invoke(string $domain, &$web = NULL, &$user = NULL):array
	{
		foreach ($this as $web => $user)
		{
			if ($data = ($this->client)($domain))
			{
				return $data;
			}
		}
		return [];
	}
}