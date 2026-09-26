<?php

class TypeChecksTest
{
    public function test()
    {
        $this->assertIsString($name);
        $this->assertIsArray($rows, 'rows must be an array');
        $this->assertIsCallable($callback);
        $this->assertIsNotInt($value, 'not an int');
        $this->assertFileExists($path);
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertStringStartsWith('Name must', $message);
        $this->assertStringEndsWith('.json', $file, 'suffix');
    }
}
-----
<?php

class TypeChecksTest
{
    public function test()
    {
        \Testo\Assert::string($name);
        \Testo\Assert::true(\is_array($rows), 'rows must be an array');
        \Testo\Assert::true(\is_callable($callback));
        \Testo\Assert::false(\is_int($value), 'not an int');
        \Testo\Assert::true(\file_exists($path));
        \Testo\Assert::false(\is_dir($dir));
        \Testo\Assert::string($message)->startsWith('Name must');
        \Testo\Assert::string($file)->endsWith('.json', 'suffix');
    }
}
