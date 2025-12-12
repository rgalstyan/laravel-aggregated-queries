<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Rgalstyan\LaravelAggregatedQueries\HasAggregatedQueries;

final class Partner extends Model
{
    use HasAggregatedQueries;

    protected $guarded = [];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function promocodes()
    {
        return $this->hasMany(Promocode::class);
    }
}
