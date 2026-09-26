<?php

class ContainsListPropertyTest
{
    public function test()
    {
        $this->assertStringContainsString('needle', $haystack);
        $this->assertStringNotContainsString('error', $output, 'no errors');
        $this->assertNotContains($removed, $items);
        self::assertNotContains('x', $letters, 'x is gone');
        $this->assertObjectHasProperty('id', $entity, 'has an id');
        $this->assertIsList($rows);
        $this->assertIsList($rows, 'sequential keys');
    }
}
-----
<?php

class ContainsListPropertyTest
{
    public function test()
    {
        \Testo\Assert::string($haystack)->contains('needle');
        \Testo\Assert::string($output)->notContains('error', 'no errors');
        \Testo\Assert::iterable($items)->notContains($removed);
        \Testo\Assert::iterable($letters)->notContains('x', 'x is gone');
        \Testo\Assert::object($entity)->hasProperty('id', 'has an id');
        \Testo\Assert::array($rows)->isList();
        \Testo\Assert::array($rows)->isList('sequential keys');
    }
}
