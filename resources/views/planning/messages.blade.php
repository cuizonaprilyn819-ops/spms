
@if(session('status'))<div class="rounded border border-green-300 bg-green-50 p-4" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())<div class="rounded border border-red-300 bg-red-50 p-4" role="alert">
@foreach($errors->all() as $error)<p>{{ $error }}</p>
@endforeach</div>
@endif
@if(session('ai_draft'))<div class="rounded border border-blue-300 bg-blue-50 p-4"><strong>AI suggestion — review before confirming relevance</strong><p class="whitespace-pre-line">{{ session('ai_draft') }}</p></div>
@endif
