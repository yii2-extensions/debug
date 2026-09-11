<?php

declare(strict_types=1);

namespace Acme\Debug;

use InvalidArgumentException;
use PHPForge\Debug\{Panel, PanelView};

/**
 * Interprets only the selected capture. No cache service is available to this presenter.
 */
class CachePanel extends Panel
{
    protected const string ICON = 'db';
    protected const string ID = 'cache-operations';
    protected const string TITLE = 'Cache operations';

    /**
     * @var array<string, list<string>> Results each observed operation is allowed to report.
     */
    private const array RESULTS = ['get' => ['hit', 'miss'], 'set' => ['stored']];

    public function present(array $data): PanelView
    {
        if (
            ($data['schema'] ?? null) !== 1 || !is_array($data['operations'] ?? null)
            || !array_is_list($data['operations'])
        ) {
            throw new InvalidArgumentException(
                'Invalid cache capture: expected schema 1 and an operations list.',
            );
        }

        $rows = [];

        foreach ($data['operations'] as $row) {
            $rows[] = self::operation($row);
        }

        $results = array_column($rows, 2);
        $hits = count(array_keys($results, 'hit', true));
        $misses = count(array_keys($results, 'miss', true));

        $view = PanelView::create()
            ->summary(' hits', $hits)
            ->summary(' misses', $misses)
            ->toolbar('Hits', $hits)
            ->toolbar('Misses', $misses)
            ->overview(['Hits' => $hits, 'Misses' => $misses]);

        return $rows === []
            ? $view->emptyState('No cache operations', 'The cache was observed, but no operations occurred.')
            : $view->table(['Operation', 'Key', 'Result'], $rows, collapsible: true);
    }

    /**
     * Validates one stored operation, rejecting results the cache could not have produced.
     *
     * @param mixed $row Stored operation.
     *
     * @throws InvalidArgumentException If the row is not an operation, key, and result the cache can report.
     *
     * @return array{string, string, string} Operation, key, and result.
     */
    private static function operation(mixed $row): array
    {
        if (is_array($row) === false || array_keys($row) !== [0, 1, 2]) {
            throw new InvalidArgumentException('Invalid cache operation.');
        }

        $operation = $row[0] ?? null;
        $key = $row[1] ?? null;
        $result = $row[2] ?? null;

        if (is_string($operation) === false || is_string($key) === false || is_string($result) === false
            || in_array($result, self::RESULTS[$operation] ?? [], true) === false) {
            throw new InvalidArgumentException('Invalid cache operation.');
        }

        return [$operation, $key, $result];
    }
}
