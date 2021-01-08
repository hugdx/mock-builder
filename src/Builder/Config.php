<?php


namespace HungDX\MockBuilder\Builder;

class Config
{
    private $logIgnoreMethods = [];

    private $logIgnoreMethodsOnEmpty = [];

    public function ignoreLogOfMethod(...$methods): self
    {
        foreach ($methods as $name) {
            $list                   = is_array($name) ? $name : [$name];
            $this->logIgnoreMethods = array_merge($this->logIgnoreMethods, $list);
        }
        $this->logIgnoreMethods = array_unique($this->logIgnoreMethods);

        return $this;
    }

    public function captureLogOfMethod(string $methodName): self
    {
        $index = array_search($methodName, $this->logIgnoreMethods);
        if ($index !== false) {
            array_splice($this->logIgnoreMethods, $index, 1);
        }
        return $this;
    }

    public function shouldCaptureLogOfMethod(string $methodName, $parameters = null): bool
    {
        // Method in ignore list -> false
        if (in_array($methodName, $this->logIgnoreMethods)) {
            return false;
        }

        // Method in ignore on empty list and parameters are empty -> false
        if (in_array($methodName, $this->logIgnoreMethodsOnEmpty) && $this->isObjectEmpty($parameters)) {
            return false;
        }

        // Ok fine, just capture that
        return true;
    }

    public function ignoreLogOfMethodIfParametersAreEmpty(string $methodName): self
    {
        if (!in_array($methodName, $this->logIgnoreMethodsOnEmpty)) {
            $this->logIgnoreMethodsOnEmpty[] = $methodName;
        }
        return $this;
    }

    public function captureLogOfMethodIfParametersAreEmpty(string $methodName): self
    {
        $index = array_search($methodName, $this->logIgnoreMethodsOnEmpty);
        if ($index !== false) {
            array_splice($this->logIgnoreMethodsOnEmpty, $index, 1);
        }
        return $this;
    }


    /**
     * Is object empty or not. An Object called empty if they are: `(object) array()` or `(object) array(array())`
     * @param $object
     * @return bool
     */
    private function isObjectEmpty($object): bool
    {
        // Parse object to array
        $objectValue = json_decode(json_encode($object), true);

        // Case array is: []
        if (empty($objectValue)) {
            return true;
        }

        // Case array is: [[]]
        if (count($objectValue) === 1 && isset($objectValue[0]) && empty($objectValue[0])) {
            return true;
        }

        // Other cases
        return false;
    }
}
