<?php
declare(strict_types=1);
class webapp_xml extends SimpleXMLElement
{
	function webapp():?webapp
	{
		return $this->dom()->ownerDocument->webapp ?? NULL;
	}
	function dom():DOMNode
	{
		return dom_import_simplexml($this);
	}
	function clone(bool $deep = TRUE):DOMNode
	{
		return $this->dom()->cloneNode($deep);
	}
	function cdata(string $value):DOMNode
	{
		return $this->dom()->appendChild(new DOMCdataSection($value));
	}
	function comment(string $value):DOMNode
	{
		return $this->dom()->appendChild(new DOMComment($value));
	}
	function entity(string $name):DOMNode
	{
		return $this->dom()->appendChild(new DOMEntityReference($name));
	}
	function pi(string ...$values):DOMNode
	{
		return $this->dom()->appendChild(new DOMProcessingInstruction(...$values));
	}
	function text(string $data = ''):DOMNode
	{
		return $this->dom()->appendChild(new DOMText($data));
	}
	function xml(string $data):DOMNode
	{
		$dom = $this->dom();
		$xml = $dom->ownerDocument->createDocumentFragment();
		if ($xml->appendXML($data))
		{
			$dom->appendChild($xml);
		}
		return $xml;
	}
	function insert(DOMNode|string $element, ?string $position = NULL):DOMNode|static
	{
		$dom = $this->dom();
		$node = is_string($element) ? $dom->ownerDocument->createElement($element) : $element;
		match ($position)
		{
			//插入到当前节点之后
			'after' => $dom->parentNode->insertBefore($node, $dom->nextSibling),
			//插入到当前节点之前
			'before' => $dom->parentNode->insertBefore($node, $dom),
			//插入到当前节点下开头
			'first' => $dom->insertBefore($node, $dom->firstChild),
			//插入到当前节点下末尾
			default => $dom->appendChild($node)
		};
		return $node->nodeType === XML_ELEMENT_NODE ? static::from($node) : $node;
	}
	function iter(iterable $contents, Closure $iterator = NULL, ...$params):static
	{
		// $doc = $this->dom()->ownerDocument;
		// $iterator ? $doc->iter = [$iterator, $params] : [$iterator, $params] = $doc->iter;
		if ($iterator === NULL)
		{
			//神奇骚操作，未来某个PHP版本不会改了吧？
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];
			$backtrace['object'] instanceof Closure
				? [$iterator, $params] = [$backtrace['object'], array_slice($backtrace['args'], 2)]
				: $params = [$iterator = function(array $element, Closure $iterator):void
				{
					$node = $this->append(array_shift($element), $element);
					if (isset($element[0]) && is_iterable($element[0]))
					{
						$node->iter($element[0], $iterator, $iterator);
					}
				}];
		}
		foreach ($contents as $value)
		{
			$iterator->call($this, $value, ...$params);
		}
		return $this;
	}
	//获取属性
	function getattr(string $name = NULL):NULL|string|array
	{
		$attributes = ((array)$this->attributes())['@attributes'] ?? [];
		return is_string($name) ? $attributes[$name] ?? NULL : $attributes;
	}
	//设置属性
	function setattr(string|array $name, $value = NULL):static
	{
		$node = $this;
		foreach (is_string($name) ? [$name => $value] : $name as $name => $value)
		{
			if ((is_string($name) || $name === 0) && is_scalar($value))
			{
				$node[$name] = $value;
				continue;
			}
			if (is_string($name) && $value === NULL)
			{
				$dom ??= $node->dom();
				$dom->appendChild($dom->ownerDocument->createAttribute($name));
			}
		}
		return $node;
	}
	function append(string $name, NULL|string|array $contents = NULL):static
	{
		return is_array($contents)
			? $this->addChild($name)->setattr($contents)
			: $this->addChild($name, $contents);
	}
	function appends(string $name, iterable $contents, string $keyattr = NULL):static
	{
		if ($keyattr) foreach ($contents as $key => $content)
		{
			$this->append($name, is_array($content)
				? [$keyattr => $key] + $content
				: [$content, $keyattr => $key]);
		}
		else foreach ($contents as $content)
		{
			$this->append($name, $content);
		}
		return $this;
	}

	

	#https://www.w3.org/TR/2003/WD-DOM-Level-3-Core-20030226/DOM3-Core.html#core-ID-1950641247
	function parent():static
	{
		return ($dom = $this->dom())->parentNode ? static::from($dom->parentNode) : $this;
	}
	function remove():static
	{
		return static::from(($dom = $this->dom())->parentNode->removeChild($dom));
	}
	function copy(webapp_xml $node, ?string $position = NULL):static
	{
		//return static::from($this->dom()->appendChild($node->clone(TRUE)));
		return $this->insert($node->clone(TRUE), $position);
	}
	function clear():string
	{
		$text = (string)$this[0];
		$this[0] = NULL;
		return $text;
	}
	//以数组递归方式导入当前节点下所有内容
	function import(iterable $values):static
	{
		foreach ($values as $key => $value)
		{
			$node = $this->append($key);
			if (is_iterable($value))
			{
				$node->import($value);
			}
			else
			{
				$node->text($value);
			}
		}
		return $this;
	}
	//以数组递归方式导出当前节点下所有内容
	function export():array
	{
		$values = [];
		if (strlen($content = trim($this[0])))
		{
			$values[] = preg_replace('/\s+/', ' ', $content);
		}
		$key = 0;
		foreach ($this[0] as $name => $node)
		{
			if (isset($values[$name]))
			{
				if ($key === 0)
				{
					$values[$name] = [$values[$name]];
				}
				$value = &$values[$name];
				$name = ++$key;
			}
			else
			{
				$value = &$values;
			}
			if ($node->count())
			{
				$value[$name] = $node->export();
			}
			else
			{
				$value[$name] = (string)$node;
			}
		}
		return $values;
	}
	static function from(DOMNode $node):?static
	{
		return simplexml_import_dom($node, static::class);
	}
	static function escape(string $value):string
	{
		return count($values = array_filter(preg_split('/(\'|")/', $value, flags:PREG_SPLIT_DELIM_CAPTURE))) > 1
			? 'concat(' . join(',', array_map(fn($value) => $value === '\'' ? "\"{$value}\"" : "'{$value}'", $values)) . ')'
			: (str_contains($value, '"') ? "'{$value}'" : "\"{$value}\"");
	}
	static function charsafe(string $content):string
	{
		return preg_replace('/[\x00-\x08\x0b-\x0c\x0e-\x1f]/', '', $content);
	}
	// function query(string $selector):array
	// {
	// 	$query = ['descendant::*'];
	// 	while (preg_match('/^\s*(\w+|\*)?([\.\#]([\w\x{00c0}-\x{ffff}\-]+))?/u', $selector, $matches) && isset($matches[1]))
	// 	{
	// 		$selector = substr($selector, strlen($matches[0]));
	// 		if ($matches[1] && $matches[1] !== '*')
	// 		{
	// 			$query[] = '[translate(name(.),"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")="' . strtoupper($matches[1]) . '"]';
	// 		}
	// 		if (isset($matches[2]))
	// 		{
	// 			$query[] = $matches[2][0] === '#' ? '[@id="' . $matches[3] . '"]' : '[contains(concat(" ",normalize-space(@class)," "),"' . $matches[3] . '")]';
	// 		}
	// 		while (preg_match('/^\[\s*(\w+)\s*(?:(\!\=|\$\=|\*\=|\=|\^\=|\~\=|\|\=)\s*(\'|\")?([\w\x{00c0}-\x{ffff}\-]+)\3?\s*)?\]/u', $selector, $matches))
	// 		{
	// 			$selector = substr($selector, strlen($matches[0]));
	// 			if (isset($matches[2]))
	// 			{
	// 				switch ($matches[2])
	// 				{
	// 					case '!=':
	// 						$query[] = '[@' . $matches[1] . '!="' . $matches[4] . '"]';
	// 						break;
	// 					case '$=':
	// 						$query[] = '[@' . $matches[1] . '$="' . $matches[4] . '"]';
	// 						break;
	// 					case '*=':
	// 						$query[] = '[contains(@' . $matches[1] . ',"' . $matches[4] . '")]';
	// 						break;
	// 					case '=':
	// 						$query[] = '[@' . $matches[1] . '="' . $matches[4] . '"]';
	// 						break;
	// 					case '^=':
	// 						$query[] = '[starts-with(@' . $matches[1] . '," ' . $matches[4] . ' ")]';
	// 						break;
	// 					case '~=':
	// 						$query[] = '[contains(concat(" ",normalize-space(@' . $matches[1] . ')," "),"' . $matches[4] . '")]';
	// 						break;
	// 					case '|=':
	// 						$query[] = '[@' . $matches[1] . '="' . $matches[4] . '" or starts-with(@' . $matches[1] . ',"' . $matches[4] . '")]';
	// 				};
	// 				continue;
	// 			}
	// 			$query[] = '[@' . $matches[1] . ']';
	// 		}
	// 		if (preg_match('/^\s*(\+|\>|\~|\,)?/', $selector, $matches))
	// 		{
	// 			$selector = substr($selector, strlen($matches[0]));
	// 			if (isset($matches[1]))
	// 			{
	// 				switch ($matches[1])
	// 				{
	// 					case '+':
	// 						$query[] = '/following-sibling::*[1]';
	// 						break;
	// 					case '>':
	// 						$query[] = '/*';
	// 						break;
	// 					case '~':
	// 						$query[] = '/following-sibling::*';
	// 						break;
	// 					default:
	// 						$query[] = '|descendant::*';
	// 				};
	// 				continue;
	// 			}
	// 			if ($selector)
	// 			{
	// 				$query[] = '/descendant::*';
	// 				continue;
	// 			}
	// 		}
	// 		break;
	// 	}
	// 	return $this[0]->xpath(join($query));
	// }
	function svg(array $attributes = []):webapp_svg
	{
		return simplexml_import_dom($this->append('svg', $attributes), 'webapp_svg');
		//return simplexml_import_dom($this->setattr(['xmlns' => 'http://www.w3.org/2000/svg'] + $attributes), 'webapp_svg');
	}
}
class webapp_svg extends webapp_xml
{
	function qrcode(IteratorAggregate&Countable $draw, int $pixel = 4):static
	{
		$size = count($draw);
		$resize = $size * $pixel;
		$this->setattr(['width' => $resize, 'height' => $resize, 'viewBox' => "0 0 {$size} {$size}", 'fill' => 'black', 'shape-rendering' => 'crispEdges']);
		foreach ($draw as $x => $y)
		{
			$this->append('rect', ['x' => $x, 'y' => $y, 'width' => 1, 'height' => 1]);
		}
		return $this;
	}
	function path(string $d):static
	{
		return $this->append('path', ['d' => $d]);
	}
	function logo():static
	{
		$this->setattr(['viewBox' => '0 0 24 24']);
		$this->path('M12 2c-4.963 0-9 4.038-9 9v8h.051c.245 1.691 1.69 3 3.449 3 1.174 0 2.074-.417 2.672-1.174a3.99 3.99 0 0 0 5.668-.014c.601.762 1.504 1.188 2.66 1.188 1.93 0 3.5-1.57 3.5-3.5V11c0-4.962-4.037-9-9-9zm7 16.5c0 .827-.673 1.5-1.5 1.5-.449 0-1.5 0-1.5-2v-1h-2v1c0 1.103-.897 2-2 2s-2-.897-2-2v-1H8v1c0 1.845-.774 2-1.5 2-.827 0-1.5-.673-1.5-1.5V11c0-3.86 3.141-7 7-7s7 3.14 7 7v7.5z');
		$this->append('circle', ['cx' => 9, 'cy' => 10, 'r' => 2]);
		$this->append('circle', ['cx' => 15, 'cy' => 10, 'r' => 2]);
		return $this;
	}
	function icon()
	{
		return new class($this)
		{
			function __construct(private readonly webapp_svg $svg)
			{
				$svg->setattr(['width' => 16, 'height'=> 16, 'viewBox' => '0 0 16 16']);
			}
			function star():webapp_svg
			{
				$this->svg->path('M8 .25a.75.75 0 0 1 .673.418l1.882 3.815 4.21.612a.75.75 0 0 1 .416 1.279l-3.046 2.97.719 4.192a.751.751 0 0 1-1.088.791L8 12.347l-3.766 1.98a.75.75 0 0 1-1.088-.79l.72-4.194L.818 6.374a.75.75 0 0 1 .416-1.28l4.21-.611L7.327.668A.75.75 0 0 1 8 .25Zm0 2.445L6.615 5.5a.75.75 0 0 1-.564.41l-3.097.45 2.24 2.184a.75.75 0 0 1 .216.664l-.528 3.084 2.769-1.456a.75.75 0 0 1 .698 0l2.77 1.456-.53-3.084a.75.75 0 0 1 .216-.664l2.24-2.183-3.096-.45a.75.75 0 0 1-.564-.41L8 2.694Z');
				return $this->svg;
			}
			function tag():webapp_svg
			{
				$this->svg->path('M1 7.775V2.75C1 1.784 1.784 1 2.75 1h5.025c.464 0 .91.184 1.238.513l6.25 6.25a1.75 1.75 0 0 1 0 2.474l-5.026 5.026a1.75 1.75 0 0 1-2.474 0l-6.25-6.25A1.752 1.752 0 0 1 1 7.775Zm1.5 0c0 .066.026.13.073.177l6.25 6.25a.25.25 0 0 0 .354 0l5.025-5.025a.25.25 0 0 0 0-.354l-6.25-6.25a.25.25 0 0 0-.177-.073H2.75a.25.25 0 0 0-.25.25ZM6 5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z');
				return $this->svg;
			}
			function copy():webapp_svg
			{
				$this->svg->path('M0 6.75C0 5.784.784 5 1.75 5h1.5a.75.75 0 0 1 0 1.5h-1.5a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-1.5a.75.75 0 0 1 1.5 0v1.5A1.75 1.75 0 0 1 9.25 16h-7.5A1.75 1.75 0 0 1 0 14.25Z');
				$this->svg->path('M5 1.75C5 .784 5.784 0 6.75 0h7.5C15.216 0 16 .784 16 1.75v7.5A1.75 1.75 0 0 1 14.25 11h-7.5A1.75 1.75 0 0 1 5 9.25Zm1.75-.25a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-7.5a.25.25 0 0 0-.25-.25Z');
				return $this->svg;
			}
		};
	}
	static function base64(array $attributes = []):static
	{
		//I don’t know if it’s good to write like this, try to use it as little as possible
		return (new class('<svg/>') extends webapp_svg
		{
			function __toString():string
			{
				$document = $this->dom()->ownerDocument;
				return 'data:image/svg+xml;base64,' . base64_encode($document->saveXML($document->documentElement));
				//return 'data:image/svg+xml;base64,' . base64_encode($this->asXML());
			}
		})->setattr($attributes);
	}
}
class webapp_html extends webapp_xml
{
	function template(iterable $struct, array|string $attr = []):static
	{
		return $this->append('template')->iter($struct)->setattr(is_array($attr) ? $attr : ['id' => $attr]);
	}
	function progress(float $value = 0, float $max = 1):static
	{
		return $this->append('progress', ['value' => $value, 'max' => $max]);
	}
	function anchor(string $href)
	{}
	function fieldset(string $legend = NULL):static
	{
		//An optional <legend> element, followed by flow content.
		$node = $this->append('fieldset');
		if ($legend !== NULL)
		{
			$node->legend = $legend;
		}
		return $node;
	}
	function details(string $summary = NULL):static
	{
		//One <summary> element followed by flow content.
		$node = $this->append('details');
		if ($summary !== NULL)
		{
			$node->summary = $summary;
		}
		return $node;
	}
	// function meter(float $value, float $min = 0, float $max = 1, float $low = NULL, float $high = NULL, float $optimum = NULL):static
	// {
	// 	return $this->append('meter', [
	// 		'value' => $value, 'min' => $min, 'max' => $max,
	// 		'low' => $low ?? $min + ($max - $min) * 0.4,
	// 		'high' => $high ?? $min + ($max - $min) * 0.9,
	// 		'optimum' => $optimum ?? $min + ($max - $min) * 0.7
	// 	]);
	// }
	// function figure(string $src):static
	// {
	// 	$node = &$this[0]->figure[];
	// 	return $node;
	// }
	// function style(array $values, bool $append = FALSE)
	// {
	// 	array_reduce(array_keys($values), fn($carry, $item) => "{$carry}{$values[$item]};", '')
	// }
	function labelinput(string $name, string $type, int|float|string $value, int|float|string $comment):static
	{
		$node = $this->append('label');
		$node->append('input', ['type' => $type, 'name' => $name, 'value' => $value]);
		$node->text($comment);
		return $node;
	}
	
	
	function options(iterable $values):static
	{
		foreach ($values as $value => $content)
		{
			if (is_iterable($content))
			{
				$this->append('optgroup', ['label' => $value])->options($content);
				continue;
			}
			$this->append('option', [$content, 'value' => $value]);
		}
		return $this;
	}
	function select(iterable $options, bool $multiple = FALSE, ?string $name = NULL, ?string $placeholder = NULL):static
	{
		if ($name)
		{
			$node = ($root = $placeholder ? $this->details($placeholder) : $this)->append('ul');
			if ($multiple)
			{
				($placeholder ? $root : $node)->setattr(['data-multiple' => NULL]);
				$name .= '[]';
				$type = 'checkbox';
			}
			else
			{
				$type = 'radio';
			}
			foreach ($options as $value => $content)
			{
				$node->append('li')->labelinput($name, $type, $value, $content);
			}

			//$node->ulselect($name, $options, $multiple);
			//$node['class'] = 'webapp-button';
		}
		else
		{
			$node = $this->append('select');
			$node->options($options);
			//return $this->append('select')->options($options);
		}
		return $node;
	}
	function selected(...$values):static
	{
		if ($value = join(' or ', array_map(
			fn($value) => '@value=' . static::escape((string)$value),
			array_filter($values, is_scalar(...))))) {
			[$selected, $selector] = match ($this->getName())
			{
				'ul' => ['checked', 'li/label/input'],
				'details' => ['checked', 'ul/li/label/input'],
				default => ['selected', 'descendant::option']
			};
			//var_dump("{$selector}[{$value}]");
			foreach ($this->xpath("{$selector}[{$value}]") as $node)
			{
				$node->setattr([$selected => NULL]);
			}
		}
		return $this;
	}
	function selectable():array
	{
		return array_map(strval(...), $this->xpath(match ($this->getName())
		{
			'ul' => 'li/label/input/@value',
			'details' => 'ul/li/label/input/@value',
			default => '//option/@value'
		}));
	}
	// function section(string $title, int $level = 1):static
	// {
	// 	$node = &$this->section[];
	// 	$node->{'h' . max(1, min(6, $level))} = $title;
	// 	return $node;
	// }
	// function ulselect(string $name, iterable $options, bool $multiple = FALSE):static
	// {
	// 	$node = $this->append('ul', ['class' => 'webapp-select']);
	// 	if ($multiple)
	// 	{
	// 		$name .= '[]';
	// 		$type = 'checkbox';
	// 	}
	// 	else
	// 	{
	// 		$type = 'radio';
	// 	}
	// 	foreach ($options as $value => $comment)
	// 	{
	// 		$node->append('li')->labelinput($name, $type, $value, $comment);
	// 	}
		
		
	// 	return $node;
	// }

