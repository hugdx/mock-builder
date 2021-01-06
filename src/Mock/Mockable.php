<?php

namespace HungDX\MockBuilder\Mock;

trait Mockable
{
    /** @var MockBuilderInterface[]|\Mockery\MockInterface[]|null[] */
    private static $mocks = [];

    /**
     * @return MockBuilderInterface|\Mockery\MockInterface
     */
    public static function fake()
    {
        if (!isset(self::$mocks[static::class])) {
            self::$mocks[static::class] = MockBuilder::create(static::class);
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
     * @return MockBuilderInterface|\Mockery\MockInterface|null
     */
    public static function getMock()
    {
        return self::$mocks[static::class] ?? self::$mocks[self::class] ?? null;
    }

    public static function __callStatic($method, $params)
    {
        $mock = static::getMock();
        if ($mock) {
            return call_user_func_array([$method, $mock], $params);
        }
        return parent::__callStatic($method, $params);
    }

    public function __call($method, $params)
    {
        $mock = static::getMock();
        if ($mock) {
            return call_user_func_array([$method, $mock], $params);
        }
        return parent::__call($method, $params);
    }

    public function __get($key)
    {
        $mock = static::getMock();
        return $mock ? $mock->{$key} : parent::__get($key);
    }

    public function __set($name, $value)
    {
        $mock = static::getMock();
        if ($mock) {
            $mock->{$name} = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    public function __isset($name)
    {
        $mock = static::getMock();
        return $mock ? isset($mock->{$name}) : parent::__isset($name);
    }

    public function __unset($name)
    {
        $mock = static::getMock();
        if ($mock) {
            unset($mock->{$name});
        } else {
            parent::__unset($name);
        }
    }
}
