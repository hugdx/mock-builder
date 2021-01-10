<?php


namespace HungDX\MockBuilder\Generator;

use Mockery;
use Mockery\Generator\MockConfigurationBuilder;
use RuntimeException;

class ClassGenerator
{
    const TOKEN_ID_INDEX    = 0;
    const TOKEN_VALUE_INDEX = 1;

    /**
     * @param string $className
     * @param mixed ...$targets
     * @return Mockery\Generator\MockDefinition|null
     */
    public static function createClass(string $className, ...$targets)
    {
        if (class_exists($className, false)) {
            return null;
        }

        $builder = new MockConfigurationBuilder();
        foreach ($targets as $index => $arg) {
            if ($arg instanceof MockConfigurationBuilder) {
                $builder = $arg;
                unset($targets[$index]);
            }
        }

        $builder->setName($className);
        foreach ($targets as $arg) {
            $builder->addTarget($arg);
        }

        $config = $builder->getMockConfiguration();
        $def    = MockableGenerator::withDefaultPasses()->generate($config);

        if (class_exists($def->getClassName(), false)) {
            return null;
        }

        return $def;
    }

    public static function createUniqueClassName(string $srcClassName): string
    {
        $count        = 0;
        $srcClassName = str_replace('\\', '_', trim($srcClassName, '\\'));
        do {
            $className = '\\MockBuilder_' . (++$count) . '_' . $srcClassName;
        } while (class_exists($className));
        return $className;
    }

    public static function overrideClass(string $className, string $newClassName = null, string $filePath = null)
    {
        if (class_exists($className, false)) {
            throw new RuntimeException('Unable to mock class '. $className. '. Class exists');
        }

        // Rename class and load it
        $realPath     = self::getFilePathHoldClass($className, $filePath);
        $newClassName = $newClassName ?: self::createUniqueClassName($className);
        $code         = file_get_contents($realPath);
        $code         = self::renameClass($code, $className, $newClassName);

        $config = new MockConfigurationBuilder();
        $config->setName($newClassName);

        return new Mockery\Generator\MockDefinition(
            $config->getMockConfiguration(),
            $code
        );
    }

    private static function getFilePathHoldClass(string $className, string $filePath = null): string
    {
        // Get file path from composer autoload
        if (!$filePath) {
            /* @var $loader \Composer\Autoload\ClassLoader */
            $loader   = require __DIR__ . '/../../vendor/autoload.php';
            $filePath = $loader->findFile($className) ?: '';
            if (!$filePath) {
                throw new RuntimeException("Cannot find file for class '$className'");
            }
        }

        // Check file exist
        $realPath = realpath($filePath);
        if (!$realPath) {
            throw new RuntimeException("File '$filePath' found for class '$className' does not exists");
        }

        return $realPath;
    }

    private static function renameClass(string $code, string $srcClassName, &$desClassName): string
    {
        $srcClassName    = self::getClassNameWithFullNamespace('', $srcClassName);
        $desClassName    = self::getClassNameWithFullNamespace('', $desClassName);

        $tokens          = token_get_all($code);
        $namespace       = '';
        $code            = '';
        $replaced        = false;

        while (!empty($tokens)) {
            $token   = array_shift($tokens);
            $tokenId = is_array($token) ? $token[self::TOKEN_ID_INDEX] : false;

            switch ($tokenId) {
                case T_NAMESPACE:
                    $namespace = self::extractNamespace($tokens);
                    $code .= $token[self::TOKEN_VALUE_INDEX] . $namespace . ';';
                    break;

                case T_CLASS:
                    // TypeHint: Class in parameter OR before call static method
                    if (empty($tokens) || !is_array($tokens[0])) {
                        $code .= $token[self::TOKEN_VALUE_INDEX];
                        break;
                    }

                    // Declare class
                    $className = self::extractClassName($tokens);
                    $className = self::getClassNameWithFullNamespace($namespace, $className);

                    // Replace current class name to new class name
                    if ($className === $srcClassName) {
                        $targetClassName = self::getClassNameWithFullNamespace($namespace, $desClassName);
                        $className       = $targetClassName;
                        $replaced        = true;
                    }

                    var_dump($className);
                    $code .= "class " . $className . ' ';
                    break;

                default: // Other case: just keep same as current
                    $code .= is_string($token) ? $token : $token[self::TOKEN_VALUE_INDEX];
                    break;
            }
        }

        if (!$replaced) {
            throw new \Exception('Class '. $srcClassName. ' not contain in file ');
        }

        return $code;
    }

    private static function getClassNameWithFullNamespace(string $namespace, string $className): string
    {
        $namespace = trim($namespace, "\r\n\t \\");
        $className = trim($className, "\r\n\t \\");

        if (empty($namespace)) {
            return $className;
        }

        return $namespace .'\\'. $className;
    }

    private static function extractClassName(&$tokens): string
    {
        $className = '';
        while (!empty($tokens)) {
            $token = array_shift($tokens);

            if (is_string($token)) {
                $className .= $token;
                continue;
            }

            $className .= $token[self::TOKEN_VALUE_INDEX];
            if ($token[self::TOKEN_ID_INDEX] === T_WHITESPACE && trim($className)) {
                return $className;
            }
        }
    }

    private static function extractNamespace(&$tokens): string
    {
        $namespace         = '';
        while (!empty($tokens)) {
            $token = array_shift($tokens);
            if (is_string($token) && $token === ';') {
                return $namespace;
            }
            $namespace .= $token[self::TOKEN_VALUE_INDEX];
        }
    }
}
