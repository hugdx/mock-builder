<?php


namespace HungDX\MockBuilder\Integration;


use Illuminate\Database\Eloquent\Model;

/**
 * Class ModelMockable
 * @package HungDX\MockBuilder\Integration
 * @method void __resetLogs()
 * @method \Mockery\ExpectationInterface|\Mockery\Expectation|\Mockery\HigherOrderMessage __mockMethod(string $methodName)
 * @method \Mockery\ExpectationInterface[]|\Mockery\Expectation[]|\Mockery\HigherOrderMessage[] __getMockMethod()
 * @method \HungDX\MockBuilder\MockBuilder __getMockBuilder()
 *
 * @method static \HungDX\MockBuilder\Contracts\MockBuilderInterface|\Mockery\MockInterface fake()
 * @method static void restoreOriginal()
 * @method static \HungDX\MockBuilder\Contracts\MockBuilderInterface|\Mockery\MockInterface|null getMock()
 * @method static void setMock($mock)
 */
class ModelMockable extends Model
{

}
