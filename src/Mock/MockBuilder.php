<?php

namespace HungDX\MockBuilder\Mock;

use Illuminate\Support\Arr;
use Mockery;
use ReflectionClass;
use ReflectionMethod;

class MockBuilder implements MockBuilderInterface
{
    const IGNORE_LOG          = 0x0001;
    const IGNORE_LOG_IF_EMPTY = 0x0010;
    const CAPTURE_LOG         = 0x1000;

    /** @var array */
    private $logs = [];

    /** @var Mockery\MockInterface|mixed */
    private $mock;

    /** @var array */
    private $treePath = [];

    private $ignoreList = [];

    private $ignoreOnEmpty = [];

    /** @var  Mockery\ExpectationInterface[]|Mockery\Expectation[]|Mockery\HigherOrderMessage[]|array */
    private $mockMethods = [];

    /**
     * MockQueryBuilder constructor.
     * @param Mockery\MockInterface $mock
     */
    public function __construct(Mockery\MockInterface $mock)
    {
        $this->mock = $mock;
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
     * @param int $flag
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage
     */
    public function __mockMethod(string $methodName, $flag = MockBuilder::CAPTURE_LOG)
    {
        if ($flag & self::IGNORE_LOG_IF_EMPTY) {
            $this->ignoreOnEmpty[] = $methodName;
            $this->ignoreOnEmpty   = array_unique($this->ignoreOnEmpty);
        }

        if ($flag & self::IGNORE_LOG) {
            $this->ignoreList[] = $methodName;
            $this->ignoreList   = array_unique($this->ignoreList);
        }

        $mock = $this->mock->shouldReceive($methodName)
            ->withArgs((function (...$parameters) use ($methodName) {
                $this->onMethodCalled($parameters, $methodName);
                return true;
            })->bindTo($this));

        $this->mockMethods[$methodName][] = $mock;

        return $mock;
    }

    /**
     * Get Mock of method which have been instanced by __mockMethod
     *
     * @param string $methodName
     * @return \Mockery\ExpectationInterface[]|\Mockery\Expectation[]|\Mockery\HigherOrderMessage[]
     */
    public function __getMockMethod(string $methodName): array
    {
        return $this->mockMethods[$methodName] ?? [];
    }

    /**
     * @return MockBuilder
     */
    public function __getMockBuilder(): MockBuilder
    {
        return $this;
    }

    /**
     * On Method called
     *
     * @param array $parameters
     * @param string $methodName
     */
    public function onMethodCalled(array $parameters, string $methodName)
    {
        if ($this->isInIgnoreList($methodName)) {
            return;
        }

        if ($this->isObjectEmpty($parameters) && $this->isInIgnoreOnEmptyList($methodName)) {
            return;
        }

        $path = $this->generateLogPath($methodName);

        // Haven't got callback: put $parameters as object
        if (!$this->hasCallback($parameters)) {
            $this->setLog($path, (object) $parameters);
            return;
        }

        // Have got callback: If $parameters more than 1 => callback should be in an array container
        $path .= '.' . (count($this->getLog($path)));

        // Do callback
        foreach ($parameters as $index => $param) {
            if (!is_callable($param)) {
                $this->setLog($path, $param);
                continue;
            }

            $this->addToLogPathStack($path);
            call_user_func($param, $this->mock);
            $this->removeFromLogPathStack();
        }
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
     * @param string $methodName
     * @return array|object
     */
    private function makeLogsToReadable(array $builderArgs, string $methodName = null)
    {
        if (count($builderArgs) === 1 && isset($builderArgs[0])) {
            $builderArgs = $builderArgs[0];
        }

        if (is_object($builderArgs)) {
            return $builderArgs;
        }

        foreach ($builderArgs as $index => $arg) {
            if (is_array($arg)) {
                $builderArgs[$index] = $this->makeLogsToReadable($arg, $index);
            }
        }

        return $builderArgs;
    }

    /**
     * Is object empty or not. An Object called empty if they are: `(object) array()` or `(object) array(array())`
     *
     * @param $object
     * @return bool
     */
    private function isObjectEmpty($object): bool
    {
        // Parse object to array
        $objectValue = json_decode(json_encode($object), true);

        // Case array is: []
        if (empty($objectValue)) {
            return true;
        }

        // Case array is: [[]]
        if (count($objectValue) === 1 && isset($objectValue[0]) && empty($objectValue[0])) {
            return true;
        }

        // Other cases
        return false;
    }

    /**
     * Is in ignore list
     *
     * @param string $methodName
     * @return bool
     */
    private function isInIgnoreList(string $methodName): bool
    {
        return !is_numeric($methodName) && in_array($methodName, $this->ignoreList);
    }

    /**
     * is $methodName in ignore on empty list
     *
     * @param string|numeric $methodName
     * @return bool
     */
    private function isInIgnoreOnEmptyList($methodName): bool
    {
        if (empty($this->ignoreOnEmpty)) {
            return false;
        }

        if (in_array('*', $this->ignoreOnEmpty)) {
            return true;
        }

        return in_array($methodName, $this->ignoreOnEmpty);
    }

    /**
     * Init methods for exist mock
     *
     * @param Mockery\MockInterface|object $mock
     * @return Mockery\MockInterface|MockBuilderInterface
     */
    public static function of(Mockery\MockInterface $mock)
    {
        self::initMock($mock);
        return $mock;
    }

    /**
     * Create mock of $className
     *
     * @param string $className
     * @return Mockery\MockInterface|MockBuilderInterface
     */
    public static function create(string $className)
    {
        $mock = Mockery::mock($className);
        self::initMock($mock);
        return $mock;
    }

    /**
     * Init methods for mock
     *
     * @param Mockery\MockInterface $mock
     */
    private static function initMock(Mockery\MockInterface $mock)
    {
        $builder = new static($mock);

        $mock->shouldReceive('__getMockBuilder')->andReturnUsing(function () use ($builder) {
            return $builder;
        });

        $mock->shouldReceive('__getLogs')->andReturnUsing(function () use ($builder) {
            return $builder->__getLogs();
        });

        $mock->shouldReceive('__resetLogs')->andReturnUsing(function () use ($builder) {
            $builder->__resetLogs();
            return $builder->getMock();
        });

        $mock->shouldReceive('__mockMethod')->andReturnUsing(function (string $methodName, $flag = self::CAPTURE_LOG) use ($builder) {
            return $builder->__mockMethod($methodName, $flag);
        });
    }

    public function initMockModelMethods()
    {
        $this->ignoreList = array_merge($this->ignoreList, [
            'query',
            'newQuery',
            'setModel',
            'withCount',
        ]);

        $this->ignoreOnEmpty = array_merge($this->ignoreOnEmpty, [
            'with',
        ]);

        if (class_exists(\Illuminate\Database\Eloquent\Builder::class)) {
            $class   = new ReflectionClass(\Illuminate\Database\Eloquent\Builder::class);
            $methods = $class->getMethods(
                ReflectionMethod::IS_STATIC |
                ReflectionMethod::IS_PUBLIC |
                ReflectionMethod::IS_PROTECTED |
                ReflectionMethod::IS_FINAL |
                ReflectionMethod::IS_ABSTRACT
            );
            foreach ($methods as $method) {
                $this->__mockMethod($method->getName())->andReturnSelf();
            }
        }
    }
}
