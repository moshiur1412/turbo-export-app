<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportBuilder
{
    private array $columns = [];
    private array $filters = [];
    private array $joins = [];
    private array $aggregations = [];
    private ?Builder $baseQuery = null;
    private string $modelClass = User::class;

    public function from(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        $this->baseQuery = null;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function addColumn(string $column, ?string $alias = null): self
    {
        $this->columns[] = $alias ? "{$column} as {$alias}" : $column;
        return $this;
    }

    public function addAggregation(string $column, string $function, string $alias): self
    {
        $this->aggregations[] = "{$function}({$column}) as {$alias}";
        return $this;
    }

    public function addJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        return $this;
    }

    public function addFilter(string $column, mixed $value, string $operator = '='): self
    {
        $this->filters[] = [
            'column' => $column,
            'value' => $value,
            'operator' => $operator,
        ];
        return $this;
    }

    public function whereDateRange(string $dateColumn, Carbon $start, Carbon $end): self
    {
        $this->filters[] = [
            'column' => $dateColumn,
            'value' => [$start, $end],
            'operator' => 'between',
        ];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->filters[] = [
            'column' => $column,
            'value' => $values,
            'operator' => 'in',
        ];
        return $this;
    }

    public function build(): Builder
    {
        $model = new $this->modelClass();
        $query = $model->newQuery();

        $selectColumns = $this->columns;
        if (empty($selectColumns)) {
            $selectColumns = ['*'];
        }

        if (!empty($this->aggregations)) {
            $selectColumns = array_merge($selectColumns, $this->aggregations);
        }

        foreach ($this->joins as $join) {
            $query->join($join['table'], $join['first'], $join['operator'], $join['second']);
        }

        foreach ($this->filters as $filter) {
            $column = $filter['column'];
            $value = $filter['value'];
            $operator = $filter['operator'];

            if (!str_contains($column, '.')) {
                $table = $model->getTable();
                $column = $table . '.' . $column;
            }

            match ($operator) {
                'between' => $query->whereBetween($column, $value),
                'in' => $query->whereIn($column, $value),
                'like' => $query->where($column, 'like', "%{$value}%"),
                default => $query->where($column, $operator, $value),
            };
        }

        $query->select($selectColumns);

        return $query;
    }

    public function execute(): Collection
    {
        return $this->build()->get();
    }

    public function toArray(): array
    {
        return $this->execute()->toArray();
    }

    public function toExportFormat(): array
    {
        return $this->execute()->map(function ($item) {
            return (array) $item;
        })->toArray();
    }
}
