<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Un usuario tiene un rol principal vía tabla pivote.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Devuelve el primer rol asociado.
     */
    public function primaryRole(): ?Role
    {
        return $this->roles->first();
    }

    /**
     * Accessor práctico para frontend/resources.
     */
    public function getRoleIdAttribute(): ?int
    {
        return $this->roles->first()?->id;
    }

    public function getRoleSlugAttribute(): ?string
    {
        return $this->roles->first()?->slug;
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Helpers de autorización simples.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === $slug);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isOperator(): bool
    {
        return $this->hasRole('operador');
    }

    public function isConsulta(): bool
    {
        return $this->hasRole('consulta');
    }

    public function isAuditoria(): bool
    {
        return $this->hasRole('auditoria');
    }

    /**
     * Opcional: si después asignás tareas a usuarios.
     */
    public function tareasAsignadas()
    {
        return $this->hasMany(Tarea::class, 'assigned_user_id');
    }
}
