<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Backend;

/**
 * Columns that listeners add to the overviews of the Provenance module under
 * a row's "extra" key. A cell is text or ['label' => …, 'url' => …]; URLs
 * other than web addresses and backend paths are dropped, so a listener can
 * add an action but never a script link. Every row gets every column, so
 * cells line up even if listeners fill only some rows.
 */
final class ExtraColumns
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    public static function normalize(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys((array)($row['extra'] ?? [])) as $column) {
                $columns[(string)$column] = true;
            }
        }
        $columns = array_keys($columns);

        foreach ($rows as &$row) {
            $extra = (array)($row['extra'] ?? []);
            $row['extra'] = [];
            foreach ($columns as $column) {
                $row['extra'][$column] = self::cell($extra[$column] ?? '');
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @return array{label: string, url: string}
     */
    private static function cell(mixed $value): array
    {
        if (!is_array($value)) {
            return ['label' => (string)$value, 'url' => ''];
        }
        $url = (string)($value['url'] ?? '');

        return [
            'label' => (string)($value['label'] ?? ''),
            'url' => preg_match('#^(https?://|/(?!/))#i', $url) === 1 ? $url : '',
        ];
    }
}
