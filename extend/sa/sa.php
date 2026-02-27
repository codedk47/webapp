<?php
require __DIR__ . '/dns.php';
require __DIR__ . '/cdn.php';
require __DIR__ . '/web.php';
trait sa
{
	public readonly ?sa_dns $dns;
	public readonly ?sa_cdn $cdn;
	public readonly ?sa_web $web;
	function sa(array $dns, array $cdn, array $web):static
	{
		$this->dns = $dns ? new sa_dns($dns) : NULL;
		$this->cdn = $cdn ? new sa_cdn($cdn) : NULL;
		$this->web = $web ? new sa_web($web) : NULL;
		return $this;
	}
	function dns(string $domain, &$dns = NULL, &$user = NULL):array
	{
		return ($this->dns)($domain, $dns, $user);
	}
	function cdn(string $domain, &$cdn = NULL, &$user = NULL):array
	{
		return ($this->cdn)($domain, $cdn, $user);
	}
	function web(string $domain, &$web = NULL, &$user = NULL):array
	{
		return ($this->web)($domain, $web, $user);
	}
	static function check(string $name, string $type = 'A', &$result = NULL):bool
	{
		return sa_dns::check($name, $type, $result);
	}
	static function checkcnames(array $domains):bool
	{
		$results = [];
		foreach ($domains as $domain)
		{
			if (static::check($domain, 'CNAME', $result))
			{
				$results[$domain] = $result;
			}
		}
		//print_r($results);
		return count($domains) === count($results);
	}
	static function cert_key():string
	{
		return file_get_contents(__DIR__ . '/sa.key');
	}
	static function cert_csr(string $domain):?string
	{
		return is_object($key = openssl_pkey_get_private(static::cert_key()))
			&& openssl_csr_export(openssl_csr_new(['commonName' => $domain, 'emailAddress' => 'local@host.app'], $key, [
				'config'			=> __DIR__ . '/openssl.cnf',
				'digest_alg'		=> 'sha256',
				'x509_extensions'	=> 'v3_ca',
				'req_extensions'	=> 'v3_req',
				'private_key_bits'	=> 2048,
				'private_key_type'	=> OPENSSL_KEYTYPE_RSA,
				'encrypt_key'		=> TRUE,
				'encrypt_key_cipher'=> OPENSSL_CIPHER_3DES
			]), $csr) ? $csr : NULL;
	}
}
// print_r((new class{use sa;})->sa(
// 	['namecheap' => ['user' => ['key' => 'KEY', 'name' => 'NAME', 'ip' => 'IP']]],
// 	['custom' => ['user' => ['API', 'USERNAME', 'PASSWORD']]],
// 	['bt' => ['user' => ['api' => 'API', 'key' => 'KEY']]]
// ));
// return;