<?php

declare(strict_types=1);

/**
 * Slide 10. Tests without a class: PHPUnit always needs a `TestCase` subclass,
 * Testo runs plain functions and applies the same lifecycle attributes to them.
 */

namespace Sample\PhpUnit {

    use App\Text\Slugger;
    use PHPUnit\Framework\TestCase;

    final class SluggerTest extends TestCase
    {
        private Slugger $slugger;

        protected function setUp(): void
        {
            parent::setUp();
            $this->slugger = new Slugger(locale: 'ru');
        }

        public function testTransliteratesCyrillic(): void
        {
            $this->assertSame('privet-mir', $this->slugger->slug('Привет, мир!'));
        }

        public function testCollapsesRepeatedSeparators(): void
        {
            $this->assertSame('a-b', $this->slugger->slug('a --- b'));
        }
    }
}

namespace Sample\Testo {

    use App\Text\Slugger;
    use Testo\Assert;
    use Testo\Lifecycle\BeforeTest;
    use Testo\Test;

    #[BeforeTest]
    function createSlugger(): void
    {
        Fixture::$slugger = new Slugger(locale: 'ru');
    }

    #[Test]
    function transliteratesCyrillic(): void
    {
        Assert::same(Fixture::$slugger->slug('Привет, мир!'), 'privet-mir');
    }

    #[Test]
    function collapsesRepeatedSeparators(): void
    {
        Assert::same(Fixture::$slugger->slug('a --- b'), 'a-b');
    }

    final class Fixture
    {
        public static Slugger $slugger;
    }
}
