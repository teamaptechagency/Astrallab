<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a customer said about a product.
 *
 * Nothing appears until somebody has read it. An unmoderated review form on a
 * public site is a spam board within a week, and the first thing a visitor
 * would see is a wall of links to somewhere else.
 */
class Review extends Model
{
    protected $fillable = ['product_slug', 'name', 'email', 'rating', 'body'];

    protected $casts = ['rating' => 'integer', 'approved' => 'boolean', 'verified' => 'boolean'];

    public function scopeShown($query, string $slug)
    {
        return $query->where('product_slug', $slug)->where('approved', true)->latest();
    }

    /**
     * The average, and how many it is made of.
     *
     * Both, always. An average with no count behind it is how "5.0" comes to
     * mean one review from the seller's cousin.
     *
     * @return array{average: ?float, count: int}
     */
    public static function summary(string $slug): array
    {
        $reviews = static::where('product_slug', $slug)->where('approved', true);
        $count = (clone $reviews)->count();

        return [
            'average' => $count ? round((clone $reviews)->avg('rating'), 1) : null,
            'count' => $count,
        ];
    }
}
