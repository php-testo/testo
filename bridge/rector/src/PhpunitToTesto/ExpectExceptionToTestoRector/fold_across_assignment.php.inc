<?php

class FoldAcrossAssignmentTest
{
    public function testInitWithWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $message = 'Type "float" is not supported. The valid types are: ' . self::TYPES . '.';
        $this->expectExceptionMessage($message);

        new Compare(type: 'float');
    }
}
-----
<?php

class FoldAcrossAssignmentTest
{
    public function testInitWithWrongType(): void
    {
        $message = 'Type "float" is not supported. The valid types are: ' . self::TYPES . '.';
        \Testo\Expect::exception(\InvalidArgumentException::class)->withMessageContaining($message);

        new Compare(type: 'float');
    }
}
