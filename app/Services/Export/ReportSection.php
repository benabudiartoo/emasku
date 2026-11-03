<?php

namespace App\Services\Export;

/**
 * One table in a report. Every cell is text that is already formatted for people (weights rounded for display,
 * "Data diperlukan" where a value cannot be computed), so both renderers print exactly the same thing.
 */
final class ReportSection
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public readonly string $heading,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?string $emptyText = null,
    ) {}
}
