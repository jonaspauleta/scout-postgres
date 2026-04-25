<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Fixtures\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ScoutPostgres\Tests\Fixtures\Models\Book;

/**
 * @extends Factory<Book>
 */
final class BookFactory extends Factory
{
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
