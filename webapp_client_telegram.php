<?php
declare(strict_types=1);
/*
1.登录 Telegram 并转到 https://telegram.me/botfather
2.点击网页界面中的 Start 按钮或输入 /start
3.点击或输入 /newbot 并输入名称
4.为聊天机器人输入一个用户名，该名称应以“bot”结尾（例如 garthsweatherbot）
5.复制生成的访问令牌
https://api.telegram.org/bot{token}/{method}
https://core.telegram.org/bots/api#available-methods
*/
class webapp_telegram_message extends ArrayObject implements Stringable
{
	public readonly ?webapp $webapp;
	public readonly int $update_id;
	function __construct(array|webapp $context)
	{
		[$this->webapp, $this->update_id, $message] = is_array($context)
			? [NULL, ...array_values($context)]
			: [$context, ...array_values($context->request_content())];
		parent::__construct($message, ArrayObject::STD_PROP_LIST);
		if (isset($this['entities']))
		{
			foreach ($this['entities'] as $entitie)
			{
				if ($entitie['type'] === 'bot_command')
				{
					$this->send_message($this['chat']['id'], $this->text($entitie['offset']));
				}
			}
			// $prarms = explode(' ', $this['text']);
			// if (method_exists($this, $command = 'cmd_' . substr(array_shift($prarms), 1)))
			// {
			// 	$this->{$command}(...$prarms);
			// }
			// else
			// {
			// 	$this->send_message($this['chat']['id'], (string)$this);
			// }
		}
		else
		{
			$this($this['chat']['id'], $this['from']['id']);
		}
	}
	function text(int $offset = 0, int $length = NULL):string
	{
		return substr($this['text'], $offset, $length);
	}
	function send_message(int|string $chat_id, string $text):array
	{
		return $this->webapp->telegram->send_message($chat_id, $text);
	}
	function __invoke(int $chat_id, int $from_id)
	{
		$this->send_message($chat_id, (string)$this);
	}
	function __toString():string
	{
		return json_encode($this->getArrayCopy(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}
	function reply_message(string $text, bool $private = FALSE)
	{
		$this->send_message($this[$private ? 'from' : 'chat']['id'], $text);
	}
	function cmd_clear()
	{

	}
}
class webapp_client_telegram extends webapp_client_http
{
	function __construct(string $token)
	{
		parent::__construct("https://api.telegram.org/bot{$token}/");
		//$this->debug();
	}
	function api(string $method, ?array $data = NULL):bool|array
	{
		$this->goto("{$this->url}{$method}", $data ? ['method' => 'POST', 'data' => $data] : []);
		return $this->status($response) === 200
			&& is_array($content = $this->content())
			&& ($content['ok'] ?? FALSE) ? $content['result'] : FALSE;
	}
	#https://core.telegram.org/bots/api#getupdates
	function get_updates(int $offset = -1):array
	{
		return $this->api('getUpdates', $offset ? ['offset' => $offset] : NULL);
	}
	#https://core.telegram.org/bots/api#setwebhook
	function set_webhook(string $url = NULL):bool
	{
		return $this->api('setWebhook', is_string($url) ? ['url' => $url] : NULL);
	}
	#https://core.telegram.org/bots/api#getwebhookinfo
	function get_webhook_info():array
	{
		return $this->api('getWebhookInfo');
	}
	function delete_message(int|string $chat_id, int $message_id):bool
	{
		return $this->api('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
	}
	#https://core.telegram.org/bots/api
	function get_me():array
	{
		return $this->api('getMe');
	}
	#https://core.telegram.org/bots/api#sendmessage
	function send_message(int|string $chat_id, string $text):array
	{
		return $this->api('sendMessage', ['chat_id' => $chat_id, 'text' => $text]);
	}
}