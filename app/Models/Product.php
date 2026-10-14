<?php

namespace App\Models;

use App\Observers\ProductObserver;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const LOW_STOCK_THRESHOLD = 10;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot('unit_cost')
            ->withTimestamps();
    }

    protected function sku(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    protected function stockStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->stock_quantity < 1) {
                    return 'out_of_stock';
                }

                return $this->stock_quantity <= self::LOW_STOCK_THRESHOLD ? 'low_stock' : 'in_stock';
            },
        );
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereBetween('stock_quantity', [1, self::LOW_STOCK_THRESHOLD]);
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        $status = $filters['stock_status'] ?? null;

        if ($status === 'in_stock') {
            $query->where('stock_quantity', '>', self::LOW_STOCK_THRESHOLD);
        } elseif ($status === 'low_stock') {
            $query->lowStock();
        } elseif ($status === 'out_of_stock') {
            $query->where('stock_quantity', 0);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            // escape the wildcards, otherwise searching for "%" matches every row.
            // "!" is the escape char because backslash is not portable between mysql and sqlite.
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';

            $query->where(function (Builder $query) use ($term) {
                $query->whereRaw("name like ? escape '!'", [$term])
                    ->orWhereRaw("sku like ? escape '!'", [$term]);
            });
        }
    }

    public function scopeSort(Builder $query, ?string $sort): void
    {
        if (! $sort) {
            $query->orderByDesc('created_at')->orderByDesc('id');

            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $query->orderBy(ltrim($sort, '-'), $direction)->orderBy('id', $direction);
    }
}
