<?php


namespace HungDX\MockBuilder\Generator\Pass;

use Mockery\Generator\MockConfiguration;

class MethodDefinitionPass extends \Mockery\Generator\StringManipulation\Pass\MethodDefinitionPass
{
    /**
     * Base on \Mockery\Generator\StringManipulation\Pass\MethodDefinitionPass::apply()
     *
     * @changed ignore all methods which modifier are not public or protected
     * @param $code
     * @param MockConfiguration $config
     * @return mixed|string
     */
    public function apply($code, MockConfiguration $config)
    {
        foreach ($config->getMethodsToMock() as $method) {
            if ($method->isPublic()) {
                $methodDef = 'public';
            } elseif ($method->isProtected()) {
                $methodDef = 'protected';
            }
            // ignore with other case
            else {
                continue;
            }

            if ($method->isStatic()) {
                $methodDef .= ' static';
            }

            $methodDef .= ' function ';
            $methodDef .= $method->returnsReference() ? ' & ' : '';
            $methodDef .= $method->getName();
            $methodDef .= $this->renderParams($method, $config);
            $methodDef .= $this->renderReturnType($method);
            $methodDef .= $this->renderMethodBody($method, $config);

            $code = $this->appendToClass($code, $methodDef);
        }

        return $code;
    }

    /**
     * Base on \Mockery\Generator\StringManipulation\Pass\MethodDefinitionPass::renderMethodBody()
     *
     * @changed rename callabck method to _mockBuilder_handleStaticMethodCall and _mockBuilder_handleMethodCall
     * @param $method
     * @param $config
     * @return string
     */
    private function renderMethodBody($method, $config)
    {
        $invoke = $method->isStatic() ? 'static::_mockBuilder_handleStaticMethodCall' : '$this->_mockBuilder_handleMethodCall';
        $body   = <<<BODY
{
\$argc = func_num_args();
\$argv = func_get_args();

BODY;

        // Fix up known parameters by reference - used func_get_args() above
        // in case more parameters are passed in than the function definition
        // says - eg varargs.
        $class      = $method->getDeclaringClass();
        $class_name = strtolower($class->getName());
        $overrides  = $config->getParameterOverrides();
        if (isset($overrides[$class_name][$method->getName()])) {
            $params     = array_values($overrides[$class_name][$method->getName()]);
            $paramCount = count($params);
            for ($i = 0; $i < $paramCount; ++$i) {
                $param = $params[$i];
                if (strpos($param, '&') !== false) {
                    $body .= <<<BODY
if (\$argc > $i) {
    \$argv[$i] = {$param};
}

BODY;
                }
            }
        } else {
            $params     = array_values($method->getParameters());
            $paramCount = count($params);
            for ($i = 0; $i < $paramCount; ++$i) {
                $param = $params[$i];
                if (!$param->isPassedByReference()) {
                    continue;
                }
                $body .= <<<BODY
if (\$argc > $i) {
    \$argv[$i] =& \${$param->getName()};
}

BODY;
            }
        }

        $body .= "\$ret = {$invoke}(__FUNCTION__, \$argv);\n";

        if ($method->getReturnType() !== "void") {
            $body .= "return \$ret;\n";
        }

        $body .= "}\n";
        return $body;
    }
}
