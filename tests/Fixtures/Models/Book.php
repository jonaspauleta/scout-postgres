<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Fixtures\Models;

use ApexScout\ScoutPostgres\Tests\Fixtures\database\factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

final class Book extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $table = 'books';

    protected $guarded = [];

    protected $hidden = ['search_vector', 'search_text'];

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }
}
