<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 1;
    public const ROLE_OPERADOR = 2;
    public const ROLE_CONSULTA = 3;
    public const ROLE_AUDITORIA = 4;

    protected $fillable = [
        'name',
        'email',
        'role',
        'avatar_path',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRoleId(self::ROLE_ADMIN);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')->withTimestamps();
    }

    public function roleId(): int
    {
        if ($this->relationLoaded('roles')) {
            $fromRelation = (int) ($this->roles->sortBy('id')->first()?->id ?? 0);
            if ($fromRelation > 0) return $fromRelation;
        } else {
            $fromRelation = (int) ($this->roles()->orderBy('roles.id')->value('roles.id') ?? 0);
            if ($fromRelation > 0) return $fromRelation;
        }

        return (int) ($this->role ?? self::ROLE_OPERADOR);
    }

    public function roleName(): string
    {
        if ($this->relationLoaded('roles')) {
            $name = (string) ($this->roles->sortBy('id')->first()?->name ?? '');
            if ($name !== '') return $name;
        } else {
            $name = (string) ($this->roles()->orderBy('roles.id')->value('roles.name') ?? '');
            if ($name !== '') return $name;
        }

        return self::roleLabelFromId($this->roleId());
    }

    public static function roleLabelFromId(int $id): string
    {
        return match ($id) {
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_OPERADOR => 'Operador',
            self::ROLE_CONSULTA => 'Consulta',
            self::ROLE_AUDITORIA => 'Auditoria',
            default => 'Sin rol',
        };
    }

    private function hasRoleId(int $roleId): bool
    {
        if ($this->relationLoaded('roles')) {
            if ($this->roles->contains(fn ($r) => (int) ($r->id ?? 0) === $roleId)) return true;
        } else {
            if ($this->roles()->where('roles.id', $roleId)->exists()) return true;
        }

        return (int) ($this->role ?? 0) === $roleId;
    }
}
