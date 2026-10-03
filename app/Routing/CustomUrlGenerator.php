<?php

namespace App\Routing;

use Illuminate\Routing\UrlGenerator;

class CustomUrlGenerator extends UrlGenerator
{
    /**
     * Resolve a named route, falling back to prefix if available.
     *
     * @param  string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    public function route($name, $parameters = [], $absolute = true)
    {
        if ($name === 'dashboard' && auth()->check()) {
            $role = auth()->user()->role;
            $roleDashboardMap = [
                'admin' => 'admin.dashboard',
                'company' => 'company.dashboard',
                'hr' => 'hr.dashboard',
                'employee' => 'employee.dashboard',
            ];
            if (isset($roleDashboardMap[$role])) {
                return parent::route($roleDashboardMap[$role], $parameters, $absolute);
            }
        }

        // Don't prefix if we are resolving a route that is already prefixed
        $prefix = panelPrefix();
        if ($prefix && !str_starts_with($name, $prefix . '.')) {
            $prefixedName = $prefix . '.' . $name;
            if ($this->routes->hasNamedRoute($prefixedName)) {
                return parent::route($prefixedName, $parameters, $absolute);
            }
        }

        if ($this->routes->hasNamedRoute($name)) {
            return parent::route($name, $parameters, $absolute);
        }

        // Check fallback panel prefixes (company, admin) if current prefix route doesn't exist
        $fallbackPrefixes = ['company', 'admin'];
        foreach ($fallbackPrefixes as $fallbackPrefix) {
            $candidateName = $fallbackPrefix . '.' . $name;
            if ($this->routes->hasNamedRoute($candidateName)) {
                return parent::route($candidateName, $parameters, $absolute);
            }
        }

        return parent::route($name, $parameters, $absolute);
    }
}
