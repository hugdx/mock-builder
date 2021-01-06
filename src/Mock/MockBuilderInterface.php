<?php


namespace HungDX\MockBuilder\Mock;

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
     * @param int $flag
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage
     */
    public function __mockMethod(string $methodName, $flag = MockBuilder::CAPTURE_LOG);

    /**
     * Get Mock of method which have been instanced by __mockMethod
     *
     * @param string $methodName
     * @return \Mockery\ExpectationInterface[]|\Mockery\Expectation[]|\Mockery\HigherOrderMessage[]
     */
    public function __getMockMethod(string $methodName): array;
}
