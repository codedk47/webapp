<?php
#https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Guides/MIME_types/Common_types
return function(string $filename):string
{
	return match (is_string($suffix = strrchr($filename, '.')) ? strtolower(substr($suffix, 1)) : NULL)
	{
		'aac'	=> 'audio/aac', //AAC 音频
		'abw'	=> 'application/x-abiword', //AbiWord 文档
		'apng'	=> 'image/apng', //动态可移植网络图形（APNG）图像
		'arc'	=> 'application/x-freearc', //归档文件（嵌入多个文件）
		'avif'	=> 'image/avif', //AVIF 图像
		'avi'	=> 'video/x-msvideo', //AVI：音频视频交织文件格式（Audio Video Interleave）
		'azw'	=> 'application/vnd.amazon.ebook', //Amazon Kindle 电子书格式
		'bmp'	=> 'image/bmp', //Windows OS/2 位图
		'bz'	=> 'application/x-bzip', //BZip 归档
		'bz2'	=> 'application/x-bzip2', //BZip2 归档
		'cda'	=> 'application/x-cdf', //CD 音频
		'csh'	=> 'application/x-csh', //C-Shell 脚本
		'css'	=> 'text/css', //层叠样式表（CSS）
		'csv'	=> 'text/csv', //逗号分隔值（CSV）
		'doc'	=> 'application/msword', //Microsoft Word
		'docx'	=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', //Microsoft Word（OpenXML）
		'eot'	=> 'application/vnd.ms-fontobject', //MS 嵌入式 OpenType 字体
		'epub'	=> 'application/epub+zip', //电子出版（EPUB）
		'gz'	=> 'application/gzip', //GZip 压缩归档
		'gif'	=> 'image/gif', //图像互换格式（GIF）
		'htm', 'html'
				=> 'text/html', //超文本标记语言（HTML）
		'ico'	=> 'image/vnd.microsoft.icon', //图标（Icon）格式
		'ics'	=> 'text/calendar', //iCalendar 格式
		'jar'	=> 'application/java-archive', //Java 归档（JAR）
		'jpeg', 'jpg'
				=> 'image/jpeg', //JPEG 图像
		'js', 'mjs'
				=> 'text/javascript', //JavaScript （规范：HTML 和 RFC 9239），JavaScript 模块
		'json'	=> 'application/json', //JSON 格式
		'jsonld'=> 'application/ld+json', //JSON-LD 格式
		'mid', 'midi'
				=> 'audio/midi、audio/x-midi', //音乐数字接口（MIDI）
		'mp3'	=> 'audio/mpeg', //MP3 音频
		'mp4'	=> 'video/mp4', //MP4 视频
		'mpeg'	=> 'video/mpeg', //MPEG 视频
		'mpkg'	=> 'application/vnd.apple.installer+xml', //Apple 安装包
		'odp'	=> 'application/vnd.oasis.opendocument.presentation', //开放文档演示稿文档
		'ods'	=> 'application/vnd.oasis.opendocument.spreadsheet', //开放文档表格文档
		'odt'	=> 'application/vnd.oasis.opendocument.text', //开放文档文本文档
		'oga'	=> 'audio/ogg', //OGG 音频
		'ogv'	=> 'video/ogg', //OGG 视频
		'ogx'	=> 'application/ogg', //OGG
		'opus'	=> 'audio/opus', //Opus 音频
		'otf'	=> 'font/otf', //OpenType 字体
		'png'	=> 'image/png', //便携式网络图形
		'pdf'	=> 'application/pdf', //Adobe 便携式文档格式（PDF）
		'php'	=> 'application/x-httpd-php', //超文本预处理器（Personal Home Page）
		'ppt'	=> 'application/vnd.ms-powerpoint', //Microsoft PowerPoint
		'pptx'	=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation', //Microsoft PowerPoint（OpenXML）
		'rar'	=> 'application/vnd.rar', //RAR 归档
		'rtf'	=> 'application/rtf', //富文本格式（RTF）
		'sh'	=> 'application/x-sh', //伯恩 shell 脚本
		'svg'	=> 'image/svg+xml', //可缩放矢量图形（SVG）
		'tar'	=> '磁带归档（TAR）	application/x-tar',
		'tif', 'tiff'
				=> 'image/tiff', //标签图像文件格式（TIFF）
		'ts'	=> 'video/mp2t', //MPEG 传输流
		'ttf'	=> 'font/ttf', //TrueType 字体
		'txt'	=> 'text/plain', //文本（通常是 ASCII 或 ISO 8859-n）
		'vsd'	=> 'application/vnd.visio', //Microsoft Visio
		'wav'	=> 'audio/wav', //波形音频格式
		'weba'	=> 'audio/webm', //WEBM 音频
		'webm'	=> 'video/webm', //WEBM 视频
		'webp'	=> 'image/webp', //WEBP 图像
		'woff'	=> 'font/woff', //Web 开放字体格式（WOFF）
		'woff2'	=> 'font/woff2', //Web 开放字体格式（WOFF）
		'xhtml'	=> 'application/xhtml+xml', //XHTML
		'xls'	=> 'application/vnd.ms-excel', //Microsoft Excel
		'xlsx'	=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', //Microsoft Excel（OpenXML）
		'xml'	=> 'application/xml', //XML	RFC 7303（section 4.1）推荐使用 application/xml，但有时仍会使用 text/xml。你可以将特定的 MIME 类型分配给具有 .xml 扩展名的文件，这取决于其内容的解释方式。例如，Atom 消息来源是 application/atom+xml，而 application/xml 是默认的有效值。
		'xul'	=> 'application/vnd.mozilla.xul+xml', //XUL
		'zip'	=> 'application/zip', //ZIP 归档
		'3gp'	=> 'video/3gpp', //3GPP 音视频容器；如果不包含视频则为 audio/3gpp
		'3g2'	=> 'video/3gpp2', //3GPP2 音视频容器；如果不包含视频则为 audio/3gpp2
		'7z'	=> 'application/x-7z-compressed', //7-zip 归档
		default => 'application/octet-stream'
	};
};