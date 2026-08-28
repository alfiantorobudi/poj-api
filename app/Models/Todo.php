<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property bool $is_completed
 * @property string $priority
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'todos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'is_completed',
        'priority',
        'due_date',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the todo.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include completed todos.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('is_completed', true);
    }

    /**
     * Scope a query to only include pending/uncompleted todos.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_completed', false);
    }

    /**
     * Scope a query to filter by priority.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePriority(Builder $query, ?string $priority): void
    {
        if ($priority && in_array(strtolower($priority), ['low', 'medium', 'high'])) {
            $query->where('priority', strtolower($priority));
        }
    }

    /**
     * Scope a query to search by keyword in title or description.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term && trim($term) !== '') {
            $searchTerm = '%'.trim($term).'%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }
    }

    /**
     * Mark the todo as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the todo as pending/incomplete.
     */
    public function markAsPending(): bool
    {
        return $this->update([
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }
}
