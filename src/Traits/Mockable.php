<?php

namespace HungDX\MockBuilder\Traits;

use HungDX\MockBuilder\Mock\MockBuilder;
use HungDX\MockBuilder\Mock\MockBuilderInterface;

trait Mockable
{
    /** @var MockBuilderInterface[]|\Mockery\MockInterface[]|null[] */
    public static $mocks = [];

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
        if (in_array($method, ['getMock'])) {
            return static::getMock();
        }

        $mock = static::getMock();
        if ($mock) {
            return call_user_func_array([$mock, $method], $params);
        }
        return parent::__callStatic($method, $params);
    }

    public function __call($method, $params)
    {
        $mock = static::getMock();
        if ($mock) {
            return call_user_func_array([$mock, $method], $params);
        }
        return parent::__call($method, $params);
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
