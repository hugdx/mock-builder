<?php


namespace HungDX\MockBuilder\Contracts;

interface MockableInterface
{
    /** @return MockBuilderInterface|\Mockery\MockInterface */
    public static function fake();

    public static function restoreOriginal();

    /** @return MockBuilderInterface|\Mockery\MockInterface|null */
    public static function getMock();

    /** @param MockBuilderInterface|\Mockery\MockInterface $mock */
    public static function setMock($mock);
}
