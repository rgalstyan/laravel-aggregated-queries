# Laravel Aggregated Queries

[![Latest Version](https://img.shields.io/packagist/v/rgalstyan/laravel-aggregated-queries.svg?style=flat-square)](https://packagist.org/packages/rgalstyan/laravel-aggregated-queries)
[![Total Downloads](https://img.shields.io/packagist/dt/rgalstyan/laravel-aggregated-queries.svg?style=flat-square)](https://packagist.org/packages/rgalstyan/laravel-aggregated-queries)
[![Tests](https://img.shields.io/github/actions/workflow/status/rgalstyan/laravel-aggregated-queries/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rgalstyan/laravel-aggregated-queries/actions)
[![License](https://img.shields.io/packagist/l/rgalstyan/laravel-aggregated-queries.svg?style=flat-square)](https://packagist.org/packages/rgalstyan/laravel-aggregated-queries)

Reduce multi-relation Eloquent queries to a single optimized SQL statement using JSON aggregation.

Perfect for read-heavy APIs, dashboards, and admin panels where traditional eager loading generates too many queries.

---

## The Problem

Even with proper eager loading, Laravel generates one query per relation:

```php
Partner::with(['profile', 'country', 'promocodes'])->get();
```

**Produces 4 separate queries:**

```sql
SELECT * FROM partners
SELECT * FROM partner_profiles WHERE partner_id IN (...)
SELECT * FROM countries WHERE id IN (...)
SELECT * FROM partner_promocodes WHERE partner_id IN (...)
```

Complex pages easily generate **5–15 queries**, increasing:
- Database round-trips
- Response time
- Memory usage
- Server load

---

## The Solution

Transform multiple queries into **one optimized SQL statement** using JSON aggregation:

```php
Partner::aggregatedQuery()
    ->withJsonRelation('profile')
    ->withJsonRelation('country')
    ->withJsonCollection('promocodes')
    ->get();
```

**Generates a single query:**

```sql
SELECT base.*,
    JSON_OBJECT('id', profile.id, 'name', profile.name) AS profile,
    JSON_OBJECT('id', country.id, 'name', country.name) AS country,
    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'code', code))
     FROM partner_promocodes WHERE partner_id = base.id) AS promocodes
FROM partners AS base
LEFT JOIN partner_profiles profile ON profile.partner_id = base.id
LEFT JOIN countries country ON country.id = base.country_id
```

**Result:**
- ✅ 1 database round-trip instead of 4
- ✅ Up to 6x faster response time
- ✅ 90%+ less memory usage
- ✅ Consistent array output

---

## Performance

Real benchmark on **2,000 partners** with 4 relations (50 records fetched):

| Method | Time | Memory | Queries |
|--------|------|--------|---------|
| Traditional Eloquent | 27.44ms | 2.06MB | 5 |
| Aggregated Query | 4.41ms | 0.18MB | 1 |
| **Improvement** | **⚡ 83.9% faster** | **💾 91.3% less** | **🔢 80% fewer** |

At scale (10,000 API requests/day):
- **40,000 fewer database queries**
- **3.8 minutes saved in response time**
- **18.6GB less memory usage**

---

## Requirements

| Component | Version                 |
|-----------|-------------------------|
| PHP | ^8.2                    |
| Laravel | ^10.0 \| ^11.0 \| ^12.0 |
| MySQL | ^8.0                    |
| PostgreSQL | ^12.0                   |

---

## Installation

```bash
composer require rgalstyan/laravel-aggregated-queries
```

---

## Quick Start

### 1. Add trait to your model

```php
use Rgalstyan\LaravelAggregatedQueries\HasAggregatedQueries;

class Partner extends Model
{
    use HasAggregatedQueries;

    public function profile() { return $this->hasOne(PartnerProfile::class); }
    public function country() { return $this->belongsTo(Country::class); }
    public function promocodes() { return $this->hasMany(PartnerPromocode::class); }
}
```

### 2. Query with aggregation

```php
$partners = Partner::aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name', 'email'])
    ->withJsonRelation('country', ['id', 'name', 'code'])
    ->withJsonCollection('promocodes', ['id', 'code', 'discount'])
    ->withCount('promocodes')
    ->where('is_active', true)
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();
```

### 3. Use the data

```php
foreach ($partners as $partner) {
    echo $partner['name'];
    echo $partner['profile']['email'] ?? 'N/A';
    echo $partner['country']['name'];
    echo "Promocodes: " . count($partner['promocodes']);
    echo "Count: " . $partner['promocodes_count'];
}
```

**Output structure (guaranteed):**

```php
[
    'id' => 1,
    'name' => 'Partner A',
    'is_active' => true,
    'profile' => ['id' => 10, 'name' => 'John', 'email' => 'john@example.com'], // array or null
    'country' => ['id' => 1, 'name' => 'USA', 'code' => 'US'],                   // array or null
    'promocodes' => [                                                             // always array, never null
        ['id' => 1, 'code' => 'SAVE10', 'discount' => 10],
        ['id' => 2, 'code' => 'SAVE20', 'discount' => 20],
    ],
    'promocodes_count' => 2
]
```

---

## Advanced Usage

### Reuse existing queries

Already have complex query logic? Pass it as base:

```php
$baseQuery = Partner::query()
    ->whereHas('profile', fn($q) => $q->where('verified', true))
    ->where('country_id', '!=', null)
    ->latest();

$partners = Partner::aggregatedQuery($baseQuery)
    ->withJsonRelation('profile')
    ->withJsonRelation('country')
    ->get();
```

The base query becomes a subquery, preserving all your filters, scopes, and joins.

### Automatic column detection

When using `['*']`, the package automatically detects columns from model's `$fillable`:

```php
Partner::aggregatedQuery()
    ->withJsonRelation('profile') // Auto-detects: ['id', 'partner_id', 'name', 'email', 'created_at', 'updated_at']
    ->get();
```

No database metadata queries needed! Works with:
- Custom primary keys (`uuid` instead of `id`)
- Custom timestamp columns
- Soft deletes (`deleted_at`)

### Explicit columns (recommended)

For best performance, specify columns explicitly:

```php
Partner::aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name', 'email'])     // ✅ Fast
    ->withJsonRelation('country', ['id', 'name'])              // ✅ Fast
    ->withJsonRelation('profile')                              // ⚠️ Slower (auto-detects columns)
    ->get();
```

---

## API Reference

### Loading Relations

```php
// Load single relation (belongsTo, hasOne)
->withJsonRelation(string $relation, array $columns = ['*'])

// Load collection (hasMany)
->withJsonCollection(string $relation, array $columns = ['*'])

// Count related records
->withCount(string $relation)
```

### Query Filters

```php
->where(string $column, mixed $value)
->where(string $column, string $operator, mixed $value)
->whereIn(string $column, array $values)
->orderBy(string $column, string $direction = 'asc')
->limit(int $limit)
->offset(int $offset)
```

### Execution

```php
->get()                    // Collection of arrays (default, fastest)
->get('array')             // Same as above
->get('eloquent')          // Hydrate into Eloquent models (not recommended)
->first()                  // Get first result
->paginate(int $perPage)   // Laravel paginator
```

### Debugging

```php
->toSql()                  // Get generated SQL
->getBindings()            // Get query bindings
->debug()                  // Log SQL + execution time
```

---

## When to Use

### ✅ Perfect for:

- **API endpoints** with multiple relations
- **Admin dashboards** with complex data
- **Mobile backends** where latency matters
- **Listings/tables** with 3–10 relations
- **Read-heavy services** (90%+ reads)
- **High-traffic applications** needing DB optimization

### ⚠️ Not suitable for:

- **Write operations** (use standard Eloquent)
- **Model events/observers** (results are arrays by default)
- **Deep nested relations** like `profile.company.country` (not yet supported)
- **Polymorphic relations** (`morphTo`, `morphMany`)
- **Many-to-many** (`belongsToMany`)

---

## Important Constraints

### Read-Only by Design

Results are **arrays**, not Eloquent models (by default).

This means:
- ❌ No model events (`created`, `updated`, `deleted`)
- ❌ No observers
- ❌ No mutators/accessors
- ❌ Cannot call `save()`, `update()`, `delete()`

**Use for read operations only.** For writes, use standard Eloquent.

### Data Shape Guarantees

| Feature | Always Returns |
|---------|----------------|
| `withJsonRelation()` | `array` or `null` |
| `withJsonCollection()` | `array` (empty `[]` if no records) |
| `withCount()` | `integer` |

No surprises. No `null` collections. Consistent types.

---

## Batch Processing

For large exports, use chunks:

```php
Partner::query()->chunkById(500, function ($partners) {
    $ids = $partners->pluck('id');
    
    $data = Partner::aggregatedQuery()
        ->withJsonRelation('country')
        ->withJsonCollection('promocodes')
        ->whereIn('id', $ids)
        ->get();
    
    // Export to CSV, send to queue, etc.
});
```

**Do NOT** use `limit(5000)` — chunk it instead!

---

## Configuration

Publish config file:

```bash
php artisan vendor:publish --tag=aggregated-queries-config
```

**config/aggregated-queries.php:**

```php
return [
    // Maximum allowed limit (safety)
    'max_limit' => 500,

    // Column cache for models without $fillable
    'column_cache' => [
        'some_table' => ['id', 'name', 'created_at'],
    ],
];
```

---

## Limitations (v1.x)

Currently **not supported** (planned for future versions):

- Nested relations (`profile.company.country`)
- Callbacks in relations (`withCount('posts', fn($q) => $q->published())`)
- `belongsToMany` (many-to-many)
- `morphTo` / `morphOne` / `morphMany`
- Query scopes via `__call`
- Automatic result caching

---

## Examples

See `/examples` directory:

- [`basic-usage.php`](examples/basic-usage.php) - Simple queries
- [`multiple-relations.php`](examples/multiple-relations.php) - Complex relations
- [`with-filters.php`](examples/with-filters.php) - Filtering and sorting
- [`pagination.php`](examples/pagination.php) - Paginated results
- [`batch-export.php`](examples/batch-export.php) - Chunk processing

---

## Testing

```bash
composer install

# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Static analysis
composer phpstan

# Code formatting
composer format
```

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Run `composer test` and `composer phpstan`
5. Submit a pull request

---

## Security

If you discover a security vulnerability, please email:

📧 **galstyanrazmik1988@gmail.com**

Do not create public issues for security vulnerabilities.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

---

## Credits

**Author:** Razmik Galstyan  
**GitHub:** [@rgalstyan](https://github.com/rgalstyan)  
**Email:** galstyanrazmik1988@gmail.com

Built with ❤️ for the Laravel community.

---

## Support

- ⭐ Star the repo if you find it useful
- 🐛 Report bugs via [GitHub Issues](https://github.com/rgalstyan/laravel-aggregated-queries/issues)
- 💡 Feature requests welcome
- 📖 Improve docs via pull requests