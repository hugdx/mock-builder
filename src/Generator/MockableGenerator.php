<?php
/**
 * Base on Mockery\Generator\StringManipulationGenerator
 */

namespace HungDX\MockBuilder\Generator;

use Mockery\Generator\MockConfiguration;
use Mockery\Generator\MockDefinition;
use Mockery\Generator\StringManipulation\Pass\ConstantsPass;
use Mockery\Generator\StringManipulationGenerator;

class MockableGenerator extends StringManipulationGenerator
{
    public static function withDefaultPasses()
    {
        return new static([
            new Pass\ClassNamePass(),
            new Pass\ClassPass(),
            new Pass\InterfacePass(),
            new Pass\MethodDefinitionPass(),
            new ConstantsPass(),
        ]);
    }

    public function generate(MockConfiguration $config)
    {
        $code      = file_get_contents(__DIR__ . '/Mockable.php');
        $className = $config->getName() ?: $config->generateName();

        $namedConfig = $config->rename($className);

        foreach ($this->passes as $pass) {
            $code = $pass->apply($code, $namedConfig);
        }

        // file_put_contents(__DIR__. '/' . $config->getName() . '.dynamic.php', $code);

        return new MockDefinition($namedConfig, $code);
    }
}
