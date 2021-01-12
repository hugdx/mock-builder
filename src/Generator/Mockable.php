<?php

namespace HungDX\MockBuilder\Generator;

use HungDX\MockBuilder\Contracts\MockableInterface;
use HungDX\MockBuilder\Contracts\MockBuilderInterface;
use HungDX\MockBuilder\MockBuilder;
use Mockery\Exception\BadMethodCallException;

/**
 * Class Mockable
 * @package HungDX\MockBuilder\Generator
 */
class Mockable implements MockableInterface
{
    /** @var MockBuilderInterface[]|\Mockery\MockInterface[]|null[] */
    public static $mocks = [];

    /** @var null|MockBuilderInterface|\Mockery\MockInterface */
    private $mock = null;

    /** @var null|callable */
    public static $onCreateMock = null;

    /** @return MockBuilderInterface|\Mockery\MockInterface */
    public static function fake()
    {
        if (!isset(self::$mocks[static::class])) {
            $parentClass                = get_parent_class(self::class);
            self::$mocks[static::class] = MockBuilder::create(static::class);
            self::$mocks[static::class]->__getMockBuilder()->mockClassMethods($parentClass);

            // Dynamic added by Generator
            if (defined(static::class . '::MOCK_CLASS_METHODS')) {
                foreach (static::MOCK_CLASS_METHODS as $classToMockMethods) {
                    self::$mocks[static::class]->__getMockBuilder()->mockClassMethods($classToMockMethods);
                }
            }

            if (isset(static::$onCreateMock) && is_callable(static::$onCreateMock)) {
                call_user_func(static::$onCreateMock, self::$mocks[static::class]);
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

    public function __call($method, $parameters)
    {
        return $this->_mockBuilder_handleMethodCall($method, $parameters);
    }

    public static function __callStatic($method, $parameters)
    {
        return static::_mockBuilder_handleStaticMethodCall($method, $parameters);
    }

    /** @return MockBuilderInterface|\Mockery\MockInterface|null */
    private static function _mockBuilder_getMock()
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

    private static function _mockBuilder_handleStaticMethodCall($method, $parameters)
    {
        switch ($method) {
            case 'setMock':
                if (!isset($parameters[0]) || !$parameters[0] instanceof \Mockery\MockInterface) {
                    throw new \BadMethodCallException('The parameter should be instance of \Mockery\MockInterface');
                }
                self::$mocks[static::class] = $parameters[0];
                return $parameters[0];
            case 'getMock':
                return static::_mockBuilder_getMock();
        }

        $mock = static::_mockBuilder_getMock();
        if ($mock) {
            if (!$mock->__getMockBuilder()->__getMockMethod($method)) {
                $mock->__getMockBuilder()->__mockMethod($method)->andReturnSelf();
            }

            # echo ' [mock]  ==> static call: ' . $method . ' <=== ' . PHP_EOL;
            return call_user_func_array([$mock, $method], $parameters);
        }

        # echo ' ==> static call: ' . $method . ' <=== ' . PHP_EOL;

        if (is_callable('parent::' . $method)) {
            return call_user_func_array('parent::' . $method, $parameters);
        }

        if (is_callable('parent::__callStatic' . $method)) {
            return call_user_func_array('parent::__callStatic', [$method, $parameters]);
        }

        throw new BadMethodCallException('Static method ' . $method . ' not found in class ' . get_called_class());
    }

    private function _mockBuilder_handleMethodCall($method, $parameters)
    {
        switch ($method) {
            case '__construct':
                $this->mock = static::_mockBuilder_getMock();
                break;
            case 'getMock':
                return $this->mock;
            case 'setMock':
                if (!isset($parameters[0]) || !$parameters[0] instanceof \Mockery\MockInterface) {
                    throw new BadMethodCallException('The first parameter must me instanceof MockInterface');
                }
                $this->mock = $parameters[0];
                return $this->mock;
        }

        if ($this->mock) {
            if (!$this->mock->__getMockBuilder()->__getMockMethod($method)) {
                $this->mock->__getMockBuilder()->__mockMethod($method)->andReturnSelf();
            }
            # echo ' [mock] ==> method call: ' . get_class($this) . '->' . $method . ' <=== ' . PHP_EOL;
            # echo '     -> CAll mock: ' . get_class($this->mock) . '->' . $method . PHP_EOL;

            return call_user_func_array([$this->mock, $method], $parameters);
        }

        # echo '  ==> method call: ' . get_class($this)  . '->'. $method . ' <=== ' . PHP_EOL;

        if (get_parent_class($this)) {
            $parentClass = get_parent_class($this);

            if (method_exists($parentClass, $method)) {
                return call_user_func_array('parent::' . $method, $parameters);
            }

            if (is_callable('parent::__call')) {
                return call_user_func('parent::__call', $method, $parameters);
            }
        }

        throw new BadMethodCallException('Method ' . $method . ' not found in class ' . get_class($this));
    }
}
