<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title>{{ (config('app.name')) ? config('app.name').' - ' : null }} CRM - Document</title>
    
    <!-- Styles -->
    @php($crmDocumentCss = public_path('vendor/laravel-crm/css/document.css'))
    @if(file_exists($crmDocumentCss))
        <style>{!! file_get_contents($crmDocumentCss) !!}</style>
    @else
        <link href="{{ asset('vendor/laravel-crm/css/document.css') }}" rel="stylesheet">
    @endif

    <style>
        @font-face {
            font-family: 'Nunito';
            font-style: normal;
            font-weight: normal;
            src: url('vendor/laravel-crm/fonts/Nunito-Regular.ttf') format('truetype');
        }
        
        @font-face {
            font-family: 'Nunito';
            font-style: normal;
            font-weight: 500;
            src: url('vendor/laravel-crm/fonts/Nunito-Medium.ttf') format('truetype');
        }

        @page {
            /* Xero-tight A4 margins so PDFs fill the printable area
               edge-to-edge rather than sitting inside DomPDF's default
               ~0.75in white gutter. */
            margin: 1.2cm 1.2cm 1.6cm 1.2cm;

            @bottom-right {
                content: counter(page) " of " counter(pages);
            }
        }

        .page-break {
            page-break-after: always;
        }

        .container-document{
            /* Fill the printable area (defined by @page margin above)
               rather than clamping to a fixed 18.6cm. Together with
               the tight @page margins this produces a page-fill layout
               matching Xero's invoice PDFs. */
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container-document">
       @yield('content', $slot ?? null)
    </div>
</body>
</html>