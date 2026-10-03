<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\IP_Address;

class IP_Check_Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
            $current_ip = file_get_contents('https://api.ipify.org');
            $current_user_data = IP_Address::pluck('ip_address');
            $check = 0;
            foreach($current_user_data as $temp_data)   
                {
                    if($temp_data == $current_ip)
                        {
                            $check = 1;
                            return $next($request);
                        }           
                }
            if($check == 0)
                {
                    echo $current_ip;
                    return response()->json([
                        'Error' => 'IP Address Does not Match. Please Contact Admin '
                    ], 403);
                }
                return $next($request);
        
    }
}
