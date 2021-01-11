<?php

namespace HungDX\MockBuilder;

use HungDX\MockBuilder\Builder\Config;
use HungDX\MockBuilder\Builder\Logger;
use HungDX\MockBuilder\Builder\Method;
use HungDX\MockBuilder\Contracts\MockableInterface;
use HungDX\MockBuilder\Contracts\MockBuilderInterface;
use HungDX\MockBuilder\Generator\ClassGenerator;
use Mockery;
use Mockery\Generator\MockConfigurationBuilder;
use ReflectionClass;

class MockBuilder implements MockBuilderInterface
{
    /** @var Logger */
    private $logger;

    /** @var Method[]|array */
    private $methods = [];

    /** @var Config */
    private $config;

    /** @var Mockery\MockInterface|mixed */
    private $mock;

    /** @var Method[]|array */
    public $mockMethods = [];

    /**
     * MockQueryBuilder constructor.
     * @param Mockery\MockInterface $mock
     */
    public function __construct(Mockery\MockInterface $mock)
    {
        $this->mock   = $mock;
        $this->config = new Config();
        $this->logger = new Logger();
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getMock(): Mockery\MockInterface
    {
        return $this->mock;
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
        $mock = call_user_func_array('\Mockery::mock', func_get_args());
        self::initMock($mock);
        return $mock;
    }

    /**
     * @param string $sourceClass
     * @param string|null $sourceClassFilePath
     * @param mixed ...$targets
     * @throws \Exception
     */
    public static function overrideClass(string $sourceClass, string $sourceClassFilePath = null, ...$targets)
    {
        // Class already mocked
        if (class_exists($sourceClass, false) && (new ReflectionClass($sourceClass))->implementsInterface(MockableInterface::class)) {
            return;
        }

        // Get MockConfigBuilder
        $classGenerator = new ClassGenerator();
        foreach ($targets as $index => $target) {
            if ($target instanceof MockConfigurationBuilder) {
                $classGenerator->setConfig($target);
                unset($targets[$index]);
            }
        }

        // Set user config
        $classGenerator->setName($sourceClass);
        foreach ($targets as $index => $target) {
            $classGenerator->getConfig()->addTarget($target);
        }

        $classGenerator->createOverride($sourceClassFilePath);
    }

    /**
     * @param string $className
     * @param mixed ...$targets
     * @throws \Exception
     */
    public static function createClass(string $className, ...$targets)
    {
        if (class_exists($className, false)) {
            return;
        }

        $classGenerator = new ClassGenerator();
        foreach ($targets as $index => $target) {
            if ($target instanceof MockConfigurationBuilder) {
                $classGenerator->setConfig($target);
                unset($targets[$index]);
            }
        }

        $classGenerator->setName($className);
        foreach ($targets as $index => $target) {
            $classGenerator->getConfig()->addTarget($target);
        }

        $classGenerator->create();
    }

    public static function createClassName(string $srcClassName): string
    {
        $count        = 0;
        $srcClassName = str_replace('\\', '_', trim($srcClassName, '\\'));
        do {
            $className = '\\MockBuilder_' . (++$count) . '_' . $srcClassName;
        } while (class_exists($className));
        return $className;
    }

    /**
     * Init methods for mock
     *
     * @param Mockery\MockInterface $mock
     */
    private static function initMock(Mockery\MockInterface $mock)
    {
        $builder = new static($mock);

        $builder->getConfig()->ignoreLogOfMethodIfParametersAreEmpty('__construct');

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

        $mock->shouldReceive('__mockMethod')->andReturnUsing(function (string $methodName) use ($builder) {
            return $builder->__mockMethod($methodName);
        });

        $mock->shouldReceive('__getMockMethod')->andReturnUsing(function (string $methodName) use ($builder) {
            return $builder->__getMockMethod($methodName);
        });

        $mock->shouldReceive('__getLastMockOfMethod')->andReturnUsing(function (string $methodName) use ($builder) {
            return $builder->__getLastMockOfMethod($methodName);
        });
    }

    public function mockDatabaseQueryBuilderMethods(): self
    {
        $this->config->ignoreLogOfMethod([
            'query',
            'newQuery',
            'setModel',
            'withCount',

            '__callStatic',
            '__call',
            '__isset',
            '__unset',
            '__construct',

            'jsonSerialize',
            'forwardCallTo',
        ]);

        $this->config->ignoreLogOfMethodIfParametersAreEmpty('with');

        $this->mockClassMethods('\Illuminate\Database\Eloquent\Model');
        $this->mockClassMethods('\Illuminate\Database\Eloquent\Builder');
        $this->mockClassMethods('\Illuminate\Database\Query\Builder');

        return $this;
    }

    public function mockClassMethods(string $className): self
    {
        if (!class_exists($className)) {
            return $this;
        }

        $this->mock->shouldAllowMockingProtectedMethods();
        try {
            $class = new ReflectionClass($className);
            foreach ($class->getMethods() as $method) {
                $methodName = $method->getName();

                // If the method have been mocked, ignore that
                if ($this->__getLastMockOfMethod($methodName)) {
                    continue;
                }

                // Ignore the private/final method
                if ($method->isPrivate() || $method->isFinal()) {
                    continue;
                }

                #echo $methodName . PHP_EOL;
                $this->__mockMethod($method->getName())->andReturnSelf();
            }
        } catch (\ReflectionException $e) {
        }

        return $this;
    }

    public function __getLogs(): array
    {
        return $this->logger->getLogs();
    }

    public function __resetLogs()
    {
        $this->logger->resetLogs();
    }

    /**
     * @param string $methodName
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage|Method
     */
    public function __mockMethod(string $methodName)
    {
        $method = new Method($this, $methodName);

        $this->methods[$methodName] = $method;
        return $method;
    }

    /**
     * @param string $methodName
     * @return \Mockery\ExpectationInterface[]|\Mockery\Expectation[]|\Mockery\HigherOrderMessage[]|Method[]
     */
    public function __getMockMethod(string $methodName): array
    {
        return $this->mockMethods[$methodName] ?? [];
    }

    public function __getMockBuilder(): MockBuilder
    {
        return $this;
    }

    /**
     * @param string $methodName
     * @return \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage|Method
     */
    public function __getLastMockOfMethod(string $methodName)
    {
        if (!empty($this->mockMethods[$methodName])) {
            return end($this->mockMethods[$methodName]);
        }
        return null;
    }
}
