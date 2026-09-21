<?php

declare(strict_types=1);

/**
 * Slide 9. Lifecycle: fixed-name `setUp()`/`tearDown()` with `parent::` calls
 * vs attribute hooks with any name, several per phase, and `#[Inject]` for dependencies.
 */

namespace Sample\PhpUnit {

    use App\Orders\OrderRepository;
    use App\Storage\Connection;
    use PHPUnit\Framework\TestCase;

    final class OrderRepositoryTest extends TestCase
    {
        private static Connection $connection;
        private OrderRepository $repository;

        public static function setUpBeforeClass(): void
        {
            parent::setUpBeforeClass();
            self::$connection = Connection::fromEnv();
            self::$connection->migrate();
        }

        public static function tearDownAfterClass(): void
        {
            self::$connection->dropSchema();
            parent::tearDownAfterClass();
        }

        protected function setUp(): void
        {
            parent::setUp();
            self::$connection->beginTransaction();
            $this->repository = new OrderRepository(self::$connection);
        }

        protected function tearDown(): void
        {
            self::$connection->rollBack();
            parent::tearDown();
        }

        public function testStoresAndLoadsOrder(): void
        {
            $id = $this->repository->store(customerId: 42, total: 1500);

            $this->assertSame(1500, $this->repository->load($id)->total);
        }
    }
}

namespace Sample\Testo {

    use App\Orders\OrderRepository;
    use App\Storage\Connection;
    use Testo\Assert;
    use Testo\Lifecycle\AfterClass;
    use Testo\Lifecycle\AfterTest;
    use Testo\Lifecycle\BeforeClass;
    use Testo\Lifecycle\BeforeTest;
    use Testo\Test;
    use Testo\Testing\Attribute\Inject;

    #[Test]
    final class OrderRepositoryTest
    {
        #[Inject]
        private Connection $connection;

        private OrderRepository $repository;

        #[BeforeClass]
        public static function migrate(): void
        {
            Connection::fromEnv()->migrate();
        }

        #[AfterClass]
        public static function dropSchema(): void
        {
            Connection::fromEnv()->dropSchema();
        }

        #[BeforeTest]
        public function openTransaction(): void
        {
            $this->connection->beginTransaction();
        }

        #[BeforeTest]
        public function createRepository(): void
        {
            $this->repository = new OrderRepository($this->connection);
        }

        #[AfterTest]
        public function rollBack(): void
        {
            $this->connection->rollBack();
        }

        public function storesAndLoadsOrder(): void
        {
            $id = $this->repository->store(customerId: 42, total: 1500);

            Assert::same($this->repository->load($id)->total, 1500);
        }
    }
}
