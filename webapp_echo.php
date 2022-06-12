<?php
declare(strict_types=1);
trait webapp_echo
{
	public readonly webapp $webapp;
	abstract function __construct(webapp $webapp);
	abstract function __toString():string;
	// function __get(string $name):mixed
	// {
	// 	return $this->{$name} = &$this->webapp->{$name};
	// }
	// function __call(string $name, array $params):mixed
	// {
	// 	return $this->webapp->{$name}(...$params);
	// }
}
// class webapp_echo_text implements Stringable
// {
// 	use webapp_echo;
// 	function __construct(public readonly webapp $webapp, private string $content = '')
// 	{
// 		$webapp->response_content_type("text/plain; charset={$webapp['app_charset']}");
// 	}
// 	function __toString():string
// 	{
// 		return $this->content;
// 	}
// }
class webapp_echo_xml extends webapp_implementation
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, string $root = 'webapp')
	{
		$webapp->response_content_type('application/xml');
		parent::__construct($root);
	}
}
class webapp_echo_svg extends webapp_implementation
{
	use webapp_echo;
	public readonly webapp_svg $svg;
	function __construct(public readonly webapp $webapp, array $attributes = [])
	{
		$webapp->response_content_type('image/svg+xml');
		parent::__construct('svg', '-//W3C//DTD SVG 1.1//EN', 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd');
		$this->xml->setattr(['xmlns' => 'http://www.w3.org/2000/svg'] + $attributes);
	}
	function __invoke(bool $loaded):bool
	{
		return parent::__invoke($loaded) && $this->svg = new webapp_svg($this->xml);
	}
}
class webapp_echo_html extends webapp_implementation
{
	use webapp_echo;
	public readonly webapp_html $header, $aside, $main, $footer;
	function __construct(public readonly webapp $webapp)
	{
		//https://validator.w3.org/nu/#textarea
		$webapp->response_content_type("text/html; charset={$webapp['app_charset']}");
		parent::__construct();
		$this->xml->setattr(['lang' => 'en'])->append('head');
		$this->meta(['charset' => $webapp['app_charset']]);
		$this->meta(['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1']);
		//$this->link(['rel' => 'manifest', 'href' => '?webmanifest']);
		$this->link(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => '?favicon']);
		$this->link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/res/ps/webapp.css', 'media' => 'all']);

		// $head->append('meta', ['charset' => $webapp['app_charset']]);
		// $head->append('meta', ['name' => 'viewport', 'content' => 'width=device-width,initial-scale=1']);
		// $head->append('link', ['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => '?favicon']);
		// $head->append('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/webapp/res/ps/webapp.css', 'media' => 'all']);
		
		//$head->append('script', ['type' => 'module', 'src' => '/webapp/res/js/webkit.js']);
		//$this->script(['src' => '/webapp/res/js/webapp.js']);
		//$this->script('import $ from "/webapp/res/js/webkit.js";');
		$node = $this->xml->append('body')->append('div', ['class' => 'webapp-grid']);
		[$this->header, $this->aside, $this->main, $this->footer] = [
			&$node->header, &$node->aside, &$node->main,
			$node->append('footer', $webapp['copy_webapp'])];
	}
	function meta(array $attributes):webapp_html
	{
		return $this->xml->head->append('meta', $attributes);
	}
	function link(array $attributes):webapp_html
	{
		return $this->xml->head->append('link', $attributes);
	}
	function script(array|string $context):void
	{
		is_array($context)
			? $this->xml->head->append('script', $context)
			: $this->xml->head->append('script')->cdata($context);
	}
	function title(string $title):void
	{
		$this->xml->head->title = $title;
	}
	// function addstyle(string $rule):DOMText
	// {
	// 	return ($this->style ??= $this->xml->head->append('style', ['media' => 'all']))->text($rule);
	// }
	function nav(array $link):webapp_html
	{
		$node = $this->header->append('nav', ['class' => 'webapp']);
		$node->atree($link, TRUE);
		return $node;
	}
	function search(?string $action = NULL):webapp_form
	{
		$form = $this->header->form($action);
		$form->xml['method'] = 'get';
		$form->field('search', 'search');
		$form->button('Search', 'submit');
		return $form;
	}


