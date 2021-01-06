<?php


namespace HungDX\MockBuilder\Traits;


use HungDX\MockBuilder\Mock\MockBuilder;
use HungDX\MockBuilder\Mock\MockBuilderInterface;

trait ModelMockable
{
    use Mockable;

    /**
     * @return MockBuilderInterface|\Mockery\MockInterface
     */
    public static function fake()
    {
        if (!isset(self::$mocks[static::class])) {
            self::$mocks[static::class] = MockBuilder::create(static::class);
            self::$mocks[static::class]->initMockModelMethods();
        }
        return self::$mocks[static::class];
    }

    /**
     * @return \HungDX\MockBuilder\Mock\MockBuilderInterface|\Mockery\MockInterface|static
     */
    public function newQuery()
    {
        return self::getMock() ?: parent::newQuery();
    }

    /**
     * @return \HungDX\MockBuilder\Mock\MockBuilderInterface|\Mockery\MockInterface|static
     */
    public static function query()
    {
        return self::getMock() ?: parent::query();
    }
}
