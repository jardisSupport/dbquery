<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Factory;

use JardisSupport\DbQuery\Query\Builder\Clause\ConditionBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\JoinBuilder;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Formatter\ValueFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for BuilderRegistry
 *
 * Tests the instance-based registry pattern for builder instances.
 * Each BuilderRegistry is a separate instance with its own cache,
 * enabling multi-dialect usage within the same request.
 */
class BuilderRegistryTest extends TestCase
{
    private BuilderRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new BuilderRegistry();
    }

    // ==================== Basic Functionality Tests ====================

    public function testGetReturnsBuilderInstance(): void
    {
        $builder = $this->registry->get(ConditionBuilder::class);

        $this->assertInstanceOf(ConditionBuilder::class, $builder);
    }

    public function testGetReturnsSameInstanceOnMultipleCalls(): void
    {
        $builder1 = $this->registry->get(ConditionBuilder::class);
        $builder2 = $this->registry->get(ConditionBuilder::class);

        $this->assertSame($builder1, $builder2);
    }

    public function testGetCreatesNewInstanceForDifferentClasses(): void
    {
        $conditionBuilder = $this->registry->get(ConditionBuilder::class);
        $joinBuilder = $this->registry->get(JoinBuilder::class);

        $this->assertNotSame($conditionBuilder, $joinBuilder);
        $this->assertInstanceOf(ConditionBuilder::class, $conditionBuilder);
        $this->assertInstanceOf(JoinBuilder::class, $joinBuilder);
    }

    // ==================== Clear Functionality Tests ====================

    public function testClearRemovesAllCachedBuilders(): void
    {
        $builder1 = $this->registry->get(ConditionBuilder::class);

        $this->registry->clear();

        $builder2 = $this->registry->get(ConditionBuilder::class);

        $this->assertNotSame($builder1, $builder2);
    }

    public function testClearAllowsFreshInstances(): void
    {
        $builder1 = $this->registry->get(ValueFormatter::class);
        $builder2 = $this->registry->get(JoinBuilder::class);

        $this->registry->clear();

        $builder3 = $this->registry->get(ValueFormatter::class);
        $builder4 = $this->registry->get(JoinBuilder::class);

        $this->assertNotSame($builder1, $builder3);
        $this->assertNotSame($builder2, $builder4);
    }

    // ==================== Multiple Builder Types Tests ====================

    public function testGetWorksWithMultipleDifferentBuilders(): void
    {
        $conditionBuilder = $this->registry->get(ConditionBuilder::class);
        $joinBuilder = $this->registry->get(JoinBuilder::class);
        $valueFormatter = $this->registry->get(ValueFormatter::class);

        $this->assertInstanceOf(ConditionBuilder::class, $conditionBuilder);
        $this->assertInstanceOf(JoinBuilder::class, $joinBuilder);
        $this->assertInstanceOf(ValueFormatter::class, $valueFormatter);

        // Verify singleton behavior for each within the same instance
        $this->assertSame($conditionBuilder, $this->registry->get(ConditionBuilder::class));
        $this->assertSame($joinBuilder, $this->registry->get(JoinBuilder::class));
        $this->assertSame($valueFormatter, $this->registry->get(ValueFormatter::class));
    }

    // ==================== Instance Isolation Tests ====================

    public function testSeparateRegistryInstancesAreIsolated(): void
    {
        $registry1 = new BuilderRegistry();
        $registry2 = new BuilderRegistry();

        $builder1 = $registry1->get(ConditionBuilder::class);
        $builder2 = $registry2->get(ConditionBuilder::class);

        // Different registry instances maintain separate caches
        $this->assertNotSame($builder1, $builder2);
    }

    public function testRegistryMaintainsSeparateInstancesPerClass(): void
    {
        $builder1 = $this->registry->get(ConditionBuilder::class);
        $builder2 = $this->registry->get(JoinBuilder::class);
        $builder3 = $this->registry->get(ValueFormatter::class);

        // Retrieve again
        $builder1Again = $this->registry->get(ConditionBuilder::class);
        $builder2Again = $this->registry->get(JoinBuilder::class);
        $builder3Again = $this->registry->get(ValueFormatter::class);

        // Same class returns same instance
        $this->assertSame($builder1, $builder1Again);
        $this->assertSame($builder2, $builder2Again);
        $this->assertSame($builder3, $builder3Again);

        // Different classes return different instances
        $this->assertNotSame($builder1, $builder2);
        $this->assertNotSame($builder1, $builder3);
        $this->assertNotSame($builder2, $builder3);
    }

    public function testGetCreatesInstanceOnlyOnce(): void
    {
        // First call creates instance
        $builder1 = $this->registry->get(ConditionBuilder::class);

        // Multiple subsequent calls return same instance
        $builder2 = $this->registry->get(ConditionBuilder::class);
        $builder3 = $this->registry->get(ConditionBuilder::class);
        $builder4 = $this->registry->get(ConditionBuilder::class);

        $this->assertSame($builder1, $builder2);
        $this->assertSame($builder1, $builder3);
        $this->assertSame($builder1, $builder4);
    }

    // ==================== Performance Tests ====================

    public function testGetIsEfficientWithManyRetrievals(): void
    {
        // First retrieval creates instance
        $firstBuilder = $this->registry->get(ConditionBuilder::class);

        // Multiple retrievals should return same instance (no new instantiation)
        for ($i = 0; $i < 1000; $i++) {
            $builder = $this->registry->get(ConditionBuilder::class);
            $this->assertSame($firstBuilder, $builder);
        }
    }

    // ==================== Clear After Multiple Builders Tests ====================

    public function testClearWorksAfterRegisteringMultipleBuilders(): void
    {
        $builder1 = $this->registry->get(ConditionBuilder::class);
        $builder2 = $this->registry->get(JoinBuilder::class);
        $builder3 = $this->registry->get(ValueFormatter::class);

        $this->registry->clear();

        $newBuilder1 = $this->registry->get(ConditionBuilder::class);
        $newBuilder2 = $this->registry->get(JoinBuilder::class);
        $newBuilder3 = $this->registry->get(ValueFormatter::class);

        $this->assertNotSame($builder1, $newBuilder1);
        $this->assertNotSame($builder2, $newBuilder2);
        $this->assertNotSame($builder3, $newBuilder3);
    }

    // ==================== Version Context Tests ====================

    public function testConstructorAcceptsDialectAndVersion(): void
    {
        $registry = new BuilderRegistry('mysql', '8.0');

        // Getting a builder should work with context set
        $builder = $registry->get(ConditionBuilder::class);
        $this->assertInstanceOf(ConditionBuilder::class, $builder);
    }

    public function testConstructorWithNullDialectAndVersion(): void
    {
        $registry = new BuilderRegistry(null, null);

        // Should work without context
        $builder = $registry->get(ConditionBuilder::class);
        $this->assertInstanceOf(ConditionBuilder::class, $builder);
    }

    public function testDialectIsCaseInsensitive(): void
    {
        $registry1 = new BuilderRegistry('MYSQL', '8.0');
        $builder1 = $registry1->get(ConditionBuilder::class);

        $registry2 = new BuilderRegistry('mysql', '8.0');
        $builder2 = $registry2->get(ConditionBuilder::class);

        // Both should work (dialect is normalized to lowercase)
        $this->assertInstanceOf(ConditionBuilder::class, $builder1);
        $this->assertInstanceOf(ConditionBuilder::class, $builder2);
    }

    public function testClearResetsContext(): void
    {
        $registry = new BuilderRegistry('mysql', '8.0');
        $builder1 = $registry->get(ConditionBuilder::class);

        $registry->clear();

        // After clear, context should be reset, new instance created
        $builder2 = $registry->get(ConditionBuilder::class);
        $this->assertNotSame($builder1, $builder2);
    }

    public function testMultipleRegistriesWithDifferentDialects(): void
    {
        $mysqlRegistry = new BuilderRegistry('mysql', '8.0');
        $postgresRegistry = new BuilderRegistry('postgres', '14');

        $mysqlBuilder = $mysqlRegistry->get(ConditionBuilder::class);
        $postgresBuilder = $postgresRegistry->get(ConditionBuilder::class);

        // Both should work with different contexts
        $this->assertInstanceOf(ConditionBuilder::class, $mysqlBuilder);
        $this->assertInstanceOf(ConditionBuilder::class, $postgresBuilder);

        // Different registries, different instances
        $this->assertNotSame($mysqlBuilder, $postgresBuilder);
    }

    // ==================== Multi-Dialect Parallel Tests ====================

    public function testMultiDialectInSameRequest(): void
    {
        // This is the core use case that was impossible with static state
        $mysqlRegistry = new BuilderRegistry('mysql', '8.0');
        $postgresRegistry = new BuilderRegistry('postgres', '14');
        $sqliteRegistry = new BuilderRegistry('sqlite', '3.39');

        $mysqlBuilder = $mysqlRegistry->get(ConditionBuilder::class);
        $postgresBuilder = $postgresRegistry->get(ConditionBuilder::class);
        $sqliteBuilder = $sqliteRegistry->get(ConditionBuilder::class);

        // All should be valid instances
        $this->assertInstanceOf(ConditionBuilder::class, $mysqlBuilder);
        $this->assertInstanceOf(ConditionBuilder::class, $postgresBuilder);
        $this->assertInstanceOf(ConditionBuilder::class, $sqliteBuilder);

        // All should be separate instances (no shared state)
        $this->assertNotSame($mysqlBuilder, $postgresBuilder);
        $this->assertNotSame($mysqlBuilder, $sqliteBuilder);
        $this->assertNotSame($postgresBuilder, $sqliteBuilder);

        // Each registry maintains its own cache
        $this->assertSame($mysqlBuilder, $mysqlRegistry->get(ConditionBuilder::class));
        $this->assertSame($postgresBuilder, $postgresRegistry->get(ConditionBuilder::class));
        $this->assertSame($sqliteBuilder, $sqliteRegistry->get(ConditionBuilder::class));
    }
}
