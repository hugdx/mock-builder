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
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage
     */
    public function __mockMethod(string $methodName);
}
