<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Auth\AccessRoles;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role', 'status', 'invited_at')
            ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status === 'blocked') {
            return false;
        }

        if ($panel->getId() === 'platform') {
            return $this->hasAnyRole(AccessRoles::platformRoles());
        }

        if ($panel->getId() === 'admin') {
            $tenant = currentTenant();
            if ($tenant === null) {
                return false;
            }

            $membership = $this->tenants()->where('tenant_id', $tenant->id)->first();
            if ($membership === null || $membership->pivot->status !== 'active') {
                return false;
            }

            return in_array($membership->pivot->role, AccessRoles::tenantMembershipRolesForPanel(), true);
        }

        return false;
    }

    public static function statuses(): array
    {
        return [
            'active' => 'Активен',
            'blocked' => 'Заблокирован',
            'invited' => 'Приглашён',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
