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

            /* Open up the very tight line-height (1.1) that the bundled
               Bootstrap build sets on <body> in document.css. In practice
               this governs the classic template only: the modern, bold,
               compact and professional templates each declare their own
               line-height on their scoped .*-pdf wrapper, which wins over
               this inherited value. */
            line-height: 1.2;
        }

        /* document.css pins `.table-sm.table-items td/th` to line-height .7,
           which is more specific than the .container-document rule above and
           so wins for the classic template's cell text — where nearly all of
           its content lives. Override it here (this <style> block is emitted
           after document.css, and the extra .container-document raises
           specificity) to match the container value.

           Scoped in practice to the classic template: the `table-items`
           class is used only by the legacy pdf views that classic renders,
           and nowhere under resources/views/pdfs/. */
        .container-document .table-sm.table-items td,
        .container-document .table-sm.table-items th {
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div class="container-document">
       @yield('content', $slot ?? null)
    </div>
</body>
</html>