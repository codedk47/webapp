<?php
interface sa_cdn_client
{
	function __invoke(string $domain, bool $raw = FALSE):array;
	function create(string $domain, array $addrs):bool;
	function creates(array $domains):array;
	function delete(string $domain):bool;
	function deletes(array $domains):array;
	function cert_request(string $domain):bool;
	function cert_info(string $domain, &$cer = NULL, &$key = NULL):array;
	function cert_config(string $domain, ?string $cer = NULL, ?string $key = NULL):bool;
	function cert_delete(string $domain):bool;
}
class sa_cdn_custom extends webapp_client_http implements sa_cdn_client, IteratorAggregate
{
	function __construct(string $api, string $uid, string $pwd)
	{
		parent::__construct($api, ['timeout' => 60, 'autoretry' => 2]);
		$this->api('POST', '/login', ['account' => $uid, 'password' => $pwd, 'code' => '', 'captcha' => ''], $content)
			&& $this->headers(['access-token' => $content['data']['access_token']]);
	}
	final function api(string $method, string $path, ?array $body = NULL, &$content = NULL):bool
	{
		return $this->request($method, $path, $body, 'application/json')
			&& is_array($content = $this->content())
			&& $content['code'] === 0;
	}
	private function format(array $data):array
	{
		return [
			'id' => $data['id'],
			'create_time' => strtotime($data['create_at'] ?? $data['create_at2']),
			'domain' => $data['domain'],
			'cname' => $data['cname_mode'] === 'package'
				? "{$data['up_cname_hostname']}.{$data['up_cname_domain']}"
				: "{$data['cname_hostname']}.{$data['cname_domain']}",
			'addrs' => json_validate($data['backend'])
				? array_column(json_decode($data['backend'], TRUE), 'addr')
				: []
		];
	}
	function __invoke(string $domain, bool $raw = FALSE):array
	{
		return ($this->api('GET', "/sites?page=1&limit=1&domains={$domain}", NULL, $content)
			&& isset($content['data'][0]['domain'])
			&& $content['data'][0]['domain'] === $domain)
				? ($raw ? $content['data'][0] : $this->format($content['data'][0])) : [];
	}
	function getIterator():Traversable
	{
		$page = 1;
		$count = 0;
		$offset = 0;
		do
		{
			if ($this->api('GET', "/sites?page={$page}&limit=100", NULL, $content))
			{
				$count = $content['count'];
				foreach ($content['data'] as $data)
				{
					++$offset;
					yield $this->format($data);
				}
				++$page;
			}
		} while ($offset < $count);
	}
	function create(string $domain, array $addrs):bool
	{
		return $this->api('POST', '/sites', [
			//'user_package' => '172',
			'domain' => $domain,
			'http_listen' => ['port' => '80'],
			'backend' => array_map(fn($addr) => ['addr' => $addr], $addrs),
			'backend_http_port' => '80'
		]);
	}
	function creates(array $domains):array
	{
		$success = [];
		foreach ($domains as $domain => $addrs)
		{
			$this->create($domain, $addrs) && $success[] = $domain;
		}
		return $success;
	}
	function delete(string $domain):bool
	{
		$data = $this($domain);
		if ($data && $this->api('PUT', '/sites', ['id' => $data['id'], 'enable' => 0]))
		{
			$time = time() + 60;
			while (($deleted = $this->api('DELETE', "/sites/{$data['id']}")) === FALSE && $time > time())
			{
				//必须等待停止后才能删除
				sleep(1);
			}
			return $deleted;
		}
		return FALSE;
	}
	function deletes(array $domains):array
	{
		return array_filter($domains, $this->delete(...));
	}



	





