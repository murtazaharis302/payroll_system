<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Scopes\CompanyScope;

trait HasCompanyScope
{
    /**
     * Boot the company scope trait for a model.
     */
    public static function bootHasCompanyScope(): void
    {
        static::addGlobalScope(new CompanyScope());
    }
}
