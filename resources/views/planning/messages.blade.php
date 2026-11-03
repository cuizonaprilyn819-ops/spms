
@if(session('status'))<x-semantic-alert tone="success" :dismissible="true">{{ session('status') }}</x-semantic-alert>
@endif
@if($errors->any())<x-semantic-alert tone="danger">
@foreach($errors->all() as $error)<p>{{ $error }}</p>
@endforeach</x-semantic-alert>
@endif
@if(session('ai_draft'))<x-semantic-alert tone="info"><strong>AI suggestion — review before confirming relevance</strong><p class="whitespace-pre-line">{{ session('ai_draft') }}</p></x-semantic-alert>
@endif
