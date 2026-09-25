@push('css')
<style>
    .timetable-grid table { min-width: 820px; table-layout: fixed; }
    .timetable-grid td { padding: 6px; vertical-align: middle; }
    .timetable-grid .time-col { width: 120px; font-weight: 600; background: var(--erp-hover); white-space: nowrap; }
    .slot { border-radius: 10px; padding: 8px 10px; min-height: 64px; border: 1px solid var(--erp-slot-line); }
    .slot-editable { cursor: pointer; transition: box-shadow .15s; }
    .slot-editable:hover { box-shadow: 0 0 0 2px var(--erp-ink-text); }
    .slot-title { font-weight: 700; font-size: .875rem; line-height: 1.25; hyphens: auto; overflow-wrap: normal; word-break: normal; }
    .slot-meta { font-size: 12px; color: var(--erp-text-2); margin-top: 4px; }
    .empty-slot { text-align: center; color: var(--erp-muted); }
    .empty-slot .slot-title { font-weight: 400; }
    .break-slot {
        text-align: center; min-height: 0; font-weight: 600; color: var(--erp-muted);
        background: repeating-linear-gradient(135deg, var(--erp-hatch-a), var(--erp-hatch-a) 10px, var(--erp-hatch-b) 10px, var(--erp-hatch-b) 20px);
        border: 1px dashed var(--erp-line-strong);
    }
    .legend-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 14px; }
    .legend-swatch { width: 18px; height: 18px; border-radius: 4px; margin-right: 10px; border: 1px solid var(--erp-slot-line); }

    /* Phone: day picker + list (timetable/day-list.blade.php) */
    .timetable-day-tabs { display: grid; grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); gap: 6px; }
    .timetable-day-tab { min-height: 52px; border: 1px solid var(--erp-line-strong); border-radius: 10px; background: var(--erp-card); color: var(--erp-text); font-weight: 600; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.1; padding: 4px 0; }
    .timetable-day-tab.active { background: var(--erp-ink); border-color: var(--erp-ink); color: #fff; }
    .timetable-day-today { font-size: 11px; font-weight: 600; }
    .timetable-day-row { display: flex; gap: 12px; align-items: stretch; margin-bottom: 8px; }
    .timetable-day-time { width: 3.5rem; flex-shrink: 0; padding-top: 10px; font-size: 13px; color: var(--erp-muted); line-height: 1.3; }
    .timetable-day-lesson { flex-grow: 1; border-radius: 12px; padding: 10px 14px; display: flex; flex-direction: column; gap: 2px; }
    .timetable-day-subject { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 1.05rem; }
    .timetable-day-meta { font-size: 14px; color: var(--erp-text-2); }
    .timetable-day-break, .timetable-day-free { flex-grow: 1; display: flex; align-items: center; justify-content: center; min-height: 40px; border-radius: 12px; border: 1px dashed var(--erp-line-strong); color: var(--erp-muted); font-weight: 600; font-size: 14px; }

    @media print {
        .timetable-day-list { display: none !important; }
        .timetable-grid-wrap { display: block !important; }
        .main-sidebar, .main-header, .main-footer, .no-print, .btn { display: none !important; }
        .content-wrapper { margin-left: 0 !important; background: #fff; }
        .col-lg-9 { flex: 0 0 100%; max-width: 100%; }
        .card { box-shadow: none; border: 0; }
        .timetable-grid table { min-width: 0; }
        .slot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush
