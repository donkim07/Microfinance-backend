<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the users for the role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Get the permissions for the role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Sync the permissions for the role.
     *
     * @param array $permissions
     * @return void
     */
    public function syncPermissions(array $permissions)
    {
        $this->permissions()->sync($permissions);
    }

    /**
     * Give permissions to the role.
     *
     * @param array $permissions
     * @return void
     */
    public function givePermissionsTo(array $permissions)
    {
        $this->permissions()->syncWithoutDetaching($permissions);
    }

    /**
     * Remove permissions from the role.
     *
     * @param array $permissions
     * @return void
     */
    public function revokePermissionsTo(array $permissions)
    {
        $this->permissions()->detach($permissions);
    }

    /**
     * Check if the role has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->permissions()->where('slug', $permission)->exists();
    }
}
