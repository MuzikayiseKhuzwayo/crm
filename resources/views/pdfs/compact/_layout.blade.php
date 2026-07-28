{{--
    Compact PDF template layout.

    Visual language:
      * Smaller type (base 9.5px vs 11px)
      * Tighter row padding (4-6px vs 8-10px)
      * Monochrome — pure black text on white, gray dividers only
      * Dense two-column meta at top
      * Single-column footer note area at bottom for terms / payment
        instructions / signature

    All CSS is scoped to `.compact-pdf` so styles never leak into other
    templates rendered in the same request.
--}}
@extends('laravel-crm::layouts.document')

@section('content')
<style>
    .compact-pdf {
        font-family: 'Nunito', 'Helvetica', sans-serif;
        color: #000000;
        font-size: 9.5px;
        line-height: 1.4;
    }

    .compact-pdf .compact-block {
        margin-bottom: 12px;
    }

    .compact-pdf table {
        width: 100%;
        border-collapse: collapse;
    }

    .compact-pdf .compact-header td {
        vertical-align: top;
        padding: 0;
    }

    .compact-pdf .compact-brand-logo {
        max-height: 36px;
        max-width: 130px;
        display: block;
        margin-bottom: 3px;
    }

    .compact-pdf .compact-brand-name {
        font-size: 10.5px;
        font-weight: 600;
        color: #000000;
    }

    .compact-pdf .compact-doc-title {
        font-size: 14px;
        font-weight: 700;
        color: #000000;
        text-align: right;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
    }

    .compact-pdf .compact-rule {
        border: 0;
        border-top: 1px solid #000000;
        margin: 8px 0 12px 0;
    }

    .compact-pdf .compact-meta td {
        padding: 2px 6px 2px 0;
        font-size: 9.5px;
        vertical-align: top;
    }

    .compact-pdf .compact-meta .compact-meta-label {
        color: #333333;
        font-weight: 600;
        width: 22%;
    }

    .compact-pdf .compact-meta .compact-meta-value {
        color: #000000;
        width: 28%;
    }

    .compact-pdf .compact-parties td {
        vertical-align: top;
        padding: 0 8px 0 0;
        width: 50%;
    }

    .compact-pdf .compact-parties td + td {
        padding: 0 0 0 8px;
    }

    .compact-pdf .compact-party-heading {
        color: #000000;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 2px;
    }

    .compact-pdf .compact-party-body {
        color: #000000;
    }

    .compact-pdf .compact-items {
        margin-top: 6px;
    }

    .compact-pdf .compact-items thead th {
        text-align: left;
        padding: 4px 6px;
        border-top: 1px solid #000000;
        border-bottom: 1px solid #000000;
        color: #000000;
        font-weight: 700;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .compact-pdf .compact-items tbody td {
        padding: 4px 6px;
        border-bottom: 1px solid #d9d9d9;
        vertical-align: top;
    }

    .compact-pdf .compact-items .compact-num {
        text-align: right;
        white-space: nowrap;
    }

    .compact-pdf .compact-totals-wrap {
        margin-top: 10px;
    }

    .compact-pdf .compact-totals-wrap > table {
        width: 100%;
    }

    .compact-pdf .compact-totals-spacer {
        width: 60%;
    }

    .compact-pdf .compact-totals {
        width: 40%;
    }

    .compact-pdf .compact-totals td {
        padding: 3px 6px;
        font-size: 9.5px;
    }

    .compact-pdf .compact-totals .compact-totals-label {
        color: #333333;
        text-align: right;
    }

    .compact-pdf .compact-totals .compact-totals-value {
        text-align: right;
        width: 40%;
        white-space: nowrap;
        color: #000000;
    }

    .compact-pdf .compact-totals .compact-totals-final td {
        border-top: 1px solid #000000;
        font-weight: 700;
        color: #000000;
        font-size: 10.5px;
    }

    .compact-pdf .compact-footer {
        margin-top: 20px;
        border-top: 1px solid #000000;
        padding-top: 8px;
    }

    .compact-pdf .compact-footer-note {
        margin-bottom: 8px;
    }

    .compact-pdf .compact-footer-heading {
        color: #000000;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 2px;
    }

    .compact-pdf .compact-footer-body {
        color: #000000;
    }
</style>

<div class="compact-pdf">
    @yield('compactContent')
</div>
@endsection
