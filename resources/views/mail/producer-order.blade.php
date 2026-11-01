<p>{{ $heading }}</p><p>@foreach ($formattedTotals as $total)@if (! $loop->first)<br>@endif- {{ $total['name'] }}: {{ $total['quantity'] }} {{ $total['unit'] }}@endforeach</p>
