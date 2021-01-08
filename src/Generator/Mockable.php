<?php

namespace HungDX\MockBuilder\Generator;

use HungDX\MockBuilder\Contracts\MockableInterface;
use HungDX\MockBuilder\Contracts\MockBuilderInterface;
use HungDX\MockBuilder\MockBuilder;

class Mockable implements MockableInterface
{
    /** @var MockBuilderInterface[]|\Mockery\MockInterface[]|null[] */
    public static $mocks = [];

    /** @var null|MockBuilderInterface|\Mockery\MockInterface */
    private $mock = null;

    /** @return MockBuilderInterface|\Mockery\MockInterface */
    public static function fake()
    {
        if (!isset(self::$mocks[static::class])) {
            $parentClass                = get_parent_class(self::class);
            self::$mocks[static::class] = MockBuilder::create($parentClass);
            self::$mocks[static::class]->__getMockBuilder()->mockClassMethods($parentClass);

            // Dynamic added by Generator
            if (defined(self::class . '::MOCK_CLASS_METHODS')) {
                foreach (self::MOCK_CLASS_METHODS as $classToMockMethods) {
                    self::$mocks[static::class]->__getMockBuilder()->mockClassMethods($classToMockMethods);
                }
            }
        }
        return self::$mocks[static::class];
    }

    public static function restoreOriginal()
    {
        if (isset(self::$mocks[static::class])) {
            unset(self::$mocks[static::class]);
        }
    }

    /**
     * @param MockBuilderInterface|\Mockery\MockInterface $mock
     * @throws \Exception
     */
    public static function setMock($mock)
    {
        if (!$mock instanceof \Mockery\MockInterface) {
            throw new \Exception('Invalid mock');
        }
        self::$mocks[static::class] = $mock;
    }

    /** @return MockBuilderInterface|\Mockery\MockInterface|null */
    public static function getMock()
    {
        $class = static::class;
        do {
            if (isset(self::$mocks[$class])) {
                return self::$mocks[$class];
            }
            $class = get_parent_class($class);
        } while ($class);
        return null;
    }

    public function _mockBuilder_setMock($mock)
    {
        $this->mock = $mock;
    }

    public function __call($method, $parameters)
    {
        return $this->_mockBuilder_handleMethodCall($method, $parameters);
    }

    public static function __callStatic($method, $parameters)
    {
        return static::_mockBuilder_handleStaticMethodCall($method, $parameters);
    }

    public static function _mockBuilder_handleStaticMethodCall($method, $parameters)
    {
        $mock = static::getMock();
        if ($mock) {
            if (!$mock->__getMockBuilder()->__getMockMethod($method)) {
                $mock->__getMockBuilder()->__mockMethod($method)->andReturnSelf();
            }

            return call_user_func_array([$mock, $method], $parameters);
        }

        // echo ' ==> static call: ' . $method . ' <=== ' . PHP_EOL;

        if (is_callable('parent::' . $method)) {
            return call_user_func_array('parent::' . $method, $parameters);
        }

        if (is_callable('parent::__callStatic' . $method)) {
            return call_user_func_array('parent::__callStatic', [$method, $parameters]);
        }

        return null;
    }

    public function _mockBuilder_handleMethodCall($method, $parameters)
    {
        if ($method === '__construct') {
            $this->mock = static::getMock();
        }

        if ($this->mock) {
            if (!$this->mock->__getMockBuilder()->__getMockMethod($method)) {
                $this->mock->__getMockBuilder()->__mockMethod($method)->andReturnSelf();
            }
            // echo ' ==> method call: ' . $method . ' <=== ' . PHP_EOL;
            return call_user_func_array([$this->mock, $method], $parameters);
        }

        // echo get_class($this) . '  ==> method call: ' . $method . ' <=== ' . PHP_EOL;

        if (get_parent_class($this)) {
            $parentClass = get_parent_class($this);

            if (method_exists($parentClass, $method)) {
                return call_user_func_array('parent::' . $method, $parameters);
            }

            if (is_callable('parent::__call')) {
                return call_user_func('parent::__call', $method, $parameters);
            }
        }

        return null;
    }
}
