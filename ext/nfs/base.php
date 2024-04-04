<?php
class webapp_ext_nfs_base extends webapp
{
	const
	folder = 1 << 7,
	hidden = 1,
	readable = 1 << 6,
	modifiable = 1 << 5;
	const tablename = 'nfas', rootdir = 'D:/nfas/';
	function init():bool
	{
		return (is_dir(static::rootdir) || mkdir(static::rootdir, recursive: TRUE)) && $this->mysql->real_query(join(',', [
			'CREATE TABLE IF NOT EXISTS ?a(`hash` char(12) NOT NULL',
			'`hits` bigint unsigned NOT NULL',
			'`size` bigint unsigned NOT NULL',
			'`time` int unsigned NOT NULL',
			'`flag` tinyint unsigned NOT NULL',
			'`node` char(12) DEFAULT NULL',
			'`type` varchar(8) DEFAULT NULL',
			'`name` varchar(255) NOT NULL',
			'`json` json',
			'PRIMARY KEY (`hash`)',
			'KEY `node` (`node`)',
			'KEY `type` (`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4']), static::tablename);
	}
	function nfas(...$conditionals):webapp_mysql_table
	{
		return $conditionals
			? $this->mysql->{static::tablename}(...$conditionals)
			: $this->mysql->{static::tablename};
	}
	function node(?string $hash):array
	{
		return $hash === NULL
			? ['hash' => NULL, 'hits' => 0, 'size' => 0, 'time' => 0, 'flag' => 0,
				'node' => NULL, 'type' => NULL, 'name' => 'root', 'json' => NULL]
			: (preg_match('/^W[0-9A-V]{11}$/', $hash)
				? $this->nfas('WHERE type IS NULL AND hash=?s LIMIT 1', $hash)->array() : []);
	}
	function file(string $hash):array
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash)
			&& ($file = $this->nfas('WHERE type IS NOT NULL AND hash=?s LIMIT 1', $hash)->array())
			? ['file' => $this->filename($file['hash'])] + $file : [];
	}
	function filename(string $hash):string
	{
		return static::rootdir . chunk_split(substr($hash, 0, 6), 2, '/') . substr($hash, -6);
	}
	function lost(string $hash):void
	{
		$this->nfas->delete('WHERE hash=?s LIMIT 1', $hash);
	}
	function assign(string $hash, int $size, string $type, string $name, string $node = NULL):?string
	{
		return preg_match('/^[0-9A-V]{12}$/', $hash)
			&& $this->nfas->insert([
				'hash' => $hash,
				'hits' => 0,
				'size' => $size,
				'time' => $this->time,
				'flag' => 0,
				'node' => $node,
				'type' => strtolower($type),
				'name' => $name])
			&& ((is_dir($dirname = dirname($filename = $this->filename($hash)))
				|| mkdir($dirname, recursive: TRUE)) || $this->lost($hash)) ? $filename : NULL;
	}
	
	function storage_localfile(string $filename, string $node = NULL):array
	{
		return is_file($filename)
			&& is_string($hash = $this->hashfile($filename))
			&& is_string($file = $this->assign(...[$hash, filesize($filename),
				...is_int($pos = strrpos($basename = basename($filename), '.'))
					? [substr($basename, $pos + 1, 8), $name = substr($basename, 0, $pos)]
					: ['', $name = $basename], $node]))
			&& (copy($filename, $file) || $this->lost($hash)) ? [$hash => $name] : [];
	}
	function storage_localfolder(string $dirname, string $node = NULL):array
	{
		$success = [];
		if (is_resource($handle = opendir($dirname)))
		{
			readdir($handle);
			readdir($handle);
			if ($hash = $this->create(basename($dirname), $node))
			{
				while (is_string($itemname = readdir($handle)))
				{
					$success += is_dir($filename = "{$dirname}/{$itemname}")
						? $this->storage_localfolder($filename, $hash)
						: $this->storage_localfile($filename, $hash);
				}
			}
			closedir($handle);
		}
		return $success;
	}
	function storage_uploadedfile(string $name, int $maximum = NULL, string $node = NULL):array
	{
		$success = [];
		if ($this->node($node))
		{
			foreach ($this->request_uploadedfile($name, $maximum) as $uploadedfile)
			{
				if (is_uploaded_file($uploadedfile['file'])
					&& is_string($file = $this->assign(
						$uploadedfile['hash'],
						$uploadedfile['size'],
						$uploadedfile['type'],
						$uploadedfile['name'],
						$node))
					&& (move_uploaded_file($uploadedfile['file'], $file) || $this->lost($uploadedfile['hash']))) {
					$success[$uploadedfile['hash']] = $uploadedfile['name'];
				}
			}
		}
		return $success;
	}



	// function exists(string $hash):bool
	// {
	// 	return preg_match('/^[0-9A-V]{12}$/', $hash) && is_file($this->file($hash));
	// }
	
	function nodeitem(?string $hash, int $page, int $item = 42):webapp_mysql_table
	{
		return ($hash === NULL
			? $this->nfas('WHERE node IS NULL ORDER BY type ASC')
			: $this->nfas('WHERE node=?s ORDER BY type ASC', $hash))->paging($page, $item);
	}


	function linkroot(string $hash):webapp_mysql
	{
		return ($this->mysql)('WITH RECURSIVE a AS(
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.hash=a.node)SELECT * FROM a',
			static::tablename, $hash, static::tablename, static::tablename);
	}
	function linktree(string $hash):webapp_mysql
	{
		return ($this->mysql)('WITH RECURSIVE a AS(
SELECT * FROM ?a WHERE hash=?s
UNION ALL
SELECT ?a.* FROM nfas,a WHERE ?a.node=a.hash)SELECT * FROM a',
			static::tablename, $hash, static::tablename, static::tablename);
	}

	function create(string $name, string $node = NULL, string $type = NULL):?string
	{
		return $this->node($node) && ($type === NULL
			? $this->nfas->insert([
				'hash' => $hash = 'W' . substr($this->hash($this->random(16)), 1),
				'hits' => 0,
				'size' => 0,
				'time' => $this->time,
				'flag' => 0,
				'node' => $node,
				'type' => $type,
				'name' => $name])
			: (is_string($file = $this->assign($hash = $this->hash($this->random(16)), 0, $type, $name, $node))
				&& touch($file, $this->time))) ? $hash : NULL;
	}
	function rename(string $hash, string $name):bool
	{
		return $this->nfas('WHERE hash=?s LIMIT 1', $hash)->update('name=?s', $name) === 1;
	}
	function moveto(string $hash, string $node = NULL):bool
	{
		return $this->node($node) && $this->nfas('WHERE hash=?s LIMIT 1', $hash)->update(['node' => $node]) === 1;
	}
	function delete(string $hash)
	{
		if ($item = $this->nfas('WHERE hash=?s LIMIT 1', $hash)->array())
		{
			//$this->mysql->autocommit(FALSE);
			foreach ($item['type'] === NULL ? $this->linktree($hash) : [$item] as $item)
			{
				if ($this->nfas->delete('WHERE hash=?s LIMIT 1', $item['hash']))
				{
					if (str_starts_with($item['hash'], 'W') === FALSE)
					{
						is_file($filename = $this->filename($item['hash'])) === FALSE || unlink($filename);
					}
				}
			}
			//$this->mysql->autocommit(TRUE);
		}
	}

}