	// function detailed(string $name, iterable $options, bool $multiple = FALSE):static
	// {
	// 	$node = $this->details('');
	// 	$node->ulselect($name, $options, $multiple);
	// 	$node['class'] = 'webapp-button';
	// 	return $node;
	// }

	function atree(iterable $link, bool $fold = FALSE)
	{
		return $this->append('ul')->iter($link, function(array $link, bool $fold):void
		{
			$node = $this->append('li');
			if (is_iterable($link[1]))
			{
				if ($fold)
				{
					$node = $node->details($link[0]);
				}
				else
				{
					$node->append('span', $link[0]);
				}
				$node->append('ul')->iter($link[1]);
			}
			else
			{
				$link['href'] ??= $link[1];
				$node->append('a', $link);
			}
		}, $fold);
	}
	function cond(array $fields, ?string $action = NULL):static
	{
		$form = $this->form($action);


		$form->xml['class'] .= '-cond';

		$form->button('Remove')['onclick'] = 'this.parentElement.remove()';
		$form->field('F', 'select', ['option' => $fields]);//['onchange'] = 'this.nextElementSibling.nextElementSibling.placeholder=this.options[this.selectedIndex].dataset.comment||""';//this.nextElementSibling.nextElementSibling.placeholder="asd";
		$form->field('d', 'select', ['option' => [
			'eq' => '=',
			'ne' => '!=',
			'gt' => '>',
			'ge' => '>=',
			'lt' => '<',
			'le' => '<=',
			'lk' => '%',
			'nl' => '!%',
			'in' => '()',
			'ni' => '!()'
		]]);
		$form->field('cond', 'search');


		$form->fieldset()['class'] = 'merge';
		$form->button('Append')['onclick'] = 'this.parentElement.parentElement.appendChild(this.parentElement.previousElementSibling.cloneNode(true))';
		$form->button('Clear')['class'] = 'danger';
		$form->button('Submit', 'submit')['class'] = 'primary';
		return $form->xml;
	}
	function form(?string $action = NULL):webapp_form
	{
		return new webapp_form($this, $action);
	}
	function table(iterable $contents = [], Closure $output = NULL, mixed ...$params):webapp_table
	{
		return new webapp_table($this, $contents, $output, ...$params);
	}
}
class webapp_implementation extends DOMImplementation implements Stringable
{
	public readonly webapp $webapp;
	public readonly DOMDocument $document;
	public webapp_xml $xml;
	function __construct(string $type = 'html', string ...$params)
	{
		$this(($this->document = $this->createDocument(qualifiedName: $type, doctype: $type === 'html'
			|| $params ? $this->createDocumentType($type, ...$params) : NULL)) !== FALSE);
		if (isset($this->webapp))
		{
			$this->document->webapp = &$this->webapp;
			$this->document->encoding = $this->webapp['app_charset'];
		}
	}
	function __invoke(bool $loaded):bool
	{
		return $loaded && ($this->xml = (match ($this->document->doctype?->name)
		{
			'html' => 'webapp_html',
			'svg' => 'webapp_svg',
			default => 'webapp_xml'
		})::from($this->document)) !== NULL;
	}
	function __toString():string
	{
		return $this->document->doctype?->name === 'html'
			? $this->document->saveHTML($this->document)
			: $this->document->saveXML();
	}
	function loadXML(string $source, int $options = 0):bool
	{
		return $this($this->document->loadXML($source, $options));
	}
	function loadXMLFile(string $source, int $options = 0):bool
	{
		return $this($this->document->load($source, $options));
	}
	function loadHTML(string $source, int $options = 0):bool
	{
		return $this($this->document->loadHTML($source, $options | LIBXML_NOWARNING | LIBXML_NOERROR));
	}
	function loadHTMLFile(string $source, int $options = 0):bool
	{
		return $this($this->document->loadHTMLFile($source, $options | LIBXML_NOWARNING | LIBXML_NOERROR));
	}
	// function xpath(string $expression):array
	// {
	// 	return iterator_to_array((new DOMXPath($this->document))->evaluate($expression));
	// }
	// function evaluate(string $expression, DOMNode $contextnode = NULL)
	// {
	// 	return (new DOMXPath($this))->evaluate($expression, $contextnode);
	// }
	// function querySelectorAll(string $selectors):array
	// {
	// 	return $this->xml->query($selectors);
	// }
	// function querySelector(string $selectors):?DOMElement
	// {
	// 	return $this->querySelectorAll($selectors)[0] ?? NULL;
	// }
	// function fragment(string $data):DOMDocumentFragment
	// {
	// 	$fragment = $this->createDocumentFragment();
	// 	$fragment->appendXML($data);
	// 	return $fragment;
	// }
	// static function html(string $data):static
	// {
	// 	$document = new static;
	// 	$document->loadHTML($data);
	// 	return $document;
	// }
}
class webapp_form implements ArrayAccess
{
	public readonly bool $echo;
	public readonly ?webapp $webapp;
	public readonly webapp_html $xml, $captcha;
	public webapp_html $fieldset;
	private array $files = [], $fields = [], $format = [];
	function __construct(private readonly array|webapp|webapp_html $context, ?string $action = NULL)
	{
		[$this->webapp, $this->xml] = ($this->echo = $context instanceof webapp_html)
			? [$context->webapp(), $context->append('form', [
				'method' => 'post',
				'autocomplete' => 'off',
				//'onsubmit' => 'webapp.submit(this)',
				'class' => 'webapp',
				...is_string($action) ? ['action' => $action] : []])]
			: [$context instanceof webapp ? $context : NULL, new webapp_html('<form/>')];
		$this->xml['enctype'] = 'application/x-www-form-urlencoded';
		$this->fieldset();
	}
	function offsetExists(mixed $offset):bool
	{
		return array_key_exists($offset, $this->fields);
	}
	function offsetGet(mixed $offset):?webapp_html
	{
		return $this->fields[$offset] ?? NULL;
	}
	function offsetSet(mixed $offset, mixed $value):void
	{

	}
	function offsetUnset(mixed $offset):void
	{
		unset($this->fields[$offset]);
	}
	function field(string $name, string $type = 'hidden', array $attr = [], callable $format = NULL):webapp_html
	{
		$alias = preg_match('/^\w+/', $name, $pattern) ? $pattern[0] : count($this->files) + count($this->fields);
		if ($type === 'file')
		{
			$this->xml['enctype'] = 'multipart/form-data';
			return $this->files[$alias] = $this->fieldset->append('input', ['type' => 'file',
				'name' => array_key_exists('multiple', $attr) ? "{$alias}[]" : $alias] + $attr);
		}
		$this->format[$alias] = $format ?? fn($value) => $value;
		return $this->fields[$alias] = match ($type)
		{
			'radio',
			'checkbox' => $this->fieldset->select($attr['options'] ?? [], $type === 'checkbox', $name),
			'webapp-select' => $this->fieldset->select($attr['options'] ?? [],
				array_key_exists('data-multiple', $attr), $name, $attr['data-placeholder'] ?? NULL)->setattr($attr),
			'select' => $this->fieldset->select($attr['options'] ?? [])
				->setattr(['name' => array_key_exists('multiple', $attr) ? "{$alias}[]" : $alias] + $attr),
			'textarea' => $this->fieldset->append('textarea', ['name' => $alias] + $attr),
			default => $this->fieldset->append('input', ['type' => $type, 'name' => $alias] + $attr)
		};
	}
	function echo(array $data):static
	{
		if ($this->echo)
		{
			foreach ($this->fields as $field => $node)
			{
				if (array_key_exists($field, $data))
				{
					$value = $this->format[$field]($data[$field], FALSE);
					match ($node->getName())
					{
						'ul',
						'details',
						'select'=> $node->selected(...is_array($value) ? $value : [$value]),
						'textarea' => $node->text((string)$value),
						default => $node->setattr(['value' => $value])
					};
				}
			}
		}
		return $this;
	}
	function fetch(?array &$data, &$error = NULL, array $input = NULL):bool
	{
		do
		{
			if ($this->echo)
			{
				$field = '';
				break;
			}
			if (is_array($this->context))
			{
				$errors = [];
				$input ??= $this->context;
			}
			else
			{
				$errors = &($this->context)(new stdClass)->errors;
				$input ??= is_string($content = $this->context->request_maskdata())
					? json_decode($content, TRUE)
					: $this->context->request_content((string)$this->xml['enctype']);
				if (isset($this->captcha) && (isset($input['captcha_encrypt'], $input['captcha_decrypt'])
					&& is_string($input['captcha_encrypt']) && is_string($input['captcha_decrypt'])
					&& $this->context->captcha_verify($input['captcha_encrypt'], $input['captcha_decrypt'])) === FALSE) {
					$field = 'captcha';
					break;
				}
			}
			foreach ($this->fields as $field => $node)
			{
				switch ($type = $node->getName())
				{
					case 'ul':
					case 'details':
					case 'select':
						[$multiple, $required] = $type === 'select'
							? ['multiple', 'required']
							: ['data-multiple', 'data-required'];
						$value = array_key_exists($field, $input)
							? (isset($node[$multiple]) && is_array($input[$field])
								? array_filter($input[$field], is_scalar(...)) : $input[$field])
							: (isset($node[$multiple]) ? [] : '');
						if ((isset($node[$required]) && strlen($value) === 0) || (isset($node[$multiple])
							? (is_array($value) && count(array_diff($value, $node->selectable())) === 0)
							: (in_array($value, $node->selectable(), TRUE))) === FALSE) {

							break 3;
						};
						break;
					default:
						$value = array_key_exists($field, $input) && is_scalar($input[$field]) ? (string)$input[$field] : '';
						if ((isset($node['required']) && strlen($value) === 0)
							|| static::validate($node, $value) === FALSE) {
							break 3;
						}
				}
				$data[$field] = $this->format[$field]($value, TRUE);
			}
			return TRUE;
		} while (0);
		$errors[] = $error = "Form input[{$field}] invalid";
		return FALSE;
	}

