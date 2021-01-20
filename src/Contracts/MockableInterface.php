<?php


namespace HungDX\MockBuilder\Contracts;

/**
 * Interface MockableInterface
 * @package HungDX\MockBuilder\Contracts
 */
interface MockableInterface
{
    /** @return MockBuilderInterface|\Mockery\MockInterface */
    public static function fake();

    public static function restoreOriginal($includeChildClass = false);
}
