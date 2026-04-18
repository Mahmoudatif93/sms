<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class TicketTag
 *
 * Represents a tag that can be assigned to tickets.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $workspace_id Foreign Key - The workspace this tag belongs to
 * @property string $name The name of the tag
 * @property string $color The color of the tag (hex code)
 * @property Carbon|null $created_at Timestamp when the tag was created
 * @property Carbon|null $updated_at Timestamp when the tag was last updated
 *
 * @property-read Workspace $workspace The workspace this tag belongs to
 * @property-read \Illuminate\Database\Eloquent\Collection|Ticket[] $tickets The tickets associated with this tag
 *
 * @method static Builder|TicketTag newModelQuery()
 * @method static Builder|TicketTag newQuery()
 * @method static Builder|TicketTag query()
 * @method static Builder|TicketTag whereId($value)
 * @method static Builder|TicketTag whereWorkspaceId($value)
 * @method static Builder|TicketTag whereName($value)
 *
 * @mixin Eloquent
 */
class TicketTag extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'workspace_id',
        'name',
        'color',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            // Set default color if not provided
            if (empty($tag->color)) {
                $tag->color = self::getRandomColor();
            }
        });
    }

    /**
     * Get a random color for tags.
     *
     * @return string
     */
    protected static function getRandomColor(): string
    {
        $colors = [
            '#3498db', // Blue
            '#2ecc71', // Green
            '#e74c3c', // Red
            '#f39c12', // Orange
            '#9b59b6', // Purple
            '#1abc9c', // Turquoise
            '#34495e', // Dark Blue
            '#e67e22', // Carrot
            '#d35400', // Pumpkin
            '#16a085', // Green Sea
            '#27ae60', // Nephritis
            '#2980b9', // Belize Hole
            '#8e44ad', // Wisteria
            '#f1c40f', // Sunflower
            '#c0392b', // Pomegranate
        ];

        return $colors[array_rand($colors)];
    }

    /**
     * Get the workspace that this tag belongs to.
     *
     * @return BelongsTo
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Get the tickets associated with this tag.
     *
     * @return BelongsToMany
     */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(TicketEntity::class, 'ticket_tag_pivot', 'tag_id', 'ticket_id');
    }

    /**
     * Scope a query to only include tags for a specific workspace.
     *
     * @param Builder $query
     * @param string $workspaceId
     * @return Builder
     */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Search tags by name.
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%{$search}%");
    }
}