<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Grid;

use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridViewBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Grid\Definition\Field;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridView;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class GridViewBuilderTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function sortings(): iterable
    {
        yield 'none asked: the default of the grid' => [[], ['confidence' => 'desc', 'id' => 'asc']];
        yield 'one asked' => [['sorting' => ['confidence' => 'asc']], ['confidence' => 'asc', 'id' => 'asc']];
        yield 'a scalar, which Sylius would choke on' => [['sorting' => 'desc'], ['confidence' => 'desc', 'id' => 'asc']];
        yield 'nothing but numbered entries: the default of the grid again' => [['sorting' => ['asc']], ['confidence' => 'desc', 'id' => 'asc']];
        yield 'numbered or nested entries, which it would choke on too' => [['sorting' => ['asc', 'confidence' => 'asc', 'key' => ['asc']]], ['confidence' => 'asc', 'id' => 'asc']];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $expected
     */
    #[DataProvider('sortings')]
    public function testAGridWithAnIdFieldIsAlwaysSortedByItLast(array $query, array $expected): void
    {
        $grid = $this->grid();
        $grid->addField(Field::fromNameAndType('id', 'string'));

        self::assertSame($expected, $this->parametersHandedToSylius($grid, $query)->get('sorting'));
    }

    public function testAGridWithoutAnIdFieldKeepsTheSortingItWasAsked(): void
    {
        self::assertSame(['confidence' => 'asc'], $this->parametersHandedToSylius($this->grid(), ['sorting' => ['confidence' => 'asc']])->get('sorting'));
        self::assertFalse($this->parametersHandedToSylius($this->grid(), [])->has('sorting'));
    }

    public function testASortingOnAFieldTheGridDoesNotSortFallsBackOnItsOwn(): void
    {
        $grid = $this->grid();
        $confidence = Field::fromNameAndType('confidence', 'string');
        $confidence->setSortable('confidence');
        $grid->addField($confidence);
        $grid->addField(Field::fromNameAndType('provider', 'string'));

        // Sylius would skip it without a word, and sort by nothing.
        self::assertFalse($this->parametersHandedToSylius($grid, ['sorting' => ['provider' => 'asc']])->has('sorting'));
        self::assertSame(['confidence' => 'asc'], $this->parametersHandedToSylius($grid, ['sorting' => ['provider' => 'asc', 'confidence' => 'asc']])->get('sorting'));
        // A field the grid does not have stays: refusing it is Sylius' answer to give.
        self::assertSame(['nope' => 'asc'], $this->parametersHandedToSylius($grid, ['sorting' => ['nope' => 'asc']])->get('sorting'));
    }

    public function testAScalarCriteriaIsLeftOut(): void
    {
        self::assertFalse($this->parametersHandedToSylius($this->grid(), ['criteria' => 'x'])->has('criteria'));
        self::assertSame(['locale' => 'fr_FR'], $this->parametersHandedToSylius($this->grid(), ['criteria' => ['x', 'locale' => 'fr_FR']])->get('criteria'));
        self::assertFalse($this->parametersHandedToSylius($this->grid(), ['criteria' => ['x'], 'sorting' => ['asc']])->has('sorting'));
    }

    private function grid(): Grid
    {
        $grid = Grid::fromCodeAndDriverConfiguration('suggestions', 'doctrine/orm', []);
        $grid->setSorting(['confidence' => 'desc']);

        return $grid;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function parametersHandedToSylius(Grid $grid, array $query): Parameters
    {
        $provider = $this->createStub(GridProviderInterface::class);
        $provider->method('get')->willReturn($grid);

        $handed = null;
        $factory = $this->createStub(GridViewFactoryInterface::class);
        $factory->method('create')->willReturnCallback(static function (Grid $grid, Parameters $parameters) use (&$handed): GridView {
            $handed = $parameters;

            return new GridView([], $grid, $parameters);
        });

        (new GridViewBuilder($provider, $factory))->build('suggestions', new Request($query));

        self::assertInstanceOf(Parameters::class, $handed);

        return $handed;
    }
}
