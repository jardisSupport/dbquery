<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Builder\Window;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Data\WindowFunction;
use JardisSupport\DbQuery\Data\WindowSpec;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbWindowBuilderInterface;

/**
 * Builder for window function specifications.
 *
 * This class is instantiated temporarily to build window specifications
 * and stores the result in QueryState when endWindow() is called.
 */
class WindowBuilder implements DbWindowBuilderInterface
{
    private QueryState $state;
    private DbQueryBuilderInterface $query;
    private string $function;
    private string $alias;
    private ?string $args;
    private WindowSpec $spec;

    private bool $isNamedWindow = false;
    private ?string $windowName = null;

    public function __construct(
        QueryState $state,
        DbQueryBuilderInterface $query,
        string $function,
        string $alias,
        ?string $args,
        bool $isNamedWindow = false,
        ?string $windowName = null
    ) {
        $this->state = $state;
        $this->query = $query;
        $this->function = $function;
        $this->alias = $alias;
        $this->args = $args;
        $this->spec = new WindowSpec();
        $this->isNamedWindow = $isNamedWindow;
        $this->windowName = $windowName;
    }

    public function partitionBy(string ...$fields): self
    {
        foreach ($fields as $field) {
            $this->spec->addPartition($field);
        }
        return $this;
    }

    public function windowOrderBy(string $field, string $direction = 'ASC'): self
    {
        $this->spec->addOrder($field, $direction);
        return $this;
    }

    public function frame(string $type, string $start, string $end): self
    {
        $this->spec->setFrame($type, $start, $end);
        return $this;
    }

    public function endWindow(): DbQueryBuilderInterface
    {
        if ($this->isNamedWindow && $this->windowName !== null) {
            // Store named window specification
            $this->state->addNamedWindow($this->windowName, $this->spec);
        } else {
            // Store window function with inline specification
            $windowFunction = new WindowFunction($this->function, $this->alias, $this->args, $this->spec);
            $this->state->addWindowFunction($windowFunction);
        }

        return $this->query;
    }
}