	//证书申请
	function cert_request(string $domain):bool
	{
		return $this->api('POST', '/certs', [
			'name' => "{$domain}证书",
			'type' => 'lets',
			'dnsapi' => NULL,
			'domain' => $domain
		]);
	}
	//证书信息
	function cert_info(string $domain, &$cer = NULL, &$key = NULL):array
	{
		$data = [];
		if ($this->api('GET', "/certs?page=1&limit=1&domain={$domain}", NULL, $content)
			&& is_array($content['data'])
			&& count($content['data'])
			//&& $content['data'][0]['expire_time2']
			&& $content['data'][0]['domain'] === $domain) {
			$data = $content['data'][0];
			if (func_num_args() > 1
				&& $this->api('GET', "/certs/{$data['id']}", NULL, $cert)
				&& is_array($cert['data'])) {
				$cer = $cert['data']['cert'];
				$key = $cert['data']['key'];
			}
		}
		return $data;
	}
	//证书配置
	function cert_config(string $domain, ?string $cer = NULL, ?string $key = NULL):bool
	{
		return count($cert = $this->cert_info($domain))
			&& count($site = $this($domain))
			&& $this->api('PUT', "/sites/{$site['id']}", [
				'backend_protocol' => 'follow',
				'https_listen' => [
				'cert' => $cert['id'],
				'http2' => TRUE,
				'force_ssl_enable' => TRUE,
				'ssl_protocols' => 'TLSv1.2 TLSv1.3',
				'ssl_ciphers' => "ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384",
				'ssl_prefer_server_ciphers' => 'off']]);
	}
	//证书删除
	function cert_delete(string $domain):bool
	{
		$cert = $this->cert_info($domain);
		if ($cert && $this->api('PUT', '/certs', ['id' => $cert['id'], 'enable' => 0]))
		{
			$time = time() + 60;
			while (($deleted = $this->api('DELETE', "/certs/{$cert['id']}")) === FALSE && $time > time())
			{
				//必须等待停止后才能删除
				sleep(1);
			}
			return $deleted;
		}
		return FALSE;
	}
}
class sa_cdn_xcdn extends webapp_client_http implements sa_cdn_client, IteratorAggregate
{
	function __construct(string $uid, string $pwd)
	{
		parent::__construct('https://www.xcdn.info', ['timeout' => 60, 'autoretry' => 2]);
		$this->api('POST', '/api/auth/login', ['username' => $uid, 'password' => $pwd, 'code' => '', 'captcha' => TRUE], $content)
			&& $this->headers(['Authorization' => "Bearer {$content['data']['accessToken']}"]);
	}
	final function api(string $method, string $path, ?array $body = NULL, &$content = NULL):bool
	{
		return $this->request($method, $path, $body, 'application/json')
			&& is_array($content = $this->content())
			&& $content['code'] === 0;
	}
	private function format(array $data):array
	{
		return [
			'id' => $data['_id'],
			'create_time' => strtotime($data['createdAt']),
			'domain' => $data['name'],
			'cname' => $data['cname'],
			'addrs' => $data['origins']
		];
	}
	function __invoke(string $domain, bool $raw = FALSE):array
	{
		return $this->api('GET', "/api/config/domain/list?page=1&pageSize=1&name={$domain}", NULL, $content)
			&& isset($content['data']['list'][0]['name'])
			&& $content['data']['list'][0]['name'] === $domain
				? ($raw ? $content['data']['list'][0] : $this->format($content['data']['list'][0])) : [];
	}
	function getIterator():Traversable
	{
		$page = 1;
		$count = 0;
		$offset = 0;
		do
		{
			if ($this->api('GET', "/api/config/domain/list?page={$page}&pageSize=100", NULL, $content)
				&& isset($content['data'])) {
				$count = $content['data']['totalRows'];
				foreach ($content['data']['list'] as $data)
				{
					++$offset;
					yield $this->format($data);
				}
				++$page;
			}
		} while ($offset < $count);
	}
	function create(string $domain, array $addrs):bool
	{
		return $this->api('POST', '/api/config/domain', [
			'area' => 'mainland',
			'name' => $domain,
			'Origins' => $addrs,
			'OriginPullProtocol' => 'follow',
			'ServerName' => $domain,
			'OriginType' => 'ip',
			'ServiceType' => 'media'
		], $content) && isset($content['data']['id']);
	}
	function creates(array $domains):array
	{
		return [];
	}
	function delete(string $domain):bool
	{
		return count($data = $this($domain))
			&& $this->api('DELETE', "/api/config/domain/{$data['id']}");
	}
	function deletes(array $domains):array
	{
		return [];
	}
	function cert_request(string $domain):bool
	{
		return count($data = $this($domain))
			&& $this->api('GET', "/api/config/domain/{$data['id']}/cert/validate-alias", [], $content)
			&& isset($content['data']['ok'])
			&& $content['data']['ok'];
	}
	function cert_info(string $domain, &$cer = NULL, &$key = NULL):array
	{
		if (count($data = $this($domain))
			&& $this->api('POST', "/api/config/domain/{$data['id']}/cert/issue-acmesh", [], $content)
			&& is_array($content['data'])) {
			$cer = $content['data']['certificate'] ?? NULL;
			$key = $content['data']['privateKey'] ?? NULL;
			return $content['data'];
		}
		return [];
	}
	function cert_config(string $domain, ?string $cer = NULL, ?string $key = NULL):bool
	{
		return count($data = $this($domain))
			&& $this->api('POST', "/api/config/domain/{$data['id']}/updateConfig", [
				'route' => 'Https',
				'value' => json_encode(['update' => [
					'Switch' => 'on',
					'CertInfo' => ['Certificate' => $cer, 'PrivateKey' => $key]
				]])
			], $content) && isset($content['data']['result']['RequestId']);
	}
	function cert_delete(string $domain):bool{return FALSE;}
}
class sa_cdn implements IteratorAggregate
{
	private array $clients = [];
	private sa_cdn_client $client;
	function __construct(private readonly array $accounts)
	{
	}
	function use(string $cdn, string $user):?sa_cdn_client
    {
        if (isset($this->accounts[$cdn][$user]))
        {
            return $this->client = $clients[$cdn][$user]
                ??= new ("sa_cdn_{$cdn}")(...$this->accounts[$cdn][$user]);
        }
        return NULL;
    }
	function getIterator():Traversable
	{
		foreach ($this->accounts as $cdn => $users)
		{
			foreach ($users as $user => $params)
			{
				$this->use($cdn, $user);
				yield $cdn => $user;
			}
		}
	}
	function __invoke(string $domain, &$cdn, &$user):array
	{
		foreach ($this as $cdn => $user)
		{
			if ($data = ($this->client)($domain))
			{
				return $data;
			}
		}
		return [];
	}
}