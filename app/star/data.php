<?php
require 'base.php';
class data extends base
{
	//广告位置数据
	function data_advertisements(int $seat = 0):array
	{
		return [
			['support' => 'javascript:alert(1);', 'picture' => '?/news/6UA5CO7B769R?mask1696937379'],
			['support' => 'javascript:alert(2);', 'picture' => '?/news/C244JVMLB9Q2?mask1696937379'],
			['support' => 'javascript:alert(3);', 'picture' => '?/news/NSHV5V94QPE6?mask1696937379']
		];
	}
	//分类数据
	function data_classify():array
	{
		return $this->mysql->tags('WHERE phash IS NULL ORDER BY sort DESC')->column('name', 'hash');
	}
	//分类最新更新数据
	function data_classify_top_videos(string $type):array
	{
		$videos = [];
		foreach ($this->mysql->videos('WHERE FIND_IN_SET(?s,tags) ORDER BY ptime DESC LIMIT 8', $type) as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}";
			$video['m3u8'] = "?/{$ym}/{$video['hash']}/play?mask{$video['ctime']}";
			$videos[] = $video;
		}
		return $videos;
	}
	function data_classify_tags(string $type):array
	{
		return $this->mysql->tags('WHERE hash=?s OR phash=?s ORDER BY sort DESC', $type, $type)->column('name', 'hash');
	}
	//分类专题数据
	function data_classify_subjects(string $type):iterable
	{
		foreach ($this->webapp->fetch_subjects($type) as $subject)
		{
			$videos = [];
			foreach ($this->mysql->videos('WHERE hash IN(?S)', $subject['videos']) as $video)
			{
				$ym = date('ym', $video['mtime']);
				$video['cover'] = "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}";
				$video['m3u8'] = "?/{$ym}/{$video['hash']}/play?mask{$video['ctime']}";
				$videos[] = $video;
			}
			$subject['videos'] = $videos;
			yield $subject;
		}
		
	}


	function data_subjects(string $hash, int $page = 0, int $size = 20):array
	{
		if ($page)
		{
			$videos = [];
			foreach ($this->mysql->videos('WHERE FIND_IN_SET(?s,subjects) ORDER BY ptime DESC', $hash)->paging($page, $size) as $video)
			{
				$ym = date('ym', $video['mtime']);
				$video['cover'] = "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}";
				$video['m3u8'] = "?/{$ym}/{$video['hash']}/play?mask{$video['ctime']}";
				$videos[] = $video;
			}
			return $videos;
		}
		return $this->mysql->subjects('WHERE hash=?s LIMIT 1', $hash)->array();
	}
	function data_search_video(string $word = NULL, string $tags = NULL, int $page = 0, int $size = 20):iterable
	{



		foreach ($this->mysql->videos('WHERE sync="allow" ORDER BY ptime DESC')->paging($page, $size) as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}";
			yield $video;
		}
	}


	function data_like_videos(array $video):iterable
	{
		$cond = $video['tags'] ? sprintf('FIND_IN_SET("%s",tags) AND ', substr($video['tags'], 0, 4)) : '';
		foreach ($this->mysql->videos('WHERE ??sync="allow" ORDER BY ctime DESC LIMIT 20', $cond) as $video)
		{
			$ym = date('ym', $video['mtime']);
			$video['cover'] = "?/{$ym}/{$video['hash']}/cover?mask{$video['ctime']}";
			yield $video;
		}
	}
}