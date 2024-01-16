<?php
class webapp_stream
{
	public $context;
	public function __construct()
	{
		var_dump(123);
	}
	// public dir_closedir(): bool
	// public dir_opendir(string $path, int $options): bool
	// public dir_readdir(): string
	// public dir_rewinddir(): bool
	// public mkdir(string $path, int $mode, int $options): bool
	// public rename(string $path_from, string $path_to): bool
	// public rmdir(string $path, int $options): bool
	// public stream_cast(int $cast_as): resource
	// public stream_close(): void
	// public stream_eof(): bool
	// public stream_flush(): bool
	// public stream_lock(int $operation): bool
	// public stream_metadata(string $path, int $option, mixed $value): bool
	public function stream_open(string $path, string $mode, int $options, ?string &$opened_path):bool
	{

		var_dump('asdasd');
		return true;

	}
	// public stream_read(int $count): string|false
	// public stream_seek(int $offset, int $whence = SEEK_SET): bool
	// public stream_set_option(int $option, int $arg1, int $arg2): bool
	// public stream_stat(): array|false
	// public stream_tell(): int
	// public stream_truncate(int $new_size): bool
	// public stream_write(string $data): int
	// public unlink(string $path): bool
	// public url_stat(string $path, int $flags): array|false
	#public __destruct()
	static function client(string $socket, array $contexts = [])
	{
		//stream_socket_client()

	}
}
