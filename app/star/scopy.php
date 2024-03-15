<?php
require '../../webapp.php';
$ffmpeg = webapp::lib('ffmpeg/interface.php');
$mysql = new webapp_mysql;
$mysql->select_db('app');
$rootsrc = 'X:/OK';
$rootdst = 'Z:';
$dirs = array_slice(scandir($rootsrc), 2);
shuffle($dirs);
foreach ($dirs as $dirname)
{
    $from = "{$rootsrc}/{$dirname}";
    $info = json_decode(shell_exec("D:/wmhp/work/webapp/lib/ffmpeg/ffprobe.exe -allowed_extensions ALL -v quiet -print_format json -show_format -i \"{$from}/play.m3u8\""), TRUE);
    $duration = (int)round($info['format']['duration']);
    if ($mysql->videos->insert([
        'hash' => $hash = webapp::hash($dirname),
        'userid' => '0000000001',
        'mtime' => $time = time(),
        'ctime' => $time,
        'ptime' => $time,
        'size' => 0,
        'tell' => 0,
        'cover' => 'finish',
        'sync' => 'slicing',
        'type' => 'h',
        'sort' => 0,
        'duration' => $duration,
        'preview' => (intval($duration * 0.6) << 16) | 10,
        'require' => 0,
        'sales' => 0,
        'view' => 0,
        'like' => 0,
        'favorite' => 0,
        'tags' => '',
        'subjects' => '',
        'name' => $dirname
    ]) === FALSE) {
        echo "{$hash} [{$dirname}] ERROR EXISTS\n";
        continue;
    }
    echo "{$hash} [{$dirname}] ";
    $ym = date('ym', $time);
    if ((is_dir($movedst = "{$rootdst}/{$ym}") || mkdir($movedst)) === FALSE)
    {
        echo "ERROR MKDIR\n";
        continue;
    }
    $to = "{$movedst}/{$hash}";
    if ((is_string($success = exec("xcopy \"{$from}/*\" \"{$to}/\" /E /C /I /F /Y", $output, $code)) && $code === 0) === FALSE)
    {
        echo "ERROR XCOPY\n";
        continue;
	}
    if (webapp::maskfile("{$to}/play.m3u8", "{$to}/play") === FALSE
        || webapp::maskfile("{$to}/cover.jpg", "{$to}/cover") === FALSE) {
        echo "ERROR MASK\n";
        continue;
    }
    if ($mysql->videos('WHERE hash=?s LIMIT 1', $hash)->update('sync="allow"') === 1)
    {
        #exec("RD /S /Q \"{$from}\"");
        echo "OK\n";
    }
}




