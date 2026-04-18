<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Country
 *
 * Represents a country in the system.
 *
 * @property int $id The primary key of the country.
 * @property string $name_en The country's name in English.
 * @property string $name_ar The country's name in Arabic.
 * @property string $symbol The country's symbol (e.g., country code).
 * @property int $min_number_count The minimum number count allowed for phone numbers.
 * @property int $max_number_count The maximum number count allowed for phone numbers.
 * @property float $price The price associated with the country.
 * @property bool $status The status of the country (e.g., active/inactive).
 * @property bool $coverage_status Whether the country is covered in the service.
 * @property Carbon|null $created_at Timestamp when the record was created.
 * @property Carbon|null $updated_at Timestamp when the record was last updated.
 *
 * @property-read Collection|User[] $users The users associated with this country.
 * @property-read Collection|OrganizationWhatsappRate[] $organizationWhatsappRates The WhatsApp rates for organizations in this country.
 *
 * @method static Builder|Country newModelQuery()
 * @method static Builder|Country newQuery()
 * @method static Builder|Country query()
 * @method static Builder|Country whereId($value)
 * @method static Builder|Country whereNameEn($value)
 * @method static Builder|Country whereNameAr($value)
 * @method static Builder|Country whereSymbol($value)
 * @method static Builder|Country whereMinNumberCount($value)
 * @method static Builder|Country whereMaxNumberCount($value)
 * @method static Builder|Country wherePrice($value)
 * @method static Builder|Country whereStatus($value)
 * @method static Builder|Country whereCoverageStatus($value)
 * @method static Builder|Country whereCreatedAt($value)
 * @method static Builder|Country whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Country extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'country'; // Replace with your actual table name

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_en',
        'name_ar',
        'symbol',
        'min_number_count',
        'max_number_count',
        'price',
        'status',
        'coverage_status',
    ];

    /**
     * Get active countries based on user and international status.
     *
     * @param int $user_id The ID of the user.
     * @param bool $is_international Whether the request is for an international user (1 = true, 0 = false).
     * @return Collection A collection of active countries.
     */
    public static function get_active_by_user(int $user_id, bool $is_international)
    {
        if ($is_international) {
            return self::all();
        }
        return self::where('coverage_status', 1)->get();
    }

    /**
     * Get the users associated with this country.
     *
     * @return HasMany
     */
    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the organization WhatsApp rates associated with this country.
     *
     * @return HasMany
     */
    public function organizationWhatsappRates(): HasMany
    {
        return $this->hasMany(OrganizationWhatsappRate::class, 'country_id');
    }
}
