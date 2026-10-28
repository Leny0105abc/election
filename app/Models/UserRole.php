<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Row in the `roles` table. Permissions themselves live in App\Enums\Role.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 */
class UserRole extends Model
{
    protected $table = 'roles';

    protected $fillable = ['slug', 'name', 'description'];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public static function idFor(Role $role): int
    {
        return (int) static::firstOrCreate(
            ['slug' => $role->value],
            ['name' => $role->label(), 'description' => $role->description()],
        )->id;
    }
}
