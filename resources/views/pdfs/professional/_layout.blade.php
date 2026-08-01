{{--
    Professional PDF template layout (Xero-inspired).

    Visual language:
      * Large document-title text on the left
      * Small brand mark / logo top-right
      * Stacked meta rows with thin underlines beneath each row
      * Party blocks separated by thin horizontal rules
      * Line items table with a single thin bottom border on the header row
      * Totals stack right-aligned, values separated by thin underlines
      * Minimal use of colour; grayscale palette

    All CSS is scoped to `.professional-pdf` so styles never leak into
    other templates rendered in the same request.
--}}
@extends('laravel-crm::layouts.document')

@section('content')
<style>
    .professional-pdf {
        font-family: 'Nunito', 'Helvetica', sans-serif;
        color: #2b2b2b;
        font-size: 12px;
        line-height: 1.3;
    }

    .professional-pdf .prof-block {
        margin-bottom: 14px;
    }

    .professional-pdf table {
        width: 100%;
        border-collapse: collapse;
    }

    /* Every block below shares the .prof-items 8px horizontal padding so
       content lines up on one left edge and one right edge. */
    .professional-pdf .prof-header td {
        vertical-align: top;
        padding: 0 8px;
    }

    .professional-pdf .prof-header td + td {
        text-align: right;
    }

    .professional-pdf .prof-doc-title {
        font-size: 26px;
        font-weight: 300;
        color: #2b2b2b;
        letter-spacing: 0.02em;
        margin: 0;
    }

    .professional-pdf .prof-brand-logo {
        max-height: 108px;
        max-width: 360px;
        display: inline-block;
    }

    .professional-pdf .prof-brand-name {
        font-size: 13px;
        font-weight: 500;
        color: #2b2b2b;
        text-align: right;
        margin-top: 6px;
    }

    .professional-pdf .prof-meta td {
        padding: 6px 8px;
        border-bottom: 1px solid #d9d9d9;
        font-size: 11.5px;
    }

    .professional-pdf .prof-meta .prof-meta-label {
        color: #7a7a7a;
        width: 42%;
    }

    .professional-pdf .prof-meta .prof-meta-value {
        text-align: right;
        color: #2b2b2b;
        font-weight: 500;
    }

    .professional-pdf .prof-parties {
        border-top: 1px solid #d9d9d9;
        border-bottom: 1px solid #d9d9d9;
        margin-bottom: 18px;
    }

    .professional-pdf .prof-parties td {
        vertical-align: top;
        padding: 14px 12px 14px 8px;
        width: 50%;
    }

    .professional-pdf .prof-parties td + td {
        padding: 14px 8px 14px 12px;
        border-left: 1px solid #d9d9d9;
    }

    .professional-pdf .prof-party-heading {
        color: #7a7a7a;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 6px;
    }

    .professional-pdf .prof-party-body {
        color: #2b2b2b;
    }

    .professional-pdf .prof-items {
        margin-top: 8px;
    }

    .professional-pdf .prof-items thead th {
        text-align: left;
        padding: 8px 8px;
        border-bottom: 1px solid #2b2b2b;
        color: #2b2b2b;
        font-weight: 500;
        font-size: 11.5px;
    }

    .professional-pdf .prof-items tbody td {
        padding: 8px;
        border-bottom: 1px solid #eeeeee;
        vertical-align: top;
    }

    .professional-pdf .prof-items .prof-num {
        text-align: right;
        white-space: nowrap;
    }

    .professional-pdf .prof-totals-wrap {
        margin-top: 20px;
    }

    .professional-pdf .prof-totals-wrap > table {
        width: 100%;
    }

    .professional-pdf .prof-totals-spacer {
        width: 55%;
    }

    .professional-pdf .prof-totals {
        width: 45%;
    }

    .professional-pdf .prof-totals td {
        padding: 8px 8px;
        border-bottom: 1px solid #d9d9d9;
        font-size: 12px;
    }

    .professional-pdf .prof-totals .prof-totals-label {
        color: #7a7a7a;
        text-align: right;
    }

    .professional-pdf .prof-totals .prof-totals-value {
        text-align: right;
        width: 40%;
        white-space: nowrap;
        color: #2b2b2b;
    }

    .professional-pdf .prof-totals .prof-totals-final td {
        border-top: 2px solid #2b2b2b;
        border-bottom: 0;
        font-weight: 600;
        color: #2b2b2b;
        font-size: 13.5px;
    }

    .professional-pdf .prof-note {
        margin-top: 18px;
        padding: 0 8px;
    }

    .professional-pdf .prof-note-heading {
        color: #7a7a7a;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 4px;
    }

    .professional-pdf .prof-note-body {
        color: #2b2b2b;
    }
</style>

<div class="professional-pdf">
    @yield('professionalContent')
</div>
@endsection
