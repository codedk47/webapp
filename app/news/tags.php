<?php
class tags extends ArrayObject
{
	function __construct(webapp_mysql $mysql, int $site = 0)
	{
		$tags = [];
		foreach ($mysql
			->tags($site ? 'ORDER BY level DESC' : 'WHERE level!=9 ORDER BY level ASC')
			->select('hash,CONCAT(name,",",alias) as ctx') as $tag) {
			$tags[$tag['hash']] = array_unique(explode(',', strtoupper($tag['ctx'])));
		}
		parent::__construct($tags, ArrayObject::STD_PROP_LIST);
	}
	function __invoke(string $title, array $tags = []):array
	{
		$content = strtoupper($title);
		foreach($this as $hash => $names)
		{
			foreach ($names as $name)
			{
				if (str_contains($content, $name))
				{
					$tags[] = $hash;
					break;
				}
			}
		}
		return array_unique($tags);
	}
	function collision():array
	{
		$data = [];
		$tags = array_map(fn($content) => join(',', $content), $this->getArrayCopy());
		foreach($this as $hash => $names)
		{
			foreach ($names as $name)
			{
				foreach ($tags as $find => $content)
				{
					if ($hash !== $find && str_contains($content, $name))
					{
						$data["{$hash}:{$name}"][$find] = $content;
					}
				}
			}
		}
		return $data;
	}
	function valid(string $tags):string
	{
		return $tags ? join(',', array_intersect(array_keys($this->getArrayCopy()), explode(',', $tags))) : '';
	}
	
}