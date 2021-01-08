<?php
/**
 * Base on Mockery\Generator\InterfacePass
 */


namespace HungDX\MockBuilder\Generator\Pass;

use Mockery\Generator\MockConfiguration;
use Mockery\Generator\StringManipulation\Pass\Pass;

class InterfacePass implements Pass
{
    public function apply($code, MockConfiguration $config)
    {
        foreach ($config->getTargetInterfaces() as $i) {
            $name = ltrim($i->getName(), "\\");
            if (!interface_exists($name)) {
                throw new \Exception('Interface ' . $name . ' not found');
            }
        }

        $interfaces = array_reduce((array) $config->getTargetInterfaces(), function ($code, $i) {
            return $code . ", \\" . ltrim($i->getName(), "\\");
        }, "");

        $code = str_replace(
            'implements MockableInterface',
            'implements MockableInterface' . $interfaces,
            $code
        );

        return $code;
    }
}
