@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://panel.fizjoterapia-kaczmarek.pl/_nuxt/logo.B0cE-icm.png" class="logo" alt="Laravel Logo">

@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
