<?php

namespace HungDX\MockQueryBuilder\Mock;

use Illuminate\Support\Arr;
use Mockery;

class MockQueryBuilder implements MockQueryBuilderInterface
{
    /** @var array */
    private $logs = [];

    /** @var Mockery\MockInterface|mixed */
    private $mock;

    /** @var array */
    private $treePath = [];

    /**
     * MockQueryBuilder constructor.
     * @param Mockery\MockInterface $mock
     */
    public function __construct(Mockery\MockInterface $mock)
    {
        $this->mock = $mock;
        $this->init();
    }

    /**
     * Get logs
     * @return array
     */
    public function __getLogs(): array
    {
        return $this->makeLogsToReadable($this->logs);
    }

    /**
     * Reset logs
     */
    public function __resetLogs()
    {
        $this->logs = [];
    }

    /**
     * Mock a method for current mock instance
     *
     * @param string $methodName
     * @param bool $capture
     * @return Mockery\ExpectationInterface|Mockery\Expectation|Mockery\HigherOrderMessage
     */
    public function __mockMethod(string $methodName, bool $capture = true)
    {
        if (!$capture) {
            return $this->mock->shouldReceive($methodName);
        }

        $that = $this;
        return $this->mock->shouldReceive($methodName)
            ->withArgs(function (...$parameters) use ($that, $methodName) {
                $path = $that->generateLogPath($methodName);

                // Haven't got callback: put $parameters as object
                if (!$that->hasCallback($parameters)) {
                    $that->setLog($path, (object) $parameters);
                    return true;
                }

                // Have got callback: If $parameters more than 1 => callback should be in an array container
                $path .= '.' . (count($that->getLog($path)));

                // Do callback
                foreach ($parameters as $index => $param) {
                    if (!is_callable($param)) {
                        $that->setLog($path, $param);
                        continue;
                    }

                    $that->addToLogPathStack($path);
                    call_user_func($param, $that->mock);
                    $that->removeFromLogPathStack();
                }
                return true;
            });
    }

    /**
     * Generate log path: use for get/update logs
     *
     * @param string $methodName
     * @return string
     */
    public function generateLogPath(string $methodName): string
    {
        $treePathLength = count($this->treePath);
        $path           = $treePathLength > 0 ? $this->treePath[$treePathLength - 1] . '.' : '';
        $path .= $methodName;
        return $path;
    }

    /**
     * Add log path to stack. Used for callback
     *
     * @param string $logPath
     */
    public function addToLogPathStack(string $logPath)
    {
        $this->treePath[] = $logPath;
    }

    /**
     * Get and remove top path from log path Stack
     *
     * @return mixed
     */
    public function removeFromLogPathStack()
    {
        return array_pop($this->treePath);
    }

    /**
     * Get current mock instance
     *
     * @return Mockery\MockInterface
     */
    public function getMock(): Mockery\MockInterface
    {
        return $this->mock;
    }

    /**
     * Set log
     *
     * @param string $logPath
     * @param object|array $parameters
     */
    public function setLog(string $logPath, $parameters)
    {
        $current = Arr::get($this->logs, $logPath, []);
        $current = is_array($current) ? $current : [$current];
        array_push($current, $parameters);
        Arr::set($this->logs, $logPath, $current);
    }

    /**
     * Get log by path
     *
     * @param string $logPath
     * @return array|\ArrayAccess|mixed
     */
    public function getLog(string $logPath)
    {
        return Arr::get($this->logs, $logPath, []);
    }

    /**
     * Is $parameters array container callable
     *
     * @param array $parameters
     * @return bool
     */
    protected function hasCallback(array $parameters): bool
    {
        foreach ($parameters as $parameter) {
            if (is_callable($parameter)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create readable array from logs
     *
     * @param array $builderArgs
     * @return array|object
     */
    private function makeLogsToReadable(array $builderArgs)
    {
        if (count($builderArgs) === 1 && isset($builderArgs[0])) {
            $builderArgs = $builderArgs[0];
        }

        if (is_object($builderArgs)) {
            return $builderArgs;
        }

        foreach ($builderArgs as $index => $arg) {
            if (is_array($arg)) {
                $builderArgs[$index] = $this->makeLogsToReadable($arg);
            }
        }

        return $builderArgs;
    }

    /**
     * Create mock of $className
     *
     * @param string $className
     * @return Mockery\MockInterface|MockQueryBuilderInterface
     */
    public static function create(string $className)
    {
        $mock = Mockery::mock('overload:' . $className);

        $builder = new static($mock);

        $mock->shouldReceive('__getLogs')->andReturnUsing(function () use ($builder) {
            return $builder->__getLogs();
        });

        $mock->shouldReceive('__resetLogs')->andReturnUsing(function () use ($builder) {
            $builder->__resetLogs();
            return $builder->getMock();
        });

        $mock->shouldReceive('__mockMethod')->andReturnUsing(function (string $methodName, bool $capture = true) use ($builder) {
            return $builder->__mockMethod($methodName, $capture);
        });

        return $mock;
    }

    public function init()
    {
        $this->__mockMethod('query', false)->withNoArgs()->andReturnSelf();
        $this->__mockMethod('where')->andReturnSelf();
        $this->__mockMethod('orWhere')->andReturnSelf();
        $this->__mockMethod('orWhereHas')->andReturnSelf();
        $this->__mockMethod('whereHas')->andReturnSelf();
        $this->__mockMethod('whereDate')->andReturnSelf();
        $this->__mockMethod('whereIn')->andReturnSelf();
        $this->__mockMethod('orderBy')->andReturnSelf();
        $this->__mockMethod('skip')->andReturnSelf();
        $this->__mockMethod('limit')->andReturnSelf();
        $this->__mockMethod('with')->andReturnSelf();
    }
}
