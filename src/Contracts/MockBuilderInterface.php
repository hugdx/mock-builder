<?php


namespace HungDX\MockBuilder\Contracts;

use HungDX\MockBuilder\Builder\Method;
use HungDX\MockBuilder\MockBuilder;

interface MockBuilderInterface
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
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage|Method
     */
    public function __mockMethod(string $methodName);

    /**
     * Get Mock of method which have been instanced by __mockMethod
     *
     * @param string $methodName
     * @return \Mockery\ExpectationInterface[]|\Mockery\Expectation[]|\Mockery\HigherOrderMessage[]|Method[]
     */
    public function __getMockMethod(string $methodName): array;

    /**
     * @param string $methodName
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage|Method
     */
    public function __getLastMockOfMethod(string $methodName);

    /**
     * @return MockBuilder
     */
    public function __getMockBuilder(): MockBuilder;
}
