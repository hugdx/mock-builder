<?php


namespace HungDX\MockBuilder\Contracts;

/**
 * Interface MockableInterface
 * @package HungDX\MockBuilder\Contracts
 * @method MockBuilderInterface|\Mockery\MockInterface|null setMock(\Mockery\MockInterface $mock)
 * @method MockBuilderInterface|\Mockery\MockInterface|null getMock
 */
interface MockableInterface
{
    /** @return MockBuilderInterface|\Mockery\MockInterface */
    public static function fake();

    public static function restoreOriginal();
}
