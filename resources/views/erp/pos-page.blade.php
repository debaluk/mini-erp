<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS / Kasir - {{ config('app.name', 'Mini ERP') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden}
        .pos-screen{height:100vh;background:#f8f9fa;display:flex;flex-direction:column;overflow:hidden}
        .pos-topbar{height:48px;flex:0 0 48px;background:#212529;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 16px}
        .pos-main{height:calc(100vh - 48px);display:flex;flex-direction:column;min-height:0;padding:10px 14px 0;overflow:hidden}
        .pos-entry{background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:10px;flex:0 0 auto}
        .pos-table-wrap{flex:1 1 auto;min-height:0;margin-top:10px;border:1px solid #dee2e6;border-radius:6px;background:#fff;overflow:auto}
        .pos-table-wrap table{margin:0}.pos-table-wrap thead th{position:sticky;top:0;z-index:2;background:#fff;box-shadow:0 1px 0 #dee2e6}
        .pos-cart-row{cursor:pointer}.pos-cart-row:hover{background:#fff3cd}
        .pos-bottom{flex:0 0 auto;border-top:1px solid #dee2e6;background:#fff;margin:10px -14px 0;padding:8px 14px}
        .pos-help{font-size:.75rem;color:#6c757d;margin-bottom:6px}.pos-summary{max-width:720px;margin-left:auto}
        .pos-screen .form-control-lg,.pos-screen .btn-lg{min-height:40px}.pos-screen .table th{font-size:.78rem;padding:.5rem}.pos-screen .table td{font-size:.82rem;padding:.45rem}.pos-screen .modal{z-index:1080}
    </style>
</head>
<body>
@include('erp.pos')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
