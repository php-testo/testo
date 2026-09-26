<?php

class PathPermissionsTest
{
    public function test()
    {
        $this->assertFileIsReadable($file);
        $this->assertFileIsWritable($file, 'writable');
        $this->assertFileIsNotReadable($file);
        $this->assertFileIsNotWritable(__DIR__ . '/locked.txt', 'locked');
        $this->assertDirectoryIsReadable($dir);
        $this->assertDirectoryIsNotReadable(self::DIR);
        $this->assertDirectoryIsWritable('/tmp');
        $this->assertDirectoryIsNotWritable(\PATH);

        // The path would be evaluated twice.
        $this->assertFileIsNotReadable($this->path());
        $this->assertDirectoryIsWritable($this->dir);
    }
}
-----
<?php

class PathPermissionsTest
{
    public function test()
    {
        \Testo\Assert::true(\is_readable($file));
        \Testo\Assert::true(\is_writable($file), 'writable');
        \Testo\Assert::true(\file_exists($file) && !\is_readable($file));
        \Testo\Assert::true(\file_exists(__DIR__ . '/locked.txt') && !\is_writable(__DIR__ . '/locked.txt'), 'locked');
        \Testo\Assert::true(\is_dir($dir) && \is_readable($dir));
        \Testo\Assert::true(\is_dir(self::DIR) && !\is_readable(self::DIR));
        \Testo\Assert::true(\is_dir('/tmp') && \is_writable('/tmp'));
        \Testo\Assert::true(\is_dir(\PATH) && !\is_writable(\PATH));

        // The path would be evaluated twice.
        $this->assertFileIsNotReadable($this->path());
        $this->assertDirectoryIsWritable($this->dir);
    }
}
