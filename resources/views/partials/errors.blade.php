@if($errors->any())<ul class="errors">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
