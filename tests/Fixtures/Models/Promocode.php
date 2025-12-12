<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Promocode extends Model
{
    protected $guarded = [];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
