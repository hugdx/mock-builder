<?php
/**
 * Base on Mockery\Generator\ClassNamePass
 */

namespace HungDX\MockBuilder\Generator\Pass;


use Mockery\Generator\MockConfiguration;
use Mockery\Generator\StringManipulation\Pass\Pass;

class ClassNamePass implements Pass
{
    public function apply($code, MockConfiguration $config)
    {
        $namespace = $config->getNamespaceName();

        $namespace = ltrim($namespace, "\\");

        $className = $config->getShortName();

        $code = str_replace(
            'namespace HungDX\MockBuilder\Generator;',
            $namespace ? 'namespace ' . $namespace . ';' : '',
            $code
        );

        $code = str_replace(
            'class Mockable',
            'class ' . $className,
            $code
        );

        return $code;
    }
}
