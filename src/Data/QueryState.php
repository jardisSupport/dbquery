<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\DbQuery\Data\Contract\FromStateInterface;
use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Holds the complete state of a SQL query being built
 *
 * This class encapsulates all query components (SELECT, FROM, JOIN, WHERE, etc.)
 * and provides typed getter/setter methods for safe data access.
 */
class QueryState implements FromStateInterface, JoinStateInterface, OrderByStateInterface, LimitStateInterface
{
    private string $fields = '*';
    private bool $isDistinct = false;
    private string|DbQueryBuilderInterface $container = '';
    private ?string $alias = '';

    /** @var array<int, array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * }>
     */
    private array $joins = [];

    /** @var array<int, string> */
    private array $groupBy = [];

    /** @var array<int, string> */
    private array $orderBy = [];

    /** @var array{limit: int|null, offset: int|null} */
    private array $limit = ['limit' => null, 'offset' => null];

    /** @var array<int, DbQueryBuilderInterface> */
    private array $union = [];

    /** @var array<int, DbQueryBuilderInterface> */
    private array $unionAll = [];

    /** @var array<string, DbQueryBuilderInterface> */
    private array $cte = [];

    /** @var array<string, DbQueryBuilderInterface> */
    private array $cteRecursive = [];

    /** @var array<string, DbQueryBuilderInterface> */
    private array $selectSubqueries = [];

    /** @var array<int, WindowFunction> */
    private array $windowFunctions = [];

    /** @var array<string, WindowSpec> */
    private array $namedWindows = [];

    /** @var array<int, WindowReference> */
    private array $windowReferences = [];

    // ==================== Getters ====================

    public function getFields(): string
    {
        return $this->fields;
    }

    public function isDistinct(): bool
    {
        return $this->isDistinct;
    }

    public function getContainer(): string|DbQueryBuilderInterface
    {
        return $this->container;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /** @return array<int, array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * }>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /** @return array<int, string> */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    /** @return array<int, string> */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): ?int
    {
        return $this->limit['limit'];
    }

    public function getOffset(): ?int
    {
        return $this->limit['offset'];
    }

    /** @return array<int, DbQueryBuilderInterface> */
    public function getUnion(): array
    {
        return $this->union;
    }

    /** @return array<int, DbQueryBuilderInterface> */
    public function getUnionAll(): array
    {
        return $this->unionAll;
    }

    /** @return array<string, DbQueryBuilderInterface> */
    public function getCte(): array
    {
        return $this->cte;
    }

    /** @return array<string, DbQueryBuilderInterface> */
    public function getCteRecursive(): array
    {
        return $this->cteRecursive;
    }

    /** @return array<string, DbQueryBuilderInterface> */
    public function getSelectSubqueries(): array
    {
        return $this->selectSubqueries;
    }

    /** @return array<int, WindowFunction> */
    public function getWindowFunctions(): array
    {
        return $this->windowFunctions;
    }

    /** @return array<string, WindowSpec> */
    public function getNamedWindows(): array
    {
        return $this->namedWindows;
    }

    /** @return array<int, WindowReference> */
    public function getWindowReferences(): array
    {
        return $this->windowReferences;
    }

    // ==================== Setters ====================

    public function setFields(string $fields): void
    {
        $this->fields = $fields;
    }

    public function setDistinct(bool $isDistinct): void
    {
        $this->isDistinct = $isDistinct;
    }

    public function setContainer(string|DbQueryBuilderInterface $container): void
    {
        $this->container = $container;
    }

    public function setAlias(?string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * @param array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * } $join
     */
    public function addJoin(array $join): void
    {
        $this->joins[] = $join;
    }

    public function addGroupBy(string $column): void
    {
        $this->groupBy[] = $column;
    }

    public function addOrderBy(string $column, string $direction = 'ASC'): void
    {
        $this->orderBy[] = $column . ' ' . $direction;
    }

    public function setLimit(?int $limit, ?int $offset = null): void
    {
        $this->limit = ['limit' => $limit, 'offset' => $offset];
    }

    public function addUnion(DbQueryBuilderInterface $query): void
    {
        $this->union[] = $query;
    }

    public function addUnionAll(DbQueryBuilderInterface $query): void
    {
        $this->unionAll[] = $query;
    }

    public function addCte(string $name, DbQueryBuilderInterface $query): void
    {
        $this->cte[$name] = $query;
    }

    public function addCteRecursive(string $name, DbQueryBuilderInterface $query): void
    {
        $this->cteRecursive[$name] = $query;
    }

    public function addSelectSubquery(string $alias, DbQueryBuilderInterface $query): void
    {
        $this->selectSubqueries[$alias] = $query;
    }

    public function addWindowFunction(WindowFunction $windowFunction): void
    {
        $this->windowFunctions[] = $windowFunction;
    }

    public function addNamedWindow(string $name, WindowSpec $spec): void
    {
        $this->namedWindows[$name] = $spec;
    }

    public function addWindowReference(WindowReference $windowReference): void
    {
        $this->windowReferences[] = $windowReference;
    }
}
