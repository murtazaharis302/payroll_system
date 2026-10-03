{{-- <link href="{{asset('/')}}assets/css/admin.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.4.6/css/flag-icon.min.css"> --}}
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>@yield('title') | Payroll System</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
        <meta content="Coderthemes" name="author" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="{{asset('/')}}assets/images/favicon.ico">

		<!-- Bootstrap css -->
		<link href="{{asset('/')}}assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		<!-- App css -->
		<link href="{{asset('/')}}assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-style"/>
		<!-- icons -->
		<link href="{{asset('/')}}assets/css/icons.min.css" rel="stylesheet" type="text/css" />
		<!-- Head js -->
		<script src="{{asset('/')}}assets/js/head.js"></script>

    </head>
    {{-- To check status --}}
    @if(session('status'))
    <div class="alert alert-success" id="div1">{!! session('status') !!}</div>
    @endif
    @if(session('statusDanger'))
    <div class="alert alert-danger" id="div1">{!! session('statusDanger') !!}</div>
    @endif
    @yield('content')
     <!-- Vendor js -->
     <script src="{{asset('/')}}assets/js/vendor.min.js"></script>

     <!-- App js -->
     <script src="{{asset('/')}}assets/js/app.min.js"></script>
     <script type="text/javascript">
        
        // hide status
        $(document).ready(function(){  
            $("#div1").delay(6000).fadeOut(1500); 
        });
    </script>
</html>