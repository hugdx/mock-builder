<?php

namespace HungDX\MockQueryBuilder\Mock;

use Illuminate\Support\Arr;
use Mockery\MockInterface;
use Mockery;

class MockQueryBuilder implements MockQueryBuilderInterface
{
    /** @var array */
    private $logs = [];

    /** @var MockInterface|mixed */
    private $mock;

    /** @var array */
    private $treePath = [];

    /**
     * MockQueryBuilder constructor.
     * @param MockInterface $mock
     */
    public function __construct(MockInterface $mock)
    {
        $this->mock = $mock;
        $this->init();
    }

    /**
     * Get logs
     * @return array
     */
    public function getLogs(): array
    {
        return $this->makeLogsToReadable($this->logs);
    }

    /**
     * Reset logs
     */
    public function resetLogs()
    {
        $this->logs = [];
    }

    /**
     * Mock a method for current mock instance
     *
     * @param string $methodName
     * @return MockInterface|MockQueryBuilderInterface|mixed
     */
    public function mockMethod(string $methodName)
    {
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
     * @return MockInterface
     */
    public function getMock(): MockInterface
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
     * @return MockInterface|MockQueryBuilderInterface
     */
    public static function create(string $className)
    {
        $mock = Mockery::mock('overload:' . $className);

        $builder = new static($mock);

        $mock->getLogs = function () use ($builder) {
            return $builder->getLogs();
        };

        $mock->resetLogs = function () use ($builder) {
            $builder->resetLogs();
            return $builder->getMock();
        };

        $mock->mockMethod = function (string $methodName) use ($builder) {
            $builder->mockMethod($methodName);
            return $builder->getMock();
        };

        return $mock;
    }

    public function init()
    {
        $this->mockMethod('where')->andReturnSelf();
        $this->mockMethod('orWhere')->andReturnSelf();
        $this->mockMethod('orWhereHas')->andReturnSelf();
        $this->mockMethod('whereHas')->andReturnSelf();
        $this->mockMethod('whereDate')->andReturnSelf();
        $this->mockMethod('whereIn')->andReturnSelf();
        $this->mockMethod('orderBy')->andReturnSelf();
        $this->mockMethod('skip')->andReturnSelf();
        $this->mockMethod('limit')->andReturnSelf();
        $this->mockMethod('with')->andReturnSelf();
    }
}
