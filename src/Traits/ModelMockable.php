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
            self::$mocks[static::class]->__getMockBuilder()->mockClassMethods(\Illuminate\Database\Eloquent\Model::class);
            self::$mocks[static::class]->__getMockBuilder()->mockClassMethods(\Illuminate\Database\Eloquent\Builder::class);
            self::$mocks[static::class]->__getMockBuilder()->mockClassMethods(\Illuminate\Database\Query\Builder::class);
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
