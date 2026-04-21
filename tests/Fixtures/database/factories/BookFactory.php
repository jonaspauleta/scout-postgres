<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Fixtures\database\factories;

use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Book>
 */
final class BookFactory extends Factory
{
    #[Override]
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'summary' => $this->faker->paragraph(),
        ];
    }
}
