<?php

namespace App\Services\Export;

/** A document to export: a title, a few "label: value" facts, tables, and notes. Rendered as Excel or PDF. */
final class Report
{
    /**
     * @param  array<string, string>  $facts  label => value, shown above the tables
     * @param  list<ReportSection>  $sections
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $title,
        public readonly string $filename,
        public readonly array $facts = [],
        public readonly array $sections = [],
        public readonly array $notes = [],
        public readonly bool $landscape = false,
    ) {}
}
