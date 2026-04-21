<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Fixtures\Models;

use ApexScout\ScoutPostgres\Tests\Fixtures\database\factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

#[Hidden(['search_vector', 'search_text'])]
#[Table(name: 'books')]
final class Book extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }
}