	// function files(string $name):ArrayObject
	// {

	// }
	function fieldset(string $name = NULL):webapp_html
	{
		return $this->fieldset = $this->xml->fieldset($name);
	}
	// function legend(string $name):webapp_html
	// {
	// 	$this->fieldset->legend = $name;
	// 	return $this->fieldset->legend;
	// }
	function progress():webapp_html
	{
		return $this->fieldset->progress();
	}
	function button(string $name, string $type = 'button', array $attributes = []):webapp_html
	{
		return $this->fieldset->append('button', [$name, 'type' => $type] + $attributes);
	}
	function captcha(string $name):?webapp_html
	{
		if ($this->webapp && $this->webapp['captcha_length'])
		{
			$this->captcha = $this->fieldset($name);
			$this->field('captcha_encrypt');
			$this->field('captcha_decrypt', 'text', [
				'placeholder' => 'Type following captcha',
				'spellcheck' => 'false',
				'autocorrect' => 'off',
				'autocapitalize' => 'off',
				'onfocus' => 'this.select()',
				'style' => 'ime-mode:disabled',
				'required' => NULL
			]);
			if ($this->echo)
			{
				$this->fields['captcha_encrypt']['value'] = $this->webapp->captcha_random($this->webapp['captcha_length'], $this->webapp['captcha_expire']);
				$this->captcha->setattr('disabled');
				$this->fieldset()->append('img', [
					'onload' => '(function(i){i.value="";i.focus()}(this.parentNode.previousElementSibling.firstElementChild.nextElementSibling.nextElementSibling,this.parentNode.previousElementSibling.disabled=false))',
					'onclick' => '(function(i,f){if(!f.disabled){f.disabled=!!fetch("?captcha").then(r=>r.ok?r.text():Promise.reject()).then(r=>i.src=`?captcha/${f.firstElementChild.nextElementSibling.value=r}`,()=>i.onload())}}(this,this.parentNode.previousElementSibling))',
					'onerror' => 'this.onload()',
					'src' => "?captcha/{$this->fields['captcha_encrypt']['value']}",
					'width' => $this->webapp['captcha_params'][0],
					'height' => $this->webapp['captcha_params'][1],
					'style' => 'cursor:pointer']);
				$this->fieldset = $this->captcha;
			}
			unset($this->fields['captcha_encrypt'], $this->fields['captcha_decrypt']);
		}
		return $this->captcha ?? NULL;
	}


