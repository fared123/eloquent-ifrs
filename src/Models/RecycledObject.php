<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Models;

/**
 * Class RecycledObject
 *
 * @package Ekmungai\Eloquent-IFRS
 *
 * @property Entity $entity
 * @property Recyclable $recyclable
 * @property User $user
 * @property Carbon $destroyed_at
 * @property Carbon $deleted_at
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use IFRS\Interfaces\Segregatable;

use IFRS\Traits\Segregating;
use IFRS\Traits\ModelTablePrefix;

class RecycledObject extends Model implements Segregatable
{
    protected $connection = 'datadb';
    
    use Segregating;
    use SoftDeletes;
    use ModelTablePrefix;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ifrs_recycled_objects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    
        'id',
        'entity_id',
        'user_id',
        'recyclable_id',
        'recyclable_type',
        'destroyed_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Recycled object.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function recyclable()
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * User responsible for the action.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('ifrs.user_model'));
    }

    /**
     * RecycledObject attributes.
     *
     * @return object
     */
    public function attributes()
    {
        return (object) $this->attributes;
    }
}
