<?php
require 'webapp.php';
if (PHP_SAPI === 'cli')
{
	final class webapp_stdio implements webapp_io
	{
		function request_ip():string
		{
			return '127.0.0.1';
		}
		function request_scheme():string
		{
			return 'php';
		}
		function request_method():string
		{
			return $_SERVER['argv'][1] ?? 'GET';
		}
		function request_entry():string
		{
			return $_SERVER['SCRIPT_NAME'];
		}
		function request_query():string
		{
			return substr($_SERVER['argv'][2] ?? '?', 1);
		}
		function request_header(string $name):?string
		{
			return $_SERVER[strtoupper($name)] ?? NULL;
		}
		function request_cookie(string $name):?string
		{
			return $_COOKIE[$name] ?? NULL;
		}
		function request_content():string
		{
			return file_get_contents('php://input');
		}
		function request_formdata():array
		{
			return [];
		}
		function request_uploadedfile():array
		{
			return [];
		}
		function response_sent():bool
		{
			return headers_sent();
		}
		function response_status(int $code):void
		{
			http_response_code($code);
		}
		function response_header(string $value):void
		{
			header($value);
		}
		function response_cookie(float|string ...$values):void
		{
			setcookie(...$values);
		}
		function response_content(string $data):bool
		{
			return file_put_contents('php://output', $data) !== FALSE;
		}
		function response_sendfile(string $filename):bool
		{
			return copy($filename, 'php://output');
		}
	}
}
else
{
	final class webapp_stdio implements webapp_io
	{
		function request_ip():string
		{
			return $_SERVER['REMOTE_ADDR'];
		}
		function request_scheme():string
		{
			return $_SERVER['REQUEST_SCHEME'];
		}
		function request_method():string
		{
			return $_SERVER['REQUEST_METHOD'];
		}
		function request_entry():string
		{
			return $_SERVER['SCRIPT_NAME'];
		}
		function request_query():string
		{
			return $_SERVER['QUERY_STRING'] ?? '';
		}
		function request_header(string $name):?string
		{
			return array_change_key_case(apache_request_headers(), CASE_UPPER)[$same = strtoupper($name)]
				?? $_SERVER[match($alias = strtr($same, '-', '_'))
				{
					'CONTENT_TYPE', 'CONTENT_LENGTH' => $alias,
					default => "HTTP_{$alias}"
				}] ?? ($same === 'AUTHORIZATION' ? match(TRUE)
				{
					array_key_exists('PHP_AUTH_DIGEST', $_SERVER)
						=> "Digest {$_SERVER['PHP_AUTH_DIGEST']}",
					isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])
						=> 'Basic ' . base64_encode("{$_SERVER['PHP_AUTH_USER']}:{$_SERVER['PHP_AUTH_PW']}"),
					default => $_SERVER['AUTHORIZATION'] ?? NULL
				} : NULL);
		}
		function request_cookie(string $name):?string
		{
			return $_COOKIE[$name] ?? NULL;
		}
		function request_content():string
		{
			return file_get_contents('php://input');
		}
		function request_formdata():array
		{
			if ($this->request_method() === 'POST')
			{
				return $_POST;
			}
			if (str_starts_with($this->request_header('Content-Type') ?? '', 'application/x-www-form-urlencoded'))
			{
				parse_str($this->request_content(), $data);
			}
			else
			{
				$data = [];
			}
			return $data;
		}
		function request_uploadedfile():array
		{
			$uploadedfiles = [];
			foreach ($_FILES as $name => $file)
			{
				$files = [];
				foreach ($file as $type => $info)
				{
					if (is_array($info))
					{
						foreach ($info as $index => $value)
						{
							$files[$index][$type] = $value;
						}
						continue;
					}
					$files[0][$type] = $info;
				}
				$uploadedfiles[$name] = [];
				foreach ($files as $file)
				{
					if ($file['error'] === UPLOAD_ERR_OK)
					{
						$uploadedfiles[$name][] = [
							'file' => $file['tmp_name'],
							'size' => $file['size'],
							'mime' => $file['type'],
							...is_int($pos = strrpos($basename = basename($file['name']), '.'))
								? ['type' => substr($basename, $pos + 1, 8), 'name' => substr($basename, 0, $pos)]
								: ['type' => '', 'name' => $basename]
						];
					}
				}
			}
			return $uploadedfiles;
		}
		function response_sent():bool
		{
			return headers_sent();
		}
		function response_status(int $code):void
		{
			http_response_code($code);
		}
		function response_header(string $value):void
		{
			header($value);
		}
		function response_cookie(float|string ...$values):void
		{
			setcookie(...$values);
		}
		function response_content(string $data):bool
		{
			return file_put_contents('php://output', $data) !== FALSE;
		}
		function response_sendfile(string $filename):bool
		{
			return PHP_SAPI === 'apache2handler'
				&& in_array('mod_xsendfile', apache_get_modules(), TRUE)
				? $this->response_header("X-Sendfile: {$filename}") === NULL
				: copy($filename, 'php://output');
		}
	}
}