	function novalidate():static
	{
		$this->xml->setattr('novalidate');
		return $this;
	}
	private static function is_numeric(webapp_html $node, string $value):bool
	{
		if (is_numeric($value))
		{
			if (($f = floatval($value))
				&& isset($node['step'])
				&& preg_match('/^\d+(?:\.(\d+))?$/', (string)$node['step'], $pattern)
				&& floatval($pattern[0])) {
				if (isset($pattern[1]) && intval($pattern[1]))
				{
					$v = intval(1 . str_repeat('0', strlen($pattern[1])));
					if (strpos(strval($i = intval($f * $v)), '.') || $i % ($pattern[0] * $v))
					{
						return FALSE;
					}
				}
				else
				{
					if (strpos(strval($f), '.') || intval($f) % intval($pattern[0]))
					{
						return FALSE;
					}
				}
			}
			return (isset($node['max']) === FALSE || floatval($node['max']) >= $f)
				&& (isset($node['min']) === FALSE || floatval($node['min']) <= $f);
		}
		return FALSE;
	}
	static function validate(webapp_html $node, string $value):bool
	{
		return match (strtolower((string)$node['type']))
		{
			'color' 		=> preg_match('/^#[0-9a-f]{6}$/i', $value) === 1,
			'date'			=> preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2]\d|3[0-2])$/', $value) === 1,
			'datetime',
			'datetime-local'=> preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[1-2]\d|3[0-2])T(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
			'email'			=> preg_match('/^[\w\.]+@[0-9a-z]+(\.[0-9a-z])?/i', $value) === 1,
			'month'			=> preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $value) === 1,
			'number',
			'range'			=> self::is_numeric($node, $value),
			'time'			=> preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?:\:[0-5]\d)?$/', $value) === 1,
			'url'			=> preg_match('/^[a-z][a-z0-9]*\:\/\//i', $value) === 1,
			'week'			=> preg_match('/^\d{4}-W(?:0[1-9]|[1-4]\d|5[0-3])$/', $value) === 1,
			default			=> (isset($node['pattern']) === FALSE || preg_match("/^{$node['pattern']}$/", $value))
				&& (isset($node['maxlength']) === FALSE || intval($node['maxlength']) >= strlen($value))
				&& (isset($node['minlength']) === FALSE || intval($node['minlength']) <= strlen($value))
		};
	}
	// static function data(array|webapp_html $fields, array|webapp $contents):array
	// {
	// 	$form = new static($contents);
	// 	$form['name'] = ['www'=> 333];
	// 	$form['age'] = ['class'=> 'wa'];
	// 	// foreach ($node->xpath('//*[@name]') as $field)
	// 	// {
	// 	// 	//if ($field->getName())
	// 	// 	print_r($field->getName());
	// 	// }



	// 	return $form();
	// }
}
class webapp_table implements Countable
{
	public readonly array $paging;
	public readonly webapp_html $xml, $tbody;
	private array $rows = [];
	private array $column = [];

