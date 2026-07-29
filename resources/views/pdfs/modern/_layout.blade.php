{{--
    Modern PDF template layout.

    Extends the shared document layout and injects the Modern CSS in an
    isolated <style> block scoped by the `.modern-pdf` container class so
    the Modern styling never leaks into other templates rendered in the
    same request. Each doc-type blade (invoice/order/purchase-order/
    delivery/quote) extends this file and provides its content via the
    `modernContent` section.

    Visual language:
      * Hair-line dividers (#e5e7eb) between logical blocks
      * Small brand mark top-left with organization name
      * TAX INVOICE-style pill top-right (rounded, dark background, uppercase)
      * Right-aligned totals block with subtle background
      * Generous whitespace between sections
--}}
@extends('laravel-crm::layouts.document')

@section('content')
<style>
    .modern-pdf {
        font-family: 'Nunito', 'Helvetica', sans-serif;
        color: #1f2937;
        font-size: 12px;
        line-height: 1.5;
    }

    .modern-pdf .modern-block {
        margin-bottom: 28px;
    }

    .modern-pdf .modern-hair {
        border: 0;
        border-top: 1px solid #e5e7eb;
        margin: 24px 0;
    }

    .modern-pdf table {
        width: 100%;
        border-collapse: collapse;
    }

    .modern-pdf .modern-header {
        margin-bottom: 12px;
    }

    .modern-pdf .modern-header td {
        vertical-align: top;
        padding: 0;
    }

    .modern-pdf .modern-brand-logo {
        max-height: 44px;
        max-width: 160px;
        display: block;
        margin-bottom: 6px;
    }

    .modern-pdf .modern-brand-name {
        font-size: 14px;
        font-weight: 500;
        color: #111827;
    }

    .modern-pdf .modern-pill {
        display: inline-block;
        padding: 6px 14px;
        background: #111827;
        color: #ffffff;
        font-size: 12px;
        font-weight: 500;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        border-radius: 999px;
    }

    .modern-pdf .modern-meta {
        margin-top: 4px;
    }

    .modern-pdf .modern-meta td {
        padding: 3px 0;
        font-size: 11.5px;
        color: #374151;
    }

    .modern-pdf .modern-meta .modern-meta-label {
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 10.5px;
        width: 130px;
    }

    .modern-pdf .modern-parties td {
        vertical-align: top;
        padding: 0 12px 0 0;
        width: 50%;
    }

    .modern-pdf .modern-parties td + td {
        padding: 0 0 0 12px;
    }

    .modern-pdf .modern-party-heading {
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #6b7280;
        font-size: 10.5px;
        margin-bottom: 6px;
    }

    .modern-pdf .modern-party-body {
        color: #111827;
    }

    .modern-pdf .modern-items {
        margin-top: 8px;
    }

    .modern-pdf .modern-items thead th {
        text-align: left;
        padding: 10px 8px;
        border-bottom: 1px solid #111827;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 10.5px;
        font-weight: 500;
    }

    .modern-pdf .modern-items tbody td {
        padding: 10px 8px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }

    .modern-pdf .modern-items .modern-num {
        text-align: right;
        white-space: nowrap;
    }

    .modern-pdf .modern-totals-wrap {
        margin-top: 18px;
    }

    .modern-pdf .modern-totals-wrap > table {
        width: 100%;
    }

    .modern-pdf .modern-totals-spacer {
        width: 55%;
    }

    .modern-pdf .modern-totals {
        width: 45%;
        background: #f9fafb;
        border-radius: 6px;
    }

    .modern-pdf .modern-totals td {
        padding: 8px 14px;
        font-size: 12px;
    }

    .modern-pdf .modern-totals .modern-totals-label {
        color: #6b7280;
        text-align: right;
    }

    .modern-pdf .modern-totals .modern-totals-value {
        text-align: right;
        width: 40%;
        white-space: nowrap;
        color: #111827;
    }

    .modern-pdf .modern-totals .modern-totals-final td {
        border-top: 1px solid #d1d5db;
        font-weight: 500;
        color: #111827;
        font-size: 13px;
    }

    .modern-pdf .modern-note {
        margin-top: 20px;
    }

    .modern-pdf .modern-note-heading {
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #6b7280;
        font-size: 10.5px;
        margin-bottom: 6px;
    }

    .modern-pdf .modern-note-body {
        color: #374151;
    }
</style>

<div class="modern-pdf">
    @yield('modernContent')
</div>
@endsection
