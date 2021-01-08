<?php
/**
 * Base on Mockery\Generator\ClassPass
 */

namespace HungDX\MockBuilder\Generator\Pass;

use Mockery\Generator\MockConfiguration;
use Mockery\Generator\StringManipulation\Pass\Pass;

class ClassPass implements Pass
{
    /**
     * @param $code
     * @param MockConfiguration $config
     * @return string|string[]
     * @throws \Exception
     */
    public function apply($code, MockConfiguration $config)
    {
        $target = $config->getTargetClass();

        if (!$target) {
            return $code;
        }

        $className = ltrim($target->getName(), "\\");

        if ($target->isFinal()) {
            throw new \Exception('Unable extends class ' . $className . '. The class '. $className .' is final');
        }

        if (!class_exists($className)) {
            throw new \Exception('Class ' . $className . ' not found');
        }

        $code = str_replace(
            'implements MockableInterface',
            'extends \\' . $className . ' implements MockableInterface',
            $code
        );

        return $code;
    }
}
