<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $report_title }}</title>
<style>
    @page { margin: 30mm 14mm 18mm 14mm; }
    @include('pdf.environment-report.partials.styles')
</style>
</head>
<body>
    @include('pdf.environment-report.cover')
    <div class="page-break"></div>
    @include('pdf.environment-report.body')
</body>
</html>
