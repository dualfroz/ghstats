<?php

declare(strict_types=1);

namespace Ghapi\Support;

final class Process
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly bool $timedOut,
    ) {
    }

    public function ok(): bool
    {
        return $this->exitCode === 0 && !$this->timedOut;
    }

    public static function run(array $command, ?string $cwd = null, int $timeoutSeconds = 300, ?array $env = null): self
    {
        $pipes = [];
        $handle = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        if (!is_resource($handle)) {
            return new self(127, '', 'cannot start ' . ($command[0] ?? ''), false);
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $timeoutSeconds;
        $timedOut = false;
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            if (@stream_select($read, $write, $except, 1) === false) {
                break;
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 1 << 16);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= strlen($stderr) < 65536 ? $chunk : '';
                }
            }
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
            if (microtime(true) > $deadline) {
                $timedOut = true;
                proc_terminate($handle, 9);
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($handle);
        return new self($timedOut ? -1 : $status, $stdout, $stderr, $timedOut);
    }
}
