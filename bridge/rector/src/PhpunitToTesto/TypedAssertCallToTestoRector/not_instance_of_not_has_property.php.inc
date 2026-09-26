<?php

interface NotInstanceOfContract {}

trait NotInstanceOfTrait {}

class NotInstanceOfNotHasPropertyTest
{
    public function test(\stdClass $entity, object $any, mixed $unknown, string $className)
    {
        $this->assertNotInstanceOf(\DateTimeInterface::class, $value);
        $this->assertNotInstanceOf(NotInstanceOfContract::class, $value, 'no contract');
        $this->assertNotInstanceOf('\ArrayObject', $value);

        // PHPUnit throws for an unknown class or a trait; a dynamic name is never checked here.
        $this->assertNotInstanceOf('Missing\Type', $value);
        $this->assertNotInstanceOf(NotInstanceOfTrait::class, $value);
        $this->assertNotInstanceOf($class, $value);

        $this->assertObjectNotHasProperty('secret', $entity);
        $this->assertObjectNotHasProperty('secret', $any, 'no secret');

        // property_exists() also takes a class name, which PHPUnit's object parameter rejects.
        $this->assertObjectNotHasProperty('secret', $unknown);
        $this->assertObjectNotHasProperty('secret', $className);
    }
}
-----
<?php

interface NotInstanceOfContract {}

trait NotInstanceOfTrait {}

class NotInstanceOfNotHasPropertyTest
{
    public function test(\stdClass $entity, object $any, mixed $unknown, string $className)
    {
        \Testo\Assert::false($value instanceof \DateTimeInterface);
        \Testo\Assert::false($value instanceof NotInstanceOfContract, 'no contract');
        \Testo\Assert::false($value instanceof \ArrayObject);

        // PHPUnit throws for an unknown class or a trait; a dynamic name is never checked here.
        $this->assertNotInstanceOf('Missing\Type', $value);
        $this->assertNotInstanceOf(NotInstanceOfTrait::class, $value);
        $this->assertNotInstanceOf($class, $value);

        \Testo\Assert::false(\property_exists($entity, 'secret'));
        \Testo\Assert::false(\property_exists($any, 'secret'), 'no secret');

        // property_exists() also takes a class name, which PHPUnit's object parameter rejects.
        $this->assertObjectNotHasProperty('secret', $unknown);
        $this->assertObjectNotHasProperty('secret', $className);
    }
}
