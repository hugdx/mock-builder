<?php


namespace HungDX\MockQueryBuilder\Mock;

interface MockQueryBuilderInterface
{
    /**
     * @return array
     */
    public function __getLogs(): array;

    /**
     * @return void
     */
    public function __resetLogs();

    /**
     * @param string $methodName
     * @return MockQueryBuilderInterface|\Mockery\MockInterface
     */
    public function __mockMethod(string $methodName);
}
