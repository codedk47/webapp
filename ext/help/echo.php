<?php
class webapp_echo_help extends webapp_echo_html
{
	function __construct(webapp $webapp)
	{
		parent::__construct($webapp);
		$this->title('Help');
		$this->nav([
			['WebApp', '?help/index'],
			['DOM', [
				['XML', '?help/index,class:webapp-xml'],
				['SVG', '?help/index,class:webapp-svg'],
				['HTML', '?help/index,class:webapp-html'],
				['Implementation', '?help/index,class:webapp-implementation'],
				['Form', '?help/index,class:webapp-form'],
				['Table', '?help/index,class:webapp-table'],

			]],
			['Echo', [
				['XML', '?help/index,class:webapp-echo-xml'],
				['SVG', '?help/index,class:webapp-echo-svg'],
				['JSON', '?help/index,class:webapp-echo-json'],
				['HTML', '?help/index,class:webapp-echo-html'],
				['Masker', '?help/index,class:webapp-echo-masker']
			]],
			['Mixed', [
				['Request Uploadedfile', '?help/index,class:webapp-request-uploadedfile'],
				['Client', '?help/index,class:webapp-client'],
				['MySQL', '?help/index,class:webapp-mysql'],
				['MySQL Table', '?help/index,class:webapp-mysql-table'],
				['Redis', '?help/index,class:webapp-redis'],
				['Redis Table', '?help/index,class:webapp-redis-table']
			]], ['Icons', '?help/icons']
		]);
	}
	function index(string $class = 'webapp', string $method = NULL)
	{
		$reflex = new ReflectionClass(strtr($class, '-', '_'));
		$code = file($reflex->getFileName());
		$this->aside->append('a', [$class, 'href' => "?help/index,class:{$class}"]);
		foreach ($reflex->getMethods() as $function)
		{
			if ($function->isInternal() === FALSE)
			{
				if ($function->name === $method)
				{
					$reflex = $function;
				}
				$this->aside->append('a', [$function->name,
					'href' => "?help/index,class:{$class},method:{$function->name}"]);
			}
		}
		$offset = $reflex->getStartLine() - 1;
		$code = join("\n", array_map(rtrim(...), array_slice($code, $offset, $reflex->getEndLine() - $offset)));
		$this->main->append('pre', ['style' => join(';', [
			'background: whitesmoke',
			'margin: 0',
			'padding: 1rem',
			'border: 1px solid black',
			'font: .8rem consolas, monospace'
		])])->cdata(highlight_string("<?php\n{$code}", TRUE));
	}
	function icons()
	{
		$this->title('Icons');
		$this->xml->head->append('style')->cdata(<<<'CSS'
main{
	display: flex;
	gap: .4rem;
	flex-wrap: wrap;
}
main>a{
	
	display: flex;
	align-items: center;
}
main>a:hover{
	background-color: whitesmoke;
}
main>a>svg{
	padding: .6rem;
}
main>a>span{
	padding-right: .6rem;
}
CSS);
		foreach (array_keys(webapp_svg::icons) as $name)
		{
			$anchor = $this->main->append('a', ['href' => 'javascript:;']);
			$anchor->svg()->icon($name);
			$anchor->append('span', $name);
		}
		$this->main['onclick'] = 'if(event.target instanceof HTMLSpanElement)navigator.clipboard.writeText(event.target.textContent)';
	}
}