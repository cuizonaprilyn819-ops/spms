<section class="spms-quick-actions" aria-labelledby="workspace-next-steps">
    <div><h2 id="workspace-next-steps">Quick actions</h2><p>Open the work you need to prepare or review.</p></div>
    <div class="spms-shortcut-links" data-action-plain>
        <a href="{{ route('reports.index') }}"><span>My reports</span><small>Current forms and named review records</small><span aria-hidden="true">↗</span></a>
        <a href="{{ route('planning.index') }}"><span>Commitment planning</span><small>Source targets, duties, and allocations</small><span aria-hidden="true">↗</span></a>
        @hasanyrole('Administrator|Planning Officer')
            <a href="{{ route('target-documents.index') }}"><span>Planning documents</span><small>Upload sources and review extracted drafts</small><span aria-hidden="true">↗</span></a>
        @else
            <a href="{{ route('historical-pcrs.index') }}"><span>Historical PCRs</span><small>Original sources and retained revisions</small><span aria-hidden="true">↗</span></a>
        @endhasanyrole
    </div>
</section>
