<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

/**
 * The `criteria[...]` and `sorting[...]` of a query string, as the grid screen and the export
 * of the same view both read them: named entries only. Sylius types both as arrays of named
 * entries further down, where a scalar (`?sorting=desc`) or a numbered entry
 * (`?sorting[0]=asc`) is a 500 - and the export must read a view the way its screen did.
 */
final class GridQuery
{
    /**
     * @param array<array-key, mixed> $query
     *
     * @return array<string, mixed>
     */
    public static function criteria(array $query): array
    {
        // A select left on its first, empty choice asks for nothing - which not every Sylius
        // filter knows: its "exists" one reads an empty string as "No".
        return array_filter(
            self::entries($query, 'criteria'),
            static fn (mixed $value, int|string $name): bool => \is_string($name) && '' !== $value,
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Whether the query narrows the list: a filter form sent with every field left empty does not.
     *
     * @param array<array-key, mixed> $query
     */
    public static function isFiltering(array $query): bool
    {
        $filtering = false;
        $criteria = self::criteria($query);
        array_walk_recursive($criteria, static function (mixed $value) use (&$filtering): void {
            $filtering = $filtering || '' !== $value;
        });

        return $filtering;
    }

    /**
     * @param array<array-key, mixed> $query
     *
     * @return array<string, string> field => what was asked for it, a valid direction or not
     */
    public static function sorting(array $query): array
    {
        return array_filter(
            self::entries($query, 'sorting'),
            static fn (mixed $direction, int|string $field): bool => \is_string($field) && \is_string($direction),
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param array<array-key, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    private static function entries(array $query, string $name): array
    {
        return \is_array($query[$name] ?? null) ? $query[$name] : [];
    }
}
