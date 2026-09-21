<?php

declare(strict_types=1);

/**
 * Slide 4. Collection checks: `every()` / `allOf()` chains vs `foreach` loops full of assertions.
 */

namespace Sample\PhpUnit {

    use App\Catalog\Product;
    use App\Catalog\ProductRepository;
    use PHPUnit\Framework\TestCase;

    final class ProductRepositoryTest extends TestCase
    {
        public function testFindsPublishedProductsInCategory(): void
        {
            $products = (new ProductRepository())->findPublished(category: 'books');

            $this->assertNotEmpty($products);
            $this->assertCount(3, $products);
            $this->assertContainsOnlyInstancesOf(Product::class, $products);

            foreach ($products as $product) {
                $this->assertTrue($product->isPublished());
                $this->assertSame('books', $product->category);
                $this->assertGreaterThan(0, $product->price);
            }

            $ids = \array_map(static fn(Product $p) => $p->id, $products);
            \sort($ids);
            $this->assertSame([7, 12, 31], $ids);
        }
    }
}

namespace Sample\Testo {

    use App\Catalog\Product;
    use App\Catalog\ProductRepository;
    use Testo\Assert;
    use Testo\Test;

    #[Test]
    final class ProductRepositoryTest
    {
        public function findsPublishedProductsInCategory(): void
        {
            $products = (new ProductRepository())->findPublished(category: 'books');

            Assert::iterable($products)
                ->notEmpty()
                ->hasCount(3)
                ->allOf(Product::class)
                ->every(static fn(Product $p) => $p->isPublished() && $p->category === 'books' && $p->price > 0);

            Assert::array(\array_map(static fn(Product $p) => $p->id, $products))
                ->sameElementsAs([7, 12, 31]);
        }
    }
}
