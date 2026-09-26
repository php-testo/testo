<?php

class TypeChecksTest
{
    public function test()
    {
        $this->assertIsString($name);
        $this->assertIsArray($rows, 'rows must be an array');
        $this->assertIsCallable($callback);
        $this->assertIsCallable($factory, 'factory must be callable');
        $this->assertIsNotCallable($value);
        $this->assertIsBool($flag);
        $this->assertIsBool($enabled, 'enabled must be a bool');
        $this->assertIsNotBool($value);
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
        \Testo\Assert::callable($callback);
        \Testo\Assert::true(\is_callable($factory), 'factory must be callable');
        \Testo\Assert::false(\is_callable($value));
        \Testo\Assert::bool($flag);
        \Testo\Assert::true(\is_bool($enabled), 'enabled must be a bool');
        \Testo\Assert::false(\is_bool($value));
        \Testo\Assert::false(\is_int($value), 'not an int');
        \Testo\Assert::true(\file_exists($path));
        \Testo\Assert::false(\is_dir($dir));
        \Testo\Assert::string($message)->startsWith('Name must');
        \Testo\Assert::string($file)->endsWith('.json', 'suffix');
    }
}