	static function form_sign_in(array|webapp|webapp_html $context, ?string $authurl = NULL):webapp_form
	{
		$form = new webapp_form($context, $authurl);
		$form->fieldset('Username');
		$form->field('username', 'text', ['placeholder' => 'Type username', 'required' => NULL, 'autofocus' => NULL]);
		$form->fieldset('Password');
		$form->field('password', 'password', ['placeholder' => 'Type password', 'required' => NULL]);
		$form->captcha('Captcha');
		$form->fieldset();
		$form->button('Sign In', 'submit');
		return $form;
	}
	static function frame(webapp_echo_html $context):webapp_html
	{
		if ($context instanceof webapp_echo_html)
		{
			unset($context->xml->head->link, $context->xml->body->div);
			$context->xml->body['style'] = $context->xml->html['style'] = 'height:100%;margin:0';
			$context = $context->xml->body;
		}
		
		$frame = $context->append('iframe', [
			'frameborder' => 0,
			'loading' => 'lazy',
			'width' => '100%',
			'height' => '100%',
			'src' => 'about:blank',
			'src' => '?a1',
			'data-src' => 'http://192.168.0.119/'
		]);
		return $frame;
	}
}
class webapp_echo_json extends ArrayObject implements Stringable
{
	use webapp_echo;
	function __construct(public readonly webapp $webapp, array|object $data = [])
	{
		$webapp->response_content_type("application/json; charset={$webapp['app_charset']}");
		parent::__construct($data, ArrayObject::STD_PROP_LIST);
	}
	function __toString():string
	{
		return json_encode($this->getArrayCopy(), JSON_UNESCAPED_UNICODE);
	}
}
/*
class webapp_echo_xls extends webapp_echo_xml
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		#$webapp->response_content_type('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$this->loadXML("<?xml version='1.0' encoding='{$webapp['app_charset']}'?><?mso-application progid='Excel.Sheet'?><Workbook/>");
		$this->xml->setattr([
			'xmlns' => 'urn:schemas-microsoft-com:office:spreadsheet',
			// 'xmlns:o' => 'urn:schemas-microsoft-com:office:office',
			'xmlns:x' => 'urn:schemas-microsoft-com:office:excel',
			'xmlns:ss' => 'urn:schemas-microsoft-com:office:spreadsheet',
			// 'xmlns:html' = 'http://www.w3.org/TR/REC-html40'
		]);
		$style = $this->xml->append('Styles')->append('Style', [
			// 'ss:Name' => 'Normal',
			'ss:ID' => 'sc0'
		]);
		$style->append('Alignment', [
			// 'ss:Horizontal' => 'Center',
			// 'ss:Horizontal' => 'Fill',
			'ss:Vertical' => 'Center'
		]);
		$borders = $style->append('Borders');
		$borders->append('Border', ['ss:Position' => 'Left', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Top', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Right', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$borders->append('Border', ['ss:Position' => 'Bottom', 'ss:LineStyle' => 'Continuous', 'ss:Weight' => 1]);
		$this->worksheet = $this->xml->append('Worksheet', ['ss:Name' => 'webapp']);
		$this->table = $this->worksheet->append('Table');
	}
	function appendrow(...$values):webapp_xml
	{
		$row = $this->table->append('Row');
		foreach ($values as $value)
		{
			$row->append('Cell', ['ss:StyleID' => 'sc0'])->append('Data', [$value, 'ss:Type' => 'String']);
		}
		return $row;
	}
	function import(iterable $data):static
	{
		foreach ($data as $values)
		{
			$this->appendrow(...$values);
		}
		return $this;
	}
}
*/