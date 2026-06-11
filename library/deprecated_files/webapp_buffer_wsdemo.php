<?php
require 'webapp/library/deprecated_files/webapp_buffer.php';
webapp_buffer_websocket::server('tcp://0.0.0.0:80', [
	'webapp' => ['readable' => function()
	{
		if ((json_validate($content = $this->content())
			&& is_array($data = json_decode($content))
			&& isset($data['mode'])
			&& $data['mode'] === 'phone'
		) === FALSE) return FALSE;




		
		print_r($data);


		$redirect = match ($this->request_header('Host'))
		{
			default => 'https://baidu.com'
		};

		return $this->sendframe($redirect);
	}]
]);