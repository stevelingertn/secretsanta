<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Car extends Model
{
    use HasFactory;

    protected $fillable = ['year', 'make', 'model', 'description'];

    protected function casts(): array
    {
        return [
            'entry_number' => 'integer',
            'year' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function vehicle(): string
    {
        return $this->description;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : $this->photoUrl();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $number = ltrim($term, '#');

        return $query->where(function (Builder $q) use ($term, $number) {
            if (ctype_digit($number)) {
                $q->orWhere('entry_number', (int) $number);
            }
            $q->orWhere('description', 'like', '%'.addcslashes($term, '%_\\').'%');
        });
    }
}
