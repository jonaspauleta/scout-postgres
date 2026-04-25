<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Fixtures\Models;

use ApexScout\ScoutPostgres\Tests\Fixtures\database\factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $author
 * @property string|null $summary
 *
 * @method static BookFactory factory($count = null, $state = [])
 */
final class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

    protected $table = 'books';

    /** @var list<string> */
    protected $hidden = ['search_vector', 'search_text'];

    /** @var list<string> */
    protected $guarded = [];

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }
}
