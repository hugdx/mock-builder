<?php


namespace HungDX\MockBuilder\Generator;

use HungDX\MockBuilder\MockBuilder;
use Mockery;
use Mockery\Generator\MockConfigurationBuilder;
use Mockery\Generator\MockDefinition;
use RuntimeException;

class ClassGenerator
{
    const TOKEN_ID_INDEX    = 0;
    const TOKEN_VALUE_INDEX = 1;

    /** @var MockConfigurationBuilder */
    private $config;

    /** @var array */
    private $classToMock = [];

    public function __construct(MockConfigurationBuilder $config = null)
    {
        $this->config = $config ?: new MockConfigurationBuilder();
    }

    /**
     * @throws \Exception
     */
    public function create()
    {
        $configBuilder = clone $this->config;
        $this->addConstants($configBuilder);

        $def = MockableGenerator::withDefaultPasses()->generate($configBuilder->getMockConfiguration());
        $this->throwExceptionIfClassExist($def->getClassName());

        # file_put_contents(__DIR__ . '/dynamic_' . str_replace('\\', '_', $def->getClassName()).'.php', $def->getCode());

        Mockery::getLoader()->load($def);
    }

    /**
     * The ideal:
     *  1. Rename source class name to Mockery_${count}_SourceClassName
     *  2. Create new class with:
     *      a. Class name is SourceClassName and extends class created from #1
     *      b. Class can be mockable
     * @param string|null $sourceClassFilePath
     * @throws \Exception
     */
    public function createOverride(string $sourceClassFilePath = null)
    {
        // Source class should not exists
        $sourceConfig    = (clone $this->config)->getMockConfiguration();
        $sourceClassName = $sourceConfig->getName();
        $this->throwExceptionIfClassExist($sourceClassName);

        // Target class should not exist
        $targetClassName = $sourceConfig->getNamespaceName() . MockBuilder::createClassName($sourceConfig->getName());
        $this->throwExceptionIfClassExist($targetClassName);

        // 1. Rename source class
        $realPath = $this->getFilePathHoldClass($sourceConfig->getName(), $sourceClassFilePath);
        $code     = $this->renameClass(file_get_contents($realPath), $sourceConfig->getName(), $targetClassName);

        $sourceConfig = $sourceConfig->rename($targetClassName);
        $sourceDef    = new MockDefinition($sourceConfig, $code);
        # file_put_contents(__DIR__ . '/dynamic_' . str_replace('\\', '_', $sourceDef->getClassName()).'.php', $sourceDef->getCode());
        Mockery::getLoader()->load($sourceDef);

        // 2. Create new class
        $targetConfigBuilder = (clone $this->config)
            ->setName($sourceClassName)
            ->addTarget($targetClassName);
        $this->addConstants($targetConfigBuilder);
        $targetDef = MockableGenerator::withDefaultPasses()->generate($targetConfigBuilder->getMockConfiguration());
        # file_put_contents(__DIR__ . '/dynamic_' . str_replace('\\', '_', $targetDef->getClassName()).'.php', $targetDef->getCode());
        Mockery::getLoader()->load($targetDef);
    }

    public function getConfig(): MockConfigurationBuilder
    {
        return $this->config;
    }

    public function setConfig(MockConfigurationBuilder $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function setName(string $className): self
    {
        $this->config->setName($className);
        return $this;
    }

    public function setMockMethodsOfClass(array $classList): self
    {
        $this->classToMock = $classList;
        return $this;
    }

    private function addConstants(MockConfigurationBuilder $configBuilder)
    {
        $config    = $configBuilder->getMockConfiguration();
        $constants = $config->getConstantsMap();
        if (!isset($constants[$config->getName()])) {
            $constants[$config->getName()] = [];
        }
        $constants[$config->getName()]['MOCK_CLASS_METHODS'] = $this->classToMock;
        $configBuilder->setConstantsMap($constants);
    }

    private function getFilePathHoldClass(string $className, string $filePath = null): string
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

    private function renameClass(string $code, string $sourceClassName, string $destinationClassName): string
    {
        $destinationConfig = (new MockConfigurationBuilder())
            ->setName($destinationClassName)
            ->getMockConfiguration();

        $sourceConfig = (new MockConfigurationBuilder())
            ->setName($sourceClassName)
            ->getMockConfiguration();

        $tokens   = token_get_all($code);
        $code     = '';
        $replaced = false;

        while (!empty($tokens)) {
            $token   = array_shift($tokens);
            $tokenId = is_array($token) ? $token[self::TOKEN_ID_INDEX] : false;

            switch ($tokenId) {
                case T_NAMESPACE:
                    $namespace = $this->extractNamespace($tokens);

                    if ($namespace !== $sourceConfig->getNamespaceName()) {
                        throw new RuntimeException('Namespace not match. Expected ' . $sourceConfig->getNamespaceName() . ', got ' . $namespace);
                    }

                    // Case original namespace not same with destination namespace
                    if ($sourceConfig->getNamespaceName() !== $destinationConfig->getNamespaceName()) {
                        if ($destinationConfig->getNamespaceName()) {
                            $code .= sprintf('namespace %s;', $destinationConfig->getNamespaceName());
                        }
                        $code .= PHP_EOL . sprintf('use %s;', $sourceConfig->getNamespaceName());
                    } else { // Namespace is match
                        $code .= sprintf('namespace %s;', $namespace);
                    }
                    break;

                case T_CLASS:
                    // TypeHint: Class in parameter OR before call static method
                    if (empty($tokens) || !is_array($tokens[0])) {
                        $code .= $token[self::TOKEN_VALUE_INDEX];
                        break;
                    }

                    $className = $this->extractClassName($tokens);

                    // Replace current class name to new class name
                    if ($className === $sourceConfig->getShortName()) {
                        $className = $destinationConfig->getShortName();
                        $replaced  = true;
                    }

                    $code .= "class " . $className . ' ';
                    break;

                default: // Other case: just keep same as current
                    $code .= is_string($token) ? $token : $token[self::TOKEN_VALUE_INDEX];
                    break;
            }
        }

        if (!$replaced) {
            throw new \Exception('Class '. $sourceClassName. ' not contain in file ');
        }

        // In case source haven't got namespace, but target have got namespace -> add namespace
        if (empty($sourceConfig->getNamespaceName()) && $destinationConfig->getNamespaceName()) {
            $firstPHPOpenTag = stripos($code, '<?php');
            $namespace       = sprintf('namespace %s;', $destinationConfig->getNamespaceName());
            if ($firstPHPOpenTag === 0) {
                $code = '<?php' . PHP_EOL . $namespace . PHP_EOL . substr($code, 5);
            } else {
                $code = '<?php' . PHP_EOL . $namespace . PHP_EOL . ' ?>'. $code;
            }
        }

        return $code;
    }

    private function extractClassName(&$tokens): string
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
                return trim($className);
            }
        }

        return trim($className);
    }

    private function extractNamespace(&$tokens): string
    {
        $namespace         = '';
        while (!empty($tokens)) {
            $token = array_shift($tokens);
            if (is_string($token) && $token === ';') {
                return trim($namespace);
            }
            $namespace .= $token[self::TOKEN_VALUE_INDEX];
        }

        return trim($namespace);
    }

    private function throwExceptionIfClassExist(string $className, $messages = 'Class %s exists')
    {
        if (empty($className)) {
            throw new \Exception('Class name empty');
        }

        if (class_exists($className, false)) {
            throw new \Exception(sprintf($messages, $className));
        }
    }
}
