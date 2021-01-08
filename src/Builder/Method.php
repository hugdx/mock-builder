<?php


namespace HungDX\MockBuilder\Builder;

use Exception;
use HungDX\MockBuilder\MockBuilder;
use ReflectionFunction;

class Method
{
    /** @var MockBuilder */
    private $mock;

    /** @var \Mockery\Expectation|\Mockery\ExpectationInterface|\Mockery\HigherOrderMessage */
    private $methodMock;

    /** @var string */
    private $methodName;

    /**
     * @param MockBuilder $mock
     * @param string $methodName
     */
    public function __construct(
        MockBuilder $mock,
        string $methodName
    ) {
        $this->mock       = $mock;
        $this->methodName = $methodName;

        $this->methodMock = $this->mock->getMock()->shouldReceive($this->methodName)
            ->withArgs((function () {
                $this->onMethodCalled(func_get_args());
                return true;
            })->bindTo($this));
    }

    public function onMethodCalled(array $parameters)
    {
        // Method shouldn't capture log -> stop
        if (!$this->mock->getConfig()->shouldCaptureLogOfMethod($this->methodName, $parameters)) {
            return;
        }

        $path = $this->mock->getLogger()->createPath($this->methodName);

        // Haven't got callback: put $parameters as object
        if (!$this->hasCallback($parameters)) {
            $this->mock->getLogger()->addLog($path, (object) $parameters);
            return;
        }

        // Have got callback: If $parameters more than 1 => callback should be in an array container
        $path .= '.' . (count($this->mock->getLogger()->getLog($path)));

        // Do callback
        foreach ($parameters as $index => $param) {
            if (!is_callable($param)) {
                $this->mock->getLogger()->addLog($path, $param);
                continue;
            }

            $this->mock->getLogger()->pushPathToStack($path);
            $this->doMethodCallback($param);
            $this->mock->getLogger()->popPathFromStack();
        }
    }

    private function doMethodCallback(callable $callback)
    {
        $mockForCallback = $this->mock;
        try {
            $parameter = (new ReflectionFunction($callback))->getParameters()[0] ?? false;
            if ($parameter) {
                if ($parameter->getClass()) {
                    $className = $parameter->getClass()->getName();
                    $className = \HungDX\MockBuilder\Generator\Generator::createSubClassOf($className);

                    $mockForCallback = new $className;
                    $mockForCallback->_mockBuilder_setMock($this->mock);
                }
            }
        } catch (Exception $e) {
        }

        call_user_func($callback, $$mockForCallback);
    }

    private function hasCallback(array $parameters): bool
    {
        foreach ($parameters as $parameter) {
            if (is_callable($parameter)) {
                return true;
            }
        }
        return false;
    }

    public function ignoreLog(): self
    {
        $this->mock->getConfig()->ignoreLogOfMethod($this->methodName);
        return $this;
    }

    public function captureLog(): self
    {
        $this->mock->getConfig()->captureLogOfMethod($this->methodName);
        return $this;
    }

    public function ignoreLogOnEmpty(): self
    {
        $this->mock->getConfig()->ignoreLogOfMethodIfParametersAreEmpty($this->methodName);
        return $this;
    }

    public function captureLogOnEmpty(): self
    {
        $this->mock->getConfig()->captureLogOfMethodIfParametersAreEmpty($this->methodName);
        return $this;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->methodMock, $name], $arguments);
    }
}
