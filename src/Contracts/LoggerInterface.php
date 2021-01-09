<?php


namespace HungDX\MockBuilder\Contracts;

interface LoggerInterface
{
    /**
     * Get logs
     * @return array
     */
    public function getLogs(): array;

    /**
     * Reset logs
     */
    public function resetLogs();
}
