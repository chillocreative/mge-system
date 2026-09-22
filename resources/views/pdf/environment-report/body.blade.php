<div class="header">
    <table>
        <tr>
            <td class="title-cell">
                <div class="project-title">{{ $project->name }}</div>
                <div class="contract-no">CONTRACT NO. : {{ $contract_no }}</div>
            </td>
            <td class="logo-cell">
                @if($logo_mge)
                    <img src="{{ $logo_mge }}" alt="MGE logo">
                @endif
            </td>
        </tr>
    </table>
    <hr>
</div>

@include('pdf.environment-report.toc')

<div class="page-break"></div>
@include('pdf.environment-report.sections.contract')

<div class="page-break"></div>
@include('pdf.environment-report.sections.ems')

<div class="page-break"></div>
@include('pdf.environment-report.sections.introduction')
@include('pdf.environment-report.sections.flow_chart')

<div class="page-break"></div>
@include('pdf.environment-report.sections.policy')

<div class="page-break"></div>
@include('pdf.environment-report.sections.location')

<div class="page-break"></div>
@include('pdf.environment-report.sections.parameters')

<div class="page-break"></div>
@include('pdf.environment-report.sections.results')

<div class="page-break"></div>
@include('pdf.environment-report.sections.bmp')
