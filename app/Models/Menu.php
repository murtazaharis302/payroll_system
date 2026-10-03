<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'route',
        'route_name',
        'icon',
        'parent_id',
        'order',
        'module',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    public function roleAccess()
    {
        return $this->hasMany(MenuRoleAccess::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootMenus($query)
    {
        return $query->whereNull('parent_id')->orderBy('order');
    }

    // Helper Methods
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    public function canAccess($role)
    {
        $access = $this->roleAccess()->where('role', $role)->first();
        return $access ? $access->can_access : false;
    }
}