	function __construct(webapp_html $node, public readonly iterable $data = [], callable $echo = NULL, ...$params)
	{
		$this->paging = is_object($this->data)
			&& property_exists($this->data, 'paging')
			&& is_array($this->data->paging) ? $this->data->paging : [];
		$this->xml = $node->append('table', ['class' => 'webapp']);
		$this->tbody = $this->xml->append('tbody');
		if ($echo)
		{
			foreach ($data as $item)
			{
				$echo($this, $item, ...$params);
			}
		}
		else
		{
			foreach ($data as $item)
			{
				$this->echo($item);
			}
		}
	}
	function __get(string $name):?webapp_html
	{
		return match ($name)
		{
			'caption'	=> $this->caption = $this->xml->caption ?? $this->xml->insert('caption', 'first'),
			'colgroup'	=> $this->colgroup = $this->xml->colgroup ?? $this->caption->insert('colgroup', 'after'),
			'fieldset'	=> $this->fieldset = $this->tbody->insert('tr', 'first'),
			'thead'		=> $this->thead = $this->xml->thead ?? $this->tbody->insert('thead', 'before'),
			'tfoot'		=> $this->tfoot = $this->xml->tfoot ?? $this->tbody->insert('tfoot', 'after'),
			'header'	=> $this->header = $this->maxspan($this->thead->insert('tr', 'first')->append('td')),
			'bar'		=> $this->bar = $this->maxspan($this->thead->append('tr')->append('td'))
								->append('div')->setattr(['class' => 'webapp-bar merge']),
			default		=> NULL
		};
	}
	function count():int
	{
		return $this->paging['count'] ?? 0;
	}
	function row(int $index = NULL):webapp_html
	{
		return $this->row = array_key_exists($index, $this->rows) ? $this->rows[$index] : $this->rows[] = $this->tbody->append('tr');
	}
	function cell(NULL|string|array $value = NULL):webapp_html
	{
		return $this->row->append('td', is_string($value) ? htmlspecialchars($value) : $value);
	}
	function cells(iterable $values):webapp_html
	{
		foreach ($values as $value)
		{
			$this->cell((string)$value);
		}
		return $this->row;
	}
	function echo(array $values):webapp_html
	{
		$row = $this->row();
		foreach ($values as $value)
		{
			$this->cell(is_int($value) ? (string)$value : $value);
		}
		return $row;
	}




