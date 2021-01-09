<?php

namespace HungDX\MockBuilder\Builder;

use HungDX\MockBuilder\Contracts\LoggerInterface;
use Illuminate\Support\Arr;

class Logger implements LoggerInterface
{
    /** @var array */
    private $logs = [];

    /** @var array */
    private $pathStack = [];

    /** Get logs */
    public function getLogs(): array
    {
        return $this->makeLogsToReadable($this->logs);
    }

    /** Reset logs */
    public function resetLogs()
    {
        $this->logs = [];
    }

    /**
     * Create path base on current path stack
     * @param string $path
     * @return string
     */
    public function createPath(string $path): string
    {
        $currentPath = $this->getCurrentPath();
        return $currentPath . ($currentPath ? '.' : '') . $path;
    }

    public function pushPathToStack(string $path)
    {
        $this->pathStack[] = $path;
    }

    public function popPathFromStack(): ?string
    {
        return array_pop($this->pathStack);
    }

    public function getCurrentPath(): string
    {
        return end($this->pathStack) ?: '';
    }

    public function addLog(string $path, $data)
    {
        $current = Arr::get($this->logs, $path, []);
        $current = is_array($current) ? $current : [$current];
        array_push($current, $data);
        Arr::set($this->logs, $path, $current);
    }

    public function getLog(string $path)
    {
        return Arr::get($this->logs, $path, []);
    }

    private function makeLogsToReadable(array $logs)
    {
        // Convert log: array($data) -> $data
        if (count($logs) === 1 && isset($logs[0])) {
            $logs = $logs[0];
        }

        // Stop condition: The last item
        if (is_object($logs)) {
            return $logs;
        }

        foreach ($logs as $index => $log) {
            if (is_array($log)) {
                $logs[$index] = $this->makeLogsToReadable($log);
            }
        }

        return $logs;
    }
}
