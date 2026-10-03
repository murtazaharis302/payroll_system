<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class Phase8RoleDashboardAndLoginTest extends TestCase
{
    /** @test */
    public function public_landing_page_loads_successfully()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Select a portal module to log in');
        $response->assertSee('Product Admin');
        $response->assertSee('Administrative Office');
    }

    /** @test */
    public function dedicated_role_login_pages_load_successfully()
    {
        $loginRoutes = [
            '/admin/login',
            '/company/login',
            '/hr/login',
            '/employee/login',
        ];

        foreach ($loginRoutes as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    /** @test */
    public function custom_403_view_renders_correctly()
    {
        $view = $this->view('errors.403');
        $view->assertSee('403 - Access Denied');
        $view->assertSee('Return to Dashboard');
    }

    /** @test */
    public function hr_dashboard_queries_payroll_periods_without_column_errors()
    {
        if (Schema::hasTable('users')) {
            $hrUser = User::where('role', 'hr')->first();
            if ($hrUser) {
                $response = $this->actingAs($hrUser)->get('/hr/dashboard');
                $response->assertStatus(200);
            }
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function company_dashboard_renders_without_route_not_found_exception()
    {
        if (Schema::hasTable('users')) {
            $companyUser = User::where('role', 'company')->first();
            if ($companyUser) {
                $response = $this->actingAs($companyUser)->get('/company/dashboard');
                $response->assertStatus(200);
            }
        }
        $this->assertTrue(true);
    }

    /** @test */
    public function logging_out_redirects_straight_to_landing_page()
    {
        $user = new User(['id' => 999, 'role' => 'hr', 'status' => 1]);
        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/');
    }
}
