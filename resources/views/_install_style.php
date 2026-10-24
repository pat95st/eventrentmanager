<style>
    :root {
        color-scheme: light;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: #f1f5f9;
        color: #1e293b;
    }
    .install-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .install-card {
        width: 100%;
        max-width: 560px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 32px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }
    .install-card h1 {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0 0 16px;
        color: #0f172a;
    }
    .install-card p {
        font-size: 0.9rem;
        line-height: 1.5;
        color: #475569;
    }
    .install-steps {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        color: #94a3b8;
    }
    .install-steps span {
        padding: 2px 8px;
        border-radius: 999px;
        background: #f1f5f9;
    }
    .install-steps span.is-active {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .install-checklist {
        list-style: none;
        margin: 16px 0;
        padding: 0;
        font-size: 0.875rem;
    }
    .install-checklist li {
        padding: 6px 0;
        display: flex;
        gap: 8px;
        align-items: baseline;
    }
    .install-checklist li.is-ok { color: #047857; }
    .install-checklist li.is-error { color: #b91c1c; font-weight: 600; }
    .install-checklist li.is-warning { color: #b45309; }
    form label {
        display: block;
        font-size: 0.8rem;
        font-weight: 500;
        color: #475569;
        margin-top: 14px;
    }
    form input {
        margin-top: 4px;
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.9rem;
    }
    form input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 1px #2563eb;
    }
    .install-btn {
        display: inline-block;
        margin-top: 20px;
        background: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        padding: 10px 18px;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
    }
    .install-btn:hover { background: #1d4ed8; }
    .install-btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }
    .install-btn-secondary {
        background: #ffffff;
        color: #334155;
        border: 1px solid #cbd5e1;
    }
    .install-btn-secondary:hover { background: #f1f5f9; }
    .install-btn-danger { background: #dc2626; }
    .install-btn-danger:hover { background: #b91c1c; }
    .install-restore {
        margin-top: 20px;
        border-top: 1px solid #e2e8f0;
        padding-top: 16px;
    }
    .install-restore summary {
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
        color: #475569;
    }
    .install-alert {
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-bottom: 16px;
    }
    .install-alert-error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .install-alert-ok {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .install-hint {
        font-size: 0.8rem;
        color: #94a3b8;
    }
    .install-hint code, p code {
        background: #f1f5f9;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 0.85em;
    }
    .install-log {
        margin-top: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.8rem;
    }
    .install-log ul {
        list-style: none;
        margin: 8px 0 0;
        padding: 0;
        max-height: 220px;
        overflow-y: auto;
    }
    .install-log li { padding: 2px 0; }
    .install-log li.is-ok { color: #047857; }
    .install-log li.is-error { color: #b91c1c; font-weight: 600; }
</style>
