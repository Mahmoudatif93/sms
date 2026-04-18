<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
            direction:
                {{ $direction ?? 'ltr' }}
            ;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .content {
            direction:
                {{ $direction ?? 'ltr' }}
            ;
            text-align:
                {{ $alignment ?? 'left' }}
            ;
        }

        .p {
            direction:
                {{ $direction ?? 'ltr' }}
            ;
            text-align:
                {{ $alignment ?? 'left' }}
            ;
        }

        .details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .details h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .details ul {
            list-style-type: none;
            padding-left: 0;
        }

        .details li {
            padding: 5px 0;
            border-bottom: 1px dotted #eee;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3490dc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 0.9em;
            color: #777;
            text-align:
                {{ $alignment ?? 'left' }}
            ;
        }
    </style>
     @yield('styles')
</head>

<body>
    <div class="container">
        @include('emails.header')

        <div class="content">
            @if(isset($title))
                <div class="header">
                    <h1>{{ $title }}</h1>
                </div>
            @endif

            @if(isset($greeting))
                <p>{{ $greeting }}</p>
            @endif

            @if(isset($messageContent))
                <p>{{ $messageContent }}</p>
            @endif

            @yield('content')

            @isset($actionUrl)
                <div style="text-align: center; margin: 25px 0;">
                    <a href="{{ $actionUrl }}" class="button">{{ $actionText }}</a>
                </div>
            @endisset

            <div class="footer">
                <p>{{ $thankYou ?? __('notification.thank_you') }}</p>
                <p>{{ $signature ?? __('notification.signature') }}</p>
            </div>
        </div>

        @include('emails.footer')
    </div>
</body>

</html>