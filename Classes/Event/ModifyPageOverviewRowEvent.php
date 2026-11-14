<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Event;

/**
 * Dispatched for every row of the "Pages" overview in the Provenance module.
 * Listeners add values under the row's "extra" key, for example the signing
 * extension a "signed" column; the template shows each extra value as a column.
 */
final class ModifyPageOverviewRowEvent
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(private array $row, private readonly int $pageUid) {}

    /**
     * @return array<string, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function setRow(array $row): void
    {
        $this->row = $row;
    }

    public function getPageUid(): int
    {
        return $this->pageUid;
    }
}