	// function cell(NULL|string|array $contents = NULL, string $method = 'setattr'):webapp_html
	// {
	// 	return is_iterable($contents)
	// 		? $this->row->append('td')->{$method}($contents)
	// 		: $this->row->append('td', $contents);
	// }
	// function cells(iterable $contents, ?string $keyattr = NULL):webapp_html
	// {
	// 	return $this->row->appends('td', $contents, $keyattr);
	// }
	
	function cond(array $fields):webapp_html
	{
		return $this->bar->details('Conditionals')->cond($fields);
	}
	function fieldset(string ...$fields):webapp_html
	{
		$this->fieldset['class'] = 'fieldset';
		foreach ($fields as $field)
		{
			$this->fieldset->td[] = $field;
		}
		return $this->fieldset;
	}
	function maxspan(webapp_html $cell):webapp_html
	{
		$colspan = 0;
		foreach ($this->tbody->tr->td ?? [] as $column)
		{
			$colspan += $column['colspan'] ?? 1;
		}
		if (in_array($cell, $this->column, TRUE) === FALSE)
		{
			$this->column[] = $cell;
		}
		if ($colspan > 1)
		{
			foreach ($this->column as $column)
			{
				$column['colspan'] = $colspan;
			}
		}
		return $cell;
	}
	function header(string $format, ...$value):webapp_html
	{
		return $this->header->setattr([sprintf($format, ...$value)]);
	}
	function button(string $name, array $attributes = []):webapp_html
	{
		return $this->bar->append('button', [$name, 'type' => 'button', ...$attributes]);
	}
	function search(array $attributes = []):webapp_html
	{
		return $this->bar->append('input', $attributes + ['type' => 'search', 'placeholder' => 'Type search keywords']);
	}
	function footer(string|array $context = NULL):webapp_html
	{
		return $this->maxspan($this->tfoot->append('tr')->append('td', $context));
	}
	function paging(string $url, int $max = 9):static
	{
		if ($this->paging && $this->paging['max'] > 1)
		{
			$node = $this->footer();
			if ($this->paging['max'] > $max)
			{
				$halved = intval($max * 0.5);
				$offset = min($this->paging['max'], max($this->paging['index'], $halved + 1) + $halved) - 1;
				$ranges = range(max(1, $offset - $halved * 2 + 1), $offset);
				$ranges[0] = 1;
				$ranges[] = $this->paging['max'];
				$node->append('a', ['Prev', 'href' => $url . ($this->paging['index'] - 1)]);
				foreach ($ranges as $index)
				{
					$curr = $node->append('a', [$index, 'href' => "{$url}{$index}"]);
					if ($index == $this->paging['index'])
					{
						$page = $curr;
					}
				}
				$node->append('a', ['Next', 'href' => $url . ($this->paging['index'] + 1)]);
				$node->append('input', ['type' => 'number', 'min' => 1, 'max' => $this->paging['max'], 'value' => $this->paging['index'], 'onkeypress' => 'event.keyCode===13&&location.assign(this.nextElementSibling.href+this.value)']);
				$node->append('a', ['Goto', 'href' => $url, 'onclick' => 'return !!location.assign(this.href+this.previousElementSibling.value)']);
			}
			else
			{
				for ($i = 1; $i <= $this->paging['max']; ++$i)
				{
					$curr = $node->append('a', [$i, 'href' => "{$url}{$i}"]);
					if ($i === $this->paging['index'])
					{
						$page = $curr;
					}
				}
			}
			if (isset($page))
			{
				$page->setattr('style', 'background:var(--webapp-anchor);color:white');
			}
		}
		return $this;
	}
	// function fileds():array
	// {
	// 	if ($this->data instanceof mysqli_result)
	// 	{
	// 		return array_column($this->data->fetch_fields(), 'name');
	// 	}
	// 	return match (TRUE)
	// 	{
	// 		$this->data instanceof mysqli_result => array_column($this->data->fetch_fields(), 'name'),
	// 		default => []
	// 	};
	// }
}