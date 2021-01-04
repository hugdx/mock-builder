<?php


namespace HungDX\MockQueryBuilder\Mock;

interface MockQueryBuilderInterface
{
    /**
     * @return array
     */
    public function getLogs(): array;

    /**
     * @return void
     */
    public function resetLogs();

    /**
     * @param string $methodName
     * @return MockQueryBuilderInterface|\Mockery\MockInterface
     */
    public function mockMethod(string $methodName);
}
