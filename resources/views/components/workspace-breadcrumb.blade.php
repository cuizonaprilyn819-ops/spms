@php
    $section = explode('.', preg_replace('/^api\./', '', request()->route()?->getName() ?? 'dashboard'))[0];
    $pages = [
        'dashboard' => ['Workspace', 'Dashboard'], 'reports' => ['Performance work', 'My reports'],
        'opcrs' => ['Performance work', 'OPCR'], 'dpcrs' => ['Performance work', 'DPCR'],
        'upcrs' => ['Performance work', 'UPCR'], 'ipcrs' => ['Performance work', 'IPCR'],
        'planning' => ['Performance work', 'Commitment planning'],
        'target-documents' => ['Performance work', 'Planning documents'], 'target-drafts' => ['Performance work', 'Review extracted targets'],
        'office-targets' => ['Performance work', 'Office targets'], 'performance-targets' => ['Performance work', 'Performance targets'],
        'accomplishments' => ['Performance work', 'Accomplishments'], 'movs' => ['Performance work', 'Evidence / MOV'],
        'evaluations' => ['Review', 'Evaluations'], 'ratings' => ['Review', 'All ratings'],
        'historical-pcrs' => ['Archive', 'Historical PCRs'], 'report-workflow' => ['Performance work', 'Edit PCR'],
        'offices' => ['Organisation', 'Offices'], 'employees' => ['Organisation', 'Personnel'],
        'evaluation-cycles' => ['Organisation', 'Evaluation periods'], 'strategic-goals' => ['Organisation', 'Strategic goals'],
        'strategic-objectives' => ['Organisation', 'Strategic objectives'], 'notifications' => ['Workspace', 'Notifications'],
        'profile' => ['Account', 'My profile'], 'admin' => ['Account', 'Password administration'],
    ];
    [$group, $page] = $pages[$section] ?? ['Workspace', 'Performance management'];
@endphp
<nav class="spms-breadcrumb" aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}">Workspace</a>@if($group !== 'Workspace')<span aria-hidden="true">›</span><span>{{ $group }}</span>@endif<span aria-hidden="true">›</span><span aria-current="page">{{ $page }}</span>
</nav>
