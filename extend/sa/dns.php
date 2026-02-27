<?php
interface sa_dns_client
{
	function __invoke(string $domain):array;
	function seta(string $domain, string $addr):bool;
	function setcname(string $domain, string $cname):bool;
}
class sa_dns_namecheap extends webapp_client_http implements sa_dns_client, IteratorAggregate
{
	const api = 'https://api.namecheap.com/';
	private readonly array $key;
	function __construct(string $user, string $key, string $name, string $ip)
	{
		parent::__construct(static::api, ['timeout' => 9, 'autoretry' => 1]);
		$this->key = [
			'ApiUser' => $user,
			'ApiKey' => $key,
			'UserName' => $name,
			'ClientIp' => $ip
		];
	}
	function api(string $command, array $params, &$xml = NULL):bool
	{
		return $this->request('GET', '/xml.response?' . http_build_query($this->key + [
			'Command' => "namecheap.{$command}"
		] + $params)) && is_a($xml = $this->content('application/xml'), 'webapp_xml');
	}
	function domain(string $domain):array
	{
		return explode('.', $domain, 3);
	}
	function __invoke(string $domain):array
	{
		[$hostname, $sld, $tld] = $this->domain($domain);
		var_dump( $this->api('domains.dns.getHosts', ['SLD' => $sld, 'TLD' => $tld], $xml) );
		$result = [];
		foreach ($xml->CommandResponse->DomainDNSGetHostsResult->host as $host)
		{
			if (str_starts_with($domain, $host['Name']))
			{
				return $host->getattr();
			}
		}
		return [];
	}
	function getIterator(string $type = 'ALL'):Traversable
	{
		$page = 0;
		$count = 0;
		$offset = 0;
		do
		{
			if ($this->api('domains.getList', [
				'ListType' => $type,	//Possible values are ALL, EXPIRING, or EXPIRED
				'Page' => ++$page,
				'PageSize' => 100,		//page. Minimum value is 10, and maximum value is 100.
				'SortBy' => 'CREATEDATE'], $xml) === FALSE) break;
			$count = intval($xml->CommandResponse->Paging->TotalItems);
			foreach ($xml->CommandResponse->DomainGetListResult->Domain as $domain)
			{
				++$offset;
				yield $domain->getattr();
			}
		} while ($offset < $count);
	}
	function set(string $domain):bool
	{
		return FALSE;
	}
	function seta(string $domain, string $addr):bool
	{
		[$hostname, $sld, $tld] = $this->domain($domain);
		return $this->api('domains.dns.setHosts', [
			'SLD' => $sld,
			'TLD' => $tld,
			'HostName1' => $hostname,
			'RecordType1' => 'A',
			'Address1' => $addr,
			'TTL1' => 1799], $xml) && (string)$xml['Status'] === 'OK';
	}
	function setcname(string $domain, string $cname):bool
	{
		[$hostname, $sld, $tld] = $this->domain($domain);
		return $this->api('domains.dns.setHosts', [
			'SLD' => $sld,
			'TLD' => $tld,
			'HostName1' => $hostname,
			'RecordType1' => 'CNAME',
			'Address1' => $cname,
			'TTL1' => 1799], $xml) && (string)$xml['Status'] === 'OK';
	}


	


}
class sa_dns_ganme extends webapp_client_http
{

}
class sa_dns
{
	private array $clients = [];
	private sa_dns_client $client;
	function __construct(private readonly array $accounts)
	{
		
	}
	function use(string $dns, string $user):?sa_dns_client
	{
		if (isset($this->accounts[$dns][$user]))
		{
			return $this->client = $clients[$dns][$user]
				??= new sa_dns_namecheap($user, ...$this->accounts[$dns][$user]);
		}
		return NULL;
	}
	function __invoke(string $domain, &$dns, &$user):array
	{
		foreach ($this as $dns => $user)
		{
			if ($site = ($this->client)($domain))
			{
				return $site;
			}
		}
		return [];
	}
	static function check(string $name, string $type = 'A', &$result = NULL):bool
	{
		static $dns = new webapp_client_http('https://dns.google/');
		$dns->request('GET', "/resolve?name={$name}&type={$type}");
		return $dns->status() === 200
			&& is_array($content = $dns->content())
			&& isset($content['Status'])
			&& $content['Status'] === 0
			&& is_array($result = $content['Answer'] ?? NULL);
	}
}