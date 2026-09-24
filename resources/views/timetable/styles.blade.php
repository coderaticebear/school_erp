@push('css')
<style>
    .timetable-grid table { min-width: 820px; table-layout: fixed; }
    .timetable-grid td { padding: 6px; vertical-align: middle; }
    .timetable-grid .time-col { width: 120px; font-weight: 600; background: #f8f9fa; white-space: nowrap; }
    .slot { border-radius: 10px; padding: 8px 10px; min-height: 64px; border: 1px solid rgba(0, 0, 0, 0.06); }
    .slot-editable { cursor: pointer; transition: box-shadow .15s; }
    .slot-editable:hover { box-shadow: 0 0 0 2px #007bff; }
    .slot-title { font-weight: 700; letter-spacing: 0.2px; }
    .slot-meta { font-size: 12px; color: #56606a; margin-top: 4px; }
    .empty-slot { text-align: center; color: #7a838c; }
    .empty-slot .slot-title { font-weight: 400; }
    .break-slot {
        text-align: center; min-height: 0; font-weight: 600; color: #56606a;
        background: repeating-linear-gradient(135deg, rgba(0,0,0,.03), rgba(0,0,0,.03) 10px, rgba(0,0,0,.06) 10px, rgba(0,0,0,.06) 20px);
        border: 1px dashed rgba(0, 0, 0, 0.15);
    }
    .bg-soft-blue { background: #e7f0ff; }
    .bg-soft-green { background: #e6f6ef; }
    .bg-soft-orange { background: #fff1dd; }
    .bg-soft-purple { background: #efe9ff; }
    .bg-soft-teal { background: #e3f7f7; }
    .bg-soft-pink { background: #fde8ef; }
    .bg-soft-yellow { background: #fdf6d8; }
    .bg-soft-indigo { background: #e8eafc; }
    .bg-soft-gray { background: #f1f3f5; }
    .legend-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 14px; }
    .legend-swatch { width: 18px; height: 18px; border-radius: 4px; margin-right: 10px; border: 1px solid rgba(0, 0, 0, 0.08); }

    @media print {
        .main-sidebar, .main-header, .main-footer, .no-print, .btn { display: none !important; }
        .content-wrapper { margin-left: 0 !important; background: #fff; }
        .col-lg-9 { flex: 0 0 100%; max-width: 100%; }
        .card { box-shadow: none; border: 0; }
        .timetable-grid table { min-width: 0; }
        .slot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush
