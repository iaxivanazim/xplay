<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'password',
        'role_id',
        'card_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * THIS is what Laravel uses to store user_id in session
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * THIS is what Laravel uses for login lookup
     */
    public function username()
    {
        return 'username';
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Return a collection of permission slugs for this user.
     * Combines the single `role` (if present) and any many-to-many `roles`.
     */
    public function permissions()
    {
        $permissions = collect();

        if ($this->role) {
            $permissions = $permissions->merge($this->role->permissions->pluck('slug'));
        }

        if ($this->roles()->exists()) {
            foreach ($this->roles as $role) {
                $permissions = $permissions->merge($role->permissions->pluck('slug'));
            }
        }

        return $permissions->unique()->values();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role && $this->role->permissions()->where('slug', $permission)->exists()) {
            return true;
        }

        return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('slug', $permission);
        })->exists();
    }
}
